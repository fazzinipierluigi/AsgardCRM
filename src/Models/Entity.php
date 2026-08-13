<?php

namespace Fazzinipierluigi\AsgardCRM\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'table_name', 'icon', 'is_system', 'is_calendar', 'is_documents', 'is_email', 'is_installed', 'show_in_menu', 'menu_position', 'show_in_quick_access', 'quick_access_position'])]
class Entity extends Model
{
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_calendar' => 'boolean',
            'is_documents' => 'boolean',
            'is_email' => 'boolean',
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
     * Where this entity's "browse records" page lives — a handful of
     * system entities (Calendario/Documenti/E-mail) get their own
     * dedicated UI/controller instead of the generic Raccoon-grid
     * `entities.*` CRUD every other entity uses (see CalendarController/
     * DocumentController/MailController). Every place that links to "this
     * entity's own page" — the sidebar (layouts/base.blade.php) and the
     * topbar quick-access icons (layouts/app.blade.php) — must resolve
     * through here, or it silently falls back to the generic grid for
     * whichever of these three it forgets to special-case, exactly like
     * the quick-access topbar did before this method existed.
     *
     * @param  array<string, mixed>  $query
     */
    public function indexUrl(array $query = []): string
    {
        return match (true) {
            $this->is_calendar => route('calendar.index', $query),
            $this->is_documents => route('documents.index', $query),
            $this->is_email => route('mail.index', $query),
            default => route('entities.index', [$this, ...$query]),
        };
    }

    /**
     * Whether the current request is viewing this entity's own page —
     * the "is this nav link active" counterpart to indexUrl().
     */
    public function indexRouteIsActive(): bool
    {
        return match (true) {
            $this->is_calendar => request()->routeIs('calendar.*'),
            $this->is_documents => request()->routeIs('documents.*'),
            $this->is_email => request()->routeIs('mail.*'),
            default => request()->routeIs('entities.*') && request()->route('entity')?->slug === $this->slug,
        };
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
