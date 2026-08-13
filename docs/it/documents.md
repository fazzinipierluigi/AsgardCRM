# Documenti

Come il calendario, Documenti è un modulo basato su entità — un'entità contrassegnata `is_documents` ottiene una propria area documentale a cartelle, con eventuali campi personalizzati aggiunti (oltre ai due fissi: Nome/Descrizione) applicati a ogni documento caricato.

## Backend di storage

Admin → Storage Documenti configura **un solo** backend di storage usato per tutti i caricamenti di documenti:

| Tipo | Valore | Campi obbligatori |
|---|---|---|
| Locale (disco del server) | `local` | — |
| Bucket S3-compatibile | `s3` | Key, Secret, Region, Bucket. Endpoint e "usa endpoint path-style" sono opzionali (per provider S3-compatibili non-AWS). |
| Server FTP | `ftp` | Host, Username, Password. Porta, percorso radice e SSL sono opzionali. |
| Server SFTP | `sftp` | Host, Username, Password. Porta e percorso radice sono opzionali. |

In modifica, lasciare vuoto un campo secret/password mantiene il valore già memorizzato.

## Cartelle

Le cartelle sono per-entità (ogni entità `is_documents` ha un proprio albero di cartelle indipendente) e possono essere annidate (`parent_id`). Il nome di una cartella deve essere univoco tra i suoi fratelli, all'interno della stessa entità e dello stesso genitore — puoi riutilizzare un nome in una cartella diversa o in un'entità diversa.

## Caricamento

Il caricamento di un documento richiede:

- **File** — obbligatorio al caricamento, opzionale in modifica (puoi rinominare/spostare un documento o cambiarne i metadati senza sostituire il file stesso).
- **Estensioni consentite** — un allowlist deliberato, non "qualsiasi file": `pdf`, `doc`, `docx`, `xls`, `xlsx`, `ppt`, `pptx`, `csv`, `txt`, `zip`, `rar`, `7z`, `jpg`, `jpeg`, `png`, `gif`, `webp`, `svg`, `bmp`, `mp4`, `avi`, `mov`, `mp3`, `wav`. È un confine di sicurezza deliberato (niente eseguibili/script), non solo un suggerimento dell'interfaccia.
- **Dimensione massima** — 50 MB per file.
- **Cartella** — opzionale; un documento senza cartella si trova nella radice documentale dell'entità.

## Dove si inserisce

Vedi [Creazione delle entità → Flag speciali dell'entità](creating-entities.md#9-flag-speciali-dellentità) per il flag `is_documents` stesso, e [Panoramica dei moduli](modules-overview.md#documenti) per come i modelli del modulo si relazionano tra loro.
