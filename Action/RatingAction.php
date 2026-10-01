<?php

namespace Omnibus\Dhl\Action;

use Omnibus\Action\ActionInterface;
use Omnibus\Action\ApiAwareInterface;
use Omnibus\Action\ApiAwareTrait;
use Omnibus\Dhl\Api;
use Omnibus\Dhl\Mapping;
use Omnibus\Model\Rate;
use Omnibus\Request\Rating;
use Omnibus\Request\Request;

/** POST /rates: every product DHL Express offers for the shipment, priced with the account's rates. */
final class RatingAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Rating;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Rating);
        $s = $request->shipment;
        $data = $this->api->call('POST', '/rates', [
            'customerDetails' => ['shipperDetails' => Mapping::details($s->sender)['postalAddress'], 'receiverDetails' => Mapping::details($s->recipient)['postalAddress']],
            'accounts' => [['typeCode' => 'shipper', 'number' => $this->api->accountNumber]],
            'plannedShippingDateAndTime' => ($s->shippingDate ?? new \DateTimeImmutable('tomorrow 10:00'))->format('Y-m-d\TH:i:s \G\M\TP'),
            'unitOfMeasurement' => 'metric',
            'isCustomsDeclarable' => (bool) $s->option('customs', strtoupper($s->sender->country) !== strtoupper($s->recipient->country)),
            'packages' => array_map(static fn ($p) => array_intersect_key(Mapping::package($p), ['weight' => 1, 'dimensions' => 1]), $s->parcels),
        ]);
        $rates = [];
        foreach ($data['products'] ?? [] as $product) {
            $price = null;
            foreach ($product['totalPrice'] ?? [] as $total) {
                if ('BILLC' === ($total['currencyType'] ?? null) || null === $price) {
                    $price = $total;
                }
            }
            $rates[] = new Rate('dhl', (string) ($product['productCode'] ?? ''), (string) ($product['productName'] ?? 'DHL Express'), (int) round(((float) ($price['price'] ?? 0)) * 100), strtoupper((string) ($price['priceCurrency'] ?? 'EUR')), isset($product['deliveryCapabilities']['totalTransitDays']) ? (int) $product['deliveryCapabilities']['totalTransitDays'] : null);
        }
        usort($rates, static fn (Rate $a, Rate $b) => $a->amount <=> $b->amount);
        $request->setResult($rates);
    }
}
