<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EntityFieldType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateEntityBuilderRequest;
use App\Models\Entity;
use App\Services\EntityRelationResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EntityBuilderController extends Controller
{
    public function __construct(private readonly EntityRelationResolver $relationResolver) {}

    /**
     * Show the tab/card/field builder for an entity.
     */
    public function edit(Entity $entity): View
    {
        $entity->load('tabs.cards.fields');

        return view('admin.entities.builder', [
            'entity' => $entity,
            'fieldTypes' => EntityFieldType::options(),
            'relationTargets' => $this->relationResolver->targetOptions($entity),
        ]);
    }

    /**
     * Replace an entity's whole tab/card/field tree with the submitted
     * one. Rejected by UpdateEntityBuilderRequest if the entity is
     * already installed — its structure becomes append-only from the
     * installer's own tooling once that exists.
     */
    public function update(UpdateEntityBuilderRequest $request, Entity $entity): RedirectResponse
    {
        DB::transaction(function () use ($request, $entity) {
            $entity->tabs()->delete();

            foreach (array_values($request->input('tabs', [])) as $tabPosition => $tabInput) {
                $tab = $entity->tabs()->create([
                    'name' => $tabInput['name'],
                    'position' => $tabPosition,
                ]);

                foreach (array_values($tabInput['cards'] ?? []) as $cardPosition => $cardInput) {
                    $card = $tab->cards()->create([
                        'name' => $cardInput['name'],
                        'position' => $cardPosition,
                    ]);

                    foreach (array_values($cardInput['fields'] ?? []) as $fieldPosition => $fieldInput) {
                        $card->fields()->create([
                            'name' => $fieldInput['name'],
                            'column_name' => $fieldInput['column_name'],
                            'type' => $fieldInput['type'],
                            'options' => $this->parseOptions($fieldInput),
                            'relation_target_type' => $this->relationTargetType($fieldInput),
                            'relation_target' => $this->relationTarget($fieldInput),
                            'required' => (bool) ($fieldInput['required'] ?? false),
                            'default_value' => $fieldInput['default_value'] ?? null,
                            'position' => $fieldPosition,
                            'width' => min(12, max(1, (int) ($fieldInput['width'] ?? 12))),
                        ]);
                    }
                }
            }
        });

        return redirect()->route('admin.entities.builder.edit', $entity)->with('status', 'entity-structure-saved');
    }

    /**
     * Parse the type-specific "options" blob: the "key:Label" per-line
     * textarea for Select fields, or the prefix input for Code fields.
     *
     * @param  array<string, mixed>  $fieldInput
     * @return array<string, string>|null
     */
    private function parseOptions(array $fieldInput): ?array
    {
        $type = $fieldInput['type'] ?? null;

        if ($type === 'code') {
            $prefix = trim((string) ($fieldInput['code_prefix'] ?? ''));

            return $prefix !== '' ? ['prefix' => $prefix] : null;
        }

        if ($type !== 'select') {
            return null;
        }

        $options = [];

        foreach (preg_split('/\R/', (string) ($fieldInput['options'] ?? '')) as $line) {
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
     * @param  array<string, mixed>  $fieldInput
     */
    private function relationTargetType(array $fieldInput): ?string
    {
        if (($fieldInput['type'] ?? null) !== 'relation') {
            return null;
        }

        return explode(':', (string) ($fieldInput['relation_target'] ?? ''), 2)[0] ?: null;
    }

    /**
     * @param  array<string, mixed>  $fieldInput
     */
    private function relationTarget(array $fieldInput): ?string
    {
        if (($fieldInput['type'] ?? null) !== 'relation') {
            return null;
        }

        return explode(':', (string) ($fieldInput['relation_target'] ?? ''), 2)[1] ?? null;
    }
}
