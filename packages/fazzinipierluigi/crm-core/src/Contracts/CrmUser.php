<?php

namespace Fazzinipierluigi\AsgardCRM\Contracts;

use Fazzinipierluigi\AsgardCRM\Models\LoginProvider;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Contract implemented by the host application's own User model. Bound
 * in config('crm.user_model'); resolved via the container instead of a
 * hardcoded App\Models\User reference throughout the package.
 *
 * getSetting()/setSetting() are called directly on auth()->user() by
 * Modulo 1's own views (e.g. the dark-mode toggle) — discovered while
 * porting the Testbench suite, not part of the original static-analysis
 * pass over PHP source (which only scans code, not Blade views).
 *
 * effectiveLoginProvider() was added for Modulo 5 (Auth/Admin/Install):
 * LoginRequest/SamlLoginController/SocialLoginController all need to
 * know which provider a user authenticates through without assuming a
 * concrete `App\Models\User` — the host implementation still owns the
 * actual `loginProvider(): BelongsTo` relation and the "fall back to
 * LoginProvider::local()" behavior, this just names the contract.
 * Direct property/column access (->username, ->email, ->id, ->save())
 * on an already-resolved CrmUser instance is fine without being on the
 * interface — every host implementation is an Eloquent model in
 * practice, same assumption getSetting()/setSetting() already make.
 */
interface CrmUser extends Authenticatable
{
    public function getSetting(string $key, mixed $default = null): mixed;

    public function setSetting(string $key, mixed $value): void;

    public function effectiveLoginProvider(): LoginProvider;
}
