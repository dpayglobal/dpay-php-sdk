<?php

declare(strict_types=1);

namespace DPay\Refund;

use DPay\Internal\ApiRequestor;
use DPay\Internal\BaseUrls;
use DPay\Internal\ServerTrim;
use DPay\Money;
use DPay\Webhook\WebhookEventType;
use DPay\Webhook\WebhookTarget;
use InvalidArgumentException;

final class RefundService
{
    /** Request header with your own refund identifier; it sets the refund transfer title. */
    public const MESSAGE_ID_HEADER = 'DigitalPayments-PSP-Message-ID';

    private ApiRequestor $api;

    public function __construct(ApiRequestor $api)
    {
        $this->api = $api;
    }

    /**
     * Orders a refund (partial with an amount, the rest of the payment without). A 200 response means the refund
     * was accepted - its outcome comes as a `refund.succeeded` / `refund.failed` event. The optional webhook target
     * receives the events of this refund and is part of the checksum. The transaction ID, reason and webhook URL are
     * sent and signed trimmed, as the API trims them before it verifies the checksum (ServerTrim); a reason that is
     * blank after trimming is sent as "".
     *
     * @param string|null $messageId your own refund identifier, sent in the `DigitalPayments-PSP-Message-ID` header
     *                               (not part of the checksum): the refund transfer title becomes
     *                               `dpay.pl zwrot {messageId}` and Refund::getMessage() returns it. Printable ASCII;
     *                               the API keeps the first 24 characters.
     */
    public function create(
        string $transactionId,
        ?Money $amount = null,
        ?string $reason = null,
        ?WebhookTarget $webhook = null,
        ?string $messageId = null
    ): Refund {
        if ($webhook !== null) {
            $webhook->assertEventsAllowed(WebhookEventType::REFUND, 'a refund');
        }
        $headers = [];
        if ($messageId !== null) {
            if (preg_match('/^[\x20-\x7E]+\z/', $messageId) !== 1 || trim($messageId) === '') {
                throw new InvalidArgumentException('Refund message ID must be non-empty printable ASCII');
            }
            $headers[self::MESSAGE_ID_HEADER] = $messageId;
        }
        $body = $this->signedBody($transactionId, $amount, $reason, $webhook);

        $data = $this->api->postJson(BaseUrls::PANEL, '/api/v1/pbl/refund', $body, $headers);

        return Refund::fromArray($data);
    }

    /**
     * Checks whether the refund is possible. The answer is decided by the body, not by a list of statuses: HTTP 200
     * and every 4xx whose JSON body carries the `refund` key are returned as RefundAvailability (getHttpStatus()
     * tells the reason apart) - today the business refusals 400, 402, 404 (transaction does not exist), 406, 409, 410,
     * 411, 423 and a business 401. Thrown: 401 "Unauthorized request" (invalid checksum), every response without the
     * `refund` key - which is how the API answers validation errors (422) and rate limits (429) - and every 5xx,
     * even with the `refund` key.
     */
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
        if ($status === 401) {
            return ($data['message'] ?? null) !== 'Unauthorized request';
        }

        return $status === 200 || ($status >= 400 && $status < 500);
    }

    /**
     * @return array<string, mixed>
     */
    private function signedBody(string $transactionId, ?Money $amount, ?string $reason, ?WebhookTarget $webhook = null): array
    {
        $body = [
            'service' => $this->api->panelService(),
            'transaction_id' => ServerTrim::required($transactionId, 'transaction_id'),
        ];
        if ($amount !== null) {
            $body['value'] = $amount->toDecimal();
        }
        if ($reason !== null) {
            // Blank after trimming is sent as "" - the API turns it into null and hashes an empty segment
            $body['reason'] = $reason;
        }
        if ($webhook !== null) {
            $body['webhook'] = $webhook->toArray();
        }

        return $this->api->signPanelBody($body);
    }
}
