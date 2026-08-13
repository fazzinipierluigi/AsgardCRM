<?php

namespace Fazzinipierluigi\AsgardCRM\Http\Requests\Install;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDatabaseRequest extends FormRequest
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
            'driver' => ['required', Rule::in(['mysql', 'mariadb', 'pgsql', 'sqlite'])],
            'host' => ['required_unless:driver,sqlite', 'nullable', 'string', 'max:255'],
            'port' => ['required_unless:driver,sqlite', 'nullable', 'integer'],
            'database' => ['required', 'string', 'max:255'],
            'username' => ['required_unless:driver,sqlite', 'nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ];
    }
}
