<?php

namespace Fazzinipierluigi\AsgardCRM\Models;

use Fazzinipierluigi\AsgardCRM\Enums\EntityVisibilityLevel;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['entity_id', 'role_id', 'level'])]
class EntityRoleVisibility extends Model
{
    protected $table = 'entity_role_visibility';

    protected function casts(): array
    {
        return [
            'level' => EntityVisibilityLevel::class,
        ];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
