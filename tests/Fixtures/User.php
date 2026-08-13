<?php

namespace Fazzinipierluigi\AsgardCRM\Tests\Fixtures;

use Fazzinipierluigi\AsgardCRM\Contracts\CrmUser;
use Fazzinipierluigi\AsgardCRM\Models\LoginProvider;
use Fazzinipierluigi\AsgardCRM\Models\Setting;
use Fazzinipierluigi\JustAGate\Traits\Authorizable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function loginProvider(): BelongsTo
    {
        return $this->belongsTo(LoginProvider::class);
    }

    public function effectiveLoginProvider(): LoginProvider
    {
        return $this->loginProvider ?? LoginProvider::local();
    }

    protected static function newFactory()
    {
        return UserFactory::new();
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return Setting::valueFor($this->id, $key, $default);
    }

    public function setSetting(string $key, mixed $value): void
    {
        Setting::setValue($this->id, $key, $value);
    }
}
