<?php

declare(strict_types=1);

namespace DPay;

use DPay\Http\HttpClientInterface;
use DPay\Internal\BaseUrls;
use InvalidArgumentException;

final class Config
{
    private const KNOWN_OPTIONS = ['service', 'secret_hash', 'timeout', 'http_client', 'base_urls'];

    private string $service;

    private string $secretHash;

    private int $timeout;

    private ?HttpClientInterface $httpClient;

    private BaseUrls $baseUrls;

    private function __construct(
        string $service,
        string $secretHash,
        int $timeout,
        ?HttpClientInterface $httpClient,
        BaseUrls $baseUrls
    ) {
        $this->service = $service;
        $this->secretHash = $secretHash;
        $this->timeout = $timeout;
        $this->httpClient = $httpClient;
        $this->baseUrls = $baseUrls;
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function fromArray(array $options): self
    {
        foreach (array_keys($options) as $key) {
            if (!in_array($key, self::KNOWN_OPTIONS, true)) {
                throw new InvalidArgumentException(sprintf('Unknown option "%s"', $key));
            }
        }

        $service = $options['service'] ?? null;
        if (!is_string($service) || $service === '') {
            throw new InvalidArgumentException('Option "service" is required and must be a non-empty string');
        }

        $secretHash = $options['secret_hash'] ?? null;
        if (!is_string($secretHash) || $secretHash === '') {
            throw new InvalidArgumentException('Option "secret_hash" is required and must be a non-empty string');
        }

        $timeout = $options['timeout'] ?? 30;
        if (!is_int($timeout) || $timeout < 1) {
            throw new InvalidArgumentException('Option "timeout" must be a positive integer');
        }

        $httpClient = $options['http_client'] ?? null;
        if ($httpClient !== null && !$httpClient instanceof HttpClientInterface) {
            throw new InvalidArgumentException('Option "http_client" must implement HttpClientInterface');
        }

        $baseUrlOverrides = $options['base_urls'] ?? [];
        if (!is_array($baseUrlOverrides)) {
            throw new InvalidArgumentException('Option "base_urls" must be an array');
        }
        $normalizedBaseUrls = [];
        foreach ($baseUrlOverrides as $host => $url) {
            if (!is_string($url) || $url === '') {
                throw new InvalidArgumentException('Base URLs must be non-empty strings');
            }
            $normalizedBaseUrls[(string) $host] = $url;
        }

        return new self($service, $secretHash, $timeout, $httpClient, new BaseUrls($normalizedBaseUrls));
    }

    public function service(): string
    {
        return $this->service;
    }

    public function secretHash(): string
    {
        return $this->secretHash;
    }

    public function timeout(): int
    {
        return $this->timeout;
    }

    public function httpClient(): ?HttpClientInterface
    {
        return $this->httpClient;
    }

    public function baseUrls(): BaseUrls
    {
        return $this->baseUrls;
    }
}
