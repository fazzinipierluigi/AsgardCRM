<?php

namespace App\Http\Requests;

use Fazzinipierluigi\CrmCore\Http\Requests\Concerns\BuildsEntityFieldRules;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a document upload against the "Documenti" entity's own
 * field definitions (Nome/Descrizione, plus any custom field an admin
 * appended — same dynamic-rules pattern as StoreEntityRecordRequest/
 * StoreCalendarEventRequest) plus the fixed upload fields
 * (file/folder_id) that aren't EntityFields.
 */
class StoreDocumentRequest extends FormRequest
{
    use BuildsEntityFieldRules;

    /**
     * Extensions accepted for upload — a deliberate allowlist (common
     * office/media/archive formats) rather than "any file", since an
     * unrestricted upload would accept executables/scripts onto the
     * server. Keep in sync with App\Support\DocumentIconResolver's
     * known extensions where it matters for a nicer icon, but this list
     * is the actual security boundary.
     */
    public const ALLOWED_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt',
        'zip', 'rar', '7z',
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp',
        'mp4', 'avi', 'mov', 'mp3', 'wav',
    ];

    public const MAX_FILE_KILOBYTES = 51200; // 50 MB

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $entity = $this->documentsEntity();
        $rules = [];

        foreach ($entity->allFields() as $field) {
            if ($field->type->isGenerated() || $field->type->isAction()) {
                continue;
            }

            $rules[$this->columnFor($field)] = $this->rulesFor($field);
        }

        $rules['file'] = [
            $this->fileRequired() ? 'required' : 'nullable',
            'file',
            'max:'.self::MAX_FILE_KILOBYTES,
            'mimes:'.implode(',', self::ALLOWED_EXTENSIONS),
        ];
        $rules['folder_id'] = ['nullable', Rule::exists('document_folders', 'id')->where('entity_id', $entity->id)];

        return $rules;
    }

    /**
     * The file is required on upload, optional on edit (a document can
     * be renamed/moved without replacing its content) — overridden by
     * UpdateDocumentRequest.
     */
    protected function fileRequired(): bool
    {
        return true;
    }

    protected function documentsEntity(): Entity
    {
        return Entity::where('slug', 'documenti')->firstOrFail();
    }
}
