<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'table_name', 'icon', 'is_system', 'is_calendar', 'is_installed'])]
class Entity extends Model
{
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_calendar' => 'boolean',
            'is_installed' => 'boolean',
        ];
    }

    public function tabs(): HasMany
    {
        return $this->hasMany(EntityTab::class)->orderBy('position');
    }

    public function roleVisibilities(): HasMany
    {
        return $this->hasMany(EntityRoleVisibility::class);
    }

    /**
     * Every field defined across this entity's tabs/cards, ordered by
     * their card's/tab's position then their own.
     *
     * @return Collection<int, EntityField>
     */
    public function allFields(): Collection
    {
        return $this->tabs->flatMap(fn (EntityTab $tab) => $tab->cards->flatMap(fn (EntityCard $card) => $card->fields));
    }

    /**
     * Slugify the given name, appending a numeric suffix until it's
     * unique. Capped short enough that "entity_" + suffix stays well
     * within MySQL's 64-char table name limit.
     */
    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug(Str::limit($name, 40, ''));
        $slug = $base;
        $suffix = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
