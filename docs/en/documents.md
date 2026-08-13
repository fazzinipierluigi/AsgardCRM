# Documents

Like the calendar, Documents is an entity-backed module — an entity flagged `is_documents` gets its own folder-based document area, with any custom fields you add to it (beyond the two fixed ones: Nome/Descrizione) applying to every uploaded document.

## Storage backend

Admin → Storage Documenti configures **one** storage backend used for all document uploads:

| Type | Value | Required fields |
|---|---|---|
| Locale (disco del server) | `local` | — |
| Bucket S3-compatibile | `s3` | Key, Secret, Region, Bucket. Endpoint and "use path-style endpoint" are optional (for non-AWS S3-compatible providers). |
| Server FTP | `ftp` | Host, Username, Password. Port, root path, and SSL are optional. |
| Server SFTP | `sftp` | Host, Username, Password. Port and root path are optional. |

On edit, leaving a secret/password field blank keeps the previously stored value.

## Folders

Folders are per-entity (each `is_documents` entity has its own independent folder tree) and can be nested (`parent_id`). A folder name must be unique among its siblings within the same entity and parent — you can reuse a name in a different folder or a different entity.

## Uploading

A document upload requires:

- **File** — required on upload, optional on edit (you can rename/move a document or change its metadata without replacing the file itself).
- **Allowed extensions** — a deliberate allowlist, not "any file": `pdf`, `doc`, `docx`, `xls`, `xlsx`, `ppt`, `pptx`, `csv`, `txt`, `zip`, `rar`, `7z`, `jpg`, `jpeg`, `png`, `gif`, `webp`, `svg`, `bmp`, `mp4`, `avi`, `mov`, `mp3`, `wav`. This is a deliberate security boundary (no executables/scripts), not just a UI hint.
- **Maximum size** — 50 MB per file.
- **Folder** — optional; an unfiled document sits at the entity's document root.

## Where this fits

See [Creating entities → Special entity flags](creating-entities.md#9-special-entity-flags) for the `is_documents` flag itself, and [Modules overview](modules-overview.md#documents) for how the module's models relate to each other.
