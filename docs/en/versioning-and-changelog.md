# Versioning & changelog

## Versioning

AsgardCRM follows [SemVer](https://semver.org/), currently at `0.x`. `1.0.0` is reserved for the point where an external application — not a sibling repository on the same disk — has installed the package from scratch via Packagist and verified it end-to-end.

## Changelog

The full, authoritative history of changes lives in [`CHANGELOG.md`](https://github.com/fazzinipierluigi/AsgardCRM/blob/main/CHANGELOG.md) at the repository root, following the [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) format. Highlights:

- **`0.2.0`** — renamed `fazzinipierluigi/crm-core` to `fazzinipierluigi/asgardcrm` (namespace `Fazzinipierluigi\CrmCore\` → `Fazzinipierluigi\AsgardCRM\`), moved the package to the repository root (no more monorepo wrapper — the old standalone app's Laravel-skeleton files were removed), and folded in Auth/Admin/Install-wizard/demo-content (previously the last pieces still living in the standalone app).
- **`0.1.0`** — first coherent, tested state of the package extracted from the original AsgardCRM monorepo: dynamic entities, workflow engine, importers, calendar connectors, documents, webmail, and the shared substrate (`Setting`/`Translation`/`Language` models, `t()`/`icon()`/`preferences()` helpers).

## A security fix worth knowing about

`EntityRecordController`'s rich-text field sanitization used to rely on `strip_tags($value, $allowedTags)` alone, which only filters tag *names* — attributes on the tags it keeps (`onmouseover=`, a `javascript:` `href`) passed through completely untouched. This was a real stored-XSS hole, fixed in `0.1.0` by replacing it with a `DOMDocument`-based sanitizer (`sanitizeRichText()` / `sanitizeRichTextNode()`) that walks the DOM, unwraps any disallowed tag (keeping its text/children, dropping the tag), and strips *every* attribute from whatever tags remain. If you ever need to accept and re-render user-supplied HTML elsewhere in the package, follow this same pattern rather than reaching for `strip_tags()` alone.
