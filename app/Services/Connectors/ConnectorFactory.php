<?php

namespace App\Services\Connectors;

use App\Enums\ConnectorType;
use App\Models\Connector;
use App\Services\Connectors\Exchange\EwsExchangeConnector;
use App\Services\Connectors\Exchange\GraphExchangeConnector;

class ConnectorFactory
{
    public function make(Connector $connector): ConnectorInterface
    {
        return match ($connector->type) {
            ConnectorType::ExchangeGraph => app(GraphExchangeConnector::class),
            ConnectorType::ExchangeEws => app(EwsExchangeConnector::class),
        };
    }
}
