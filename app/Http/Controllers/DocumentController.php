<?php

namespace App\Http\Controllers;

use App\Enums\EntityFieldType;
use App\Http\Requests\StoreDocumentFolderRequest;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Models\DocumentFolder;
use App\Models\Entity;
use App\Models\EntityField;
use App\Models\EntityRecord;
use App\Services\DocumentStorageDiskResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The "Documenti" system entity's own UI: a folder tree
 * (App\Models\DocumentFolder, infinitely nestable, not an EntityField —
 * see EntitySchemaBuilder) with the entity's own dynamic-table records
 * as the files filed into it. Reads/writes through EntityRecord::forEntity()
 * like every other entity, but with a dedicated browse/upload/download UI
 * instead of the generic Raccoon-grid listing — same shape as
 * CalendarController for the "calendario" system entity.
 *
 * Permission checks mirror EntityRecordController::authorizeAction()
 * exactly, against the standard entity_documenti.* keys EntityInstaller
 * already creates — no new permission namespace, and managing folders
 * is treated as part of create/delete on documents themselves.
 */
class DocumentController extends Controller
{
    private const STORAGE_PREFIX = 'documents';

    private ?string $diskName = null;

    public function __construct(private DocumentStorageDiskResolver $diskResolver) {}

    private function disk(): string
    {
        return $this->diskName ??= $this->diskResolver->diskName();
    }

    public function index(Request $request, ?DocumentFolder $folder = null): View
    {
        $entity = $this->documentsEntity();
        $this->authorizeAction($entity, 'index');
        $this->authorizeFolder($entity, $folder);

        $search = trim((string) $request->query('q', ''));

        $subfolders = $search === ''
            ? DocumentFolder::where('entity_id', $entity->id)->where('parent_id', $folder?->id)->orderBy('name')->get()
            : collect();

        $documents = EntityRecord::forEntity($entity)->newQuery()
            ->when($search === '', fn ($query) => $query->where('folder_id', $folder?->id))
            ->when($search !== '', fn ($query) => $query->where('nome', 'like', "%{$search}%"))
            ->orderBy('nome')
            ->get();

        return view('documents.index', [
            'entity' => $entity,
            'folder' => $folder,
            'search' => $search,
            'breadcrumb' => $this->breadcrumb($folder),
            'subfolders' => $subfolders,
            'documents' => $documents,
            'folderTree' => $this->folderTree($entity),
            'canCreate' => request()->user()->can('entity_documenti.create'),
            'canDelete' => request()->user()->can('entity_documenti.delete'),
        ]);
    }

    public function create(Request $request): View
    {
        $entity = $this->documentsEntity();
        $this->authorizeAction($entity, 'create');

        $folderId = $request->integer('folder') ?: null;

        return view('documents.upload', [
            'entity' => $entity,
            'record' => null,
            'folderId' => $folderId,
            'folderOptions' => $this->folderOptions($entity),
        ]);
    }

    public function store(StoreDocumentRequest $request): RedirectResponse
    {
        $entity = $this->documentsEntity();
        $this->authorizeAction($entity, 'create');

        $file = $request->file('file');
        $attributes = $this->prepareFieldAttributes($entity, $request);
        $attributes['user_id'] = $request->user()->id;
        $attributes['folder_id'] = $request->validated('folder_id');
        $attributes['original_filename'] = $file->getClientOriginalName();
        $attributes['stored_path'] = $file->store(self::STORAGE_PREFIX.'/'.$entity->id, $this->disk());
        $attributes['mime_type'] = $file->getClientMimeType();
        $attributes['file_size'] = $file->getSize();

        EntityRecord::forEntity($entity)->newQuery()->create($attributes);

        return $this->redirectToFolder($request->validated('folder_id'))->with('status', 'document-uploaded');
    }

    public function edit(int $record): View
    {
        $entity = $this->documentsEntity();
        $this->authorizeAction($entity, 'edit');
        $recordModel = $this->findRecordOrFail($entity, $record);

        return view('documents.upload', [
            'entity' => $entity,
            'record' => $recordModel,
            'folderId' => $recordModel->folder_id,
            'folderOptions' => $this->folderOptions($entity),
        ]);
    }

    public function update(UpdateDocumentRequest $request, int $record): RedirectResponse
    {
        $entity = $this->documentsEntity();
        $this->authorizeAction($entity, 'edit');
        $recordModel = $this->findRecordOrFail($entity, $record);

        $attributes = $this->prepareFieldAttributes($entity, $request);
        $attributes['folder_id'] = $request->validated('folder_id');

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            Storage::disk($this->disk())->delete($recordModel->stored_path);
            $attributes['original_filename'] = $file->getClientOriginalName();
            $attributes['stored_path'] = $file->store(self::STORAGE_PREFIX.'/'.$entity->id, $this->disk());
            $attributes['mime_type'] = $file->getClientMimeType();
            $attributes['file_size'] = $file->getSize();
        }

        $recordModel->update($attributes);

        return $this->redirectToFolder($request->validated('folder_id'))->with('status', 'document-updated');
    }

    public function destroy(int $record): RedirectResponse
    {
        $entity = $this->documentsEntity();
        $this->authorizeAction($entity, 'delete');
        $recordModel = $this->findRecordOrFail($entity, $record);
        $folderId = $recordModel->folder_id;

        $recordModel->delete();

        return $this->redirectToFolder($folderId)->with('status', 'document-deleted');
    }

    public function download(int $record): StreamedResponse
    {
        $entity = $this->documentsEntity();
        $this->authorizeAction($entity, 'index');
        $recordModel = $this->findRecordOrFail($entity, $record);

        return Storage::disk($this->disk())->download($recordModel->stored_path, $recordModel->original_filename);
    }

    public function storeFolder(StoreDocumentFolderRequest $request): RedirectResponse
    {
        $entity = $this->documentsEntity();
        $this->authorizeAction($entity, 'create');

        DocumentFolder::create([
            'entity_id' => $entity->id,
            'parent_id' => $request->validated('parent_id'),
            'name' => $request->validated('name'),
            'user_id' => $request->user()->id,
        ]);

        return $this->redirectToFolder($request->validated('parent_id'))->with('status', 'folder-created');
    }

    /**
     * Only deletes an empty folder — refuses (rather than cascading) if
     * it still has subfolders or documents, so removing a folder can
     * never silently take a whole subtree of files down with it.
     */
    public function destroyFolder(DocumentFolder $folder): RedirectResponse
    {
        $entity = $this->documentsEntity();
        $this->authorizeAction($entity, 'delete');
        $this->authorizeFolder($entity, $folder);

        $hasSubfolders = DocumentFolder::where('parent_id', $folder->id)->exists();
        $hasDocuments = EntityRecord::forEntity($entity)->newQuery()->where('folder_id', $folder->id)->exists();

        if ($hasSubfolders || $hasDocuments) {
            return back()->with('error', 'La cartella non è vuota: rimuovi prima cartelle e documenti al suo interno.');
        }

        $parentId = $folder->parent_id;
        $folder->delete();

        return $this->redirectToFolder($parentId)->with('status', 'folder-deleted');
    }

    private function redirectToFolder(?int $folderId): RedirectResponse
    {
        return redirect()->route('documents.index', $folderId !== null ? ['folder' => $folderId] : []);
    }

    /**
     * @return Collection<int, array{folder: DocumentFolder, children: Collection<int, mixed>}>
     */
    private function folderTree(Entity $entity): Collection
    {
        $all = DocumentFolder::where('entity_id', $entity->id)->orderBy('name')->get();

        return $this->buildFolderTree($all, null);
    }

    /**
     * @param  Collection<int, DocumentFolder>  $all
     * @return Collection<int, array{folder: DocumentFolder, children: Collection<int, mixed>}>
     */
    private function buildFolderTree(Collection $all, ?int $parentId): Collection
    {
        return $all->where('parent_id', $parentId)
            ->map(fn (DocumentFolder $f) => ['folder' => $f, 'children' => $this->buildFolderTree($all, $f->id)])
            ->values();
    }

    /**
     * Every folder flattened into `<select>` options, indented by depth
     * — used by the upload/edit form's folder picker.
     *
     * @return array<int, string>
     */
    private function folderOptions(Entity $entity): array
    {
        $all = DocumentFolder::where('entity_id', $entity->id)->orderBy('name')->get();
        $options = [];
        $this->flattenFolderOptions($all, null, 0, $options);

        return $options;
    }

    /**
     * @param  Collection<int, DocumentFolder>  $all
     * @param  array<int, string>  $options
     */
    private function flattenFolderOptions(Collection $all, ?int $parentId, int $depth, array &$options): void
    {
        foreach ($all->where('parent_id', $parentId) as $folder) {
            $options[$folder->id] = str_repeat('— ', $depth).$folder->name;
            $this->flattenFolderOptions($all, $folder->id, $depth + 1, $options);
        }
    }

    /**
     * @return array<int, DocumentFolder>
     */
    private function breadcrumb(?DocumentFolder $folder): array
    {
        $trail = [];

        while ($folder !== null) {
            array_unshift($trail, $folder);
            $folder = $folder->parent;
        }

        return $trail;
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareFieldAttributes(Entity $entity, Request $request): array
    {
        $validated = $request->validated();
        $attributes = [];

        foreach ($entity->allFields() as $field) {
            if ($field->type->isGenerated() || $field->type->isAction()) {
                continue;
            }

            $column = $this->columnFor($field);

            $attributes[$column] = match ($field->type) {
                EntityFieldType::Checkbox => $request->boolean($column),
                default => $validated[$column] ?? null,
            };
        }

        return $attributes;
    }

    private function columnFor(EntityField $field): string
    {
        return $field->type === EntityFieldType::Relation ? "{$field->column_name}_id" : $field->column_name;
    }

    private function documentsEntity(): Entity
    {
        return Entity::where('slug', 'documenti')->firstOrFail();
    }

    private function findRecordOrFail(Entity $entity, int $recordId): EntityRecord
    {
        return EntityRecord::forEntity($entity)->newQuery()->findOrFail($recordId);
    }

    /**
     * 404 if the entity isn't installed yet, 403 if the user lacks the
     * flat entity_documenti.{action} permission — same shape as
     * EntityRecordController::authorizeAction().
     */
    private function authorizeAction(Entity $entity, string $action): void
    {
        if (! $entity->is_installed) {
            throw new NotFoundHttpException;
        }

        if (request()->user()->cannot("entity_documenti.{$action}")) {
            abort(403);
        }
    }

    private function authorizeFolder(Entity $entity, ?DocumentFolder $folder): void
    {
        abort_unless($folder === null || $folder->entity_id === $entity->id, 404);
    }
}
