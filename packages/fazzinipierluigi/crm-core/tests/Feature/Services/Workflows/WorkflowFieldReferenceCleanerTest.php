<?php

use Fazzinipierluigi\AsgardCRM\Enums\EntityFieldType;
use Fazzinipierluigi\AsgardCRM\Enums\EntityRelationTargetType;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowActionPhase;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowActionType;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowVersionStatus;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\EntityCard;
use Fazzinipierluigi\AsgardCRM\Models\EntityField;
use Fazzinipierluigi\AsgardCRM\Models\EntityTab;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowAction;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowEdge;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowNode;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowVersion;
use Fazzinipierluigi\AsgardCRM\Services\EntityInstaller;
use Fazzinipierluigi\AsgardCRM\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function entityForCleanerTests(): Entity
{
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Anagrafica', 'position' => 0]);
    $card->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => 'string', 'position' => 0]);
    app(EntityInstaller::class)->install($entity);

    return $entity->fresh(['tabs.cards.fields']);
}

function deleteFieldFromInstalledEntity(Entity $entity, EntityField $field): void
{
    $tab = $entity->tabs->first();
    $card = $tab->cards->first();

    $response = test()->actingAs(adminUser())->put(route('admin.entities.builder.update', $entity), [
        'tabs' => [
            $tab->id => [
                'name' => $tab->name,
                'cards' => [
                    $card->id => ['name' => $card->name, 'fields' => []],
                ],
            ],
        ],
    ]);

    $response->assertRedirect(route('admin.entities.builder.edit', $entity));
}

test('a draft start_condition referencing the deleted field is cleared', function () {
    $entity = entityForCleanerTests();

    $version = WorkflowVersion::factory()->create(['status' => WorkflowVersionStatus::Draft]);
    $startNode = WorkflowNode::factory()->start()->create([
        'workflow_version_id' => $version->id,
        'config' => [
            'trigger_type' => 'entity_updated',
            'entity_slug' => 'contatti',
            'start_condition' => ['==' => [['var' => 'entity.nome'], 'Mario']],
        ],
    ]);

    $field = $entity->allFields()->firstWhere('column_name', 'nome');
    deleteFieldFromInstalledEntity($entity, $field);

    expect($startNode->fresh()->config['start_condition'])->toBeNull();
});

test('a draft edge condition_logic referencing the deleted field is cleared', function () {
    $entity = entityForCleanerTests();

    $version = WorkflowVersion::factory()->create(['status' => WorkflowVersionStatus::Draft]);
    WorkflowNode::factory()->start()->create([
        'workflow_version_id' => $version->id,
        'config' => ['trigger_type' => 'entity_updated', 'entity_slug' => 'contatti'],
    ]);
    $gate = WorkflowNode::factory()->create(['workflow_version_id' => $version->id]);
    $target = WorkflowNode::factory()->end()->create(['workflow_version_id' => $version->id]);
    $edge = WorkflowEdge::factory()->create([
        'workflow_version_id' => $version->id,
        'source_node_id' => $gate->id,
        'target_node_id' => $target->id,
        'condition_logic' => ['==' => [['var' => 'entity.nome'], 'Mario']],
    ]);

    $field = $entity->allFields()->firstWhere('column_name', 'nome');
    deleteFieldFromInstalledEntity($entity, $field);

    expect($edge->fresh()->condition_logic)->toBeNull();
});

test('an UpdateEntity action loses only the field assignment for the deleted column', function () {
    $entity = entityForCleanerTests();

    $version = WorkflowVersion::factory()->create(['status' => WorkflowVersionStatus::Draft]);
    $node = WorkflowNode::factory()->create(['workflow_version_id' => $version->id]);
    $action = WorkflowAction::factory()->create([
        'workflow_version_id' => $version->id,
        'actionable_type' => WorkflowNode::class,
        'actionable_id' => $node->id,
        'phase' => WorkflowActionPhase::After,
        'type' => WorkflowActionType::UpdateEntity,
        'config' => [
            'entity_slug' => 'contatti',
            'id_expression' => 'entity.id',
            'fields' => [
                ['column' => 'nome', 'expression' => "'Mario'"],
                ['column' => 'altro', 'expression' => "'X'"],
            ],
        ],
    ]);

    $field = $entity->allFields()->firstWhere('column_name', 'nome');
    deleteFieldFromInstalledEntity($entity, $field);

    expect($action->fresh()->config['fields'])->toBe([
        ['column' => 'altro', 'expression' => "'X'"],
    ]);
});

test('a FetchEntity action loses only the condition for the deleted column', function () {
    $entity = entityForCleanerTests();

    $version = WorkflowVersion::factory()->create(['status' => WorkflowVersionStatus::Draft]);
    $node = WorkflowNode::factory()->create(['workflow_version_id' => $version->id]);
    $action = WorkflowAction::factory()->create([
        'workflow_version_id' => $version->id,
        'actionable_type' => WorkflowNode::class,
        'actionable_id' => $node->id,
        'phase' => WorkflowActionPhase::After,
        'type' => WorkflowActionType::FetchEntity,
        'config' => [
            'entity_slug' => 'contatti',
            'variable' => 'risultato',
            'conditions' => [
                ['column' => 'nome', 'operator' => '=', 'expression' => "'Mario'"],
                ['column' => 'altro', 'operator' => '=', 'expression' => "'X'"],
            ],
        ],
    ]);

    $field = $entity->allFields()->firstWhere('column_name', 'nome');
    deleteFieldFromInstalledEntity($entity, $field);

    expect($action->fresh()->config['conditions'])->toBe([
        ['column' => 'altro', 'operator' => '=', 'expression' => "'X'"],
    ]);
});

test('a published version is never touched by the cleanup', function () {
    $entity = entityForCleanerTests();

    $version = WorkflowVersion::factory()->create(['status' => WorkflowVersionStatus::Published]);
    $startNode = WorkflowNode::factory()->start()->create([
        'workflow_version_id' => $version->id,
        'config' => [
            'trigger_type' => 'entity_updated',
            'entity_slug' => 'contatti',
            'start_condition' => ['==' => [['var' => 'entity.nome'], 'Mario']],
        ],
    ]);

    $field = $entity->allFields()->firstWhere('column_name', 'nome');
    deleteFieldFromInstalledEntity($entity, $field);

    expect($startNode->fresh()->config['start_condition'])->not->toBeNull();
});

test('a reference belonging to a different entity with a same-named column is left untouched', function () {
    $entity = entityForCleanerTests();

    $otherEntity = Entity::create(['name' => 'Aziende', 'slug' => 'aziende', 'table_name' => 'entity_aziende']);
    $otherTab = EntityTab::create(['entity_id' => $otherEntity->id, 'name' => 'Generale', 'position' => 0]);
    $otherCard = EntityCard::create(['entity_tab_id' => $otherTab->id, 'name' => 'Dati', 'position' => 0]);
    $otherCard->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => 'string', 'position' => 0]);
    app(EntityInstaller::class)->install($otherEntity);

    $version = WorkflowVersion::factory()->create(['status' => WorkflowVersionStatus::Draft]);
    $startNode = WorkflowNode::factory()->start()->create([
        'workflow_version_id' => $version->id,
        'config' => [
            'trigger_type' => 'entity_updated',
            'entity_slug' => 'aziende',
            'start_condition' => ['==' => [['var' => 'entity.nome'], 'Acme']],
        ],
    ]);

    $field = $entity->allFields()->firstWhere('column_name', 'nome');
    deleteFieldFromInstalledEntity($entity, $field);

    expect($startNode->fresh()->config['start_condition'])->not->toBeNull();
});

test('deleting a relation field cleans references keyed by its physical _id column', function () {
    $entity = Entity::create(['name' => 'Ordini', 'slug' => 'ordini-cleaner', 'table_name' => 'entity_ordini_cleaner']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Dati', 'position' => 0]);
    $card->fields()->create([
        'name' => 'Responsabile',
        'column_name' => 'responsabile',
        'type' => EntityFieldType::Relation,
        'relation_target_type' => EntityRelationTargetType::Model,
        'relation_target' => User::class,
        'position' => 0,
    ]);
    app(EntityInstaller::class)->install($entity);
    $entity = $entity->fresh(['tabs.cards.fields']);

    $version = WorkflowVersion::factory()->create(['status' => WorkflowVersionStatus::Draft]);
    $node = WorkflowNode::factory()->create(['workflow_version_id' => $version->id]);
    $action = WorkflowAction::factory()->create([
        'workflow_version_id' => $version->id,
        'actionable_type' => WorkflowNode::class,
        'actionable_id' => $node->id,
        'phase' => WorkflowActionPhase::After,
        'type' => WorkflowActionType::UpdateEntity,
        'config' => [
            'entity_slug' => 'ordini-cleaner',
            'id_expression' => 'entity.id',
            'fields' => [
                ['column' => 'responsabile_id', 'expression' => '1'],
            ],
        ],
    ]);

    $field = $entity->allFields()->firstWhere('column_name', 'responsabile');
    deleteFieldFromInstalledEntity($entity, $field);

    expect($action->fresh()->config['fields'])->toBe([]);
});
