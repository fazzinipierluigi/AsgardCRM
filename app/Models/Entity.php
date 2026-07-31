<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'table_name', 'icon', 'is_system', 'is_calendar', 'is_documents', 'is_installed', 'show_in_menu', 'menu_position', 'show_in_quick_access', 'quick_access_position'])]
class Entity extends Model
{
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_calendar' => 'boolean',
            'is_documents' => 'boolean',
            'is_installed' => 'boolean',
            'show_in_menu' => 'boolean',
            'show_in_quick_access' => 'boolean',
        ];
    }

    public function tabs(): HasMany
    {
        return $this->hasMany(EntityTab::class)->orderBy('position');
    }

    public function folders(): HasMany
    {
        return $this->hasMany(DocumentFolder::class);
    }

    public function roleVisibilities(): HasMany
    {
        return $this->hasMany(EntityRoleVisibility::class);
    }

    public function listWidgets(): HasMany
    {
        return $this->hasMany(EntityListWidget::class)->orderBy('position');
    }

    public function fieldConditions(): HasMany
    {
        return $this->hasMany(EntityFieldCondition::class)->orderBy('position');
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
