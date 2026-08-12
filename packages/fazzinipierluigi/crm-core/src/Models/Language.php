<?php

namespace Fazzinipierluigi\CrmCore\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name'])]
class Language extends Model
{
    /**
     * All languages as `code => name`, ordered by name.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return static::query()->orderBy('name')->pluck('name', 'code')->all();
    }
}
