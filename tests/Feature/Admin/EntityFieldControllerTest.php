<?php

use Fazzinipierluigi\AsgardCRM\Enums\EntityFieldType;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowActionPhase;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowActionType;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowVersionStatus;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\EntityCard;
use Fazzinipierluigi\AsgardCRM\Models\EntityTab;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowAction;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowNode;
use Fazzinipierluigi\AsgardCRM\Services\EntityInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function installedEntityWithCard(): array
{
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Anagrafica', 'position' => 0]);
    $card->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'position' => 0]);

    app(EntityInstaller::class)->install($entity);

    return [$entity->fresh(), $card];
}

test('guests are redirected to login', function () {
    [$entity] = installedEntityWithCard();
    $field = $entity->allFields()->firstWhere('column_name', 'nome');

    $this->getJson(route('admin.entities.fields.usage', [$entity, $field]))->assertUnauthorized();
});

test('the usage check reports no references when the field is unused', function () {
    [$entity] = installedEntityWithCard();
    $field = $entity->allFields()->firstWhere('column_name', 'nome');

    $response = $this->actingAs(adminUser())->getJson(route('admin.entities.fields.usage', [$entity, $field]));

    $response->assertOk()->assertJson(['cleanable' => [], 'manual' => []]);
});

test('the usage check splits draft references as cleanable and published ones as manual', function () {
    [$entity] = installedEntityWithCard();
    $field = $entity->allFields()->firstWhere('column_name', 'nome');

    $draftWorkflow = wfWorkflowWithVersion();
    $draftVersion = $draftWorkflow->currentVersion;
    $draftVersion->update(['status' => WorkflowVersionStatus::Draft]);
    WorkflowNode::factory()->start()->create([
        'workflow_version_id' => $draftVersion->id,
        'config' => [
            'trigger_type' => 'entity_updated',
            'entity_slug' => 'contatti',
            'start_condition' => ['==' => [['var' => 'entity.nome'], 'Mario']],
        ],
    ]);

    $publishedWorkflow = wfWorkflowWithVersion();
    $publishedNode = WorkflowNode::factory()->create(['workflow_version_id' => $publishedWorkflow->currentVersion->id]);
    WorkflowNode::factory()->start()->create([
        'workflow_version_id' => $publishedWorkflow->currentVersion->id,
        'config' => ['trigger_type' => 'entity_updated', 'entity_slug' => 'contatti'],
    ]);
    WorkflowAction::factory()->create([
        'workflow_version_id' => $publishedWorkflow->currentVersion->id,
        'actionable_type' => WorkflowNode::class,
        'actionable_id' => $publishedNode->id,
        'phase' => WorkflowActionPhase::After,
        'type' => WorkflowActionType::UpdateEntity,
        'config' => [
            'entity_slug' => 'contatti',
            'id_expression' => 'entity.id',
            'fields' => [['column' => 'nome', 'expression' => "'Mario'"]],
        ],
    ]);

    $response = $this->actingAs(adminUser())->getJson(route('admin.entities.fields.usage', [$entity, $field]));

    $response->assertOk();
    expect($response->json('cleanable'))->toHaveCount(1);
    expect($response->json('manual'))->toHaveCount(1);
});

test('the usage check flags a free-text expression reference as manual only', function () {
    [$entity] = installedEntityWithCard();
    $field = $entity->allFields()->firstWhere('column_name', 'nome');

    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $node = WorkflowNode::factory()->create(['workflow_version_id' => $version->id]);
    WorkflowNode::factory()->start()->create([
        'workflow_version_id' => $version->id,
        'config' => ['trigger_type' => 'entity_updated', 'entity_slug' => 'contatti'],
    ]);
    WorkflowAction::factory()->create([
        'workflow_version_id' => $version->id,
        'actionable_type' => WorkflowNode::class,
        'actionable_id' => $node->id,
        'phase' => WorkflowActionPhase::After,
        'type' => WorkflowActionType::SetVariable,
        'config' => ['variable' => 'x', 'expression' => "entity.nome + ' extra'"],
    ]);

    $response = $this->actingAs(adminUser())->getJson(route('admin.entities.fields.usage', [$entity, $field]));

    $response->assertOk()->assertJson(['cleanable' => []]);
    expect($response->json('manual'))->toHaveCount(1);
});

test('a field from another entity is not found by the usage check', function () {
    [$entity] = installedEntityWithCard();
    $other = Entity::create(['name' => 'Aziende', 'slug' => 'aziende', 'table_name' => 'entity_aziende']);
    $otherTab = EntityTab::create(['entity_id' => $other->id, 'name' => 'Generale', 'position' => 0]);
    $otherCard = EntityCard::create(['entity_tab_id' => $otherTab->id, 'name' => 'Dati', 'position' => 0]);
    $field = $otherCard->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => 'string', 'position' => 0]);

    $this->actingAs(adminUser())->getJson(route('admin.entities.fields.usage', [$entity, $field]))->assertNotFound();
});
