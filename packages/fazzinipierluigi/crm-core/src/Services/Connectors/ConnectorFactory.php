<?php

namespace Fazzinipierluigi\CrmCore\Services\Connectors;

use Fazzinipierluigi\CrmCore\Enums\ConnectorType;
use Fazzinipierluigi\CrmCore\Models\Connector;
use Fazzinipierluigi\CrmCore\Services\Connectors\Exchange\EwsExchangeConnector;
use Fazzinipierluigi\CrmCore\Services\Connectors\Exchange\GraphExchangeConnector;

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
