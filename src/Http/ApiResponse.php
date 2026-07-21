<?php

declare(strict_types=1);

namespace DPay\Http;

final class ApiResponse
{
    private int $status;

    /** @var array<string, string> */
    private array $headers;

    private string $body;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(int $status, array $headers, string $body)
    {
        $this->status = $status;
        $this->headers = [];
        foreach ($headers as $name => $value) {
            $this->headers[strtolower($name)] = $value;
        }
        $this->body = $body;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @return array<mixed>|null
     */
    public function decodeJson(): ?array
    {
        $decoded = json_decode($this->body, true);

        return is_array($decoded) ? $decoded : null;
    }
}
