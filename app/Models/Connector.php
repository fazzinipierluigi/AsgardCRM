<?php

namespace App\Models;

use App\Enums\ConnectorSyncDirection;
use App\Enums\ConnectorType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'type', 'name', 'slug', 'is_active', 'config',
    'sync_direction', 'sync_interval_minutes',
    'last_synced_at', 'last_sync_status', 'last_sync_message',
])]
class Connector extends Model
{
    protected function casts(): array
    {
        return [
            'type' => ConnectorType::class,
            'is_active' => 'boolean',
            'config' => 'encrypted:array',
            'sync_direction' => ConnectorSyncDirection::class,
            'sync_interval_minutes' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    public function mailboxes(): HasMany
    {
        return $this->hasMany(ConnectorUserMailbox::class);
    }

    /**
     * Slugify the given name, appending a numeric suffix until it's unique.
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
