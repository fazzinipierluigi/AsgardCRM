<?php

namespace Fazzinipierluigi\CrmCore\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['type', 'name', 'slug', 'is_active', 'is_system', 'config'])]
class LoginProvider extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'config' => 'encrypted:array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(config('crm.user_model'));
    }

    /**
     * The always-present, non-deletable local (database) provider. Local
     * authentication must keep working even before this row is seeded
     * (e.g. a fresh test database), so this falls back to an unsaved,
     * in-memory instance rather than failing.
     */
    public static function local(): self
    {
        return static::firstOrNew(
            ['slug' => 'local'],
            ['name' => 'Locale', 'type' => 'local', 'is_active' => true, 'is_system' => true]
        );
    }

    /**
     * Slugify the given name, appending a numeric suffix until it's
     * unique.
     */
    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
