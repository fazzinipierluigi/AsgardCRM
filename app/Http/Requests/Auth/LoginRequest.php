<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\Auth\LdapAuthenticator;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials. Routes to the
     * user's assigned login provider — the local database check for
     * `local` (or an unrecognized username), an LDAP bind for `ldap`.
     * OAuth/OIDC/SAML users authenticate via a redirect flow instead
     * (see SocialLoginController/SamlLoginController) and are rejected here.
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $user = User::where('username', $this->string('username'))->first();
        $provider = $user?->effectiveLoginProvider();

        if ($provider && in_array($provider->type, ['oauth', 'oidc', 'saml'], true)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'username' => trans('auth.provider_redirect', ['provider' => $provider->name]),
            ]);
        }

        if ($provider?->type === 'ldap') {
            $authenticated = app(LdapAuthenticator::class)->attempt($provider, $user, $this->string('password')->value());

            if ($authenticated) {
                Auth::login($user, $this->boolean('remember'));
            }
        } else {
            $authenticated = Auth::attempt($this->only('username', 'password'), $this->boolean('remember'));
        }

        if (! $authenticated) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'username' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('username')).'|'.$this->ip());
    }
}
