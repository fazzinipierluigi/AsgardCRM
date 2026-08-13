<?php

namespace Fazzinipierluigi\AsgardCRM\Services\Importers;

use Fazzinipierluigi\AsgardCRM\Models\Importer;

/**
 * Contract every import source channel implements (see
 * Channels\DatabaseImporterChannel, RestApiImporterChannel,
 * CsvImporterChannel, JsonImporterChannel). A channel only speaks the
 * external source — it never touches EntityRecord or field_mapping,
 * that's ImporterRunner's job.
 */
interface ImporterChannelInterface
{
    /**
     * Fetch just enough of the source to populate the mapping step:
     * the available column names and one sample row. Never throws —
     * connection/format failures are reported in the returned array
     * so the admin UI can show them inline.
     *
     * @return array{ok: bool, message?: string, columns?: array<int, string>, sample?: array<string, mixed>}
     */
    public function preview(Importer $importer): array;

    /**
     * Fetch every row from the source, as an iterable of associative
     * arrays (source field name => value). A generator, so callers
     * never need to hold the whole source in memory at once.
     *
     * @return iterable<int, array<string, mixed>>
     */
    public function fetch(Importer $importer): iterable;
}
