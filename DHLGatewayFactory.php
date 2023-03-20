<?php

namespace Omnibus\DHL;

use Omnibus\Core\GatewayFactory;
use Omnibus\DHL\Action\ShippingAction;
use Omnibus\DHL\Action\TrackingAction;
use Omnibus\DHL\Action\PackingSlipAction;

class DHLGatewayFactory extends GatewayFactory
{
    protected function populateConfig(ArrayObject $config)
    {
        $config->defaults([
            'omnibus.factory_name' => 'dhl',
            'omnibus.factory_title' => 'DHL'
        ]);
    }
}