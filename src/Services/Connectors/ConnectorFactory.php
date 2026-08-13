<?php

namespace Fazzinipierluigi\AsgardCRM\Services\Connectors;

use Fazzinipierluigi\AsgardCRM\Enums\ConnectorType;
use Fazzinipierluigi\AsgardCRM\Models\Connector;
use Fazzinipierluigi\AsgardCRM\Services\Connectors\Exchange\EwsExchangeConnector;
use Fazzinipierluigi\AsgardCRM\Services\Connectors\Exchange\GraphExchangeConnector;

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
