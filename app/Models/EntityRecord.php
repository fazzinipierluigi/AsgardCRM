<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single record of an installed Entity's dynamic table. There is no
 * one model per entity — this generic model just points its $table at
 * whichever entity's table it's asked to represent.
 */
class EntityRecord extends Model
{
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
