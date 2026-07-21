<?php

namespace App\Models;

use App\Enums\ImporterChannel;
use App\Enums\ImporterScheduleType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'title',
    'description',
    'entity_id',
    'created_by',
    'slug',
    'channel',
    'config',
    'field_mapping',
    'unique_key_field',
    'schedule_type',
    'cron_expression',
    'is_active',
    'last_run_at',
    'last_run_status',
    'last_run_message',
])]
class Importer extends Model
{
    protected function casts(): array
    {
        return [
            'channel' => ImporterChannel::class,
            'config' => 'encrypted:array',
            'field_mapping' => 'array',
            'schedule_type' => ImporterScheduleType::class,
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ImporterRun::class)->latest('started_at');
    }

    /**
     * Slugify the given title, appending a numeric suffix until it's
     * unique — used by the artisan command's --importer= option.
     */
    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
