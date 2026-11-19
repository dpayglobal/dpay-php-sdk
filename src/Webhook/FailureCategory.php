<?php

declare(strict_types=1);

namespace DPay\Webhook;

/** Who can fix a decline (`failure.category`). */
final class FailureCategory
{
    /** The customer (funds, limits, code, bank app) - they can pay again. */
    public const CUSTOMER = 'customer';
    /** Your request or configuration. */
    public const MERCHANT = 'merchant';
    /** Security checks - do not retry automatically. */
    public const RISK = 'risk';
    /** dpay or the provider - temporary errors. */
    public const SYSTEM = 'system';

    public const ALL = [
        self::CUSTOMER,
        self::MERCHANT,
        self::RISK,
        self::SYSTEM,
    ];

    private function __construct()
    {
    }
}
