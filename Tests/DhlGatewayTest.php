<?php

namespace Omnibus\Dhl\Tests;

use Omnibus\Dhl\DhlGatewayFactory;
use Omnibus\Exception\CarrierException;
use Omnibus\Model\TrackingStatus;
use Omnibus\Tests\Fixtures;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class DhlGatewayTest extends TestCase
{
    private array $calls = [];

    private function gateway(bool $locations = true): \Omnibus\GatewayInterface
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $path = (string) parse_url($url, \PHP_URL_PATH);
            $this->calls[] = [$method, $url, \is_string($options['body'] ?? null) && str_starts_with($options['body'], '{') ? json_decode($options['body'], true) : []];

            return match (true) {
                str_ends_with($path, '/mydhlapi/test/rates') => new MockResponse(json_encode(['products' => [
                    ['productName' => 'EXPRESS WORLDWIDE', 'productCode' => 'P', 'totalPrice' => [['currencyType' => 'BILLC', 'priceCurrency' => 'EUR', 'price' => 57.4]], 'deliveryCapabilities' => ['totalTransitDays' => 2]],
                    ['productName' => 'EXPRESS 12:00', 'productCode' => 'T', 'totalPrice' => [['currencyType' => 'BILLC', 'priceCurrency' => 'EUR', 'price' => 71.0]], 'deliveryCapabilities' => ['totalTransitDays' => 1]],
                ]])),
                str_ends_with($path, '/mydhlapi/test/shipments') => new MockResponse(json_encode(['shipmentTrackingNumber' => '1234567890', 'trackingUrl' => 'https://express.api.dhl.com/track/1234567890', 'documents' => [['typeCode' => 'label', 'imageFormat' => 'PDF', 'content' => base64_encode('%PDF-1.4 dhl')]]])),
                str_contains($path, '/tracking') => new MockResponse(json_encode(['shipments' => [['status' => 'Delivered', 'events' => [
                    ['date' => '2026-10-01', 'time' => '09:12:00', 'typeCode' => 'PU', 'description' => 'Shipment picked up', 'serviceArea' => [['description' => 'STRASBOURG-FRA']]],
                    ['date' => '2026-10-02', 'time' => '11:40:00', 'typeCode' => 'OK', 'description' => 'Delivered - Signed for by', 'serviceArea' => [['description' => 'BERLIN-DEU']]],
                ]]]])),
                str_contains($url, 'location-finder') => new MockResponse(json_encode(['locations' => [['url' => '/locations/8003-4SA3', 'location' => ['ids' => [['locationId' => '8003-4SA3', 'provider' => 'express']]], 'name' => 'Press Point', 'distance' => 350, 'place' => ['address' => ['streetAddress' => '3 Rue des Francs-Bourgeois', 'postalCode' => '67000', 'addressLocality' => 'Strasbourg', 'countryCode' => 'FR'], 'geo' => ['latitude' => 48.58, 'longitude' => 7.75]], 'openingHours' => [['dayOfWeek' => 'http://schema.org/Monday', 'opens' => '08:30:00', 'closes' => '19:00:00']]]]])),
                default => new MockResponse(json_encode(['title' => 'Not found', 'detail' => 'No such resource '.$path, 'status' => 404]), ['http_code' => 404]),
            };
        });

        return (new DhlGatewayFactory($http))->create(['api_key' => 'key', 'api_secret' => 'secret', 'account_number' => '123456789', 'sandbox' => true, 'location_api_key' => $locations ? 'loc-key' : null]);
    }

    public function testRatesAreTheProductsCheapestFirst(): void
    {
        $rates = $this->gateway()->rate(Fixtures::shipment());
        self::assertSame(['P', 'T'], array_map(fn ($r) => $r->service, $rates));
        self::assertSame(5740, $rates[0]->amount);
        self::assertSame(2, $rates[0]->days);
        self::assertSame('123456789', $this->calls[0][2]['accounts'][0]['number']);
        self::assertSame('metric', $this->calls[0][2]['unitOfMeasurement']);
    }

    public function testAShipmentIsBookedWithItsLabel(): void
    {
        $label = $this->gateway()->ship(Fixtures::shipment());
        self::assertSame('1234567890', $label->trackingNumber);
        self::assertSame('%PDF-1.4 dhl', $label->content);
        self::assertSame('P', $this->calls[0][2]['productCode']);
        self::assertSame('pdf', $this->calls[0][2]['outputImageProperties']['encodingFormat']);
    }

    public function testTrackingReadsTheEventsAndTheStatus(): void
    {
        $tracking = $this->gateway()->track('1234567890');
        self::assertSame(TrackingStatus::DELIVERED, $tracking->status);
        self::assertSame(TrackingStatus::IN_TRANSIT, $tracking->events[0]->status);
        self::assertSame('BERLIN-DEU', $tracking->latest()->location);
    }

    public function testServicePointsNeedTheLocationFindersKey(): void
    {
        $points = $this->gateway()->pickupPoints(Fixtures::shipment()->recipient);
        self::assertSame('8003-4SA3', $points[0]->id);
        self::assertSame(350, $points[0]->distance);
        self::assertSame([['08:30:00', '19:00:00']], $points[0]->openingHours[1]);

        $this->expectException(CarrierException::class);
        $this->gateway(false)->pickupPoints(Fixtures::shipment()->recipient);
    }
}
