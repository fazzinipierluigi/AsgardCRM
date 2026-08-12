<?php

namespace Fazzinipierluigi\CrmCore\Contracts;

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
 */
interface CrmUser extends Authenticatable
{
    public function getSetting(string $key, mixed $default = null): mixed;

    public function setSetting(string $key, mixed $value): void;
}
