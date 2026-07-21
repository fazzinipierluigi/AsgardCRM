<?php

namespace App\Services\Importers;

use App\Enums\ImporterChannel;
use App\Models\Importer;
use App\Services\Importers\Channels\CsvImporterChannel;
use App\Services\Importers\Channels\DatabaseImporterChannel;
use App\Services\Importers\Channels\JsonImporterChannel;
use App\Services\Importers\Channels\RestApiImporterChannel;

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
