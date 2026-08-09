<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMailConnectorRequest;
use App\Http\Requests\Admin\UpdateMailConnectorRequest;
use App\Models\MailConnector;
use Fazzinipierluigi\LaraccoonDatasource\EloquentSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin CRUD for MailConnectors — org-wide Exchange app registrations
 * / service accounts a user's personal MailAccount can point at
 * instead of entering their own Exchange credentials (see
 * App\Models\MailAccount::mail_connector_id). Mirrors ConnectorController's
 * shape closely (same two Exchange types, same encrypted-config
 * pattern, same "blank secret on edit keeps the previous value"
 * trick) — kept as a separate class since it's a distinct, mail-only
 * concept with no sync-state fields.
 */
class MailConnectorController extends Controller
{
    public function index(): View
    {
        return view('admin.mail-connectors.index');
    }

    public function data(Request $request): JsonResponse
    {
        $connectors = MailConnector::query()->select('id', 'type', 'name', 'slug', 'is_active', 'created_at');

        $source = new EloquentSource;
        $source->apply($connectors, $request, null, ['name', 'type', 'slug']);

        return $source->getResponse(function (MailConnector $connector) {
            return [
                'id' => $connector->id,
                'type' => $connector->type->label(),
                'name' => $connector->name,
                'slug' => $connector->slug,
                'is_active' => $connector->is_active,
                'created_at' => $connector->created_at->format('d/m/Y H:i'),
            ];
        });
    }

    public function create(): View
    {
        return view('admin.mail-connectors.create');
    }

    public function store(StoreMailConnectorRequest $request): RedirectResponse
    {
        MailConnector::create([
            'type' => $request->string('type')->value(),
            'name' => $request->string('name'),
            'slug' => MailConnector::uniqueSlug($request->string('name')),
            'is_active' => $request->boolean('is_active'),
            'config' => $this->configFor($request, $request->string('type')->value()),
        ]);

        return redirect()->route('admin.mail-connectors.index')->with('status', 'mail-connector-created');
    }

    public function edit(MailConnector $mailConnector): View
    {
        return view('admin.mail-connectors.edit', ['connector' => $mailConnector]);
    }

    public function update(UpdateMailConnectorRequest $request, MailConnector $mailConnector): RedirectResponse
    {
        $config = $this->configFor($request, $mailConnector->type->value);

        foreach (['client_secret', 'password'] as $secret) {
            if (array_key_exists($secret, $config) && $config[$secret] === null) {
                $config[$secret] = $mailConnector->config[$secret] ?? null;
            }
        }

        $mailConnector->name = $request->string('name');
        $mailConnector->is_active = $request->boolean('is_active');
        $mailConnector->config = $config;
        $mailConnector->save();

        return redirect()->route('admin.mail-connectors.index')->with('status', 'mail-connector-updated');
    }

    public function destroy(MailConnector $mailConnector): RedirectResponse
    {
        $mailConnector->delete();

        return redirect()->route('admin.mail-connectors.index')->with('status', 'mail-connector-deleted');
    }

    /**
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
