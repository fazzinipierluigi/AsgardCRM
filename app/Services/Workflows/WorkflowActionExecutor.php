<?php

namespace App\Services\Workflows;

use App\Enums\WorkflowActionType;
use App\Http\Requests\Admin\EntityListWidgetRequest;
use App\Mail\WorkflowNotificationMail;
use App\Models\Entity;
use App\Models\EntityRecord;
use App\Models\WorkflowAction;
use App\Models\WorkflowApiEndpoint;
use App\Models\WorkflowInstance;
use App\Models\WorkflowSqlConnection;
use App\Services\DynamicDatabaseConnector;
use App\Services\EntityChangeLogger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Executes a single WorkflowAction against a running instance. Every
 * action type reads its own shaped `config` array (validated by the
 * editor's action form, not here) and, where relevant, evaluates
 * expressions against the instance's current variables plus the
 * triggering `entity`.
 *
 * @phpstan-type SetVariableConfig array{variable: string, expression: string}
 * @phpstan-type ClearVariableConfig array{variable: string}
 * @phpstan-type AssignEntityConfig array{variable: string, entity_slug: string, id_expression: string}
 * @phpstan-type SendEmailConfig array{to: string, subject: string, body: string}
 * @phpstan-type EntityFieldAssignment array{column: string, expression: string}
 * @phpstan-type UpdateEntityConfig array{entity_slug: string, id_expression: string, fields: list<EntityFieldAssignment>}
 * @phpstan-type CreateEntityConfig array{entity_slug: string, fields: list<EntityFieldAssignment>, assign_to_variable: ?string}
 * @phpstan-type SqlBinding array{name: string, expression: string}
 * @phpstan-type AssignVariableFromSqlConfig array{connection_id: int, query: string, bindings: list<SqlBinding>, variable: string}
 * @phpstan-type ApiParam array{key: string, expression: string}
 * @phpstan-type AssignVariableFromApiConfig array{endpoint_id: int, method: string, path: ?string, query: list<ApiParam>, body: ?string, variable: string}
 * @phpstan-type FetchEntityCondition array{column: string, operator: string, expression: string}
 * @phpstan-type FetchEntityConfig array{entity_slug: string, conditions: list<FetchEntityCondition>, variable: string}
 * @phpstan-type RedirectConfig array{entity_slug: string, id_expression: string}
 */
class WorkflowActionExecutor
{
    /**
     * Hard cap on how many records "Preleva entità" can assign to a
     * variable in one go — not admin-configurable, a sanity/safety
     * guard against a workflow instance's `variables` JSON blowing up.
     */
    private const FETCH_ENTITY_LIMIT = 500;

    /**
     * Set by a Redirect action, read right back by whichever HTTP
     * controller triggered this execution (currently only
     * WorkflowUserTaskController::update()) to redirect the user there
     * instead of its own default route — the flow itself keeps
     * advancing in the background regardless. Null when no Redirect
     * action ran, or its target record didn't resolve. This class is
     * bound as a singleton (see AppServiceProvider) so the same
     * instance is shared between WorkflowEngine's internal use and a
     * controller's own injected copy within one request.
     */
    public ?string $lastRedirectUrl = null;

    public function __construct(
        private readonly WorkflowExpressionEvaluator $evaluator,
        private readonly EntityChangeLogger $changeLogger,
        private readonly DynamicDatabaseConnector $sqlConnector,
    ) {}

    public function execute(WorkflowAction $action, WorkflowInstance $instance): void
    {
        match ($action->type) {
            WorkflowActionType::SetVariable => $this->setVariable($action, $instance),
            WorkflowActionType::ClearVariable => $this->clearVariable($action, $instance),
            WorkflowActionType::AssignEntityToVariable => $this->assignEntityToVariable($action, $instance),
            WorkflowActionType::SendEmail => $this->sendEmail($action, $instance),
            WorkflowActionType::UpdateEntity => $this->updateEntity($action, $instance),
            WorkflowActionType::CreateEntity => $this->createEntity($action, $instance),
            WorkflowActionType::AssignVariableFromSql => $this->assignVariableFromSql($action, $instance),
            WorkflowActionType::AssignVariableFromApi => $this->assignVariableFromApi($action, $instance),
            WorkflowActionType::FetchEntity => $this->fetchEntity($action, $instance),
            WorkflowActionType::Redirect => $this->redirect($action, $instance),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function buildContext(WorkflowInstance $instance): array
    {
        $context = $instance->variables ?? [];
        $entity = $instance->resolveEntity();

        if ($entity) {
            $context['entity'] = $entity;
        }

        return $context;
    }

    private function setVariable(WorkflowAction $action, WorkflowInstance $instance): void
    {
        /** @var SetVariableConfig $config */
        $config = $action->config;
        $value = $this->evaluator->evaluate($config['expression'] ?? null, $this->buildContext($instance));

        $variable = $instance->workflowVersion->variables->firstWhere('name', $config['variable']);
        if ($variable) {
            $value = $variable->type->cast($value);
        }

        $instance->setVariable($config['variable'], $value);
        $instance->save();
    }

    private function clearVariable(WorkflowAction $action, WorkflowInstance $instance): void
    {
        /** @var ClearVariableConfig $config */
        $config = $action->config;

        $instance->setVariable($config['variable'], null);
        $instance->save();
    }

    private function assignEntityToVariable(WorkflowAction $action, WorkflowInstance $instance): void
    {
        /** @var AssignEntityConfig $config */
        $config = $action->config;
        $entity = Entity::where('slug', $config['entity_slug'])->firstOrFail();
        $id = $this->evaluator->evaluate($config['id_expression'], $this->buildContext($instance));

        $record = EntityRecord::forEntity($entity)->find($id);

        $instance->setVariable($config['variable'], $record ? [
            '__entity_slug' => $entity->slug,
            '__entity_id' => $record->getKey(),
        ] : null);
        $instance->save();
    }

    private function sendEmail(WorkflowAction $action, WorkflowInstance $instance): void
    {
        /** @var SendEmailConfig $config */
        $config = $action->config;
        $context = $this->buildContext($instance);

        $to = $this->evaluator->renderTemplate($config['to'], $context);
        $subject = $this->evaluator->renderTemplate($config['subject'], $context);
        $body = $this->evaluator->renderTemplate($config['body'], $context);

        $recipients = array_filter(array_map('trim', explode(',', $to)));

        if ($recipients === []) {
            return;
        }

        Mail::to($recipients)->send(new WorkflowNotificationMail($subject, $body));
    }

    private function updateEntity(WorkflowAction $action, WorkflowInstance $instance): void
    {
        /** @var UpdateEntityConfig $config */
        $config = $action->config;
        $context = $this->buildContext($instance);

        $entity = Entity::where('slug', $config['entity_slug'])->firstOrFail();
        $id = $this->evaluator->evaluate($config['id_expression'], $context);

        $record = EntityRecord::forEntity($entity)->findOrFail($id);
        $columns = array_map(fn ($field) => $field['column'], $config['fields'] ?? []);
        $original = $record->only($columns);

        foreach ($config['fields'] ?? [] as $field) {
            $record->setAttribute($field['column'], $this->evaluator->evaluate($field['expression'], $context));
        }

        $record->save();

        $this->changeLogger->logUpdated($entity, $record, $original, $record->only($columns), null, "Flusso: {$instance->workflow->name}");
    }

    private function createEntity(WorkflowAction $action, WorkflowInstance $instance): void
    {
        /** @var CreateEntityConfig $config */
        $config = $action->config;
        $context = $this->buildContext($instance);

        $entity = Entity::where('slug', $config['entity_slug'])->firstOrFail();

        $attributes = [];
        foreach ($config['fields'] ?? [] as $field) {
            $attributes[$field['column']] = $this->evaluator->evaluate($field['expression'], $context);
        }

        $record = EntityRecord::forEntity($entity)->create($attributes);
        $this->changeLogger->logCreated($entity, $record, $attributes, null, "Flusso: {$instance->workflow->name}");

        if (! empty($config['assign_to_variable'])) {
            $instance->setVariable($config['assign_to_variable'], [
                '__entity_slug' => $entity->slug,
                '__entity_id' => $record->getKey(),
            ]);
            $instance->save();
        }
    }

    /**
     * Runs a read-only query (SELECT or WITH/CTE only, single statement)
     * against a configured WorkflowSqlConnection, with every value bound
     * as a real PDO parameter — never string-interpolated, see
     * assertReadOnlyQuery() and DynamicDatabaseConnector. The first row
     * becomes the assigned value: its lone column if it only has one,
     * otherwise the whole row as an array; no rows assigns null.
     */
    private function assignVariableFromSql(WorkflowAction $action, WorkflowInstance $instance): void
    {
        /** @var AssignVariableFromSqlConfig $config */
        $config = $action->config;
        $context = $this->buildContext($instance);

        $connection = WorkflowSqlConnection::findOrFail($config['connection_id']);
        $query = trim((string) $config['query']);
        $this->assertReadOnlyQuery($query);

        $bindings = [];
        foreach ($config['bindings'] ?? [] as $binding) {
            $bindings[$binding['name']] = $this->evaluator->evaluate($binding['expression'], $context);
        }

        $rows = $this->sqlConnector->run(
            $connection->config ?? [],
            'workflow_sql',
            fn ($dbConnection) => $dbConnection->select($query, $bindings),
        );

        $instance->setVariable($config['variable'], $this->firstRowValue($rows));
        $instance->save();
    }

    /**
     * Rejects anything but a single SELECT/WITH statement — this action
     * assigns a value, it never writes. Guards against both a wrong
     * statement type and a stacked second statement.
     */
    private function assertReadOnlyQuery(string $query): void
    {
        $query = rtrim($query, "; \t\n\r");

        if (str_contains($query, ';')) {
            throw new RuntimeException('La query SQL non può contenere più di uno statement.');
        }

        if (! preg_match('/^(select|with)\b/i', $query)) {
            throw new RuntimeException('La query SQL deve iniziare con SELECT o WITH: questa azione può solo leggere dati.');
        }
    }

    /**
     * @param  list<object>  $rows
     */
    private function firstRowValue(array $rows): mixed
    {
        $first = $rows[0] ?? null;

        if ($first === null) {
            return null;
        }

        $row = (array) $first;

        return count($row) === 1 ? array_values($row)[0] : $row;
    }

    /**
     * Calls a configured WorkflowApiEndpoint and assigns its decoded
     * JSON response to a variable. The endpoint's own auth (bearer/
     * basic/custom header) is applied here, never exposed to the
     * workflow config itself.
     */
    private function assignVariableFromApi(WorkflowAction $action, WorkflowInstance $instance): void
    {
        /** @var AssignVariableFromApiConfig $config */
        $config = $action->config;
        $context = $this->buildContext($instance);

        $endpoint = WorkflowApiEndpoint::findOrFail($config['endpoint_id']);
        $authConfig = $endpoint->config ?? [];

        $request = match ($authConfig['auth_type'] ?? 'none') {
            'bearer' => Http::withToken((string) ($authConfig['token'] ?? '')),
            'basic' => Http::withBasicAuth((string) ($authConfig['username'] ?? ''), (string) ($authConfig['password'] ?? '')),
            'header' => Http::withHeaders([(string) ($authConfig['header_name'] ?? '') => (string) ($authConfig['header_value'] ?? '')]),
            default => Http::asJson(),
        };

        $query = [];
        foreach ($config['query'] ?? [] as $param) {
            $query[$param['key']] = $this->evaluator->evaluate($param['expression'], $context);
        }

        $path = ltrim($this->evaluator->renderTemplate($config['path'] ?? '', $context), '/');
        $url = rtrim($endpoint->base_url, '/').($path !== '' ? "/{$path}" : '');
        $body = ! empty($config['body']) ? json_decode($this->evaluator->renderTemplate($config['body'], $context), true) : null;

        $response = $request->timeout(15)->send(strtoupper($config['method'] ?? 'GET'), $url, array_filter([
            'query' => $query ?: null,
            'json' => $body,
        ], fn ($value) => $value !== null));

        $response->throw();

        $instance->setVariable($config['variable'], $response->json());
        $instance->save();
    }

    /**
     * Filters another entity's records by a list of AND-ed conditions
     * (column/operator whitelisted, value evaluated against the current
     * context) and assigns the matches — capped at
     * self::FETCH_ENTITY_LIMIT — to a variable, one {__entity_slug,
     * __entity_id} pair per row, the same shape AssignEntityToVariable
     * already uses for a single record.
     */
    private function fetchEntity(WorkflowAction $action, WorkflowInstance $instance): void
    {
        /** @var FetchEntityConfig $config */
        $config = $action->config;
        $context = $this->buildContext($instance);

        $entity = Entity::where('slug', $config['entity_slug'])->firstOrFail();
        $allowedColumns = EntityListWidgetRequest::filterableColumns($entity);
        $allowedOperators = ['=', '!=', '>', '<', '>=', '<='];

        $query = EntityRecord::forEntity($entity)->newQuery();

        foreach ($config['conditions'] ?? [] as $condition) {
            if (! array_key_exists($condition['column'], $allowedColumns) || ! in_array($condition['operator'], $allowedOperators, true)) {
                throw new RuntimeException('Condizione non valida in «Preleva entità»: colonna o operatore non ammessi.');
            }

            $value = $this->evaluator->evaluate($condition['expression'], $context);
            $query->where($condition['column'], $condition['operator'], $value);
        }

        $records = $query->limit(self::FETCH_ENTITY_LIMIT)->get();

        $instance->setVariable($config['variable'], $records->map(fn (EntityRecord $record) => [
            '__entity_slug' => $entity->slug,
            '__entity_id' => $record->getKey(),
        ])->all());
        $instance->save();
    }

    /**
     * Resolves the target record and, if it exists, stores its detail
     * page URL in $lastRedirectUrl for the calling controller to pick
     * up — see the property's own docblock. A record that doesn't
     * resolve (bad expression result, already-deleted record) is a
     * silent no-op: this action never fails the instance.
     */
    private function redirect(WorkflowAction $action, WorkflowInstance $instance): void
    {
        /** @var RedirectConfig $config */
        $config = $action->config;
        $context = $this->buildContext($instance);

        $entity = Entity::where('slug', $config['entity_slug'])->first();

        if (! $entity) {
            return;
        }

        $id = $this->evaluator->evaluate($config['id_expression'] ?? null, $context);
        $record = EntityRecord::forEntity($entity)->find($id);

        if (! $record) {
            return;
        }

        $this->lastRedirectUrl = route('entities.edit', [$entity, $record->getKey()]);
    }
}
