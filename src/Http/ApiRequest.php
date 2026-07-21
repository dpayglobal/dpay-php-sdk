<?php

declare(strict_types=1);

namespace DPay\Http;

final class ApiRequest
{
    private string $method;

    private string $url;

    /** @var array<string, string> */
    private array $headers;

    private ?string $body;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(string $method, string $url, array $headers = [], ?string $body = null)
    {
        $this->method = $method;
        $this->url = $url;
        $this->headers = $headers;
        $this->body = $body;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }
}
