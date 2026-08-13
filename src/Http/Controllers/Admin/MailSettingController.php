<?php

namespace Fazzinipierluigi\AsgardCRM\Http\Controllers\Admin;

use Fazzinipierluigi\AsgardCRM\Http\Controllers\Controller;
use Fazzinipierluigi\AsgardCRM\Http\Requests\Admin\UpdateMailSettingRequest;
use Fazzinipierluigi\AsgardCRM\Models\MailSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin singleton settings page: global policy for the "E-mail" module
 * (connection timeout, max attachment size, which protocols a user may
 * pick when adding a mailbox, the short on-demand-listing cache
 * window, and each OAuth provider's app registration) — see
 * Fazzinipierluigi\AsgardCRM\Models\MailSetting. The two client_secret fields use the same
 * "blank keeps the previous value" trick as DocumentStorageController/
 * ConnectorController.
 */
class MailSettingController extends Controller
{
    public function edit(): View
    {
        return view('crm::admin.mail-settings.edit', ['setting' => MailSetting::current()]);
    }

    public function update(UpdateMailSettingRequest $request): RedirectResponse
    {
        $setting = MailSetting::current();
        $data = $request->validated();

        foreach (['google_oauth_client_secret', 'microsoft_oauth_client_secret'] as $key) {
            if (($data[$key] ?? null) === null) {
                unset($data[$key]);
            }
        }

        $setting->update($data);

        return redirect()->route('admin.mail-settings.edit')->with('status', 'mail-settings-updated');
    }
}
