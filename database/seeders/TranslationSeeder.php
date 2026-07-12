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
            'Aggiungi campo' => ['it' => 'Aggiungi campo', 'en' => 'Add field'],
            'Aggiungi card' => ['it' => 'Aggiungi card', 'en' => 'Add card'],
            'Aggiungi lingua' => ['it' => 'Aggiungi lingua', 'en' => 'Add language'],
            'Aggiungi tab' => ['it' => 'Aggiungi tab', 'en' => 'Add tab'],
            'Accessi' => ['it' => 'Accessi', 'en' => 'Access control'],
            'Amministrazione' => ['it' => 'Amministrazione', 'en' => 'Administration'],
            'Azioni' => ['it' => 'Azioni', 'en' => 'Actions'],
            'Cerca nel menù' => ['it' => 'Cerca nel menù', 'en' => 'Search menu'],
            'Cerca nelle entità...' => ['it' => 'Cerca nelle entità...', 'en' => 'Search entities...'],
            'Chiave' => ['it' => 'Chiave', 'en' => 'Key'],
            'Codice' => ['it' => 'Codice', 'en' => 'Code'],
            'Conferma password' => ['it' => 'Conferma password', 'en' => 'Confirm password'],
            "Confermi l'eliminazione?" => ['it' => "Confermi l'eliminazione?", 'en' => 'Confirm deletion?'],
            'Crea entità' => ['it' => 'Crea entità', 'en' => 'Create entity'],
            'Crea ruolo' => ['it' => 'Crea ruolo', 'en' => 'Create role'],
            'Creata il' => ['it' => 'Creata il', 'en' => 'Created at'],
            'Creato il' => ['it' => 'Creato il', 'en' => 'Created at'],
            'Crea traduzione' => ['it' => 'Crea traduzione', 'en' => 'Create translation'],
            'Crea utente' => ['it' => 'Crea utente', 'en' => 'Create user'],
            'Dashboard' => ['it' => 'Dashboard', 'en' => 'Dashboard'],
            'Date format' => ['it' => 'Formato data', 'en' => 'Date format'],
            'Disinstalla' => ['it' => 'Disinstalla', 'en' => 'Uninstall'],
            'Doppio click per modificare' => ['it' => 'Doppio click per modificare', 'en' => 'Double-click to edit'],
            'File schema (JSON)' => ['it' => 'File schema (JSON)', 'en' => 'Schema file (JSON)'],
            'Elenco' => ['it' => 'Elenco', 'en' => 'List'],
            'Elimina' => ['it' => 'Elimina', 'en' => 'Delete'],
            'Email' => ['it' => 'Email', 'en' => 'Email'],
            'Entità' => ['it' => 'Entità', 'en' => 'Entities'],
            'Esporta' => ['it' => 'Esporta', 'en' => 'Export'],
            'Entità aggiornata correttamente.' => ['it' => 'Entità aggiornata correttamente.', 'en' => 'Entity updated successfully.'],
            'Entità creata correttamente.' => ['it' => 'Entità creata correttamente.', 'en' => 'Entity created successfully.'],
            'Entità disinstallata correttamente.' => ['it' => 'Entità disinstallata correttamente.', 'en' => 'Entity uninstalled successfully.'],
            'Entità eliminata correttamente.' => ['it' => 'Entità eliminata correttamente.', 'en' => 'Entity deleted successfully.'],
            'Entità importata correttamente.' => ['it' => 'Entità importata correttamente.', 'en' => 'Entity imported successfully.'],
            'Entità installata correttamente.' => ['it' => 'Entità installata correttamente.', 'en' => 'Entity installed successfully.'],
            'Gestisci lingue' => ['it' => 'Gestisci lingue', 'en' => 'Manage languages'],
            'Gestisci permessi' => ['it' => 'Gestisci permessi', 'en' => 'Manage permissions'],
            'Icona' => ['it' => 'Icona', 'en' => 'Icon'],
            'Icona Tabler mostrata nel menu.' => ['it' => 'Icona Tabler mostrata nel menu.', 'en' => 'Tabler icon shown in the menu.'],
            'Impostazioni' => ['it' => 'Impostazioni', 'en' => 'Settings'],
            'Impostazioni aggiornate.' => ['it' => 'Impostazioni aggiornate.', 'en' => 'Settings updated.'],
            'Impostazioni personali' => ['it' => 'Impostazioni personali', 'en' => 'Personal settings'],
            'Importa' => ['it' => 'Importa', 'en' => 'Import'],
            'Importa entità' => ['it' => 'Importa entità', 'en' => 'Import entity'],
            'Installa' => ['it' => 'Installa', 'en' => 'Install'],
            'Installata' => ['it' => 'Installata', 'en' => 'Installed'],
            'Lascia vuoto per non modificarla' => ['it' => 'Lascia vuoto per non modificarla', 'en' => 'Leave blank to keep it unchanged'],
            'Language' => ['it' => 'Lingua', 'en' => 'Language'],
            'Lingua' => ['it' => 'Lingua', 'en' => 'Language'],
            'Lingua creata correttamente.' => ['it' => 'Lingua creata correttamente.', 'en' => 'Language created successfully.'],
            'Lingua eliminata correttamente.' => ['it' => 'Lingua eliminata correttamente.', 'en' => 'Language deleted successfully.'],
            'Lingue' => ['it' => 'Lingue', 'en' => 'Languages'],
            'Lingue disponibili' => ['it' => 'Lingue disponibili', 'en' => 'Available languages'],
            'Localizzazione' => ['it' => 'Localizzazione', 'en' => 'Localization'],
            'Login' => ['it' => 'Accedi', 'en' => 'Login'],
            'Login to your account' => ['it' => 'Accedi al tuo account', 'en' => 'Login to your account'],
            'Logout' => ['it' => 'Logout', 'en' => 'Logout'],
            'Lo slug di un ruolo di sistema non può essere modificato.' => [
                'it' => 'Lo slug di un ruolo di sistema non può essere modificato.',
                'en' => "A system role's slug cannot be changed.",
            ],
            'Lo slug non può essere modificato dopo la creazione.' => [
                'it' => 'Lo slug non può essere modificato dopo la creazione.',
                'en' => 'The slug cannot be changed after creation.',
            ],
            'Modifica' => ['it' => 'Modifica', 'en' => 'Edit'],
            'Modifica entità' => ['it' => 'Modifica entità', 'en' => 'Edit entity'],
            'Modifica record' => ['it' => 'Modifica record', 'en' => 'Edit record'],
            'Modifica ruolo' => ['it' => 'Modifica ruolo', 'en' => 'Edit role'],
            'Modifica traduzione' => ['it' => 'Modifica traduzione', 'en' => 'Edit translation'],
            'Modifica utente' => ['it' => 'Modifica utente', 'en' => 'Edit user'],
            'Nessuna notifica' => ['it' => 'Nessuna notifica', 'en' => 'No notifications'],
            'Nessun risultato' => ['it' => 'Nessun risultato', 'en' => 'No results'],
            'Nessuna icona' => ['it' => 'Nessuna icona', 'en' => 'No icon'],
            'Nessun permesso disponibile.' => ['it' => 'Nessun permesso disponibile.', 'en' => 'No permissions available.'],
            'Nessun ruolo' => ['it' => 'Nessun ruolo', 'en' => 'No role'],
            'Nessun ruolo configurabile.' => ['it' => 'Nessun ruolo configurabile.', 'en' => 'No configurable role.'],
            'No' => ['it' => 'No', 'en' => 'No'],
            'Nome' => ['it' => 'Nome', 'en' => 'Name'],
            'Nome campo' => ['it' => 'Nome campo', 'en' => 'Field name'],
            'Nome card' => ['it' => 'Nome card', 'en' => 'Card name'],
            'Nome colonna' => ['it' => 'Nome colonna', 'en' => 'Column name'],
            'Nome tab' => ['it' => 'Nome tab', 'en' => 'Tab name'],
            'Nuova entità' => ['it' => 'Nuova entità', 'en' => 'New entity'],
            'Nuova password' => ['it' => 'Nuova password', 'en' => 'New password'],
            'Nuova traduzione' => ['it' => 'Nuova traduzione', 'en' => 'New translation'],
            'Nuovo record' => ['it' => 'Nuovo record', 'en' => 'New record'],
            'Nuovo ruolo' => ['it' => 'Nuovo ruolo', 'en' => 'New role'],
            'Nuovo utente' => ['it' => 'Nuovo utente', 'en' => 'New user'],
            'Number format' => ['it' => 'Formato numeri', 'en' => 'Number format'],
            'Obbligatorio' => ['it' => 'Obbligatorio', 'en' => 'Required'],
            'Opzioni (una per riga, formato chiave:Etichetta)' => [
                'it' => 'Opzioni (una per riga, formato chiave:Etichetta)',
                'en' => 'Options (one per line, key:Label format)',
            ],
            'Password' => ['it' => 'Password', 'en' => 'Password'],
            'Permessi' => ['it' => 'Permessi', 'en' => 'Permissions'],
            'Permessi di :role' => ['it' => 'Permessi di :role', 'en' => 'Permissions for :role'],
            'Prefisso' => ['it' => 'Prefisso', 'en' => 'Prefix'],
            'Preferenze' => ['it' => 'Preferenze', 'en' => 'Preferences'],
            'Preferenze aggiornate.' => ['it' => 'Preferenze aggiornate.', 'en' => 'Preferences updated.'],
            'Progetta' => ['it' => 'Progetta', 'en' => 'Design'],
            'Progetta :entity' => ['it' => 'Progetta :entity', 'en' => 'Design :entity'],
            'Progetta struttura' => ['it' => 'Progetta struttura', 'en' => 'Design structure'],
            'Proprietario' => ['it' => 'Proprietario', 'en' => 'Owner'],
            'Questa entità è installata: la struttura non è più modificabile da qui.' => [
                'it' => 'Questa entità è installata: la struttura non è più modificabile da qui.',
                'en' => 'This entity is installed: its structure can no longer be changed from here.',
            ],
            'Questo ruolo ha accesso completo automatico: i permessi selezionati qui non hanno effetto.' => [
                'it' => 'Questo ruolo ha accesso completo automatico: i permessi selezionati qui non hanno effetto.',
                'en' => 'This role already has full access automatically: any permissions selected here have no effect.',
            ],
            'Record aggiornato correttamente.' => ['it' => 'Record aggiornato correttamente.', 'en' => 'Record updated successfully.'],
            'Record creato correttamente.' => ['it' => 'Record creato correttamente.', 'en' => 'Record created successfully.'],
            'Record eliminato correttamente.' => ['it' => 'Record eliminato correttamente.', 'en' => 'Record deleted successfully.'],
            'Relazione verso' => ['it' => 'Relazione verso', 'en' => 'Relation to'],
            'Remember me' => ['it' => 'Ricordami', 'en' => 'Remember me'],
            'Rimuovi campo' => ['it' => 'Rimuovi campo', 'en' => 'Remove field'],
            'Rimuovi card' => ['it' => 'Rimuovi card', 'en' => 'Remove card'],
            'Rimuovi tab' => ['it' => 'Rimuovi tab', 'en' => 'Remove tab'],
            'Ruolo' => ['it' => 'Ruolo', 'en' => 'Role'],
            'Ruoli' => ['it' => 'Ruoli', 'en' => 'Roles'],
            'Ruolo aggiornato correttamente.' => ['it' => 'Ruolo aggiornato correttamente.', 'en' => 'Role updated successfully.'],
            'Ruolo creato correttamente.' => ['it' => 'Ruolo creato correttamente.', 'en' => 'Role created successfully.'],
            'Ruolo eliminato correttamente.' => ['it' => 'Ruolo eliminato correttamente.', 'en' => 'Role deleted successfully.'],
            'Salva' => ['it' => 'Salva', 'en' => 'Save'],
            'Salva modifiche' => ['it' => 'Salva modifiche', 'en' => 'Save changes'],
            'Salva permessi' => ['it' => 'Salva permessi', 'en' => 'Save permissions'],
            'Salva preferenze' => ['it' => 'Salva preferenze', 'en' => 'Save preferences'],
            'Salva struttura' => ['it' => 'Salva struttura', 'en' => 'Save structure'],
            'Salva visibilità' => ['it' => 'Salva visibilità', 'en' => 'Save visibility'],
            'Seleziona...' => ['it' => 'Seleziona...', 'en' => 'Select...'],
            'Sì' => ['it' => 'Sì', 'en' => 'Yes'],
            'Sign in' => ['it' => 'Accedi', 'en' => 'Sign in'],
            'Sistema' => ['it' => 'Sistema', 'en' => 'System'],
            'Slug' => ['it' => 'Slug', 'en' => 'Slug'],
            'Struttura dati' => ['it' => 'Struttura dati', 'en' => 'Data structure'],
            'Struttura salvata correttamente.' => ['it' => 'Struttura salvata correttamente.', 'en' => 'Structure saved successfully.'],
            'Theme' => ['it' => 'Tema', 'en' => 'Theme'],
            'Theme base' => ['it' => 'Base tema', 'en' => 'Theme base'],
            'Theme color' => ['it' => 'Colore tema', 'en' => 'Theme color'],
            'Tipo' => ['it' => 'Tipo', 'en' => 'Type'],
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
            'Valore predefinito' => ['it' => 'Valore predefinito', 'en' => 'Default value'],
            'Verrà generato automaticamente al salvataggio.' => [
                'it' => 'Verrà generato automaticamente al salvataggio.',
                'en' => 'Will be generated automatically on save.',
            ],
            'Il valore generato sarà "prefisso" + numero progressivo, es. INV-1, INV-2...' => [
                'it' => 'Il valore generato sarà "prefisso" + numero progressivo, es. INV-1, INV-2...',
                'en' => 'The generated value will be "prefix" + a running number, e.g. INV-1, INV-2...',
            ],
            'Visibilità' => ['it' => 'Visibilità', 'en' => 'Visibility'],
            'Visibilità aggiornata correttamente.' => ['it' => 'Visibilità aggiornata correttamente.', 'en' => 'Visibility updated successfully.'],
            'Visibilità di :entity' => ['it' => 'Visibilità di :entity', 'en' => 'Visibility of :entity'],
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
