<?php

namespace App\Http\Controllers;

use App\Enums\EntityFieldType;
use App\Models\Entity;
use App\Models\EntityField;
use App\Models\EntityRecord;
use App\Models\User;
use App\Services\EntityRecordAuthorizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Omnibox-style search across every installed entity's own records, used
 * by the search box in the main app's top navbar (not shown in the admin
 * section — see layouts/app.blade.php). Results are filtered through the
 * same two authorization layers as the entity records grid: the flat
 * entity_{slug}.index permission (Just A Gate) and the per-row visibility
 * level (EntityRecordAuthorizer) — a user never sees a record here that
 * they couldn't also see on the entity's own listing page.
 */
class GlobalSearchController extends Controller
{
    private const MIN_QUERY_LENGTH = 2;

    private const RESULTS_PER_ENTITY = 5;

    public function __construct(private readonly EntityRecordAuthorizer $authorizer) {}

    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->string('q'));

        if (mb_strlen($term) < self::MIN_QUERY_LENGTH) {
            return response()->json(['results' => []]);
        }

        $user = $request->user();

        $results = Entity::where('is_installed', true)
            ->with('tabs.cards.fields')
            ->orderBy('name')
            ->get()
            ->filter(fn (Entity $entity) => $user->can("entity_{$entity->slug}.index"))
            ->map(fn (Entity $entity) => $this->searchEntity($entity, $user, $term))
            ->filter(fn (array $group) => $group['records'] !== [])
            ->values();

        return response()->json(['results' => $results]);
    }

    /**
     * @return array{entity: array{slug: string, name: string}, records: array<int, array{id: int, title: string, url: string}>}
     */
    private function searchEntity(Entity $entity, User $user, string $term): array
    {
        $fields = $entity->allFields();

        $searchableFields = $fields->filter(
            fn (EntityField $f) => in_array($f->type, [EntityFieldType::String, EntityFieldType::Textarea, EntityFieldType::RichText], true)
        );

        if ($searchableFields->isEmpty()) {
            return ['entity' => ['slug' => $entity->slug, 'name' => $entity->name], 'records' => []];
        }

        $titleField = $fields->first(fn (EntityField $f) => $f->type === EntityFieldType::String);
        $columns = array_unique(array_filter(['id', $titleField?->column_name]));

        $query = EntityRecord::forEntity($entity)->newQuery()->select($columns);
        $this->authorizer->scopeQuery($query, $user, $entity);
        $this->applySearch($query, $searchableFields, $term);

        $records = $query->orderByDesc('id')->limit(self::RESULTS_PER_ENTITY)->get();

        return [
            'entity' => ['slug' => $entity->slug, 'name' => $entity->name],
            'records' => $records->map(fn (EntityRecord $record) => [
                'id' => $record->id,
                'title' => ($titleField !== null ? $record->{$titleField->column_name} : null) ?: "#{$record->id}",
                'url' => route('entities.edit', [$entity, $record->id]),
            ])->all(),
        ];
    }

    /**
     * @param  Collection<int, EntityField>  $searchableFields
     */
    private function applySearch(Builder $query, Collection $searchableFields, string $term): void
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);

        $query->where(function (Builder $q) use ($searchableFields, $escaped) {
            foreach ($searchableFields as $field) {
                $q->orWhere($field->column_name, 'like', "%{$escaped}%");
            }
        });
    }
}
