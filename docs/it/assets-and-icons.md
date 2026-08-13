# Asset e icone

## Icone

Le icone vengono sempre renderizzate come markup SVG inline — **mai** come web font — tramite l'helper `icon()` (`src/helpers.php`) e il suo equivalente JS (`resources/js/icon.js`). Entrambi leggono direttamente i file SVG statici del package npm `@tabler/icons`.

La tua applicazione host installa `@tabler/icons` autonomamente; il package non ne include una copia propria per l'uso a runtime:

```bash
npm install @tabler/icons
```

`config('crm.icons.path')` ha come default `base_path('node_modules/@tabler/icons/icons')` — sovrascrivilo con la variabile d'ambiente `CRM_ICONS_PATH` se le tue icone si trovano altrove (una posizione di installazione diversa, un monorepo, ecc.).

## Asset front-end compilati

Il package include il proprio output Vite **già compilato** (`public/vendor/crm/`), pubblicato tramite il tag `crm-assets` — la configurazione Vite e il processo di build della tua applicazione restano intatti, senza dover unire dipendenze npm. Le viste caricano gli asset del package tramite:

```blade
@vite([...], 'vendor/crm')
```

Un package installato via Composer non può eseguire l'`npm run build` dell'host, motivo per cui l'output compilato viene versionato e distribuito già pronto invece di essere ricompilato dai sorgenti all'installazione.

### Sviluppare il package stesso

Se stai lavorando sul front-end di AsgardCRM:

```bash
npm install
npm run build   # oppure `npm run dev` per l'HMR
```

Poi ripubblica l'output compilato verso qualsiasi host che consuma le tue modifiche locali:

```bash
php artisan vendor:publish --tag=crm-assets --force
```

### Perché `buildDirectory` è importante

Il `buildDirectory: 'vendor/crm'` di `vite.config.js` deve corrispondere **esattamente** alla destinazione di pubblicazione di `crm-assets` (`public_path('vendor/crm')`). I percorsi all'interno del manifest compilato e gli URL `src` di `@font-face` del CSS dei font sono fissati in fase di build, non risolti a runtime — un disallineamento servirebbe silenziosamente percorsi font/asset rotti sull'host consumer.

C'è un'unica eccezione nota e accettata: `Vite::renderFontPreloads()` ignora l'argomento `buildDirectory` passato a `@vite()` per singola chiamata e usa invece la propria proprietà di default del framework — una limitazione confermata del core di Laravel, non un bug del package. Il CSS `@font-face` effettivo è comunque corretto; solo il suggerimento `<link rel="preload">` punta al percorso sbagliato, il che è solo un problema estetico (i font si caricano comunque correttamente tramite il vero `@font-face`).
