<?php

namespace Omnibus\Dhl;

use Omnibus\Config;
use Omnibus\Dhl\Action\PickupAction;
use Omnibus\Dhl\Action\RatingAction;
use Omnibus\Dhl\Action\ShippingAction;
use Omnibus\Dhl\Action\TrackingAction;
use Omnibus\Exception\InvalidConfigException;
use Omnibus\GatewayFactory;
use Symfony\Component\HttpClient\HttpClient;

/**
 *   options:
 *     api_key: '%env(DHL_API_KEY)%'           # a MyDHL API app (developer.dhl.com)
 *     api_secret: '%env(DHL_API_SECRET)%'
 *     account_number: '%env(DHL_ACCOUNT)%'    # the export account
 *     sandbox: true
 *     location_api_key: null                  # optional, DHL's Location Finder (service points)
 *     rates: [...]                            # optional: configured prices instead of the rates API
 */
final class DhlGatewayFactory extends GatewayFactory
{
    protected function populateConfig(Config $config): void
    {
        $config->defaults([
            'omnibus.factory_name' => 'dhl',
            'omnibus.factory_title' => 'DHL Express',
            'omnibus.required_options' => ['api_key', 'api_secret', 'account_number'],
            'sandbox' => false,
            'location_api_key' => null,
            'omnibus.api' => function (Config $c) {
                $http = $this->http ?? (class_exists(HttpClient::class) ? HttpClient::create() : throw new InvalidConfigException('The "dhl" gateway needs symfony/http-client.'));

                return new Api($http, (string) $c['api_key'], (string) $c['api_secret'], (string) $c['account_number'], (bool) $c['sandbox'], $c['location_api_key'] ?: null);
            },
            'omnibus.action.rating' => static fn (Config $c) => $c->get('rates') ? null : new RatingAction(),
            'omnibus.action.shipping' => new ShippingAction(),
            'omnibus.action.tracking' => new TrackingAction(),
            'omnibus.action.pickup' => new PickupAction(),
        ]);
    }
}
