<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['entity_id', 'name', 'position'])]
class EntityTab extends Model
{
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(EntityCard::class)->orderBy('position');
    }
}
