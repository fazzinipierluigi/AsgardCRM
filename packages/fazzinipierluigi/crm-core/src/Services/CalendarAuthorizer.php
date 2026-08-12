<?php

namespace Fazzinipierluigi\CrmCore\Services;

use Fazzinipierluigi\CrmCore\Enums\CalendarSharePermission;
use Fazzinipierluigi\CrmCore\Enums\EntityVisibilityLevel;
use Fazzinipierluigi\CrmCore\Models\CalendarShare;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Contracts\CrmUser;
use Illuminate\Database\Eloquent\Builder;

/**
 * Composes two independent axes of calendar access: EntityRecordAuthorizer's
 * per-role visibility level (organizational — "operators see everyone's
 * events") and CalendarShare's per-user discretionary grants ("I'm
 * sharing my calendar with Mario"). A user's role visibility, if better
 * than OwnOnly, already grants them everyone's events regardless of any
 * CalendarShare row — explicit sharing only matters for a user whose
 * role leaves them at OwnOnly.
 */
class CalendarAuthorizer
{
    public function __construct(private readonly EntityRecordAuthorizer $roleAuthorizer) {}

    public function canView(CrmUser $actor, Entity $entity, int $ownerId): bool
    {
        if ($this->roleAuthorizer->canView($actor, $entity, $ownerId)) {
            return true;
        }

        return $this->shareFor($actor, $ownerId) !== null;
    }

    /**
     * A share's "edit" permission covers both editing and deleting the
     * owner's events — there's no separate delete-only grant.
     */
    public function canEdit(CrmUser $actor, Entity $entity, int $ownerId): bool
    {
        if ($this->roleAuthorizer->canEdit($actor, $entity, $ownerId)) {
            return true;
        }

        return $this->shareFor($actor, $ownerId)?->permission === CalendarSharePermission::Edit;
    }

    public function canDelete(CrmUser $actor, Entity $entity, int $ownerId): bool
    {
        if ($this->roleAuthorizer->canDelete($actor, $entity, $ownerId)) {
            return true;
        }

        return $this->shareFor($actor, $ownerId)?->permission === CalendarSharePermission::Edit;
    }

    /**
     * Scope a records query to what the user is allowed to see: every
     * record if their role visibility already grants that, otherwise
     * their own plus whatever's been explicitly shared with them.
     */
    public function scopeQuery(Builder $query, CrmUser $actor, Entity $entity): Builder
    {
        if ($this->roleAuthorizer->levelFor($actor, $entity) !== EntityVisibilityLevel::OwnOnly) {
            return $query;
        }

        $ownerIds = CalendarShare::where('shared_with_user_id', $actor->id)->pluck('owner_user_id')->push($actor->id);

        return $query->whereIn('user_id', $ownerIds);
    }

    private function shareFor(CrmUser $actor, int $ownerId): ?CalendarShare
    {
        return CalendarShare::where('owner_user_id', $ownerId)
            ->where('shared_with_user_id', $actor->id)
            ->first();
    }
}
