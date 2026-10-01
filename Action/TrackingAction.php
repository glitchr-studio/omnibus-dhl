<?php

namespace Omnibus\Dhl\Action;

use Omnibus\Action\ActionInterface;
use Omnibus\Action\ApiAwareInterface;
use Omnibus\Action\ApiAwareTrait;
use Omnibus\Dhl\Api;
use Omnibus\Dhl\Mapping;
use Omnibus\Model\Tracking as TrackingModel;
use Omnibus\Model\TrackingEvent;
use Omnibus\Model\TrackingStatus;
use Omnibus\Request\Request;
use Omnibus\Request\Tracking;

/** GET /shipments/{id}/tracking: the shipment's events, oldest first. */
final class TrackingAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Tracking;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Tracking);
        $data = $this->api->call('GET', '/shipments/'.rawurlencode($request->trackingNumber).'/tracking', null, ['trackingView' => 'all-checkpoints', 'levelOfDetail' => 'all']);
        $shipment = $data['shipments'][0] ?? [];
        $events = [];
        foreach ($shipment['events'] ?? [] as $event) {
            $events[] = new TrackingEvent(new \DateTimeImmutable(($event['date'] ?? '1970-01-01').'T'.($event['time'] ?? '00:00:00')), Mapping::status($event['typeCode'] ?? null), (string) ($event['description'] ?? ''), $event['serviceArea'][0]['description'] ?? null, $event['typeCode'] ?? null);
        }
        usort($events, static fn (TrackingEvent $a, TrackingEvent $b) => $a->at <=> $b->at);
        $status = $events ? $events[array_key_last($events)]->status : TrackingStatus::UNKNOWN;
        if ('Delivered' === ($shipment['status'] ?? null)) {
            $status = TrackingStatus::DELIVERED;
        }
        $request->setResult(new TrackingModel('dhl', $request->trackingNumber, $status, $events));
    }
}
