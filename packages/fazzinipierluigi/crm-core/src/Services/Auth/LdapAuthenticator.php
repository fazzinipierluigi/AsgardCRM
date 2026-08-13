<?php

namespace Fazzinipierluigi\CrmCore\Services\Auth;

use Fazzinipierluigi\CrmCore\Contracts\CrmUser;
use Fazzinipierluigi\CrmCore\Models\LoginProvider;
use LdapRecord\Auth\BindException;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\LdapRecordException;

class LdapAuthenticator
{
    /**
     * Attempt to authenticate the given user against the provider's
     * directory: bind as the configured service account, search for the
     * entry matching the user's username, then verify the password by
     * re-binding as that entry's DN. On success, refreshes the user's
     * name/email from the mapped directory attributes.
     */
    public function attempt(LoginProvider $provider, CrmUser $user, string $password): bool
    {
        $config = $provider->config ?? [];

        $connection = $this->connectionFor($provider, $config);

        try {
            $connection->connect();
        } catch (LdapRecordException) {
            return false;
        }

        $entry = $this->findUserEntry($connection, $config, $user->username);

        if ($entry === null) {
            return false;
        }

        $dn = is_array($entry['dn']) ? $entry['dn'][0] : $entry['dn'];

        try {
            $connection->connect($dn, $password);
        } catch (BindException) {
            return false;
        }

        $this->syncAttributes($user, $entry, $config);

        return true;
    }

    /**
     * Resolve (or lazily register) this provider's connection in the
     * LdapRecord\Container, keyed by its slug. Registering by name — rather
     * than handing back a bare `new Connection()` — is what lets tests swap
     * it for a `DirectoryEmulator` fake before it's first used.
     *
     * @param  array<string, mixed>  $config
     */
    private function connectionFor(LoginProvider $provider, array $config): Connection
    {
        $name = static::connectionName($provider);

        if (Container::hasConnection($name)) {
            return Container::getConnection($name);
        }

        $connection = new Connection([
            'hosts' => [$config['host'] ?? ''],
            'port' => (int) ($config['port'] ?? 389),
            'base_dn' => $config['base_dn'] ?? '',
            'username' => $config['bind_dn'] ?? null,
            'password' => $config['bind_password'] ?? null,
            'use_tls' => (bool) ($config['use_tls'] ?? false),
        ]);

        Container::addConnection($connection, $name);

        return $connection;
    }

    /**
     * The LdapRecord\Container connection name this provider is registered
     * under.
     */
    public static function connectionName(LoginProvider $provider): string
    {
        return 'login-provider-'.$provider->slug;
    }

    /**
     * Resolve the directory entry matching the given username.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>|null
     */
    private function findUserEntry(Connection $connection, array $config, string $username): ?array
    {
        $query = $connection->query()->in($config['base_dn'] ?? '');

        $escaped = (string) $query->escape($username);
        $filter = str_replace('%s', $escaped, $config['user_filter'] ?? '(uid=%s)');

        return $query->rawFilter($filter)->first();
    }

    /**
     * Refresh the local user's name/email from the directory entry, using
     * the provider's attribute mapping.
     *
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $config
     */
    private function syncAttributes(CrmUser $user, array $entry, array $config): void
    {
        $email = $this->firstValue($entry, $config['attr_email'] ?? 'mail');
        $name = $this->firstValue($entry, $config['attr_name'] ?? 'cn');

        if ($email !== null) {
            $user->email = $email;
        }

        if ($name !== null) {
            $user->name = $name;
        }

        if ($user->isDirty()) {
            $user->save();
        }
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function firstValue(array $entry, string $attribute): ?string
    {
        $attribute = strtolower($attribute);

        if (! isset($entry[$attribute])) {
            return null;
        }

        $value = $entry[$attribute];

        return is_array($value) ? ($value[0] ?? null) : $value;
    }
}
