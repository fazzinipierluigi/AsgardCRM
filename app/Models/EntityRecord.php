<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A single record of an installed Entity's dynamic table. There is no
 * one model per entity — this generic model just points its $table at
 * whichever entity's table it's asked to represent. Every dynamic
 * table has a deleted_at column (see EntitySchemaBuilder), so deletion
 * is soft here too — the "Cestino" screen is what reads onlyTrashed()
 * and offers restore()/forceDelete().
 */
class EntityRecord extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public static function forEntity(Entity $entity): static
    {
        $instance = new static;
        $instance->setTable($entity->table_name);

        return $instance;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
