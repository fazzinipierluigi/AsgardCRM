<?php

use Fazzinipierluigi\AsgardCRM\Enums\EntityFieldType;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\EntityCard;
use Fazzinipierluigi\AsgardCRM\Models\EntityListWidget;
use Fazzinipierluigi\AsgardCRM\Models\EntityTab;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowNode;
use Fazzinipierluigi\AsgardCRM\Services\EntityInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function widgetTestEntity(): Entity
{
    $entity = Entity::create(['name' => 'Ordini', 'slug' => 'ordini-widget', 'table_name' => 'entity_ordini_widget']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Dati', 'position' => 0]);
    $card->fields()->create(['name' => 'Stato', 'column_name' => 'stato', 'type' => EntityFieldType::Select, 'options' => ['aperto' => 'Aperto', 'chiuso' => 'Chiuso'], 'position' => 0]);
    $card->fields()->create(['name' => 'Importo', 'column_name' => 'importo', 'type' => EntityFieldType::DecimalNumber, 'position' => 1]);

    app(EntityInstaller::class)->install($entity);

    return $entity->fresh();
}

test('guests are redirected to login', function () {
    $entity = widgetTestEntity();

    $this->get(route('admin.entities.widgets.index', $entity))->assertRedirect(route('login'));
});

test('admin can create a counter widget with a filter', function () {
    $entity = widgetTestEntity();

    $response = $this->actingAs(adminUser())->post(route('admin.entities.widgets.store', $entity), [
        'name' => 'Ordini aperti',
        'type' => 'counter',
        'filter_column' => 'stato',
        'filter_operator' => '=',
        'filter_value' => 'aperto',
        'counter_color' => 'success',
    ]);

    $response->assertRedirect(route('admin.entities.widgets.index', $entity));

    $widget = EntityListWidget::where('entity_id', $entity->id)->firstOrFail();
    expect($widget->type)->toBe('counter');
    expect($widget->config)->toBe([
        'filter' => ['column' => 'stato', 'operator' => '=', 'value' => 'aperto'],
        'color' => 'success',
        'icon' => null,
    ]);
});

test('admin can create a chart widget aggregating a numeric column', function () {
    $entity = widgetTestEntity();

    $response = $this->actingAs(adminUser())->post(route('admin.entities.widgets.store', $entity), [
        'name' => 'Importo per stato',
        'type' => 'chart',
        'chart_type' => 'bar',
        'chart_group_by' => 'stato',
        'chart_aggregate' => 'sum',
        'chart_value_column' => 'importo',
    ]);

    $response->assertRedirect(route('admin.entities.widgets.index', $entity));

    $widget = EntityListWidget::where('entity_id', $entity->id)->firstOrFail();
    expect($widget->config)->toBe([
        'chart_type' => 'bar',
        'group_by' => 'stato',
        'aggregate' => 'sum',
        'value_column' => 'importo',
        'filter' => null,
    ]);
});

test('a chart with sum/avg aggregate requires a numeric value column', function () {
    $entity = widgetTestEntity();

    $response = $this->actingAs(adminUser())->post(route('admin.entities.widgets.store', $entity), [
        'name' => 'Importo per stato',
        'type' => 'chart',
        'chart_type' => 'bar',
        'chart_group_by' => 'stato',
        'chart_aggregate' => 'sum',
    ]);

    $response->assertSessionHasErrors('chart_value_column');
});

test('a chart cannot group by or aggregate a non-existent column', function () {
    $entity = widgetTestEntity();

    $this->actingAs(adminUser())->post(route('admin.entities.widgets.store', $entity), [
        'name' => 'Bad chart',
        'type' => 'chart',
        'chart_type' => 'bar',
        'chart_group_by' => 'campo_inesistente',
        'chart_aggregate' => 'count',
    ])->assertSessionHasErrors('chart_group_by');

    $this->actingAs(adminUser())->post(route('admin.entities.widgets.store', $entity), [
        'name' => 'Bad chart',
        'type' => 'chart',
        'chart_type' => 'bar',
        'chart_group_by' => 'stato',
        'chart_aggregate' => 'sum',
        'chart_value_column' => 'stato',
    ])->assertSessionHasErrors('chart_value_column');
});

test('admin can create a button widget reusing the same workflow action config', function () {
    $entity = widgetTestEntity();
    $workflow = wfWorkflowWithVersion();
    WorkflowNode::factory()->for($workflow->currentVersion)->start()->create();

    $response = $this->actingAs(adminUser())->post(route('admin.entities.widgets.store', $entity), [
        'name' => 'Avvia flusso',
        'type' => 'button',
        'button_action' => 'workflow',
        'button_workflow_id' => $workflow->id,
    ]);

    $response->assertRedirect(route('admin.entities.widgets.index', $entity));

    $widget = EntityListWidget::where('entity_id', $entity->id)->firstOrFail();
    expect($widget->config['button_action'])->toBe('workflow');
    expect($widget->config['button_workflow_id'])->toBe($workflow->id);
});

test('admin can update and delete a widget', function () {
    $entity = widgetTestEntity();
    $widget = $entity->listWidgets()->create([
        'type' => 'counter',
        'name' => 'Originale',
        'config' => ['filter' => null, 'color' => 'primary', 'icon' => null],
        'position' => 1,
        'is_active' => true,
    ]);

    $this->actingAs(adminUser())->put(route('admin.entities.widgets.update', [$entity, $widget]), [
        'name' => 'Rinominato',
        'type' => 'counter',
        'counter_color' => 'danger',
        'is_active' => '1',
    ])->assertRedirect(route('admin.entities.widgets.index', $entity));

    expect($widget->fresh()->name)->toBe('Rinominato');
    expect($widget->fresh()->config['color'])->toBe('danger');
    expect($widget->fresh()->is_active)->toBeTrue();

    $this->actingAs(adminUser())->delete(route('admin.entities.widgets.destroy', [$entity, $widget]))
        ->assertRedirect(route('admin.entities.widgets.index', $entity));

    expect(EntityListWidget::find($widget->id))->toBeNull();
});
