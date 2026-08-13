<?php

namespace Fazzinipierluigi\AsgardCRM\Http\Requests\Admin;

use Cron\CronExpression;
use Fazzinipierluigi\AsgardCRM\Enums\ImporterChannel;
use Fazzinipierluigi\AsgardCRM\Enums\ImporterScheduleType;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Throwable;

class StoreImporterRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'entity_id' => ['required', Rule::exists('entities', 'id')->where(fn ($query) => $query->where('is_installed', true))],
            'channel' => ['required', Rule::enum(ImporterChannel::class)],
            'is_active' => ['boolean'],

            'driver' => ['required_if:channel,database', 'nullable', 'string', 'max:255'],
            'host' => ['required_if:channel,database', 'nullable', 'string', 'max:255'],
            'port' => ['required_if:channel,database', 'nullable', 'integer'],
            'database' => ['required_if:channel,database', 'nullable', 'string', 'max:255'],
            'username' => ['required_if:channel,database', 'nullable', 'string', 'max:255'],
            'password' => ['required_if:channel,database', 'nullable', 'string', 'max:255'],
            'query' => ['required_if:channel,database', 'nullable', 'string'],

            'method' => ['required_if:channel,rest_api', 'nullable', 'string', 'max:10'],
            'endpoint' => ['required_if:channel,rest_api', 'nullable', 'url', 'max:2048'],
            'auth_type' => ['nullable', 'string', 'in:none,basic,bearer,api_key'],
            'auth_username' => ['nullable', 'string', 'max:255'],
            'auth_password' => ['nullable', 'string', 'max:255'],
            'auth_token' => ['nullable', 'string', 'max:255'],
            'auth_api_key_name' => ['nullable', 'string', 'max:255'],
            'auth_api_key_value' => ['nullable', 'string', 'max:255'],
            'params_json' => ['nullable', 'json'],

            'path_or_url' => ['required_if:channel,csv,json', 'nullable', 'string', 'max:2048', $this->pathOrUrlRule()],
            'delimiter' => ['nullable', 'string', 'max:5'],
            'has_header' => ['boolean'],

            'field_mapping_json' => ['required', 'json', $this->fieldMappingRule()],
            'unique_key_field' => ['nullable', 'string', $this->uniqueKeyFieldRule()],

            'schedule_type' => ['required', Rule::enum(ImporterScheduleType::class)],
            'cron_expression' => ['required_if:schedule_type,cron,both', 'nullable', 'string', $this->cronExpressionRule()],
        ];
    }

    private function pathOrUrlRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if (preg_match('#^https?://#i', (string) $value) !== 1 && ! str_starts_with((string) $value, '/')) {
                $fail('Il percorso deve essere un URL http/https oppure un path assoluto (che inizia con "/").');
            }
        };
    }

    private function fieldMappingRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $mapping = json_decode((string) $value, true);

            if (! is_array($mapping) || $mapping === []) {
                $fail('La mappatura dei campi non può essere vuota.');

                return;
            }

            $entity = Entity::find($this->input('entity_id'));

            if ($entity === null) {
                return;
            }

            $validColumns = $entity->allFields()->pluck('column_name')->all();

            foreach ($mapping as $columnName) {
                if (! in_array($columnName, $validColumns, true)) {
                    $fail("Campo di destinazione non valido: {$columnName}");

                    return;
                }
            }
        };
    }

    private function uniqueKeyFieldRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            $mapping = json_decode((string) $this->input('field_mapping_json'), true);

            if (! is_array($mapping) || ! in_array($value, array_values($mapping), true)) {
                $fail('La chiave univoca deve corrispondere a un campo mappato.');
            }
        };
    }

    private function cronExpressionRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            try {
                new CronExpression($value);
            } catch (Throwable) {
                $fail('Espressione cron non valida.');
            }
        };
    }
}
