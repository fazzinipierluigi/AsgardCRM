<?php

namespace Fazzinipierluigi\CrmCore\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'key', 'value'])]
class Setting extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('crm.user_model'));
    }

    /**
     * Resolve a setting's value: user-specific value first, then the
     * global value (user_id null), then the given default.
     */
    public static function valueFor(?int $userId, string $key, mixed $default = null): mixed
    {
        if ($userId !== null) {
            $userValue = static::query()
                ->where('user_id', $userId)
                ->where('key', $key)
                ->value('value');

            if ($userValue !== null) {
                return $userValue;
            }
        }

        $globalValue = static::query()
            ->whereNull('user_id')
            ->where('key', $key)
            ->value('value');

        return $globalValue ?? $default;
    }

    /**
     * Set a setting's value, scoped to a user or global (user_id null).
     */
    public static function setValue(?int $userId, string $key, mixed $value): self
    {
        return static::query()->updateOrCreate(
            ['user_id' => $userId, 'key' => $key],
            ['value' => $value]
        );
    }
}
