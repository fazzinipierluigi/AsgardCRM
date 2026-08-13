# Versioning e changelog

## Versioning

AsgardCRM segue lo [SemVer](https://semver.org/), attualmente in `0.x`. La `1.0.0` è riservata al momento in cui un'applicazione esterna — non un repository gemello sullo stesso disco — avrà installato il package da zero tramite Packagist e lo avrà verificato end-to-end.

## Changelog

La cronologia completa e autorevole delle modifiche vive in [`CHANGELOG.md`](https://github.com/fazzinipierluigi/AsgardCRM/blob/main/CHANGELOG.md) nella root del repository, seguendo il formato [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). In sintesi:

- **`0.2.0`** — rinominato `fazzinipierluigi/crm-core` in `fazzinipierluigi/asgardcrm` (namespace `Fazzinipierluigi\CrmCore\` → `Fazzinipierluigi\AsgardCRM\`), spostato il package alla root del repository (niente più wrapper monorepo — i file dello scheletro Laravel della vecchia app standalone sono stati rimossi), e integrati Auth/Admin/Wizard di installazione/contenuti dimostrativi (in precedenza gli ultimi pezzi ancora nell'app standalone).
- **`0.1.0`** — primo stato coerente e testato del package estratto dall'originale monorepo AsgardCRM: entità dinamiche, motore di workflow, importer, connettori calendario, documenti, webmail e il substrato condiviso (modelli `Setting`/`Translation`/`Language`, helper `t()`/`icon()`/`preferences()`).

## Una correzione di sicurezza da conoscere

La sanificazione dei campi rich-text di `EntityRecordController` si basava in passato solo su `strip_tags($value, $allowedTags)`, che filtra soltanto i *nomi* dei tag — gli attributi sui tag mantenuti (`onmouseover=`, un `href` con `javascript:`) passavano completamente inalterati. Era una vera falla di stored-XSS, corretta nella `0.1.0` sostituendola con un sanificatore basato su `DOMDocument` (`sanitizeRichText()` / `sanitizeRichTextNode()`) che percorre il DOM, "srotola" ogni tag non ammesso (mantenendone testo/figli, eliminando il tag) e rimuove *ogni* attributo dai tag rimanenti. Se in futuro ti serve accettare e ripresentare HTML fornito dall'utente altrove nel package, segui questo stesso schema invece di affidarti a `strip_tags()` da solo.
