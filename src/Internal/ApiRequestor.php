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
use InvalidArgumentException;
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
     * Your service name for a panel (`/api/v1/pbl/*`) request body, trimmed like the API trims it (ServerTrim).
     */
    public function panelService(): string
    {
        return ServerTrim::required($this->config->service(), 'service');
    }

    /**
     * A panel (`/api/v1/pbl/*`) body ready to send: every string trimmed the way the API trims it before it verifies
     * the checksum (ServerTrim), then signed with the ordered-body checksum over the values as sent.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function signPanelBody(array $body): array
    {
        /** @var array<string, mixed> $trimmed */
        $trimmed = ServerTrim::trimStrings($body);
        $trimmed['checksum'] = $this->checksum->orderedBody($trimmed);

        return $trimmed;
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers extra request headers (e.g. `DigitalPayments-PSP-Message-ID`)
     * @return array<mixed>
     */
    public function postJson(string $host, string $path, array $body, array $headers = []): array
    {
        return $this->decodeOrFail($this->send('POST', $host, $path, $body, $headers));
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
     * @param array<string, string> $headers
     */
    public function send(string $method, string $host, string $path, ?array $body, array $headers = []): ApiResponse
    {
        $response = $this->sendRaw($method, $host, $path, $body, $headers);
        if ($response->getStatus() >= 400) {
            throw $this->errorMapper->map($response);
        }

        return $response;
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, string> $extraHeaders sent after the SDK headers; names and values must not contain
     *                                            line breaks
     */
    public function sendRaw(string $method, string $host, string $path, ?array $body, array $extraHeaders = []): ApiResponse
    {
        $url = $this->baseUrls->resolve($host) . $path;
        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => 'dpay-php-sdk/' . Version::SDK . ' php/' . PHP_VERSION,
        ];
        foreach ($extraHeaders as $name => $value) {
            if (preg_match('/^[A-Za-z0-9-]+\z/', $name) !== 1 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
                throw new InvalidArgumentException(sprintf('Invalid request header "%s"', $name));
            }
            $headers[$name] = $value;
        }

        $encoded = null;
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            $previousPrecision = ini_set('serialize_precision', '-1');
            $encoded = json_encode(
                $body === [] ? new stdClass() : $body,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
            if ($previousPrecision !== false) {
                ini_set('serialize_precision', $previousPrecision);
            }
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
