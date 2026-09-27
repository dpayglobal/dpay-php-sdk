<?php

declare(strict_types=1);

namespace DPay\Recurring;

use DPay\Internal\ApiRequestor;
use DPay\Internal\BaseUrls;

/**
 * Recurring payments shared by payment methods (today BLIK). Registration and charges go through
 * `payments->register()` with `withRecurringRegistration()` and `withRecurringAlias()`.
 */
final class RecurringService
{
    private ApiRequestor $api;

    public function __construct(ApiRequestor $api)
    {
        $this->api = $api;
    }

    /**
     * Current status and terms of the recurring payment registered for this service.
     */
    public function status(string $alias): RecurringStatus
    {
        $service = $this->api->config()->service();
        $body = [
            'service' => $service,
            'alias' => $alias,
            'checksum' => $this->api->checksum()->secretSecond($service, [$alias]),
        ];

        $data = $this->api->postJson(BaseUrls::API_PAYMENTS, '/api/v1_0/payments/recurring/status', $body);

        return RecurringStatus::fromArray(is_array($data['data'] ?? null) ? $data['data'] : []);
    }

    /**
     * Retries a declined recurring charge with the same BLIK transaction (up to 3 times within 5 minutes, only after
     * declines the customer can fix, e.g. INSUFFICIENT_FUNDS). Not retryable: InvalidRequestException with the reason
     * in getFieldErrors()['retry'] (e.g. DECLINE_NOT_RETRYABLE, RETRY_LIMIT_REACHED).
     *
     * @param string $transactionId transactionId of the declined charge
     */
    public function retry(string $transactionId): RecurringRetryResult
    {
        $service = $this->api->config()->service();
        $body = [
            'service' => $service,
            'transaction_id' => $transactionId,
            'checksum' => $this->api->checksum()->secretSecond($service, [$transactionId]),
        ];

        $data = $this->api->postJson(BaseUrls::API_PAYMENTS, '/api/v1_0/payments/recurring/retry', $body);

        return RecurringRetryResult::fromArray(is_array($data['data'] ?? null) ? $data['data'] : []);
    }

    /**
     * Cancels the recurring payment (for BLIK: unregisters the alias at BLIK). Later charges with the alias are
     * rejected. The checksum ends with the operation name, so a status checksum cannot cancel.
     *
     * @return string the new status (UNREGISTERED)
     */
    public function cancel(string $alias, ?string $reason = null): string
    {
        $service = $this->api->config()->service();
        $body = [
            'service' => $service,
            'alias' => $alias,
        ];
        if ($reason !== null) {
            $body['reason'] = $reason;
        }
        $body['checksum'] = $this->api->checksum()->secretSecond($service, [$alias, 'cancel']);

        $data = $this->api->postJson(BaseUrls::API_PAYMENTS, '/api/v1_0/payments/recurring/cancel', $body);
        $status = is_array($data['data'] ?? null) ? ($data['data']['status'] ?? null) : null;

        return is_string($status) ? $status : RecurringStatus::UNREGISTERED;
    }
}
