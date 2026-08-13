<?php

namespace Fazzinipierluigi\AsgardCRM\Services\Importers;

use Fazzinipierluigi\AsgardCRM\Enums\ImporterChannel;
use Fazzinipierluigi\AsgardCRM\Models\Importer;
use Fazzinipierluigi\AsgardCRM\Services\Importers\Channels\CsvImporterChannel;
use Fazzinipierluigi\AsgardCRM\Services\Importers\Channels\DatabaseImporterChannel;
use Fazzinipierluigi\AsgardCRM\Services\Importers\Channels\JsonImporterChannel;
use Fazzinipierluigi\AsgardCRM\Services\Importers\Channels\RestApiImporterChannel;

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
