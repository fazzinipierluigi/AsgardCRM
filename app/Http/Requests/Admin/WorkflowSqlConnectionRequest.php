<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared by store() and update() — same fields either way, the
 * controller keeps the previous password when this one's left blank
 * on an edit (same trick as ConnectorController).
 */
class WorkflowSqlConnectionRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'workflow_id' => ['nullable', 'integer', Rule::exists('workflows', 'id')],
            'driver' => ['required', Rule::in(['mysql', 'pgsql', 'sqlsrv', 'sqlite'])],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer'],
            'database' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ];
    }
}
