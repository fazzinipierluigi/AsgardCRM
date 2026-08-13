<?php

namespace Fazzinipierluigi\AsgardCRM\Services;

use Fazzinipierluigi\AsgardCRM\Contracts\CrmUser;
use Fazzinipierluigi\AsgardCRM\Enums\EntityVisibilityLevel;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\EntityRoleVisibility;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolves and enforces an entity's per-role visibility level: how much
 * of an entity's records a user can see/edit/delete beyond their own.
 *
 * This is deliberately separate from Just A Gate's flat permissions
 * (entity_{slug}.index/create/edit/delete, see EntityInstaller) — those
 * only gate whether a user can use the entity at all; this decides,
 * given that they can, which *rows* they're allowed to touch.
 */
class EntityRecordAuthorizer
{
    /**
     * The effective visibility level for a user on an entity: the most
     * permissive level across all of the user's roles. A user with an
     * `is_admin` role always gets Full, mirroring how Just A Gate's own
     * permission checks bypass everything for admin roles. A role with
     * no configured level for this entity defaults to OwnOnly — the
     * safest behavior for a role nobody has explicitly opened up yet.
     */
    public function levelFor(CrmUser $user, Entity $entity): EntityVisibilityLevel
    {
        $roles = $user->getRoles();

        if ($roles->contains('is_admin', true)) {
            return EntityVisibilityLevel::Full;
        }

        $levels = EntityRoleVisibility::where('entity_id', $entity->id)
            ->whereIn('role_id', $roles->pluck('id'))
            ->get()
            ->pluck('level');

        if ($levels->isEmpty()) {
            return EntityVisibilityLevel::OwnOnly;
        }

        return $levels->sortByDesc(fn (EntityVisibilityLevel $level) => $level->rank())->first();
    }

    public function canView(CrmUser $user, Entity $entity, int $ownerId): bool
    {
        return match ($this->levelFor($user, $entity)) {
            EntityVisibilityLevel::OwnOnly => $ownerId === $user->id,
            default => true,
        };
    }

    public function canEdit(CrmUser $user, Entity $entity, int $ownerId): bool
    {
        return match ($this->levelFor($user, $entity)) {
            EntityVisibilityLevel::OwnOnly, EntityVisibilityLevel::OwnManageOthersRead => $ownerId === $user->id,
            EntityVisibilityLevel::OwnManageOthersEdit, EntityVisibilityLevel::Full => true,
        };
    }

    public function canDelete(CrmUser $user, Entity $entity, int $ownerId): bool
    {
        return match ($this->levelFor($user, $entity)) {
            EntityVisibilityLevel::Full => true,
            default => $ownerId === $user->id,
        };
    }

    /**
     * Scope a records query down to what the user is allowed to see.
     * Every level except OwnOnly can at least read every record.
     */
    public function scopeQuery(Builder $query, CrmUser $user, Entity $entity): Builder
    {
        if ($this->levelFor($user, $entity) === EntityVisibilityLevel::OwnOnly) {
            return $query->where('user_id', $user->id);
        }

        return $query;
    }
}
