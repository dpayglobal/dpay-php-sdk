<?php

declare(strict_types=1);

namespace DPay\Internal;

final class ChecksumCalculator
{
    private string $secretHash;

    public function __construct(string $secretHash)
    {
        $this->secretHash = $secretHash;
    }

    /**
     * @param array<int, int|float|string> $fields
     */
    public function secretSecond(string $service, array $fields): string
    {
        $parts = array_merge([$service, $this->secretHash], array_map('strval', $fields));

        return hash('sha256', implode('|', $parts));
    }

    /**
     * @param array<int, int|float|string> $values
     */
    public function orderedBody(array $values): string
    {
        return hash('sha256', implode('|', array_map('strval', $values)) . '|' . $this->secretHash);
    }
}
