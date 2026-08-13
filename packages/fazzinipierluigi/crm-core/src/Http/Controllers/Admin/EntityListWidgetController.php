<?php

namespace Fazzinipierluigi\AsgardCRM\Http\Controllers\Admin;

use Fazzinipierluigi\AsgardCRM\Enums\WorkflowNodeType;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowTriggerType;
use Fazzinipierluigi\AsgardCRM\Http\Controllers\Controller;
use Fazzinipierluigi\AsgardCRM\Http\Requests\Admin\EntityListWidgetRequest;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\EntityListWidget;
use Fazzinipierluigi\AsgardCRM\Models\Importer;
use Fazzinipierluigi\AsgardCRM\Models\Workflow;
use Fazzinipierluigi\AsgardCRM\Support\ButtonConfigValidator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin CRUD for an entity list's global buttons/counters/charts (see
 * resources/views/entities/index.blade.php) — the same 3 button actions
 * as the entity Button field, plus two read-only display widgets
 * computed at runtime by the public EntityListWidgetController.
 */
class EntityListWidgetController extends Controller
{
    public function index(Entity $entity): View
    {
        $entity->load('listWidgets');

        return view('crm::admin.entities.widgets.index', ['entity' => $entity]);
    }

    public function create(Entity $entity): View
    {
        return view('crm::admin.entities.widgets.form', $this->formData($entity));
    }

    public function store(EntityListWidgetRequest $request, Entity $entity): RedirectResponse
    {
        $entity->listWidgets()->create($this->attributesFrom($request, $entity));

        return redirect()->route('admin.entities.widgets.index', $entity)->with('status', 'entity-widget-added');
    }

    public function edit(Entity $entity, EntityListWidget $widget): View
    {
        return view('crm::admin.entities.widgets.form', [...$this->formData($entity), 'widget' => $widget]);
    }

    public function update(EntityListWidgetRequest $request, Entity $entity, EntityListWidget $widget): RedirectResponse
    {
        $widget->update($this->attributesFrom($request, $entity));

        return redirect()->route('admin.entities.widgets.index', $entity)->with('status', 'entity-widget-updated');
    }

    public function destroy(Entity $entity, EntityListWidget $widget): RedirectResponse
    {
        $widget->delete();

        return redirect()->route('admin.entities.widgets.index', $entity)->with('status', 'entity-widget-deleted');
    }

    /**
     * @return array{entity: Entity, manualWorkflows: Collection, importers: Collection, columns: array<string, string>, numericColumns: array<string, string>}
     */
    private function formData(Entity $entity): array
    {
        return [
            'entity' => $entity,
            'manualWorkflows' => $this->manualWorkflows(),
            'importers' => Importer::where('entity_id', $entity->id)->where('is_active', true)->get(),
            'columns' => EntityListWidgetRequest::filterableColumns($entity),
            'numericColumns' => EntityListWidgetRequest::numericColumns($entity),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesFrom(EntityListWidgetRequest $request, Entity $entity): array
    {
        $type = $request->validated('type');

        $config = match ($type) {
            'button' => ButtonConfigValidator::parse($request->validated()),
            'counter' => [
                'filter' => $request->filterConfig(),
                'color' => $request->validated('counter_color') ?: 'primary',
                'icon' => $request->validated('counter_icon') ?: null,
            ],
            'chart' => [
                'chart_type' => $request->validated('chart_type'),
                'group_by' => $request->validated('chart_group_by'),
                'aggregate' => $request->validated('chart_aggregate'),
                'value_column' => $request->validated('chart_aggregate') !== 'count' ? $request->validated('chart_value_column') : null,
                'filter' => $request->filterConfig(),
            ],
        };

        return [
            'entity_id' => $entity->id,
            'type' => $type,
            'name' => $request->validated('name'),
            'config' => $config,
            'position' => (int) ($request->validated('position') ?? $entity->listWidgets()->max('position') + 1),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /**
     * Active workflows whose start node is configured for manual
     * launch — the only ones a button widget can offer to run.
     */
    private function manualWorkflows(): Collection
    {
        return Workflow::where('is_active', true)
            ->whereHas('currentVersion.nodes', function ($query) {
                $query->where('type', WorkflowNodeType::Start->value)
                    ->where('config->trigger_type', WorkflowTriggerType::Manual->value);
            })
            ->get();
    }
}
