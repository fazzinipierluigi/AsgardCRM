<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A folder in the "Documenti" system entity's tree — infinitely
 * nestable via self-referencing `parent_id`. Not an EntityRecord: this
 * is structural (the tree the entity's own records are filed into),
 * kept in its own fixed table rather than the entity's dynamic one.
 * See EntitySchemaBuilder for the `folder_id` column every "Documenti"
 * record gets to point into this tree.
 */
#[Fillable(['entity_id', 'parent_id', 'name', 'user_id'])]
class DocumentFolder extends Model
{
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
