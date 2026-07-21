<?php

namespace App\Models;

use App\Enums\EntityFieldType;
use App\Enums\EntityRelationTargetType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'entity_card_id',
    'name',
    'column_name',
    'type',
    'options',
    'relation_target_type',
    'relation_target',
    'required',
    'default_value',
    'position',
    'width',
    'is_locked',
])]
class EntityField extends Model
{
    protected function casts(): array
    {
        return [
            'type' => EntityFieldType::class,
            'options' => 'array',
            'relation_target_type' => EntityRelationTargetType::class,
            'required' => 'boolean',
            'is_locked' => 'boolean',
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(EntityCard::class, 'entity_card_id');
    }
}
