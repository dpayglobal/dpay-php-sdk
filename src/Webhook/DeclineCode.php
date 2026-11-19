<?php

declare(strict_types=1);

namespace DPay\Webhook;

/**
 * dpay decline codes (`failure.code` of webhook objects). React to a decline by Failure::getCategory() and
 * Failure::isRetryable(); new codes may appear - treat an unknown code like `generic_decline`.
 */
final class DeclineCode
{
    public const INSUFFICIENT_FUNDS = 'insufficient_funds';
    public const LIMIT_EXCEEDED = 'limit_exceeded';
    public const CUSTOMER_DECLINED = 'customer_declined';
    public const CUSTOMER_TIMEOUT = 'customer_timeout';
    public const INVALID_CODE = 'invalid_code';
    public const EXPIRED = 'expired';
    public const APP_UPDATE_REQUIRED = 'app_update_required';
    public const ISSUER_DECLINED = 'issuer_declined';
    public const CARD_INVALID = 'card_invalid';
    public const AUTHENTICATION_REQUIRED = 'authentication_required';
    public const ALIAS_INVALID = 'alias_invalid';
    public const UNSUPPORTED_BY_BANK = 'unsupported_by_bank';
    public const GENERIC_DECLINE = 'generic_decline';
    public const SECURITY_DECLINED = 'security_declined';
    public const BLOCKED = 'blocked';
    public const RECURRING_CONDITIONS_NOT_MET = 'recurring_conditions_not_met';
    public const INVALID_REQUEST = 'invalid_request';
    public const REFUND_WINDOW_EXPIRED = 'refund_window_expired';
    public const REFUND_AMOUNT_EXCEEDED = 'refund_amount_exceeded';
    public const PAYOUT_FAILED = 'payout_failed';
    public const REFUND_FAILED = 'refund_failed';
    public const PROCESSING_ERROR = 'processing_error';

    public const ALL = [
        self::INSUFFICIENT_FUNDS,
        self::LIMIT_EXCEEDED,
        self::CUSTOMER_DECLINED,
        self::CUSTOMER_TIMEOUT,
        self::INVALID_CODE,
        self::EXPIRED,
        self::APP_UPDATE_REQUIRED,
        self::ISSUER_DECLINED,
        self::CARD_INVALID,
        self::AUTHENTICATION_REQUIRED,
        self::ALIAS_INVALID,
        self::UNSUPPORTED_BY_BANK,
        self::GENERIC_DECLINE,
        self::SECURITY_DECLINED,
        self::BLOCKED,
        self::RECURRING_CONDITIONS_NOT_MET,
        self::INVALID_REQUEST,
        self::REFUND_WINDOW_EXPIRED,
        self::REFUND_AMOUNT_EXCEEDED,
        self::PAYOUT_FAILED,
        self::REFUND_FAILED,
        self::PROCESSING_ERROR,
    ];

    private function __construct()
    {
    }
}
