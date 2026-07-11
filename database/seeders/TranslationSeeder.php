<?php

namespace Database\Seeders;

use App\Models\Translation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class TranslationSeeder extends Seeder
{
    /**
     * Bulk-import the translations for every UI string used across the
     * application's views (key => ['it' => ..., 'en' => ...]).
     *
     * Run automatically as part of `db:seed` (see DatabaseSeeder), and
     * safe to re-run: existing (key, language) rows are updated in
     * place rather than duplicated.
     */
    public function run(): void
    {
        $strings = [
            'Amministrazione' => ['it' => 'Amministrazione', 'en' => 'Administration'],
            'Azioni' => ['it' => 'Azioni', 'en' => 'Actions'],
            'Chiave' => ['it' => 'Chiave', 'en' => 'Key'],
            'Conferma password' => ['it' => 'Conferma password', 'en' => 'Confirm password'],
            "Confermi l'eliminazione?" => ['it' => "Confermi l'eliminazione?", 'en' => 'Confirm deletion?'],
            'Crea ruolo' => ['it' => 'Crea ruolo', 'en' => 'Create role'],
            'Creato il' => ['it' => 'Creato il', 'en' => 'Created at'],
            'Crea traduzione' => ['it' => 'Crea traduzione', 'en' => 'Create translation'],
            'Crea utente' => ['it' => 'Crea utente', 'en' => 'Create user'],
            'Dashboard' => ['it' => 'Dashboard', 'en' => 'Dashboard'],
            'Date format' => ['it' => 'Formato data', 'en' => 'Date format'],
            'Elimina' => ['it' => 'Elimina', 'en' => 'Delete'],
            'Email' => ['it' => 'Email', 'en' => 'Email'],
            'Gestisci permessi' => ['it' => 'Gestisci permessi', 'en' => 'Manage permissions'],
            'Impostazioni' => ['it' => 'Impostazioni', 'en' => 'Settings'],
            'Impostazioni aggiornate.' => ['it' => 'Impostazioni aggiornate.', 'en' => 'Settings updated.'],
            'Impostazioni personali' => ['it' => 'Impostazioni personali', 'en' => 'Personal settings'],
            'Lascia vuoto per non modificarla' => ['it' => 'Lascia vuoto per non modificarla', 'en' => 'Leave blank to keep it unchanged'],
            'Language' => ['it' => 'Lingua', 'en' => 'Language'],
            'Lingua' => ['it' => 'Lingua', 'en' => 'Language'],
            'Login' => ['it' => 'Accedi', 'en' => 'Login'],
            'Login to your account' => ['it' => 'Accedi al tuo account', 'en' => 'Login to your account'],
            'Logout' => ['it' => 'Logout', 'en' => 'Logout'],
            'Lo slug di un ruolo di sistema non può essere modificato.' => [
                'it' => 'Lo slug di un ruolo di sistema non può essere modificato.',
                'en' => "A system role's slug cannot be changed.",
            ],
            'Modifica' => ['it' => 'Modifica', 'en' => 'Edit'],
            'Modifica ruolo' => ['it' => 'Modifica ruolo', 'en' => 'Edit role'],
            'Modifica traduzione' => ['it' => 'Modifica traduzione', 'en' => 'Edit translation'],
            'Modifica utente' => ['it' => 'Modifica utente', 'en' => 'Edit user'],
            'Nessuna notifica' => ['it' => 'Nessuna notifica', 'en' => 'No notifications'],
            'Nessun permesso disponibile.' => ['it' => 'Nessun permesso disponibile.', 'en' => 'No permissions available.'],
            'Nessun ruolo' => ['it' => 'Nessun ruolo', 'en' => 'No role'],
            'No' => ['it' => 'No', 'en' => 'No'],
            'Nome' => ['it' => 'Nome', 'en' => 'Name'],
            'Nuova password' => ['it' => 'Nuova password', 'en' => 'New password'],
            'Nuova traduzione' => ['it' => 'Nuova traduzione', 'en' => 'New translation'],
            'Nuovo ruolo' => ['it' => 'Nuovo ruolo', 'en' => 'New role'],
            'Nuovo utente' => ['it' => 'Nuovo utente', 'en' => 'New user'],
            'Number format' => ['it' => 'Formato numeri', 'en' => 'Number format'],
            'Password' => ['it' => 'Password', 'en' => 'Password'],
            'Permessi' => ['it' => 'Permessi', 'en' => 'Permissions'],
            'Permessi di :role' => ['it' => 'Permessi di :role', 'en' => 'Permissions for :role'],
            'Preferenze' => ['it' => 'Preferenze', 'en' => 'Preferences'],
            'Preferenze aggiornate.' => ['it' => 'Preferenze aggiornate.', 'en' => 'Preferences updated.'],
            'Questo ruolo ha accesso completo automatico: i permessi selezionati qui non hanno effetto.' => [
                'it' => 'Questo ruolo ha accesso completo automatico: i permessi selezionati qui non hanno effetto.',
                'en' => 'This role already has full access automatically: any permissions selected here have no effect.',
            ],
            'Remember me' => ['it' => 'Ricordami', 'en' => 'Remember me'],
            'Ruoli' => ['it' => 'Ruoli', 'en' => 'Roles'],
            'Ruolo aggiornato correttamente.' => ['it' => 'Ruolo aggiornato correttamente.', 'en' => 'Role updated successfully.'],
            'Ruolo creato correttamente.' => ['it' => 'Ruolo creato correttamente.', 'en' => 'Role created successfully.'],
            'Ruolo eliminato correttamente.' => ['it' => 'Ruolo eliminato correttamente.', 'en' => 'Role deleted successfully.'],
            'Salva' => ['it' => 'Salva', 'en' => 'Save'],
            'Salva modifiche' => ['it' => 'Salva modifiche', 'en' => 'Save changes'],
            'Salva permessi' => ['it' => 'Salva permessi', 'en' => 'Save permissions'],
            'Salva preferenze' => ['it' => 'Salva preferenze', 'en' => 'Save preferences'],
            'Sì' => ['it' => 'Sì', 'en' => 'Yes'],
            'Sign in' => ['it' => 'Accedi', 'en' => 'Sign in'],
            'Sistema' => ['it' => 'Sistema', 'en' => 'System'],
            'Slug' => ['it' => 'Slug', 'en' => 'Slug'],
            'Theme' => ['it' => 'Tema', 'en' => 'Theme'],
            '← Torna alla Dashboard' => ['it' => '← Torna alla Dashboard', 'en' => '← Back to Dashboard'],
            'Traduzione aggiornata correttamente.' => ['it' => 'Traduzione aggiornata correttamente.', 'en' => 'Translation updated successfully.'],
            'Traduzione creata correttamente.' => ['it' => 'Traduzione creata correttamente.', 'en' => 'Translation created successfully.'],
            'Traduzione eliminata correttamente.' => ['it' => 'Traduzione eliminata correttamente.', 'en' => 'Translation deleted successfully.'],
            'Traduzioni' => ['it' => 'Traduzioni', 'en' => 'Translations'],
            'Username' => ['it' => 'Username', 'en' => 'Username'],
            'Utente aggiornato correttamente.' => ['it' => 'Utente aggiornato correttamente.', 'en' => 'User updated successfully.'],
            'Utente creato correttamente.' => ['it' => 'Utente creato correttamente.', 'en' => 'User created successfully.'],
            'Utente eliminato correttamente.' => ['it' => 'Utente eliminato correttamente.', 'en' => 'User deleted successfully.'],
            'Utenti' => ['it' => 'Utenti', 'en' => 'Users'],
            'Valore' => ['it' => 'Valore', 'en' => 'Value'],
            'Welcome, :name' => ['it' => 'Benvenuto, :name', 'en' => 'Welcome, :name'],
        ];

        $now = Carbon::now();
        $rows = [];

        foreach ($strings as $key => $languages) {
            foreach ($languages as $language => $value) {
                $rows[] = [
                    'key' => $key,
                    'language' => $language,
                    'value' => $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // upsert() is a bulk query-builder operation, so it doesn't fire
        // Eloquent's saved/deleted events (which is how Translation::$cache
        // normally invalidates itself) — clear it explicitly.
        Translation::upsert($rows, ['key', 'language'], ['value', 'updated_at']);
        Translation::forgetCache();
    }
}
