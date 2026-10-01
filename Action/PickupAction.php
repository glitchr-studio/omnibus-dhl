<?php

namespace Omnibus\Dhl\Action;

use Omnibus\Action\ActionInterface;
use Omnibus\Action\ApiAwareInterface;
use Omnibus\Action\ApiAwareTrait;
use Omnibus\Dhl\Api;
use Omnibus\Model\Address;
use Omnibus\Model\PickupPoint;
use Omnibus\Request\Pickup;
use Omnibus\Request\Request;

/** DHL's Location Finder: service points near an address (its own API key). */
final class PickupAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Pickup;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Pickup);
        $near = $request->near;
        $data = $this->api->locations(array_filter(['countryCode' => strtoupper($near->country), 'postalCode' => $near->postcode, 'addressLocality' => $near->city, 'streetAddress' => $near->line(0) ?: null, 'providerType' => 'express', 'limit' => min(50, max(1, $request->limit))]));
        $points = [];
        foreach ($data['locations'] ?? [] as $location) {
            $address = $location['place']['address'] ?? [];
            $hours = [];
            foreach ($location['openingHours'] ?? [] as $slot) {
                $n = array_search((string) ($slot['dayOfWeek'] ?? ''), ['', 'http://schema.org/Monday', 'http://schema.org/Tuesday', 'http://schema.org/Wednesday', 'http://schema.org/Thursday', 'http://schema.org/Friday', 'http://schema.org/Saturday', 'http://schema.org/Sunday'], true);
                if ($n) {
                    $hours[$n][] = [$slot['opens'] ?? '', $slot['closes'] ?? ''];
                }
            }
            $points[] = new PickupPoint('dhl', (string) ($location['location']['ids'][0]['locationId'] ?? $location['url'] ?? ''), (string) ($location['name'] ?? 'DHL ServicePoint'),
                new Address((string) ($location['name'] ?? ''), array_values(array_filter([$address['streetAddress'] ?? null])), (string) ($address['postalCode'] ?? ''), (string) ($address['addressLocality'] ?? ''), (string) ($address['countryCode'] ?? $near->country)),
                isset($location['place']['geo']['latitude']) ? (float) $location['place']['geo']['latitude'] : null, isset($location['place']['geo']['longitude']) ? (float) $location['place']['geo']['longitude'] : null,
                $hours, isset($location['distance']) ? (int) $location['distance'] : null);
        }
        $request->setResult(\array_slice($points, 0, $request->limit));
    }
}
