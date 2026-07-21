<?php

declare(strict_types=1);

namespace DPay\Tests\Support;

use DPay\Http\ApiRequest;
use DPay\Http\ApiResponse;
use DPay\Http\HttpClientInterface;
use LogicException;

final class MockHttpClient implements HttpClientInterface
{
    /** @var array<int, ApiResponse> */
    private array $queue = [];

    /** @var array<int, ApiRequest> */
    private array $requests = [];

    public function queue(ApiResponse $response): void
    {
        $this->queue[] = $response;
    }

    /**
     * @param array<mixed> $body
     * @param array<string, string> $headers
     */
    public function queueJson(int $status, array $body, array $headers = []): void
    {
        $encoded = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new LogicException('Unable to encode fixture body');
        }
        $this->queue(new ApiResponse($status, $headers + ['content-type' => 'application/json'], $encoded));
    }

    /**
     * @param array<string, string> $headers
     */
    public function queueText(int $status, string $body, array $headers = []): void
    {
        $this->queue(new ApiResponse($status, $headers, $body));
    }

    public function request(ApiRequest $request): ApiResponse
    {
        $this->requests[] = $request;
        $response = array_shift($this->queue);
        if ($response === null) {
            throw new LogicException('MockHttpClient queue is empty');
        }

        return $response;
    }

    /**
     * @return array<mixed>
     */
    public function lastRequestBody(): array
    {
        $decoded = json_decode((string) $this->lastRequest()->getBody(), true);
        if (!is_array($decoded)) {
            throw new LogicException('Last request has no JSON object body');
        }

        return $decoded;
    }

    public function lastRequest(): ApiRequest
    {
        $last = end($this->requests);
        if ($last === false) {
            throw new LogicException('No requests recorded');
        }

        return $last;
    }

    /**
     * @return array<int, ApiRequest>
     */
    public function requests(): array
    {
        return $this->requests;
    }
}
