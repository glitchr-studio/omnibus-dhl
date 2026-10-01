<?php

namespace Omnibus\Dhl;

use Omnibus\Exception\CarrierException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** DHL Express's MyDHL API (basic auth, API key and secret), and DHL's location finder (its own key). */
final class Api
{
    public const LIVE = 'https://express.api.dhl.com/mydhlapi';
    public const TEST = 'https://express.api.dhl.com/mydhlapi/test';
    public const LOCATIONS = 'https://api.dhl.com/location-finder/v1/find-by-address';

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $apiKey,
        private readonly string $apiSecret,
        public readonly string $accountNumber,
        public readonly bool $sandbox = false,
        public readonly ?string $locationApiKey = null,
        private readonly int $timeout = 20,
    ) {
    }

    /** @return array<string, mixed> */
    public function call(string $method, string $path, ?array $body = null, array $query = []): array
    {
        try {
            $response = $this->http->request($method, ($this->sandbox ? self::TEST : self::LIVE).$path, [
                'auth_basic' => [$this->apiKey, $this->apiSecret],
                'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json', 'Message-Reference' => bin2hex(random_bytes(16))],
                'query' => $query,
                'body' => null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR),
                'timeout' => $this->timeout,
            ]);
            $status = $response->getStatusCode();
            $data = json_decode($response->getContent(false), true);
        } catch (HttpExceptionInterface|\JsonException $e) {
            throw new CarrierException('dhl', 'DHL request failed: '.$e->getMessage(), null, $e);
        }
        if (!\is_array($data)) {
            throw new CarrierException('dhl', sprintf('DHL answered HTTP %d with a body that is not JSON.', $status));
        }
        if ($status >= 400) {
            throw new CarrierException('dhl', (string) ($data['detail'] ?? $data['message'] ?? $data['title'] ?? sprintf('HTTP %d', $status)), isset($data['status']) ? (string) $data['status'] : null);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    public function locations(array $query): array
    {
        if (null === $this->locationApiKey || '' === $this->locationApiKey) {
            throw new CarrierException('dhl', 'Service points need the location finder\'s key (option location_api_key).');
        }
        try {
            $response = $this->http->request('GET', self::LOCATIONS, ['headers' => ['DHL-API-Key' => $this->locationApiKey], 'query' => $query, 'timeout' => $this->timeout]);
            $data = $response->toArray(false);
        } catch (HttpExceptionInterface $e) {
            throw new CarrierException('dhl', 'DHL location finder failed: '.$e->getMessage(), null, $e);
        }

        return $data;
    }
}
