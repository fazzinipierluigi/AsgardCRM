<?php

use App\Enums\EntityFieldType;
use App\Enums\WorkflowActionPhase;
use App\Enums\WorkflowActionType;
use App\Mail\WorkflowNotificationMail;
use App\Models\Entity;
use App\Models\EntityCard;
use App\Models\EntityRecord;
use App\Models\EntityTab;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowNode;
use App\Services\EntityInstaller;
use App\Services\Workflows\WorkflowActionExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

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
