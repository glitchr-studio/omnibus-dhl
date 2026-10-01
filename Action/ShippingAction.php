<?php

namespace Omnibus\Dhl\Action;

use Omnibus\Action\ActionInterface;
use Omnibus\Action\ApiAwareInterface;
use Omnibus\Action\ApiAwareTrait;
use Omnibus\Dhl\Api;
use Omnibus\Dhl\Mapping;
use Omnibus\Exception\CarrierException;
use Omnibus\Model\Label;
use Omnibus\Request\Request;
use Omnibus\Request\Shipping;

/** POST /shipments: the shipment booked with the product chosen (service: a product code, "P" by default), its label as PDF. */
final class ShippingAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Shipping;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Shipping);
        $s = $request->shipment;
        $customs = (bool) $s->option('customs', strtoupper($s->sender->country) !== strtoupper($s->recipient->country));
        $data = $this->api->call('POST', '/shipments', [
            'plannedShippingDateAndTime' => ($s->shippingDate ?? new \DateTimeImmutable('tomorrow 10:00'))->format('Y-m-d\TH:i:s \G\M\TP'),
            'pickup' => ['isRequested' => false],
            'productCode' => $s->service ?? 'P',
            'accounts' => [['typeCode' => 'shipper', 'number' => $this->api->accountNumber]],
            'customerReferences' => $s->reference ? [['value' => mb_substr($s->reference, 0, 35)]] : [],
            'customerDetails' => ['shipperDetails' => Mapping::details($s->sender), 'receiverDetails' => Mapping::details($s->recipient)],
            'content' => array_filter([
                'packages' => array_map([Mapping::class, 'package'], $s->parcels),
                'isCustomsDeclarable' => $customs,
                'description' => $s->option('description', 'Parcel'),
                'incoterm' => $customs ? $s->option('incoterm', 'DAP') : null,
                'unitOfMeasurement' => 'metric',
                'declaredValue' => $customs ? array_sum(array_map(static fn ($p) => ($p->value ?? 0) / 100, $s->parcels)) : null,
                'declaredValueCurrency' => $customs ? $s->parcels[0]->currency : null,
            ], static fn ($v) => null !== $v),
            'outputImageProperties' => ['encodingFormat' => 'pdf', 'imageOptions' => [['typeCode' => 'label', 'templateName' => 'ECOM26_84_001', 'isRequested' => true]]],
        ]);
        $number = (string) ($data['shipmentTrackingNumber'] ?? '');
        if ('' === $number) {
            throw new CarrierException('dhl', 'DHL booked no shipment.');
        }
        $content = null;
        foreach ($data['documents'] ?? [] as $document) {
            if ('label' === ($document['typeCode'] ?? null) && isset($document['content'])) {
                $content = base64_decode((string) $document['content']);
            }
        }
        $request->setResult(new Label('dhl', $number, $content, Label::PDF, $data['trackingUrl'] ?? null, 'https://www.dhl.com/global-en/home/tracking.html?tracking-id='.rawurlencode($number)));
    }
}
