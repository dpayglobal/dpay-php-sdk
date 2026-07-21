<?php

declare(strict_types=1);

namespace DPay\Exception;

final class RateLimitException extends ApiErrorException
{
    private ?int $retryAfter;

    private ?int $limit;

    private ?int $remaining;

    public function __construct(
        string $message,
        int $httpStatus,
        ?int $retryAfter = null,
        ?int $limit = null,
        ?int $remaining = null,
        string $rawBody = ''
    ) {
        parent::__construct($message, $httpStatus, null, [], $rawBody);
        $this->retryAfter = $retryAfter;
        $this->limit = $limit;
        $this->remaining = $remaining;
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getRemaining(): ?int
    {
        return $this->remaining;
    }
}
