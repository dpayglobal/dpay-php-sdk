<?php

declare(strict_types=1);

namespace DPay\Internal;

use InvalidArgumentException;

final class BaseUrls
{
    public const API_PAYMENTS = 'api_payments';
    public const PANEL = 'panel';
    public const GATEWAY = 'gateway';

    private const DEFAULTS = [
        self::API_PAYMENTS => 'https://api-payments.dpay.pl',
        self::PANEL => 'https://panel.dpay.pl',
        self::GATEWAY => 'https://secure.dpay.pl',
    ];

    /** @var array<string, string> */
    private array $urls;

    /**
     * @param array<string, string> $overrides
     */
    public function __construct(array $overrides = [])
    {
        foreach ($overrides as $host => $url) {
            if (!isset(self::DEFAULTS[$host])) {
                throw new InvalidArgumentException(sprintf('Unknown base URL key "%s"', $host));
            }
        }
        $this->urls = array_merge(
            self::DEFAULTS,
            array_map(static fn (string $url): string => rtrim($url, '/'), $overrides)
        );
    }

    public function resolve(string $host): string
    {
        if (!isset($this->urls[$host])) {
            throw new InvalidArgumentException(sprintf('Unknown API host "%s"', $host));
        }

        return $this->urls[$host];
    }
}
