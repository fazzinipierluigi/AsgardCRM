<?php

namespace App\Services\Workflows;

use App\Enums\WorkflowActionType;
use App\Mail\WorkflowNotificationMail;
use App\Models\Entity;
use App\Models\EntityRecord;
use App\Models\WorkflowAction;
use App\Models\WorkflowInstance;
use Illuminate\Support\Facades\Mail;

/**
 * Executes a single WorkflowAction against a running instance. Every
 * action type reads its own shaped `config` array (validated by the
 * editor's action form, not here) and, where relevant, evaluates
 * expressions against the instance's current variables plus the
 * triggering `entity`.
 *
 * @phpstan-type SetVariableConfig array{variable: string, expression: string}
 * @phpstan-type AssignEntityConfig array{variable: string, entity_slug: string, id_expression: string}
 * @phpstan-type SendEmailConfig array{to: string, subject: string, body: string}
 * @phpstan-type EntityFieldAssignment array{column: string, expression: string}
 * @phpstan-type UpdateEntityConfig array{entity_slug: string, id_expression: string, fields: list<EntityFieldAssignment>}
 * @phpstan-type CreateEntityConfig array{entity_slug: string, fields: list<EntityFieldAssignment>, assign_to_variable: ?string}
 */
class WorkflowActionExecutor
{
    public function __construct(private readonly WorkflowExpressionEvaluator $evaluator) {}

    public function execute(WorkflowAction $action, WorkflowInstance $instance): void
    {
        match ($action->type) {
            WorkflowActionType::SetVariable => $this->setVariable($action, $instance),
            WorkflowActionType::AssignEntityToVariable => $this->assignEntityToVariable($action, $instance),
            WorkflowActionType::SendEmail => $this->sendEmail($action, $instance),
            WorkflowActionType::UpdateEntity => $this->updateEntity($action, $instance),
            WorkflowActionType::CreateEntity => $this->createEntity($action, $instance),
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

        foreach ($config['fields'] ?? [] as $field) {
            $record->setAttribute($field['column'], $this->evaluator->evaluate($field['expression'], $context));
        }

        $record->save();
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

        if (! empty($config['assign_to_variable'])) {
            $instance->setVariable($config['assign_to_variable'], [
                '__entity_slug' => $entity->slug,
                '__entity_id' => $record->getKey(),
            ]);
            $instance->save();
        }
    }
}
