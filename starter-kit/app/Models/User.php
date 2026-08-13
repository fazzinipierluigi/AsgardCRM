<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Fazzinipierluigi\CrmCore\Contracts\CrmUser;
use Fazzinipierluigi\CrmCore\Models\Setting;
use Fazzinipierluigi\JustAGate\Traits\Authorizable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'phone', 'job_title', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements CrmUser
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
