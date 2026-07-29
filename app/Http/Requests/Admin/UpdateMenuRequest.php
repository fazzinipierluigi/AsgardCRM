<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMenuRequest extends FormRequest
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
        // Plain scalar ->where(column, false) values get stringified via
        // Rule::exists()'s "exists:table,col,extra..." DSL, and false
        // silently becomes an empty string there (not "0"), so the
        // generated clause never matches — a closure applies real query
        // builder wheres instead and avoids that entirely.
        $installedEntity = Rule::exists('entities', 'id')->where(
            fn ($query) => $query->where('is_installed', true)->where('is_calendar', false)
        );

        return [
            'visible' => ['array'],
            'visible.*' => ['integer', $installedEntity],
            'quick_access' => ['array'],
            'quick_access.*' => ['integer', $installedEntity],
        ];
    }
}
