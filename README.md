# omnibus/dhl

DHL Express for [glitchr/omnibus](https://github.com/glitchr-studio/omnibus): rates, shipments
and labels, tracking - the MyDHL API (basic auth) - and service points through DHL's Location
Finder (its own API key).

```yaml
omnibus:
    gateways:
        dhl:
            factory: dhl
            options:
                api_key: '%env(DHL_API_KEY)%'
                api_secret: '%env(DHL_API_SECRET)%'
                account_number: '%env(DHL_ACCOUNT)%'     # the export account
                sandbox: true
                location_api_key: '%env(DHL_LOCATION_KEY)%'   # optional: service points
                rates: [...]                              # optional: configured prices instead of the rates API
```

The service is the product code (P Express Worldwide, T Express 12:00, K Express 9:00, N Domestic
Express...). Shipment options: `customs` (true for a dutiable shipment; by default, across borders),
`incoterm` (DAP by default), `description`.

Credentials: a MyDHL API app at [developer.dhl.com](https://developer.dhl.com) (key and secret;
the test environment has its own), your DHL Express export account, and - for service points - a
Location Finder (Unified) app's key.

Built from DHL's published API documentation and tested on recorded answers; not yet run against
the test environment: that needs the credentials above.

License: LGPL-3.0-or-later.
