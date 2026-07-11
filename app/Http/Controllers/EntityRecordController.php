<?php

namespace App\Http\Controllers;

use App\Enums\EntityFieldType;
use App\Enums\EntityRelationTargetType;
use App\Http\Requests\StoreEntityRecordRequest;
use App\Http\Requests\UpdateEntityRecordRequest;
use App\Models\Entity;
use App\Models\EntityField;
use App\Models\EntityRecord;
use App\Models\User;
use App\Services\EntityCodeGenerator;
use App\Services\EntityRecordAuthorizer;
use Fazzinipierluigi\LaraccoonDatasource\EloquentSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Frontend CRUD for an installed entity's own records. A single
 * generic controller serves every entity — the {entity:slug} route
 * parameter picks which one. Because of that, the `acl` middleware
 * (which keys permissions off Controller@action) can't tell entities
 * apart, so the flat entity_{slug}.* permission is checked by hand in
 * every action, on top of EntityRecordAuthorizer's per-row ownership
 * checks — see EntityInstaller for the permission keys.
 *
 * Tags/labels used in the allowlist below for RichText fields are kept
 * deliberately minimal (see sanitizeRichText()).
 */
class EntityRecordController extends Controller
{
    private const RICH_TEXT_ALLOWED_TAGS = '<b><i><u><strong><em><ul><ol><li><p><br><a>';

    public function __construct(
        private readonly EntityRecordAuthorizer $authorizer,
        private readonly EntityCodeGenerator $codeGenerator,
    ) {}

    /**
     * Show the records listing page for an entity.
     */
    public function index(Entity $entity): View
    {
        $this->authorizeAction($entity, 'index');

        return view('entities.index', [
            'entity' => $entity->load('tabs.cards.fields'),
            'canCreate' => request()->user()->can("entity_{$entity->slug}.create"),
        ]);
    }

    /**
     * Serve the server-side datatable request for an entity's records.
     */
    public function data(Request $request, Entity $entity): JsonResponse
    {
        $this->authorizeAction($entity, 'index');

        $fields = $entity->allFields();
        $columns = array_merge(['id', 'user_id', 'created_at'], $fields->map(fn (EntityField $f) => $this->columnFor($f))->all());

        $records = EntityRecord::forEntity($entity)->newQuery()->with('owner')->select($columns);
        $this->authorizer->scopeQuery($records, $request->user(), $entity);

        $relationLabels = $fields->filter(fn (EntityField $f) => $f->type === EntityFieldType::Relation)
            ->mapWithKeys(fn (EntityField $f) => [$f->column_name => $this->relationLabels($f)]);

        $source = new EloquentSource;
        $source->apply($records, $request, null, []);

        return $source->getResponse(function (EntityRecord $record) use ($fields, $relationLabels, $request, $entity) {
            $row = [
                'id' => $record->id,
                'owner' => $record->owner?->name,
                'created_at' => $record->created_at->format('d/m/Y H:i'),
                'can_edit' => $this->authorizer->canEdit($request->user(), $entity, $record->user_id),
                'can_delete' => $this->authorizer->canDelete($request->user(), $entity, $record->user_id),
            ];

            foreach ($fields as $field) {
                $row[$field->column_name] = $this->displayValue($field, $record, $relationLabels);
            }

            return $row;
        });
    }

    /**
     * Show the form to create a new record.
     */
    public function create(Entity $entity): View
    {
        $this->authorizeAction($entity, 'create');

        return view('entities.create', [
            'entity' => $entity->load('tabs.cards.fields'),
            'record' => null,
            'relationOptions' => $this->relationOptionsForEntity($entity),
        ]);
    }

    /**
     * Persist a new record, owned by the current user.
     */
    public function store(StoreEntityRecordRequest $request, Entity $entity): RedirectResponse
    {
        $this->authorizeAction($entity, 'create');

        $attributes = $this->prepareAttributes($entity, $request, generateCodes: true);
        $attributes['user_id'] = $request->user()->id;

        EntityRecord::forEntity($entity)->newQuery()->create($attributes);

        return redirect()->route('entities.index', $entity)->with('status', 'record-created');
    }

    /**
     * Show the form to edit an existing record.
     */
    public function edit(Entity $entity, int $record): View
    {
        $this->authorizeAction($entity, 'edit');
        $recordModel = $this->findRecordOrFail($entity, $record);
        $this->authorizeRow($entity, $recordModel, 'edit');

        return view('entities.edit', [
            'entity' => $entity->load('tabs.cards.fields'),
            'record' => $recordModel,
            'relationOptions' => $this->relationOptionsForEntity($entity),
        ]);
    }

    /**
     * Update an existing record.
     */
    public function update(UpdateEntityRecordRequest $request, Entity $entity, int $record): RedirectResponse
    {
        $this->authorizeAction($entity, 'edit');
        $recordModel = $this->findRecordOrFail($entity, $record);
        $this->authorizeRow($entity, $recordModel, 'edit');

        $recordModel->update($this->prepareAttributes($entity, $request));

        return redirect()->route('entities.index', $entity)->with('status', 'record-updated');
    }

    /**
     * Delete a record.
     */
    public function destroy(Entity $entity, int $record): RedirectResponse
    {
        $this->authorizeAction($entity, 'delete');
        $recordModel = $this->findRecordOrFail($entity, $record);
        $this->authorizeRow($entity, $recordModel, 'delete');

        $recordModel->delete();

        return redirect()->route('entities.index', $entity)->with('status', 'record-deleted');
    }

    private function findRecordOrFail(Entity $entity, int $recordId): EntityRecord
    {
        return EntityRecord::forEntity($entity)->newQuery()->findOrFail($recordId);
    }

    /**
     * 404 if the entity isn't installed yet (no table to read/write),
     * 403 if the user lacks the flat entity_{slug}.{action} permission.
     */
    private function authorizeAction(Entity $entity, string $action): void
    {
        if (! $entity->is_installed) {
            throw new NotFoundHttpException;
        }

        if (request()->user()->cannot("entity_{$entity->slug}.{$action}")) {
            abort(403);
        }
    }

    /**
     * 403 if the user's visibility level doesn't let them touch this
     * specific row (see EntityRecordAuthorizer for the rules).
     */
    private function authorizeRow(Entity $entity, EntityRecord $record, string $action): void
    {
        $allowed = match ($action) {
            'edit' => $this->authorizer->canEdit(request()->user(), $entity, $record->user_id),
            'delete' => $this->authorizer->canDelete(request()->user(), $entity, $record->user_id),
        };

        if (! $allowed) {
            abort(403);
        }
    }

    /**
     * Build the attributes to persist from the validated request,
     * filling in the checkbox fields the browser omits when unchecked
     * and sanitizing RichText HTML before it's ever stored. Generated
     * (Code) fields are only ever set on creation — $generateCodes is
     * false on update() so an existing record's code is never touched.
     *
     * @return array<string, mixed>
     */
    private function prepareAttributes(Entity $entity, Request $request, bool $generateCodes = false): array
    {
        $validated = $request->validated();
        $attributes = [];

        foreach ($entity->allFields() as $field) {
            $column = $this->columnFor($field);

            if ($field->type->isGenerated()) {
                if ($generateCodes) {
                    $attributes[$column] = $this->codeGenerator->nextValue($field);
                }

                continue;
            }

            $attributes[$column] = match ($field->type) {
                EntityFieldType::Checkbox => $request->boolean($column),
                EntityFieldType::RichText => $this->sanitizeRichText($validated[$column] ?? null),
                default => $validated[$column] ?? null,
            };
        }

        return $attributes;
    }

    private function sanitizeRichText(?string $value): ?string
    {
        return $value === null ? null : strip_tags($value, self::RICH_TEXT_ALLOWED_TAGS);
    }

    private function columnFor(EntityField $field): string
    {
        return $field->type === EntityFieldType::Relation ? "{$field->column_name}_id" : $field->column_name;
    }

    /**
     * Human-friendly display value for a field on a record, used by the
     * records datatable. Relation values are resolved through the
     * pre-fetched $relationLabels map rather than one query per row.
     *
     * @param  Collection<int, array<int|string, string>>  $relationLabels
     */
    private function displayValue(EntityField $field, EntityRecord $record, $relationLabels): mixed
    {
        $column = $this->columnFor($field);
        $value = $record->{$column};

        return match ($field->type) {
            EntityFieldType::Checkbox => $value ? t('Sì') : t('No'),
            EntityFieldType::Select => $field->options[$value] ?? $value,
            EntityFieldType::Relation => $value === null ? null : ($relationLabels[$field->column_name][$value] ?? "#{$value}"),
            EntityFieldType::RichText => $value === null ? null : strip_tags((string) $value),
            default => $value,
        };
    }

    /**
     * All relation target options for every Relation field on an
     * entity, keyed by field column name — used to populate <select>s
     * on the create/edit forms.
     *
     * @return array<string, array<int|string, string>>
     */
    private function relationOptionsForEntity(Entity $entity): array
    {
        return $entity->allFields()
            ->filter(fn (EntityField $f) => $f->type === EntityFieldType::Relation)
            ->mapWithKeys(fn (EntityField $f) => [$f->column_name => $this->relationLabels($f)])
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    private function relationLabels(EntityField $field): array
    {
        if ($field->relation_target_type === EntityRelationTargetType::Model) {
            $class = $field->relation_target;

            if ($class === User::class) {
                return User::query()->orderBy('name')->pluck('name', 'id')->all();
            }

            return [];
        }

        $target = Entity::where('slug', $field->relation_target)->where('is_installed', true)->first();

        if ($target === null) {
            return [];
        }

        $labelField = $target->allFields()->first(fn (EntityField $f) => $f->type === EntityFieldType::String);
        $columns = $labelField !== null ? ['id', $labelField->column_name] : ['id'];

        return EntityRecord::forEntity($target)->newQuery()->get($columns)
            ->mapWithKeys(fn (EntityRecord $r) => [$r->id => ($labelField !== null ? $r->{$labelField->column_name} : null) ?: "#{$r->id}"])
            ->all();
    }
}
