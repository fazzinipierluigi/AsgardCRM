<?php

namespace App\Http\Controllers;

use App\Enums\MailAuthMethod;
use App\Http\Requests\StoreMailAccountRequest;
use App\Http\Requests\UpdateMailAccountRequest;
use App\Models\MailAccount;
use App\Models\MailConnector;
use App\Models\MailSignature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

/**
 * Self-service CRUD for a user's own mailbox accounts ("Le mie
 * caselle e-mail") — personal, like adding an account in a desktop
 * mail client, not an admin-managed resource. Authorization is pure
 * ownership (mail_account.user_id === the logged-in user), no ACL
 * permission — same reasoning as CalendarSettingsController's own
 * self-service screen. Protocol-specific fieldsets on the form share
 * this page, so their field names are prefixed (imap_/pop3_/
 * exchange_/smtp_) to avoid id/name collisions — see
 * StoreMailAccountRequest's docblock — and stripped back to plain
 * keys here before being stored in the encrypted `config` column.
 */
class MailAccountController extends Controller
{
    public function index(Request $request): View
    {
        return view('mail.accounts.index', [
            'accounts' => MailAccount::where('user_id', $request->user()->id)->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('mail.accounts.create', [
            'connectors' => MailConnector::where('is_active', true)->orderBy('name')->get(),
            'signatures' => MailSignature::orderBy('name')->get(),
        ]);
    }

    public function store(StoreMailAccountRequest $request): RedirectResponse
    {
        $protocol = $request->string('protocol')->value();
        $authMethod = $request->enum('auth_method', MailAuthMethod::class) ?? MailAuthMethod::Password;

        $account = MailAccount::create([
            'user_id' => $request->user()->id,
            'protocol' => $protocol,
            'auth_method' => $authMethod,
            'name' => $request->string('name'),
            'email_address' => $request->string('email_address'),
            'is_active' => $request->boolean('is_active'),
            'mail_connector_id' => $request->input('mail_connector_id') ?: null,
            'mail_signature_id' => $request->input('mail_signature_id') ?: null,
            'config' => $this->configFor($request, $protocol, $authMethod, (bool) $request->input('mail_connector_id'), null),
        ]);

        // An OAuth account has nothing to connect to yet — the edit
        // page is where the "Connetti con Google/Microsoft" button
        // lives (it needs the account's own id for the redirect_uri's
        // state, see MailOAuthService::authorizeUrl()).
        if ($authMethod !== MailAuthMethod::Password) {
            return redirect()->route('mail.accounts.edit', $account)->with('status', 'mail-account-created');
        }

        return redirect()->route('mail.accounts.index')->with('status', 'mail-account-created');
    }

    public function edit(Request $request, MailAccount $mailAccount): View
    {
        $this->authorizeOwnership($request, $mailAccount);

        return view('mail.accounts.edit', [
            'account' => $mailAccount,
            'connectors' => MailConnector::where('is_active', true)->orderBy('name')->get(),
            'signatures' => MailSignature::orderBy('name')->get(),
        ]);
    }

    public function update(UpdateMailAccountRequest $request, MailAccount $mailAccount): RedirectResponse
    {
        // Ownership is already enforced by UpdateMailAccountRequest::authorize().
        $usesConnector = (bool) $request->input('mail_connector_id');
        $authMethod = $request->enum('auth_method', MailAuthMethod::class) ?? MailAuthMethod::Password;
        $config = $this->configFor($request, $mailAccount->protocol->value, $authMethod, $usesConnector, $mailAccount);

        // Blank password fields keep the previously stored value — same
        // trick as DocumentStorageController/ConnectorController.
        foreach (['password', 'smtp_password'] as $key) {
            if (array_key_exists($key, $config) && $config[$key] === null) {
                $config[$key] = $mailAccount->config[$key] ?? null;
            }
        }

        $mailAccount->name = $request->string('name');
        $mailAccount->auth_method = $authMethod;
        $mailAccount->email_address = $request->string('email_address');
        $mailAccount->is_active = $request->boolean('is_active');
        $mailAccount->mail_connector_id = $request->input('mail_connector_id') ?: null;
        $mailAccount->mail_signature_id = $request->input('mail_signature_id') ?: null;
        $mailAccount->config = $config;
        $mailAccount->save();

        return redirect()->route('mail.accounts.index')->with('status', 'mail-account-updated');
    }

    public function destroy(Request $request, MailAccount $mailAccount): RedirectResponse
    {
        $this->authorizeOwnership($request, $mailAccount);

        $mailAccount->delete();

        return redirect()->route('mail.accounts.index')->with('status', 'mail-account-deleted');
    }

    private function authorizeOwnership(Request $request, MailAccount $mailAccount): void
    {
        abort_if($mailAccount->user_id !== $request->user()->id, 403);
    }

    /**
     * Build the config array from the request's prefixed fieldset
     * fields, stripped back to plain keys — empty entirely when the
     * account uses a shared MailConnector (its own credentials cover
     * both reading and sending, see MailAccount::usesSharedConnector()).
     * An OAuth auth_method (see MailAuthMethod) is a third, disjoint
     * case: no fieldset of its own at all, just whatever
     * MailOAuthService already wrote onto $existing's config, which
     * must survive a plain "edit name" save untouched.
     *
     * @return array<string, mixed>
     */
    private function configFor(Request $request, string $protocol, MailAuthMethod $authMethod, bool $usesConnector, ?MailAccount $existing): array
    {
        if ($usesConnector) {
            return [];
        }

        if ($authMethod !== MailAuthMethod::Password) {
            return Arr::only($existing?->config ?? [], ['oauth_provider', 'access_token', 'refresh_token', 'token_expires_at']);
        }

        $config = match ($protocol) {
            'imap' => $this->prefixedConfig($request, 'imap_', ['host', 'port', 'encryption', 'username', 'password']),
            'pop3' => $this->prefixedConfig($request, 'pop3_', ['host', 'port', 'encryption', 'username', 'password']),
            'exchange' => $this->prefixedConfig($request, 'exchange_', ['ews_url', 'username', 'password', 'use_ntlm']),
            default => [],
        };

        if (in_array($protocol, ['imap', 'pop3'], true)) {
            $config = array_merge($config, $this->prefixedConfig($request, 'smtp_', ['host', 'port', 'encryption', 'username', 'password']));
        }

        return $config;
    }

    /**
     * Reads the request's prefixed fieldset fields (imap_host, smtp_host,
     * ...) into a config array. The SMTP fieldset keeps its "smtp_"
     * prefix in storage — it coexists in the same config array as the
     * read-leg fields (host/port/encryption/username/password), which
     * would otherwise collide with it once stripped. The read-leg
     * fieldsets (imap_/pop3_/exchange_) don't need that: an account has
     * exactly one protocol, so only one of them is ever populated.
     *
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    private function prefixedConfig(Request $request, string $prefix, array $fields): array
    {
        $config = [];

        foreach ($fields as $field) {
            $key = $prefix === 'smtp_' ? "smtp_{$field}" : $field;

            $config[$key] = $field === 'use_ntlm'
                ? $request->boolean($prefix.$field)
                : $request->input($prefix.$field);
        }

        return $config;
    }
}
