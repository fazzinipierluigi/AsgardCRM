<?php

namespace App\Http\Controllers;

use App\Enums\EntityFieldType;
use App\Http\Requests\Admin\EntityListWidgetRequest;
use App\Jobs\RunImporterJob;
use App\Models\Entity;
use App\Models\EntityField;
use App\Models\EntityListWidget;
use App\Models\EntityRecord;
use App\Models\Importer;
use App\Models\Workflow;
use App\Services\EntityRelationResolver;
use App\Services\Workflows\WorkflowEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Executes a list widget's button action, or computes the data behind
 * a counter/chart widget — the runtime counterpart of the admin CRUD in
 * Admin\EntityListWidgetController. Reachable by anyone who can see the
 * entity's own record list (entity_{slug}.index): there's no finer
 * permission for an individual widget.
 */
class EntityListWidgetController extends Controller
{
    public function __construct(
        private readonly WorkflowEngine $engine,
        private readonly EntityRelationResolver $relationResolver,
    ) {}

    public function trigger(Entity $entity, EntityListWidget $widget): JsonResponse
    {
        $this->authorizeWidget($entity, $widget);

        if ($widget->type !== 'button') {
            throw new NotFoundHttpException;
        }

        return match ($widget->config['button_action'] ?? null) {
            'workflow' => $this->runWorkflow($widget),
            'importer' => $this->runImporters($widget),
            default => response()->json(['message' => 'Nessuna azione configurata per questo widget.'], 422),
        };
    }

    public function data(Entity $entity, EntityListWidget $widget): JsonResponse
    {
        $this->authorizeWidget($entity, $widget);

        return match ($widget->type) {
            'counter' => response()->json(['value' => $this->counterValue($entity, $widget)]),
            'chart' => response()->json($this->chartData($entity, $widget)),
            default => throw new NotFoundHttpException,
        };
    }

    private function authorizeWidget(Entity $entity, EntityListWidget $widget): void
    {
        if (! $entity->is_installed || $widget->entity_id !== $entity->id || ! $widget->is_active) {
            throw new NotFoundHttpException;
        }

        if (request()->user()->cannot("entity_{$entity->slug}.index")) {
            abort(403);
        }
    }

    private function runWorkflow(EntityListWidget $widget): JsonResponse
    {
        $workflow = Workflow::find($widget->config['button_workflow_id'] ?? null);

        if (! $workflow) {
            return response()->json(['message' => 'Workflow non trovato.'], 422);
        }

        try {
            $instance = $this->engine->start($workflow);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($instance === null) {
            return response()->json(['message' => 'La condizione di avvio non è soddisfatta.'], 422);
        }

        return response()->json(['message' => 'Flusso avviato correttamente.']);
    }

    private function runImporters(EntityListWidget $widget): JsonResponse
    {
        $importerIds = $widget->config['button_importer_ids'] ?? [];

        foreach (Importer::whereIn('id', $importerIds)->get() as $importer) {
            RunImporterJob::dispatch($importer);
        }

        return response()->json(['message' => 'Importazione avviata.']);
    }

    private function counterValue(Entity $entity, EntityListWidget $widget): int
    {
        $query = EntityRecord::forEntity($entity)->newQuery();
        $this->applyFilter($query, $entity, $widget->config['filter'] ?? null);

        return $query->count();
    }

    /**
     * @return array{labels: list<string>, values: list<float>, chart_type: string}
     */
    private function chartData(Entity $entity, EntityListWidget $widget): array
    {
        $config = $widget->config;
        $groupBy = $config['group_by'] ?? null;
        $aggregate = $config['aggregate'] ?? 'count';
        $valueColumn = $config['value_column'] ?? null;

        $validColumns = EntityListWidgetRequest::filterableColumns($entity);

        if ($groupBy === null || ! array_key_exists($groupBy, $validColumns)) {
            abort(422, 'Colonna di raggruppamento non valida.');
        }

        if ($aggregate !== 'count' && (! $valueColumn || ! array_key_exists($valueColumn, EntityListWidgetRequest::numericColumns($entity)))) {
            abort(422, 'Colonna da aggregare non valida.');
        }

        $query = EntityRecord::forEntity($entity)->newQuery();
        $this->applyFilter($query, $entity, $config['filter'] ?? null);

        $selectRaw = match ($aggregate) {
            'sum' => "SUM({$valueColumn}) as agg",
            'avg' => "AVG({$valueColumn}) as agg",
            default => 'COUNT(*) as agg',
        };

        $rows = $query->select($groupBy)->selectRaw($selectRaw)->groupBy($groupBy)->get();
        $labels = $this->labelsFor($entity, $groupBy);

        return [
            'labels' => $rows->map(fn ($row) => (string) ($labels[$row->{$groupBy}] ?? $row->{$groupBy}))->all(),
            'values' => $rows->map(fn ($row) => (float) $row->agg)->all(),
            'chart_type' => $config['chart_type'] ?? 'bar',
        ];
    }

    /**
     * @param  array{column: string, operator: string, value: string}|null  $filter
     */
    private function applyFilter(Builder $query, Entity $entity, ?array $filter): void
    {
        if ($filter === null) {
            return;
        }

        $validColumns = EntityListWidgetRequest::filterableColumns($entity);

        if (! array_key_exists($filter['column'], $validColumns)) {
            return;
        }

        $query->where($filter['column'], $filter['operator'], $filter['value']);
    }

    /**
     * Human-friendly labels for a group-by column's raw values — Select
     * options and Relation targets resolve to a label, everything else
     * (string/date/checkbox/number) is shown as-is.
     *
     * @return array<int|string, string>
     */
    private function labelsFor(Entity $entity, string $column): array
    {
        $field = $entity->allFields()->first(fn (EntityField $f) => $this->columnFor($f) === $column);

        if (! $field) {
            return [];
        }

        return match ($field->type) {
            EntityFieldType::Select => $field->options ?? [],
            EntityFieldType::Relation => $this->relationResolver->labelsForField($field),
            default => [],
        };
    }

    private function columnFor(EntityField $field): string
    {
        return $field->type === EntityFieldType::Relation ? "{$field->column_name}_id" : $field->column_name;
    }
}
