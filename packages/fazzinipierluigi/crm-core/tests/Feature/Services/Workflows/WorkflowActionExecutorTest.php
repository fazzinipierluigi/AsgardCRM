<?php

use Fazzinipierluigi\CrmCore\Enums\EntityFieldType;
use Fazzinipierluigi\CrmCore\Enums\WorkflowActionPhase;
use Fazzinipierluigi\CrmCore\Enums\WorkflowActionType;
use Fazzinipierluigi\CrmCore\Mail\WorkflowNotificationMail;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityCard;
use Fazzinipierluigi\CrmCore\Models\EntityFieldChange;
use Fazzinipierluigi\CrmCore\Models\EntityRecord;
use Fazzinipierluigi\CrmCore\Models\EntityTab;
use Fazzinipierluigi\CrmCore\Models\WorkflowApiEndpoint;
use Fazzinipierluigi\CrmCore\Models\WorkflowInstance;
use Fazzinipierluigi\CrmCore\Models\WorkflowNode;
use Fazzinipierluigi\CrmCore\Models\WorkflowSqlConnection;
use Fazzinipierluigi\CrmCore\Services\EntityInstaller;
use Fazzinipierluigi\CrmCore\Services\Workflows\WorkflowActionExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Fazzinipierluigi\CrmCore\Tests\Fixtures\User;

uses(RefreshDatabase::class);

function wfActionClienteEntity(): Entity
{
    $entity = Entity::create(['name' => 'Cliente WF', 'slug' => 'cliente-wf-'.uniqid(), 'table_name' => 'entity_cliente_wf_'.uniqid()]);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Anagrafica', 'position' => 0]);
    $card->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'position' => 0]);
    $card->fields()->create(['name' => 'Email', 'column_name' => 'email', 'type' => EntityFieldType::String, 'position' => 1]);

    app(EntityInstaller::class)->install($entity);

    return $entity;
}

function wfActionUserId(): int
{
    return User::factory()->create()->id;
}

function wfActionInstance(): WorkflowInstance
{
    $workflow = wfWorkflowWithVersion();

    return WorkflowInstance::factory()->for($workflow)->for($workflow->currentVersion)->create(['variables' => []]);
}

test('assign_entity_to_variable stores a resolvable reference to the matched record', function () {
    $entity = wfActionClienteEntity();
    $record = EntityRecord::forEntity($entity)->create(['nome' => 'Mario Rossi', 'email' => 'mario@example.com', 'user_id' => wfActionUserId()]);
    $instance = wfActionInstance();

    $node = WorkflowNode::factory()->for($instance->workflowVersion)->create();
    $action = $node->actions()->create([
        'workflow_version_id' => $instance->workflow_version_id,
        'phase' => WorkflowActionPhase::After,
        'type' => WorkflowActionType::AssignEntityToVariable,
        'config' => ['variable' => 'cliente', 'entity_slug' => $entity->slug, 'id_expression' => (string) $record->id],
    ]);

    app(WorkflowActionExecutor::class)->execute($action, $instance);

    expect($instance->fresh()->getVariable('cliente'))->toBe([
        '__entity_slug' => $entity->slug,
        '__entity_id' => $record->id,
    ]);
});

test('update_entity writes evaluated field values onto the matched record', function () {
    $entity = wfActionClienteEntity();
    $record = EntityRecord::forEntity($entity)->create(['nome' => 'Mario Rossi', 'email' => 'mario@example.com', 'user_id' => wfActionUserId()]);
    $instance = wfActionInstance();

    $node = WorkflowNode::factory()->for($instance->workflowVersion)->create();
    $action = $node->actions()->create([
        'workflow_version_id' => $instance->workflow_version_id,
        'phase' => WorkflowActionPhase::After,
        'type' => WorkflowActionType::UpdateEntity,
        'config' => [
            'entity_slug' => $entity->slug,
            'id_expression' => (string) $record->id,
            'fields' => [['column' => 'email', 'expression' => "'nuovo@example.com'"]],
        ],
    ]);

    app(WorkflowActionExecutor::class)->execute($action, $instance);

    expect(EntityRecord::forEntity($entity)->find($record->id)->email)->toBe('nuovo@example.com');
});

test('create_entity inserts a new record and can assign its reference to a variable', function () {
    $entity = wfActionClienteEntity();
    $instance = wfActionInstance();

    $node = WorkflowNode::factory()->for($instance->workflowVersion)->create();
    $action = $node->actions()->create([
        'workflow_version_id' => $instance->workflow_version_id,
        'phase' => WorkflowActionPhase::After,
        'type' => WorkflowActionType::CreateEntity,
        'config' => [
            'entity_slug' => $entity->slug,
            'fields' => [
                ['column' => 'nome', 'expression' => "'Luca Bianchi'"],
                ['column' => 'email', 'expression' => "'luca@example.com'"],
                ['column' => 'user_id', 'expression' => (string) wfActionUserId()],
            ],
            'assign_to_variable' => 'nuovo_cliente',
        ],
    ]);

    app(WorkflowActionExecutor::class)->execute($action, $instance);

    $created = EntityRecord::forEntity($entity)->where('nome', 'Luca Bianchi')->first();

    expect($created)->not->toBeNull()
        ->and($instance->fresh()->getVariable('nuovo_cliente.__entity_id'))->toBe($created->id);
});

test('update_entity logs the change with the workflow as source, no user', function () {
    $entity = wfActionClienteEntity();
    $record = EntityRecord::forEntity($entity)->create(['nome' => 'Mario Rossi', 'email' => 'mario@example.com', 'user_id' => wfActionUserId()]);
    $instance = wfActionInstance();

    $node = WorkflowNode::factory()->for($instance->workflowVersion)->create();
    $action = $node->actions()->create([
        'workflow_version_id' => $instance->workflow_version_id,
        'phase' => WorkflowActionPhase::After,
        'type' => WorkflowActionType::UpdateEntity,
        'config' => [
            'entity_slug' => $entity->slug,
            'id_expression' => (string) $record->id,
            'fields' => [['column' => 'email', 'expression' => "'nuovo@example.com'"]],
        ],
    ]);

    app(WorkflowActionExecutor::class)->execute($action, $instance);

    $change = EntityFieldChange::where('entity_slug', $entity->slug)->where('entity_id', $record->id)->firstOrFail();
    expect($change->column_name)->toBe('email');
    expect($change->old_value)->toBe('mario@example.com');
    expect($change->new_value)->toBe('nuovo@example.com');
    expect($change->changed_by_user_id)->toBeNull();
    expect($change->changed_by_label)->toBe("Flusso: {$instance->workflow->name}");
});

test('create_entity logs the new record with the workflow as source, no user', function () {
    $entity = wfActionClienteEntity();
    $instance = wfActionInstance();

    $node = WorkflowNode::factory()->for($instance->workflowVersion)->create();
    $action = $node->actions()->create([
        'workflow_version_id' => $instance->workflow_version_id,
        'phase' => WorkflowActionPhase::After,
        'type' => WorkflowActionType::CreateEntity,
        'config' => [
            'entity_slug' => $entity->slug,
            'fields' => [
                ['column' => 'nome', 'expression' => "'Luca Bianchi'"],
                ['column' => 'user_id', 'expression' => (string) wfActionUserId()],
            ],
        ],
    ]);

    app(WorkflowActionExecutor::class)->execute($action, $instance);

    $created = EntityRecord::forEntity($entity)->where('nome', 'Luca Bianchi')->firstOrFail();
    $change = EntityFieldChange::where('entity_slug', $entity->slug)->where('entity_id', $created->id)->where('column_name', 'nome')->firstOrFail();

    expect($change->new_value)->toBe('Luca Bianchi');
    expect($change->changed_by_user_id)->toBeNull();
    expect($change->changed_by_label)->toBe("Flusso: {$instance->workflow->name}");
});

test('send_email renders {{ variabile }} placeholders and sends to the resolved recipients', function () {
    Mail::fake();

    $instance = wfActionInstance();
    $instance->setVariable('destinatario', 'test@example.com');
    $instance->setVariable('nome', 'Mario');
    $instance->save();

    $node = WorkflowNode::factory()->for($instance->workflowVersion)->create();
    $action = $node->actions()->create([
        'workflow_version_id' => $instance->workflow_version_id,
        'phase' => WorkflowActionPhase::After,
        'type' => WorkflowActionType::SendEmail,
        'config' => [
            'to' => '{{ destinatario }}',
            'subject' => 'Ciao {{ nome }}',
            'body' => '<p>Benvenuto {{ nome }}</p>',
        ],
    ]);

    app(WorkflowActionExecutor::class)->execute($action, $instance);

    Mail::assertSent(WorkflowNotificationMail::class, function ($mail) {
        return $mail->renderedSubject === 'Ciao Mario'
            && $mail->hasTo('test@example.com');
    });
});

function wfActionSqliteDatabase(): string
{
    $path = tempnam(sys_get_temp_dir(), 'wf_action_sql_').'.sqlite';
    touch($path);

    $pdo = new PDO('sqlite:'.$path);
    $pdo->exec('CREATE TABLE ordini (id INTEGER PRIMARY KEY, cliente_id INTEGER, totale REAL, note TEXT)');
    $pdo->exec("INSERT INTO ordini (cliente_id, totale, note) VALUES (1, 100.5, 'primo ordine')");
    $pdo->exec("INSERT INTO ordini (cliente_id, totale, note) VALUES (2, 50, 'secondo ordine')");

    return $path;
}

test('assign_variable_from_sql assigns the lone column of the first matching row', function () {
    $path = wfActionSqliteDatabase();
    $connection = WorkflowSqlConnection::create(['name' => 'Test', 'config' => ['driver' => 'sqlite', 'database' => $path]]);
    $instance = wfActionInstance();
    $instance->setVariable('cliente_id', 1);
    $instance->save();

    $node = WorkflowNode::factory()->for($instance->workflowVersion)->create();
    $action = $node->actions()->create([
        'workflow_version_id' => $instance->workflow_version_id,
        'phase' => WorkflowActionPhase::After,
        'type' => WorkflowActionType::AssignVariableFromSql,
        'config' => [
            'connection_id' => $connection->id,
            'query' => 'SELECT totale FROM ordini WHERE cliente_id = :cid',
            'bindings' => [['name' => 'cid', 'expression' => 'cliente_id']],
            'variable' => 'totale',
        ],
    ]);

    app(WorkflowActionExecutor::class)->execute($action, $instance);

    expect($instance->fresh()->getVariable('totale'))->toBe(100.5);

    unlink($path);
});

test('assign_variable_from_sql assigns the whole row when more than one column is selected', function () {
    $path = wfActionSqliteDatabase();
    $connection = WorkflowSqlConnection::create(['name' => 'Test', 'config' => ['driver' => 'sqlite', 'database' => $path]]);
    $instance = wfActionInstance();

    $node = WorkflowNode::factory()->for($instance->workflowVersion)->create();
    $action = $node->actions()->create([
        'workflow_version_id' => $instance->workflow_version_id,
        'phase' => WorkflowActionPhase::After,
        'type' => WorkflowActionType::AssignVariableFromSql,
        'config' => [
            'connection_id' => $connection->id,
            'query' => 'SELECT cliente_id, totale FROM ordini ORDER BY cliente_id',
            'bindings' => [],
            'variable' => 'riga',
        ],
    ]);

    app(WorkflowActionExecutor::class)->execute($action, $instance);

    expect($instance->fresh()->getVariable('riga'))->toBe(['cliente_id' => 1, 'totale' => 100.5]);

    unlink($path);
});

test('assign_variable_from_sql rejects a query that is not a SELECT or WITH', function () {
    $path = wfActionSqliteDatabase();
    $connection = WorkflowSqlConnection::create(['name' => 'Test', 'config' => ['driver' => 'sqlite', 'database' => $path]]);
    $instance = wfActionInstance();

    $node = WorkflowNode::factory()->for($instance->workflowVersion)->create();
    $action = $node->actions()->create([
        'workflow_version_id' => $instance->workflow_version_id,
        'phase' => WorkflowActionPhase::After,
        'type' => WorkflowActionType::AssignVariableFromSql,
        'config' => [
            'connection_id' => $connection->id,
            'query' => 'DELETE FROM ordini',
            'bindings' => [],
            'variable' => 'x',
        ],
    ]);

    expect(fn () => app(WorkflowActionExecutor::class)->execute($action, $instance))
        ->toThrow(RuntimeException::class);

    unlink($path);
});

test('assign_variable_from_sql rejects a query with a stacked second statement', function () {
    $path = wfActionSqliteDatabase();
    $connection = WorkflowSqlConnection::create(['name' => 'Test', 'config' => ['driver' => 'sqlite', 'database' => $path]]);
    $instance = wfActionInstance();

    $node = WorkflowNode::factory()->for($instance->workflowVersion)->create();
    $action = $node->actions()->create([
        'workflow_version_id' => $instance->workflow_version_id,
        'phase' => WorkflowActionPhase::After,
        'type' => WorkflowActionType::AssignVariableFromSql,
        'config' => [
            'connection_id' => $connection->id,
            'query' => 'SELECT 1; DELETE FROM ordini',
            'bindings' => [],
            'variable' => 'x',
        ],
    ]);

    expect(fn () => app(WorkflowActionExecutor::class)->execute($action, $instance))
        ->toThrow(RuntimeException::class);

    unlink($path);
});

test('assign_variable_from_api assigns the decoded JSON response and sends bearer auth', function () {
    Http::fake(['api.example.com/*' => Http::response(['id' => 5, 'name' => 'Mario'], 200)]);

    $endpoint = WorkflowApiEndpoint::create([
        'name' => 'Test API',
        'base_url' => 'https://api.example.com',
        'config' => ['auth_type' => 'bearer', 'token' => 'segreto123'],
    ]);
    $instance = wfActionInstance();
    $instance->setVariable('cliente_id', 5);
    $instance->save();

    $node = WorkflowNode::factory()->for($instance->workflowVersion)->create();
    $action = $node->actions()->create([
        'workflow_version_id' => $instance->workflow_version_id,
        'phase' => WorkflowActionPhase::After,
        'type' => WorkflowActionType::AssignVariableFromApi,
        'config' => [
            'endpoint_id' => $endpoint->id,
            'method' => 'GET',
            'path' => '/users/{{ cliente_id }}',
            'query' => [],
            'body' => null,
            'variable' => 'utente',
        ],
    ]);

    app(WorkflowActionExecutor::class)->execute($action, $instance);

    expect($instance->fresh()->getVariable('utente'))->toBe(['id' => 5, 'name' => 'Mario']);
    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization', 'Bearer segreto123')
            && $request->url() === 'https://api.example.com/users/5';
    });
});

test('assign_variable_from_api fails the instance on a non-2xx response', function () {
    Http::fake(['api.example.com/*' => Http::response(['error' => 'nope'], 500)]);

    $endpoint = WorkflowApiEndpoint::create([
        'name' => 'Test API',
        'base_url' => 'https://api.example.com',
        'config' => ['auth_type' => 'none'],
    ]);
    $instance = wfActionInstance();

    $node = WorkflowNode::factory()->for($instance->workflowVersion)->create();
    $action = $node->actions()->create([
        'workflow_version_id' => $instance->workflow_version_id,
        'phase' => WorkflowActionPhase::After,
        'type' => WorkflowActionType::AssignVariableFromApi,
        'config' => [
            'endpoint_id' => $endpoint->id,
            'method' => 'GET',
            'path' => '/broken',
            'query' => [],
            'body' => null,
            'variable' => 'utente',
        ],
    ]);

    expect(fn () => app(WorkflowActionExecutor::class)->execute($action, $instance))
        ->toThrow(RequestException::class);
});

function wfActionFetchEntity(): Entity
{
    $entity = Entity::create(['name' => 'Ordini Fetch', 'slug' => 'ordini-fetch-'.uniqid(), 'table_name' => 'entity_ordini_fetch_'.uniqid()]);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Dati', 'position' => 0]);
    $card->fields()->create(['name' => 'Attivo', 'column_name' => 'attivo', 'type' => EntityFieldType::Checkbox, 'position' => 0]);
    $card->fields()->create(['name' => 'Importo', 'column_name' => 'importo', 'type' => EntityFieldType::DecimalNumber, 'position' => 1]);

    app(EntityInstaller::class)->install($entity);

    return $entity->fresh();
}

test('fetch_entity assigns records matching every AND-ed condition', function () {
    $entity = wfActionFetchEntity();
    $userId = wfActionUserId();
    EntityRecord::forEntity($entity)->create(['user_id' => $userId, 'attivo' => true, 'importo' => 10]);
    EntityRecord::forEntity($entity)->create(['user_id' => $userId, 'attivo' => true, 'importo' => 2]);
    EntityRecord::forEntity($entity)->create(['user_id' => $userId, 'attivo' => false, 'importo' => 20]);
    $instance = wfActionInstance();
    $instance->setVariable('soglia', 5);
    $instance->save();

    $node = WorkflowNode::factory()->for($instance->workflowVersion)->create();
    $action = $node->actions()->create([
        'workflow_version_id' => $instance->workflow_version_id,
        'phase' => WorkflowActionPhase::After,
        'type' => WorkflowActionType::FetchEntity,
        'config' => [
            'entity_slug' => $entity->slug,
            'conditions' => [
                ['column' => 'attivo', 'operator' => '=', 'expression' => 'true'],
                ['column' => 'importo', 'operator' => '>', 'expression' => 'soglia'],
            ],
            'variable' => 'risultati',
        ],
    ]);

    app(WorkflowActionExecutor::class)->execute($action, $instance);

    $results = $instance->fresh()->getVariable('risultati');
    expect($results)->toHaveCount(1);
    expect($results[0]['__entity_slug'])->toBe($entity->slug);
});

test('fetch_entity rejects a condition on a non-whitelisted column', function () {
    $entity = wfActionFetchEntity();
    $instance = wfActionInstance();

    $node = WorkflowNode::factory()->for($instance->workflowVersion)->create();
    $action = $node->actions()->create([
        'workflow_version_id' => $instance->workflow_version_id,
        'phase' => WorkflowActionPhase::After,
        'type' => WorkflowActionType::FetchEntity,
        'config' => [
            'entity_slug' => $entity->slug,
            'conditions' => [
                ['column' => 'id; DROP TABLE users', 'operator' => '=', 'expression' => '1'],
            ],
            'variable' => 'risultati',
        ],
    ]);

    expect(fn () => app(WorkflowActionExecutor::class)->execute($action, $instance))
        ->toThrow(RuntimeException::class);
});
