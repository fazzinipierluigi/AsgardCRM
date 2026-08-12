<?php

namespace App\Http\Controllers;

use Fazzinipierluigi\CrmCore\Enums\EntityFieldType;
use Fazzinipierluigi\CrmCore\Enums\EntityRelationTargetType;
use App\Http\Requests\StoreCalendarEventRequest;
use App\Http\Requests\UpdateCalendarEventRequest;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityField;
use Fazzinipierluigi\CrmCore\Models\EntityRecord;
use Fazzinipierluigi\CrmCore\Services\CalendarAuthorizer;
use Fazzinipierluigi\CrmCore\Services\EntityRelationResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The Calendar's own FullCalendar-driven UI, reading/writing the
 * "calendario" system Entity's dynamic table (see CalendarEntitySeeder)
 * the same way EntityRecordController does for any other entity — but
 * with a dedicated JSON API shaped for FullCalendar instead of a
 * Raccoon-grid listing, and its own handling of the relatable_type/
 * relatable_id pair, which isn't a regular EntityField (see
 * EntitySchemaBuilder).
 *
 * Permission checks mirror EntityRecordController::authorizeAction()/
 * authorizeRow() exactly, against the same entity_calendario.* keys
 * EntityInstaller creates — no new permission namespace.
 */
class CalendarController extends Controller
{
    public function __construct(
        private readonly CalendarAuthorizer $authorizer,
        private readonly EntityRelationResolver $relationResolver,
    ) {}

    public function index(): View
    {
        $entity = $this->calendarEntity();
        $this->authorizeAction($entity, 'index');

        return view('calendar.index', [
            'entity' => $entity,
            'canCreate' => request()->user()->can('entity_calendario.create'),
            'relationTargets' => $this->relationResolver->targetOptions($entity),
        ]);
    }

    /**
     * JSON feed FullCalendar fetches for a given visible date range.
     */
    public function events(Request $request): JsonResponse
    {
        $entity = $this->calendarEntity();
        $this->authorizeAction($entity, 'index');

        $start = $request->date('start');
        $end = $request->date('end');

        $query = EntityRecord::forEntity($entity)->newQuery()->with('owner');

        if ($start !== null && $end !== null) {
            $query->where('start_datetime', '<', $end)->where('end_datetime', '>', $start);
        }

        $this->authorizer->scopeQuery($query, $request->user(), $entity);

        return response()->json(
            $query->get()->map(fn (EntityRecord $record) => $this->toFullCalendarEvent($record, $entity, $request))->values()
        );
    }

    public function store(StoreCalendarEventRequest $request): JsonResponse
    {
        $entity = $this->calendarEntity();
        $this->authorizeAction($entity, 'create');

        $attributes = $this->prepareAttributes($entity, $request);
        $attributes['user_id'] = $request->user()->id;

        $record = EntityRecord::forEntity($entity)->newQuery()->create($attributes);

        return response()->json($this->toFullCalendarEvent($record, $entity, $request), 201);
    }

    public function update(UpdateCalendarEventRequest $request, int $record): JsonResponse
    {
        $entity = $this->calendarEntity();
        $this->authorizeAction($entity, 'edit');
        $recordModel = $this->findRecordOrFail($entity, $record);
        $this->authorizeRow($entity, $recordModel, 'edit');

        $recordModel->update($this->prepareAttributes($entity, $request));

        return response()->json($this->toFullCalendarEvent($recordModel->fresh(), $entity, $request));
    }

    public function destroy(Request $request, int $record): JsonResponse
    {
        $entity = $this->calendarEntity();
        $this->authorizeAction($entity, 'delete');
        $recordModel = $this->findRecordOrFail($entity, $record);
        $this->authorizeRow($entity, $recordModel, 'delete');

        $recordModel->delete();

        return response()->json(null, 204);
    }

    /**
     * AJAX lookup powering the "relationship with an entity" picker:
     * given a target picked from relationTargets ("entity:slug" or
     * "model:Fqcn"), returns its {id, label} options.
     */
    public function relatables(Request $request): JsonResponse
    {
        $entity = $this->calendarEntity();
        $this->authorizeAction($entity, 'index');

        [$typeValue, $target] = array_pad(explode(':', (string) $request->query('type'), 2), 2, null);
        $type = EntityRelationTargetType::tryFrom((string) $typeValue);

        if ($type === null || $target === null) {
            return response()->json([]);
        }

        $options = $this->relationResolver->labelsFor($type, $target);

        return response()->json(
            collect($options)->map(fn ($label, $id) => ['id' => $id, 'label' => $label])->values()
        );
    }

    private function calendarEntity(): Entity
    {
        return Entity::where('slug', 'calendario')->firstOrFail();
    }

    private function findRecordOrFail(Entity $entity, int $recordId): EntityRecord
    {
        return EntityRecord::forEntity($entity)->newQuery()->findOrFail($recordId);
    }

    /**
     * 404 if the calendar isn't installed yet, 403 if the user lacks the
     * flat entity_calendario.{action} permission — same shape as
     * EntityRecordController::authorizeAction().
     */
    private function authorizeAction(Entity $entity, string $action): void
    {
        if (! $entity->is_installed) {
            throw new NotFoundHttpException;
        }

        if (request()->user()->cannot("entity_calendario.{$action}")) {
            abort(403);
        }
    }

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
     * @return array<string, mixed>
     */
    private function prepareAttributes(Entity $entity, Request $request): array
    {
        $validated = $request->validated();
        $attributes = [
            'relatable_type' => $validated['relatable_type'] ?? null,
            'relatable_id' => $validated['relatable_id'] ?? null,
        ];

        foreach ($entity->allFields() as $field) {
            $column = $this->columnFor($field);

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
        return $value === null ? null : strip_tags($value, '<b><i><u><strong><em><ul><ol><li><p><br><a>');
    }

    private function columnFor(EntityField $field): string
    {
        return $field->type === EntityFieldType::Relation ? "{$field->column_name}_id" : $field->column_name;
    }

    /**
     * Shape a record into FullCalendar's event object
     * (https://fullcalendar.io/docs/event-object).
     *
     * @return array<string, mixed>
     */
    private function toFullCalendarEvent(EntityRecord $record, Entity $entity, Request $request): array
    {
        return [
            'id' => $record->id,
            'title' => $record->title,
            'start' => $record->start_datetime !== null ? Carbon::parse($record->start_datetime)->toIso8601String() : null,
            'end' => $record->end_datetime !== null ? Carbon::parse($record->end_datetime)->toIso8601String() : null,
            'editable' => $this->authorizer->canEdit($request->user(), $entity, $record->user_id),
            'backgroundColor' => $this->colorForOwner($record->user_id),
            'borderColor' => $this->colorForOwner($record->user_id),
            'extendedProps' => [
                'description' => $record->description,
                'show_as' => $record->show_as,
                'status' => $record->status,
                'relatable_type' => $record->relatable_type,
                'relatable_id' => $record->relatable_id,
                'owner_id' => $record->user_id,
                'owner_name' => $record->relationLoaded('owner') ? $record->owner?->name : $record->owner()->value('name'),
                'can_delete' => $this->authorizer->canDelete($request->user(), $entity, $record->user_id),
            ],
        ];
    }

    /**
     * A stable, deterministic color per calendar owner — lets the shared
     * calendars mixed into one FullCalendar instance stay visually
     * distinguishable without any client-side bookkeeping.
     */
    private function colorForOwner(int $ownerId): string
    {
        $hue = ($ownerId * 47) % 360;

        return "hsl({$hue}, 65%, 45%)";
    }
}
