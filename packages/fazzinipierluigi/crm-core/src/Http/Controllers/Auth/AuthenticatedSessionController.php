<?php

namespace Fazzinipierluigi\AsgardCRM\Http\Controllers\Auth;

use Fazzinipierluigi\AsgardCRM\Http\Controllers\Controller;
use Fazzinipierluigi\AsgardCRM\Http\Requests\Auth\LoginRequest;
use Fazzinipierluigi\AsgardCRM\Models\LoginProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('crm::auth.login', [
            'redirectProviders' => LoginProvider::where('is_active', true)
                ->whereIn('type', ['oauth', 'oidc', 'saml'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
