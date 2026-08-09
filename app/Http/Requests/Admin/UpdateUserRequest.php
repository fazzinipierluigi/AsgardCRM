<?php

namespace App\Http\Requests\Admin;

use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'roles' => ['array'],
            'roles.*' => ['integer', 'exists:'.(new Role)->getTable().',id'],
            'login_provider_id' => ['nullable', 'integer', 'exists:login_providers,id'],
            'provider_identifier' => ['nullable', 'string', 'max:255'],
        ];
    }
}
