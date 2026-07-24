<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EntityFieldType;
use App\Enums\WorkflowNodeType;
use App\Enums\WorkflowTriggerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEntityFieldRequest;
use App\Models\Entity;
use App\Models\Importer;
use App\Models\Workflow;
use App\Services\EntityRelationResolver;
use App\Services\EntitySchemaBuilder;
use App\Support\ButtonConfigValidator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Adds a single new field to an already-installed entity, appending a
 * real column to its dynamic table via EntitySchemaBuilder::addColumn().
 *
 * This is deliberately separate from EntityBuilderController, whose
 * whole-tree update is rejected outright once an entity is installed
 * (see UpdateEntityBuilderRequest): an installed entity's structure is
 * append-only from here on, never replaced or reordered. Fields created
 * through this endpoint are never is_locked — only fields seeded as part
 * of a system entity's fixed shape (e.g. the Calendar) are.
 */
class EntityFieldController extends Controller
{
    public function __construct(
        private readonly EntitySchemaBuilder $schemaBuilder,
        private readonly EntityRelationResolver $relationResolver,
    ) {}

    /**
     * Show the form to add a field to an installed entity.
     */
    public function create(Entity $entity): View
    {
        if (! $entity->is_installed) {
            throw new NotFoundHttpException;
        }

        $entity->load('tabs.cards');

        return view('admin.entities.fields.create', [
            'entity' => $entity,
            'fieldTypes' => EntityFieldType::options(),
            'relationTargets' => $this->relationResolver->targetOptions($entity),
            'manualWorkflows' => $this->manualWorkflows(),
            'importers' => Importer::where('entity_id', $entity->id)->where('is_active', true)->get(),
        ]);
    }

    /**
     * Persist the new field and add its column to the entity's table.
     */
    public function store(StoreEntityFieldRequest $request, Entity $entity): RedirectResponse
    {
        DB::transaction(function () use ($request, $entity) {
            $field = $request->card()->fields()->create([
                'name' => $request->validated('name'),
                'column_name' => $request->validated('column_name'),
                'type' => $request->validated('type'),
                'options' => $this->parseOptions($request),
                'relation_target_type' => $this->relationTargetType($request),
                'relation_target' => $this->relationTarget($request),
                'required' => $request->boolean('required'),
                'default_value' => $request->validated('default_value'),
                'position' => $request->card()->fields()->max('position') + 1,
                'width' => min(12, max(1, (int) ($request->validated('width') ?? 12))),
                'is_locked' => false,
            ]);

            $this->schemaBuilder->addColumn($entity, $field);
        });

        return redirect()->route('admin.entities.builder.edit', $entity)->with('status', 'entity-field-added');
    }

    private function parseOptions(StoreEntityFieldRequest $request): ?array
    {
        $type = $request->validated('type');

        if ($type === EntityFieldType::Code->value) {
            $prefix = trim((string) $request->validated('code_prefix'));

            return $prefix !== '' ? ['prefix' => $prefix] : null;
        }

        if ($type === EntityFieldType::Table->value) {
            return ['columns' => StoreEntityFieldRequest::parseTableColumns((string) $request->validated('table_columns'))];
        }

        if ($type === EntityFieldType::Button->value) {
            return ButtonConfigValidator::parse($request->validated());
        }

        if ($type !== EntityFieldType::Select->value) {
            return null;
        }

        $options = [];

        foreach (preg_split('/\R/', (string) $request->validated('options')) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            [$key, $label] = array_pad(explode(':', $line, 2), 2, null);
            $key = trim((string) $key);

            if ($key !== '') {
                $options[$key] = trim((string) ($label ?? $key));
            }
        }

        return $options ?: null;
    }

    /**
     * Active workflows whose start node is configured for manual
     * launch — the only ones a Button field can offer to run.
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

    private function relationTargetType(StoreEntityFieldRequest $request): ?string
    {
        if ($request->validated('type') !== EntityFieldType::Relation->value) {
            return null;
        }

        return explode(':', (string) $request->validated('relation_target'), 2)[0] ?: null;
    }

    private function relationTarget(StoreEntityFieldRequest $request): ?string
    {
        if ($request->validated('type') !== EntityFieldType::Relation->value) {
            return null;
        }

        return explode(':', (string) $request->validated('relation_target'), 2)[1] ?? null;
    }
}
