<?php

namespace Fazzinipierluigi\AsgardCRM\Http\Controllers\Admin;

use Fazzinipierluigi\AsgardCRM\Enums\DocumentStorageType;
use Fazzinipierluigi\AsgardCRM\Http\Controllers\Controller;
use Fazzinipierluigi\AsgardCRM\Http\Requests\Admin\UpdateDocumentStorageRequest;
use Fazzinipierluigi\AsgardCRM\Models\DocumentStorageSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin singleton settings page: which disk the "Documenti" entity
 * (Fazzinipierluigi\AsgardCRM\Http\Controllers\DocumentController) stores files on. Same
 * "blank secret on edit keeps the previous value" trick as
 * ConnectorController — see Fazzinipierluigi\AsgardCRM\Services\DocumentStorageDiskResolver
 * for how the saved config turns into an actual Flysystem disk.
 */
class DocumentStorageController extends Controller
{
    public function edit(): View
    {
        return view('crm::admin.document-storage.edit', ['setting' => DocumentStorageSetting::current()]);
    }

    public function update(UpdateDocumentStorageRequest $request): RedirectResponse
    {
        $setting = DocumentStorageSetting::current();
        $type = $request->string('type')->value();
        $config = $this->configFor($request, $type);

        foreach (['secret', 'password'] as $secretField) {
            if (array_key_exists($secretField, $config) && $config[$secretField] === null) {
                $config[$secretField] = $setting->config[$secretField] ?? null;
            }
        }

        $setting->type = $type;
        $setting->config = $config;
        $setting->save();

        return redirect()->route('admin.document-storage.edit')->with('status', 'document-storage-updated');
    }

    /**
     * Build the type-specific config array from the request, keeping
     * only the fields relevant to the given storage type. FTP/SFTP form
     * fields are prefixed (ftp_host, sftp_host, ...) to avoid id/name
     * collisions between the two fieldsets on the same page — stripped
     * back to plain keys (host, ...) here for storage.
     *
     * @return array<string, mixed>
     */
    private function configFor(Request $request, string $type): array
    {
        return match ($type) {
            DocumentStorageType::S3->value => $this->s3Config($request),
            DocumentStorageType::Ftp->value => $this->prefixedConfig($request, 'ftp_', ['host', 'port', 'username', 'password', 'root', 'ssl']),
            DocumentStorageType::Sftp->value => $this->prefixedConfig($request, 'sftp_', ['host', 'port', 'username', 'password', 'root']),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function s3Config(Request $request): array
    {
        $config = $request->only(['key', 'secret', 'region', 'bucket', 'endpoint', 'use_path_style_endpoint']);
        $config['use_path_style_endpoint'] = $request->boolean('use_path_style_endpoint');

        return $config;
    }

    /**
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    private function prefixedConfig(Request $request, string $prefix, array $fields): array
    {
        $config = [];

        foreach ($fields as $field) {
            $config[$field] = $field === 'ssl'
                ? $request->boolean($prefix.$field)
                : $request->input($prefix.$field);
        }

        return $config;
    }
}
