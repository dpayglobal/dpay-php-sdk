<?php

declare(strict_types=1);

namespace DPay\Refund;

use DPay\Internal\ApiRequestor;
use DPay\Internal\BaseUrls;
use DPay\Money;
use DPay\Webhook\WebhookEventType;
use DPay\Webhook\WebhookTarget;

final class RefundService
{
    private ApiRequestor $api;

    public function __construct(ApiRequestor $api)
    {
        $this->api = $api;
    }

    /**
     * Orders a refund (partial with an amount, the rest of the payment without). A 200 response means the refund
     * was accepted - its outcome comes as a `refund.succeeded` / `refund.failed` event. The optional webhook target
     * receives the events of this refund and is part of the checksum.
     */
    public function create(string $transactionId, ?Money $amount = null, ?string $reason = null, ?WebhookTarget $webhook = null): Refund
    {
        if ($webhook !== null) {
            $webhook->assertEventsAllowed(WebhookEventType::REFUND, 'a refund');
        }
        $body = $this->signedBody($transactionId, $amount, $reason, $webhook);

        $data = $this->api->postJson(BaseUrls::PANEL, '/api/v1/pbl/refund', $body);

        return Refund::fromArray($data);
    }

    public function checkAvailability(string $transactionId, ?Money $amount = null, ?string $reason = null): RefundAvailability
    {
        $body = $this->signedBody($transactionId, $amount, $reason);

        $response = $this->api->sendRaw('POST', BaseUrls::PANEL, '/api/v1/pbl/check-refund-availability', $body);
        $data = $response->decodeJson();

        $status = $response->getStatus();
        if (is_array($data) && array_key_exists('refund', $data) && $this->isAvailabilityOutcome($status, $data)) {
            return RefundAvailability::fromArray($data, $status);
        }

        throw $this->api->mapError($response);
    }

    /**
     * @param array<mixed> $data
     */
    private function isAvailabilityOutcome(int $status, array $data): bool
    {
        if (in_array($status, [200, 400, 402, 406, 409, 410, 411], true)) {
            return true;
        }

        return $status === 401 && ($data['message'] ?? null) !== 'Unauthorized request';
    }

    /**
     * @return array<string, mixed>
     */
    private function signedBody(string $transactionId, ?Money $amount, ?string $reason, ?WebhookTarget $webhook = null): array
    {
        $body = [
            'service' => $this->api->config()->service(),
            'transaction_id' => $transactionId,
        ];
        if ($amount !== null) {
            $body['value'] = $amount->toDecimal();
        }
        if ($reason !== null) {
            $body['reason'] = $reason;
        }
        if ($webhook !== null) {
            $body['webhook'] = $webhook->toArray();
        }
        $body['checksum'] = $this->api->checksum()->orderedBody($body);

        return $body;
    }
}
