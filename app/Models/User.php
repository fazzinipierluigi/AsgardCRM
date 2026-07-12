<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Fazzinipierluigi\JustAGate\Traits\Authorizable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password', 'login_provider_id', 'provider_identifier'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Authorizable, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function loginProvider(): BelongsTo
    {
        return $this->belongsTo(LoginProvider::class);
    }

    /**
     * The provider this user authenticates through — the local provider
     * when none is explicitly assigned.
     */
    public function effectiveLoginProvider(): LoginProvider
    {
        return $this->loginProvider ?? LoginProvider::local();
    }

    /**
     * Resolve a setting for this user, falling back to the global value
     * and then to the given default.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return Setting::valueFor($this->id, $key, $default);
    }

    /**
     * Set a setting scoped to this user.
     */
    public function setSetting(string $key, mixed $value): void
    {
        Setting::setValue($this->id, $key, $value);
    }
}
