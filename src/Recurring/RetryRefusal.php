<?php

declare(strict_types=1);

namespace DPay\Recurring;

/**
 * Why recurring->retry() was refused (HTTP 400, nothing sent to BLIK): the InvalidRequestException field error
 * `retry` - getFieldErrors()['retry'][0]. With DECLINE_NOT_RETRYABLE the decline code is in
 * getFieldErrors()['decline_reason'][0].
 */
final class RetryRefusal
{
    public const PAYMENT_NOT_DECLINED = 'PAYMENT_NOT_DECLINED';
    public const DECLINE_NOT_RETRYABLE = 'DECLINE_NOT_RETRYABLE';
    public const RETRY_LIMIT_REACHED = 'RETRY_LIMIT_REACHED';
    public const RETRY_WINDOW_EXPIRED = 'RETRY_WINDOW_EXPIRED';
    public const ALIAS_NOT_AVAILABLE = 'ALIAS_NOT_AVAILABLE';
    public const RECURRING_NOT_ENABLED = 'RECURRING_NOT_ENABLED';
    public const PAYMENT_NOT_FOUND = 'PAYMENT_NOT_FOUND';
    public const SERVICE_UNAVAILABLE = 'Service unavailable';

    public const ALL = [
        self::PAYMENT_NOT_DECLINED,
        self::DECLINE_NOT_RETRYABLE,
        self::RETRY_LIMIT_REACHED,
        self::RETRY_WINDOW_EXPIRED,
        self::ALIAS_NOT_AVAILABLE,
        self::RECURRING_NOT_ENABLED,
        self::PAYMENT_NOT_FOUND,
        self::SERVICE_UNAVAILABLE,
    ];

    private function __construct()
    {
    }
}
