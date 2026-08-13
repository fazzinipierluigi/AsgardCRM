<?php

namespace Fazzinipierluigi\AsgardCRM\Http\Controllers\Admin;

use Fazzinipierluigi\AsgardCRM\Enums\ImporterChannel;
use Fazzinipierluigi\AsgardCRM\Enums\ImporterScheduleType;
use Fazzinipierluigi\AsgardCRM\Http\Controllers\Controller;
use Fazzinipierluigi\AsgardCRM\Http\Requests\Admin\PreviewImporterRequest;
use Fazzinipierluigi\AsgardCRM\Http\Requests\Admin\StoreImporterRequest;
use Fazzinipierluigi\AsgardCRM\Http\Requests\Admin\UpdateImporterRequest;
use Fazzinipierluigi\AsgardCRM\Jobs\RunImporterJob;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\Importer;
use Fazzinipierluigi\AsgardCRM\Models\ImporterRun;
use Fazzinipierluigi\AsgardCRM\Services\Importers\ImporterChannelFactory;
use Fazzinipierluigi\LaraccoonDatasource\EloquentSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Admin CRUD + wizard for Importers — configurable jobs that pull data
 * from an external Database/REST API/CSV/JSON source into one of this
 * app's dynamic Entities. Mirrors ConnectorController's shape closely:
 * same encrypted-config pattern, same "blank secret on edit keeps the
 * previous value" trick.
 */
class ImporterController extends Controller
{
    /**
     * Common cron presets offered in the wizard's step 5, alongside a
     * free-form expression input.
     */
    public const CRON_PRESETS = [
        'Ogni ora' => '0 * * * *',
        'Ogni giorno alle 02:00' => '0 2 * * *',
        'Ogni lunedì alle 08:00' => '0 8 * * 1',
        'Ogni primo del mese' => '0 0 1 * *',
    ];

    public function index(): View
    {
        return view('crm::admin.importers.index');
    }

    public function data(Request $request): JsonResponse
    {
        $importers = Importer::query()->with('entity')->select('id', 'title', 'entity_id', 'channel', 'schedule_type', 'is_active', 'last_run_at', 'last_run_status', 'created_at');

        $source = new EloquentSource;
        $source->apply($importers, $request, null, ['title']);

        return $source->getResponse(function (Importer $importer) {
            return [
                'id' => $importer->id,
                'title' => $importer->title,
                'entity' => $importer->entity?->name,
                'channel' => $importer->channel->label(),
                'schedule_type' => $importer->schedule_type->label(),
                'is_active' => $importer->is_active,
                'last_run_at' => $importer->last_run_at?->format('d/m/Y H:i'),
                'last_run_status' => $importer->last_run_status,
                'created_at' => $importer->created_at->format('d/m/Y H:i'),
            ];
        });
    }

    public function create(): View
    {
        return view('crm::admin.importers.create', $this->formData());
    }

    public function store(StoreImporterRequest $request): RedirectResponse
    {
        $channel = $request->string('channel')->value();

        Importer::create([
            'title' => $request->string('title'),
            'description' => $request->string('description')->value() ?: null,
            'entity_id' => $request->integer('entity_id'),
            'created_by' => $request->user()->id,
            'slug' => Importer::uniqueSlug($request->string('title')),
            'channel' => $channel,
            'config' => $this->configFor($request, $channel),
            'field_mapping' => json_decode((string) $request->string('field_mapping_json'), true),
            'unique_key_field' => $request->filled('unique_key_field') ? $request->string('unique_key_field')->value() : null,
            'schedule_type' => $request->string('schedule_type')->value(),
            'cron_expression' => $request->string('cron_expression')->value() ?: null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.importers.index')->with('status', 'importer-created');
    }

    public function edit(Importer $importer): View
    {
        return view('crm::admin.importers.edit', $this->formData() + ['importer' => $importer]);
    }

    public function update(UpdateImporterRequest $request, Importer $importer): RedirectResponse
    {
        $channel = $importer->channel->value;
        $config = $this->configFor($request, $channel);

        foreach (['password', 'auth_password', 'auth_token', 'auth_api_key_value'] as $secret) {
            if (array_key_exists($secret, $config) && $config[$secret] === null) {
                $config[$secret] = $importer->config[$secret] ?? null;
            }
        }

        $importer->title = $request->string('title');
        $importer->description = $request->string('description')->value() ?: null;
        $importer->config = $config;
        $importer->field_mapping = json_decode((string) $request->string('field_mapping_json'), true);
        $importer->unique_key_field = $request->filled('unique_key_field') ? $request->string('unique_key_field')->value() : null;
        $importer->schedule_type = $request->string('schedule_type')->value();
        $importer->cron_expression = $request->string('cron_expression')->value() ?: null;
        $importer->is_active = $request->boolean('is_active');
        $importer->save();

        return redirect()->route('admin.importers.index')->with('status', 'importer-updated');
    }

    public function destroy(Importer $importer): RedirectResponse
    {
        $importer->delete();

        return redirect()->route('admin.importers.index')->with('status', 'importer-deleted');
    }

    public function show(Importer $importer): View
    {
        return view('crm::admin.importers.show', ['importer' => $importer]);
    }

    public function runsData(Request $request, Importer $importer): JsonResponse
    {
        $runs = $importer->runs()->getQuery();

        $source = new EloquentSource;
        $source->apply($runs, $request, null, []);

        return $source->getResponse(function (ImporterRun $run) {
            return [
                'id' => $run->id,
                'started_at' => $run->started_at?->format('d/m/Y H:i:s'),
                'finished_at' => $run->finished_at?->format('d/m/Y H:i:s'),
                'status' => $run->status->label(),
                'rows_imported' => $run->rows_imported,
                'rows_failed' => $run->rows_failed,
                'error_message' => $run->error_message,
            ];
        });
    }

    public function run(Importer $importer): RedirectResponse
    {
        RunImporterJob::dispatch($importer);

        return redirect()->route('admin.importers.show', $importer)->with('status', 'importer-run-dispatched');
    }

    public function preview(PreviewImporterRequest $request, ImporterChannelFactory $factory): JsonResponse
    {
        $channel = $request->string('channel')->value();

        $importer = new Importer([
            'channel' => $channel,
            'config' => $this->configFor($request, $channel),
        ]);

        try {
            $result = $factory->make($importer)->preview($importer);
        } catch (Throwable $e) {
            $result = ['ok' => false, 'message' => $e->getMessage()];
        }

        return response()->json($result);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        $entities = Entity::where('is_installed', true)->orderBy('name')->get();

        return [
            'entities' => $entities,
            'entityFields' => $entities->mapWithKeys(fn (Entity $entity) => [
                $entity->id => $entity->allFields()->map(fn ($field) => [
                    'column_name' => $field->column_name,
                    'name' => $field->name,
                ])->values(),
            ]),
            'channels' => ImporterChannel::options(),
            'scheduleTypes' => ImporterScheduleType::options(),
            'cronPresets' => self::CRON_PRESETS,
        ];
    }

    /**
     * Build the channel-specific config array from the request, keeping
     * only the fields relevant to the given channel.
     *
     * @return array<string, mixed>
     */
    private function configFor(Request $request, string $channel): array
    {
        $fields = match ($channel) {
            ImporterChannel::Database->value => ['driver', 'host', 'port', 'database', 'username', 'password', 'query'],
            ImporterChannel::RestApi->value => ['method', 'endpoint', 'auth_type', 'auth_username', 'auth_password', 'auth_token', 'auth_api_key_name', 'auth_api_key_value', 'params_json'],
            ImporterChannel::Csv->value => ['path_or_url', 'delimiter', 'has_header'],
            ImporterChannel::Json->value => ['path_or_url'],
            default => [],
        };

        $config = $request->only($fields);

        if (array_key_exists('has_header', $config)) {
            $config['has_header'] = $request->boolean('has_header');
        }

        return $config;
    }
}
