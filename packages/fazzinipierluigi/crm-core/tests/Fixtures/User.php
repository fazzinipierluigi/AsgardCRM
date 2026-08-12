<?php

namespace Fazzinipierluigi\CrmCore\Tests\Fixtures;

use Fazzinipierluigi\CrmCore\Contracts\CrmUser;
use Fazzinipierluigi\JustAGate\Traits\Authorizable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Minimal stand-in for the host application's own User model, used
 * only by the package's Testbench suite (never published). Bound as
 * crm.user_model in tests/TestCase.php.
 */
class User extends Authenticatable implements CrmUser
{
    use Authorizable, HasFactory;

    protected $table = 'users';

    protected $guarded = [];

    protected static function newFactory()
    {
        return \Fazzinipierluigi\CrmCore\Tests\Fixtures\UserFactory::new();
    }

    /**
     * In-memory stand-in for the host's Setting-backed implementation
     * (Setting model is out of Modulo 1's scope) — good enough for views
     * that only read a value with a fallback default (e.g. dark-mode).
     */
    private array $testSettings = [];

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->testSettings[$key] ?? $default;
    }

    public function setSetting(string $key, mixed $value): void
    {
        $this->testSettings[$key] = $value;
    }
}
