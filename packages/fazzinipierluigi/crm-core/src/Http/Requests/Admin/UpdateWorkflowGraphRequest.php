<?php

namespace Fazzinipierluigi\CrmCore\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkflowGraphRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Deep structural validation (exactly one start node, edges pointing
     * at real node keys, known node/action types...) is delegated to
     * WorkflowGraphPersister — this only guards the outer shape so the
     * controller can safely index into it.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'variables' => ['array'],
            'nodes' => ['required', 'array', 'min:1'],
            'edges' => ['array'],
        ];
    }
}
