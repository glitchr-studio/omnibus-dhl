<?php

namespace Omnibus\Dhl;

use Omnibus\Model\Address;
use Omnibus\Model\Parcel;
use Omnibus\Model\TrackingStatus;

/** MyDHL's shapes for ours. */
final class Mapping
{
    public static function details(Address $a): array
    {
        return [
            'postalAddress' => array_filter(['postalCode' => $a->postcode, 'cityName' => $a->city, 'countryCode' => strtoupper($a->country), 'addressLine1' => $a->line(0) ?: null, 'addressLine2' => $a->line(1) ?: null]),
            'contactInformation' => array_filter(['email' => $a->email, 'phone' => $a->phone ?? '0', 'companyName' => $a->company ?? $a->name, 'fullName' => $a->name]),
        ];
    }

    public static function package(Parcel $p): array
    {
        return array_filter([
            'weight' => round(max(0.1, $p->weight / 1000), 2),
            'dimensions' => $p->length && $p->width && $p->height ? ['length' => $p->length, 'width' => $p->width, 'height' => $p->height] : null,
            'customerReferences' => $p->reference ? [['value' => mb_substr($p->reference, 0, 35)]] : null,
        ]);
    }

    public static function status(?string $code): TrackingStatus
    {
        return match (strtoupper((string) $code)) {
            'OK', 'DD' => TrackingStatus::DELIVERED,
            'WC' => TrackingStatus::OUT_FOR_DELIVERY,
            'PU', 'AR', 'DF', 'PL', 'AF', 'TR', 'CR', 'CC' => TrackingStatus::IN_TRANSIT,
            'RD', 'ND', 'OH', 'CA', 'BA', 'UD', 'MC' => TrackingStatus::EXCEPTION,
            'RT' => TrackingStatus::RETURNED,
            'SA', 'PE' => TrackingStatus::PENDING,
            default => TrackingStatus::UNKNOWN,
        };
    }
}
