<?php

namespace Fazzinipierluigi\AsgardCRM\Http\Controllers;

use Fazzinipierluigi\AsgardCRM\Enums\MailOAuthProvider;
use Fazzinipierluigi\AsgardCRM\Models\MailAccount;
use Fazzinipierluigi\AsgardCRM\Services\Mail\OAuth\MailOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Two legs of the "Connetti con Google/Microsoft" flow started from
 * mail/accounts/edit.blade.php (see MailAuthMethod) — connect()
 * redirects to the provider's consent screen, callback() lands back
 * here once the user has granted (or denied) access. Both are thin:
 * all the actual token exchange/validation lives in MailOAuthService,
 * this controller only turns its result (or exception) into a
 * redirect the "Le mie caselle" screen can show.
 */
class MailOAuthController extends Controller
{
    public function __construct(private readonly MailOAuthService $oauthService) {}

    public function connect(Request $request, MailAccount $mailAccount, MailOAuthProvider $provider): RedirectResponse
    {
        abort_if($mailAccount->user_id !== $request->user()->id, 403);

        if ($mailAccount->auth_method->provider() !== $provider) {
            abort(422, 'Il provider non corrisponde al metodo di autenticazione configurato per questo account.');
        }

        if (! $this->oauthService->isConfigured($provider)) {
            return redirect()->route('mail.accounts.edit', $mailAccount)
                ->withErrors(['auth_method' => t('Provider OAuth non configurato. Contatta un amministratore.')]);
        }

        return redirect()->away($this->oauthService->authorizeUrl($request, $mailAccount, $provider));
    }

    public function callback(Request $request, MailOAuthProvider $provider): RedirectResponse
    {
        try {
            $account = $this->oauthService->handleCallback($request, $provider);
        } catch (RuntimeException $e) {
            return redirect()->route('mail.accounts.index')->withErrors(['auth_method' => $e->getMessage()]);
        }

        return redirect()->route('mail.accounts.edit', $account)->with('status', 'mail-account-oauth-connected');
    }
}
