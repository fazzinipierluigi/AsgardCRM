<?php

namespace Fazzinipierluigi\AsgardCRM\Http\Controllers;

use Fazzinipierluigi\AsgardCRM\Enums\EntityFieldType;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\EntityField;
use Fazzinipierluigi\AsgardCRM\Models\EntityRecord;
use Fazzinipierluigi\AsgardCRM\Services\EntityRecordAuthorizer;
use Fazzinipierluigi\LaraccoonDatasource\EloquentSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * The "Cestino": soft-deleted records across every installed entity
 * (system or custom — see EntityRecord's SoftDeletes trait). Unlike
 * EntityRecordController, access is gated by flat, global permissions
 * (trash.show/restore/empty/delete, never pre-created — same
 * CLI-only pattern as admin.access) rather than one permission per
 * entity: which entities show up in the picker, and which of their
 * rows can actually be touched, is instead decided by the existing
 * entity_{slug}.delete permission and EntityRecordAuthorizer's
 * per-row ownership rules.
 */
class TrashController extends Controller
{
    public function __construct(private readonly EntityRecordAuthorizer $authorizer) {}

    public function index(Request $request): View
    {
        $this->authorizeGlobal('trash.show');

        $entity = $request->filled('entity')
            ? $this->deletableEntities()->firstWhere('slug', (string) $request->query('entity'))
            : null;

        return view('crm::trash.index', [
            'entities' => $this->deletableEntities(),
            'entity' => $entity,
            'canEmpty' => $request->user()->can('trash.empty'),
        ]);
    }

    public function data(Request $request, Entity $entity): JsonResponse
    {
        $this->authorizeGlobal('trash.show');
        $this->authorizeEntity($entity);

        $fields = $entity->allFields()->reject(fn (EntityField $f) => $f->type->isAction())->take(3);
        $columns = array_merge(['id', 'user_id', 'deleted_at'], $fields->map(fn (EntityField $f) => $this->columnFor($f))->all());

        $records = EntityRecord::forEntity($entity)->newQuery()->onlyTrashed()->with('owner')->select($columns);
        $this->authorizer->scopeQuery($records, $request->user(), $entity);

        $source = new EloquentSource;
        $source->apply($records, $request, null, []);

        return $source->getResponse(function (EntityRecord $record) use ($fields, $entity, $request) {
            $row = [
                'id' => $record->id,
                'owner' => $record->owner?->name,
                'deleted_at' => $record->deleted_at?->format('d/m/Y H:i'),
                'can_restore' => $this->authorizer->canDelete($request->user(), $entity, $record->user_id),
            ];

            foreach ($fields as $field) {
                $row[$field->column_name] = $record->{$this->columnFor($field)};
            }

            return $row;
        });
    }

    public function restore(Request $request, Entity $entity, int $record): RedirectResponse
    {
        $this->authorizeGlobal('trash.restore');
        $this->authorizeEntity($entity);

        $recordModel = EntityRecord::forEntity($entity)->newQuery()->onlyTrashed()->findOrFail($record);
        $this->authorizeRow($entity, $recordModel);

        $recordModel->restore();

        return redirect()->route('trash.index', ['entity' => $entity->slug])->with('status', 'record-restored');
    }

    public function forceDelete(Request $request, Entity $entity, int $record): RedirectResponse
    {
        $this->authorizeGlobal('trash.delete');
        $this->authorizeEntity($entity);

        $recordModel = EntityRecord::forEntity($entity)->newQuery()->onlyTrashed()->findOrFail($record);
        $this->authorizeRow($entity, $recordModel);

        $this->deletePhysicalFile($entity, $recordModel);
        $recordModel->forceDelete();

        return redirect()->route('trash.index', ['entity' => $entity->slug])->with('status', 'record-force-deleted');
    }

    public function emptyEntity(Request $request, Entity $entity): RedirectResponse
    {
        $this->authorizeGlobal('trash.empty');
        $this->authorizeEntity($entity);

        $trashed = EntityRecord::forEntity($entity)->newQuery()->onlyTrashed();
        $this->authorizer->scopeQuery($trashed, $request->user(), $entity);
        $trashed->get()->each(function (EntityRecord $record) use ($entity) {
            $this->deletePhysicalFile($entity, $record);
            $record->forceDelete();
        });

        return redirect()->route('trash.index', ['entity' => $entity->slug])->with('status', 'trash-emptied');
    }

    public function emptyAll(Request $request): RedirectResponse
    {
        $this->authorizeGlobal('trash.empty');

        foreach ($this->deletableEntities() as $entity) {
            $trashed = EntityRecord::forEntity($entity)->newQuery()->onlyTrashed();
            $this->authorizer->scopeQuery($trashed, $request->user(), $entity);
            $trashed->get()->each(function (EntityRecord $record) use ($entity) {
                $this->deletePhysicalFile($entity, $record);
                $record->forceDelete();
            });
        }

        return redirect()->route('trash.index')->with('status', 'trash-emptied');
    }

    /**
     * A "Documenti" record's row is metadata only — the actual file
     * bytes live on the `local` disk (see DocumentController) and are
     * never touched by a soft delete (still recoverable from the
     * Cestino until this point), only removed here, right before the
     * DB row itself is gone for good.
     */
    private function deletePhysicalFile(Entity $entity, EntityRecord $record): void
    {
        if ($entity->is_documents && $record->stored_path) {
            Storage::disk('local')->delete($record->stored_path);
        }
    }

    /**
     * @return Collection<int, Entity>
     */
    private function deletableEntities(): Collection
    {
        return Entity::where('is_installed', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (Entity $entity) => request()->user()->can("entity_{$entity->slug}.delete"))
            ->values();
    }

    private function authorizeGlobal(string $permission): void
    {
        if (request()->user()->cannot($permission)) {
            abort(403);
        }
    }

    private function authorizeEntity(Entity $entity): void
    {
        if (request()->user()->cannot("entity_{$entity->slug}.delete")) {
            abort(403);
        }
    }

    private function authorizeRow(Entity $entity, EntityRecord $record): void
    {
        if (! $this->authorizer->canDelete(request()->user(), $entity, $record->user_id)) {
            abort(403);
        }
    }

    private function columnFor(EntityField $field): string
    {
        return $field->type === EntityFieldType::Relation ? "{$field->column_name}_id" : $field->column_name;
    }
}
