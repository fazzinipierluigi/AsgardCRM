<?php

namespace Fazzinipierluigi\CrmCore\Models;

use Fazzinipierluigi\CrmCore\Enums\MailConnectorType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Admin-configured, org-wide Exchange app registration / service
 * account (Microsoft Graph app-only credentials, or an EWS
 * impersonation account) — lets a user's MailAccount skip entering
 * personal Exchange credentials by pointing mail_connector_id here
 * instead. Unlike the calendar's Connector, there is no sync state:
 * mail is read/sent live on demand, never proactively synced.
 */
#[Fillable(['type', 'name', 'slug', 'is_active', 'config'])]
class MailConnector extends Model
{
    protected function casts(): array
    {
        return [
            'type' => MailConnectorType::class,
            'is_active' => 'boolean',
            'config' => 'encrypted:array',
        ];
    }

    public function mailAccounts(): HasMany
    {
        return $this->hasMany(MailAccount::class);
    }

    /**
     * Slugify the given name, appending a numeric suffix until it's unique.
     */
    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
