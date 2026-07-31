<?php

namespace App\Http\Requests;

use App\Models\Entity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $entity = Entity::where('slug', 'documenti')->firstOrFail();
        $parentId = $this->input('parent_id') ?: null;

        return [
            'name' => [
                'required', 'string', 'max:255',
                // A plain scalar where('parent_id', null) on the fluent
                // Unique rule stringifies into the exists:/unique: DSL
                // and loses real NULL semantics — a closure builds a
                // genuine query-builder where instead (see gotcha #15 in
                // DOCUMENTATION.md, same underlying DatabaseRule class).
                Rule::unique('document_folders', 'name')->where(function ($query) use ($entity, $parentId) {
                    $query->where('entity_id', $entity->id);
                    $parentId === null ? $query->whereNull('parent_id') : $query->where('parent_id', $parentId);
                }),
            ],
            'parent_id' => ['nullable', Rule::exists('document_folders', 'id')->where('entity_id', $entity->id)],
        ];
    }
}
