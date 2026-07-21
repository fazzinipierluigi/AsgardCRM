<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreConnectorRequest;
use App\Http\Requests\Admin\UpdateConnectorRequest;
use App\Models\Connector;
use Fazzinipierluigi\LaraccoonDatasource\EloquentSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin CRUD for Connectors — external sources the app can sync data
 * from/to (starting with Exchange, for the Calendar — see
 * app/Services/Connectors once the sync engine lands). Mirrors
 * LoginProviderController's shape closely: same encrypted-config
 * pattern, same "blank secret on edit keeps the previous value" trick.
 */
class ConnectorController extends Controller
{
    public function index(): View
    {
        return view('admin.connectors.index');
    }

    public function data(Request $request): JsonResponse
    {
        $connectors = Connector::query()->select('id', 'type', 'name', 'slug', 'is_active', 'sync_direction', 'last_synced_at', 'last_sync_status', 'created_at');

        $source = new EloquentSource;
        $source->apply($connectors, $request, null, ['name', 'type', 'slug']);

        return $source->getResponse(function (Connector $connector) {
            return [
                'id' => $connector->id,
                'type' => $connector->type->label(),
                'name' => $connector->name,
                'slug' => $connector->slug,
                'is_active' => $connector->is_active,
                'sync_direction' => $connector->sync_direction->label(),
                'last_synced_at' => $connector->last_synced_at?->format('d/m/Y H:i'),
                'last_sync_status' => $connector->last_sync_status,
                'created_at' => $connector->created_at->format('d/m/Y H:i'),
            ];
        });
    }

    public function create(): View
    {
        return view('admin.connectors.create');
    }

    public function store(StoreConnectorRequest $request): RedirectResponse
    {
        Connector::create([
            'type' => $request->string('type')->value(),
            'name' => $request->string('name'),
            'slug' => Connector::uniqueSlug($request->string('name')),
            'is_active' => $request->boolean('is_active'),
            'config' => $this->configFor($request, $request->string('type')->value()),
            'sync_direction' => $request->string('sync_direction')->value(),
            'sync_interval_minutes' => $request->integer('sync_interval_minutes'),
        ]);

        return redirect()->route('admin.connectors.index')->with('status', 'connector-created');
    }

    public function edit(Connector $connector): View
    {
        return view('admin.connectors.edit', ['connector' => $connector]);
    }

    public function update(UpdateConnectorRequest $request, Connector $connector): RedirectResponse
    {
        $config = $this->configFor($request, $connector->type->value);

        foreach (['client_secret', 'password'] as $secret) {
            if (array_key_exists($secret, $config) && $config[$secret] === null) {
                $config[$secret] = $connector->config[$secret] ?? null;
            }
        }

        $connector->name = $request->string('name');
        $connector->is_active = $request->boolean('is_active');
        $connector->config = $config;
        $connector->sync_direction = $request->string('sync_direction')->value();
        $connector->sync_interval_minutes = $request->integer('sync_interval_minutes');
        $connector->save();

        return redirect()->route('admin.connectors.index')->with('status', 'connector-updated');
    }

    public function destroy(Connector $connector): RedirectResponse
    {
        $connector->delete();

        return redirect()->route('admin.connectors.index')->with('status', 'connector-deleted');
    }

    /**
     * Build the type-specific config array from the request, keeping
     * only the fields relevant to the given connector type.
     *
     * @return array<string, mixed>
     */
    private function configFor(Request $request, string $type): array
    {
        $fields = match ($type) {
            'exchange_graph' => ['tenant_id', 'client_id', 'client_secret'],
            'exchange_ews' => ['ews_url', 'username', 'password', 'use_ntlm'],
            default => [],
        };

        $config = $request->only($fields);

        if (array_key_exists('use_ntlm', $config)) {
            $config['use_ntlm'] = $request->boolean('use_ntlm');
        }

        return $config;
    }
}
