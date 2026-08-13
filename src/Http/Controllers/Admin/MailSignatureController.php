<?php

namespace Fazzinipierluigi\AsgardCRM\Http\Controllers\Admin;

use Fazzinipierluigi\AsgardCRM\Http\Controllers\Controller;
use Fazzinipierluigi\AsgardCRM\Http\Requests\Admin\StoreMailSignatureRequest;
use Fazzinipierluigi\AsgardCRM\Http\Requests\Admin\UpdateMailSignatureRequest;
use Fazzinipierluigi\AsgardCRM\Models\MailSignature;
use Fazzinipierluigi\LaraccoonDatasource\EloquentSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin CRUD for MailSignature templates — see that model's own
 * docblock for the {{user.*}} placeholder mechanism. Mirrors
 * MailConnectorController's shape (same datatable index, same
 * create/edit/_form split), simpler than it since there's no
 * encrypted config or "blank keeps previous secret" concern here.
 */
class MailSignatureController extends Controller
{
    public function index(): View
    {
        return view('crm::admin.mail-signatures.index');
    }

    public function data(Request $request): JsonResponse
    {
        $signatures = MailSignature::query()->select('id', 'name', 'created_at');

        $source = new EloquentSource;
        $source->apply($signatures, $request, null, ['name']);

        return $source->getResponse(function (MailSignature $signature) {
            return [
                'id' => $signature->id,
                'name' => $signature->name,
                'created_at' => $signature->created_at->format('d/m/Y H:i'),
            ];
        });
    }

    public function create(): View
    {
        return view('crm::admin.mail-signatures.create');
    }

    public function store(StoreMailSignatureRequest $request): RedirectResponse
    {
        MailSignature::create($request->validated());

        return redirect()->route('admin.mail-signatures.index')->with('status', 'mail-signature-created');
    }

    public function edit(MailSignature $mailSignature): View
    {
        return view('crm::admin.mail-signatures.edit', ['signature' => $mailSignature]);
    }

    public function update(UpdateMailSignatureRequest $request, MailSignature $mailSignature): RedirectResponse
    {
        $mailSignature->update($request->validated());

        return redirect()->route('admin.mail-signatures.index')->with('status', 'mail-signature-updated');
    }

    public function destroy(MailSignature $mailSignature): RedirectResponse
    {
        $mailSignature->delete();

        return redirect()->route('admin.mail-signatures.index')->with('status', 'mail-signature-deleted');
    }
}
