<?php

namespace App\Models;

use App\Enums\CalendarSharePermission;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's grant of read/edit access to their own calendar events to
 * another specific user — discretionary and per-user, distinct from
 * EntityRoleVisibility's per-role visibility levels (see
 * CalendarAuthorizer, which composes both).
 */
#[Fillable(['owner_user_id', 'shared_with_user_id', 'permission'])]
class CalendarShare extends Model
{
    protected function casts(): array
    {
        return [
            'permission' => CalendarSharePermission::class,
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function sharedWith(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_with_user_id');
    }
}
