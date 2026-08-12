<?php

namespace Fazzinipierluigi\CrmCore\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['entity_tab_id', 'name', 'position'])]
class EntityCard extends Model
{
    public function tab(): BelongsTo
    {
        return $this->belongsTo(EntityTab::class, 'entity_tab_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(EntityField::class)->orderBy('position');
    }
}
