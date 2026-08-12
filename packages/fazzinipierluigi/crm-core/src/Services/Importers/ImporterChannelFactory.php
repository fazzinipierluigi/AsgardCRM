<?php

namespace Fazzinipierluigi\CrmCore\Services\Importers;

use Fazzinipierluigi\CrmCore\Enums\ImporterChannel;
use Fazzinipierluigi\CrmCore\Models\Importer;
use Fazzinipierluigi\CrmCore\Services\Importers\Channels\CsvImporterChannel;
use Fazzinipierluigi\CrmCore\Services\Importers\Channels\DatabaseImporterChannel;
use Fazzinipierluigi\CrmCore\Services\Importers\Channels\JsonImporterChannel;
use Fazzinipierluigi\CrmCore\Services\Importers\Channels\RestApiImporterChannel;

class ImporterChannelFactory
{
    public function make(Importer $importer): ImporterChannelInterface
    {
        return match ($importer->channel) {
            ImporterChannel::Database => app(DatabaseImporterChannel::class),
            ImporterChannel::RestApi => app(RestApiImporterChannel::class),
            ImporterChannel::Csv => app(CsvImporterChannel::class),
            ImporterChannel::Json => app(JsonImporterChannel::class),
        };
    }
}
