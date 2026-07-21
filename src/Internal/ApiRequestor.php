<?php

declare(strict_types=1);

namespace DPay\Internal;

use DPay\Config;
use DPay\Exception\ApiErrorException;
use DPay\Exception\ApiServerException;
use DPay\Exception\TransportException;
use DPay\Http\ApiRequest;
use DPay\Http\ApiResponse;
use DPay\Http\HttpClientInterface;
use DPay\Version;
use stdClass;

final class ApiRequestor
{
    private Config $config;

    private HttpClientInterface $httpClient;

    private ChecksumCalculator $checksum;

    private BaseUrls $baseUrls;

    private ErrorMapper $errorMapper;

    public function __construct(Config $config, HttpClientInterface $httpClient)
    {
        $this->config = $config;
        $this->httpClient = $httpClient;
        $this->checksum = new ChecksumCalculator($config->secretHash());
        $this->baseUrls = $config->baseUrls();
        $this->errorMapper = new ErrorMapper();
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function checksum(): ChecksumCalculator
    {
        return $this->checksum;
    }

    /**
     * @param array<string, mixed> $body
     * @return array<mixed>
     */
    public function postJson(string $host, string $path, array $body): array
    {
        return $this->decodeOrFail($this->send('POST', $host, $path, $body));
    }

    /**
     * @return array<mixed>
     */
    public function getJson(string $host, string $path): array
    {
        return $this->decodeOrFail($this->send('GET', $host, $path, null));
    }

    public function getText(string $host, string $path): string
    {
        return $this->send('GET', $host, $path, null)->getBody();
    }

    /**
     * @param array<string, mixed>|null $body
     */
    public function send(string $method, string $host, string $path, ?array $body): ApiResponse
    {
        $response = $this->sendRaw($method, $host, $path, $body);
        if ($response->getStatus() >= 400) {
            throw $this->errorMapper->map($response);
        }

        return $response;
    }

    /**
     * @param array<string, mixed>|null $body
     */
    public function sendRaw(string $method, string $host, string $path, ?array $body): ApiResponse
    {
        $url = $this->baseUrls->resolve($host) . $path;
        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => 'dpay-php-sdk/' . Version::SDK . ' php/' . PHP_VERSION,
        ];

        $encoded = null;
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            $encoded = json_encode(
                $body === [] ? new stdClass() : $body,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
            if ($encoded === false) {
                throw new TransportException('Unable to encode request body as JSON');
            }
        }

        return $this->httpClient->request(new ApiRequest($method, $url, $headers, $encoded));
    }

    public function mapError(ApiResponse $response): ApiErrorException
    {
        return $this->errorMapper->map($response);
    }

    /**
     * @return array<mixed>
     */
    private function decodeOrFail(ApiResponse $response): array
    {
        $data = $response->decodeJson();
        if ($data === null) {
            throw new ApiServerException(
                'Invalid JSON in API response',
                $response->getStatus(),
                null,
                [],
                $response->getBody()
            );
        }

        return $data;
    }
}
