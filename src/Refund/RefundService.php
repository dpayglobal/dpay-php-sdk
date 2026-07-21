<?php

declare(strict_types=1);

namespace DPay\Refund;

use DPay\Internal\ApiRequestor;
use DPay\Internal\BaseUrls;
use DPay\Money;

final class RefundService
{
    private ApiRequestor $api;

    public function __construct(ApiRequestor $api)
    {
        $this->api = $api;
    }

    public function create(string $transactionId, ?Money $amount = null, ?string $reason = null): Refund
    {
        $body = $this->signedBody($transactionId, $amount, $reason);

        $data = $this->api->postJson(BaseUrls::PANEL, '/api/v1/pbl/refund', $body);

        return Refund::fromArray($data);
    }

    public function checkAvailability(string $transactionId, ?Money $amount = null, ?string $reason = null): RefundAvailability
    {
        $body = $this->signedBody($transactionId, $amount, $reason);

        $response = $this->api->sendRaw('POST', BaseUrls::PANEL, '/api/v1/pbl/check-refund-availability', $body);
        $data = $response->decodeJson();

        $status = $response->getStatus();
        if (in_array($status, [200, 400, 402], true) && is_array($data) && array_key_exists('refund', $data)) {
            return RefundAvailability::fromArray($data);
        }

        throw $this->api->mapError($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function signedBody(string $transactionId, ?Money $amount, ?string $reason): array
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
        $body['checksum'] = $this->api->checksum()->orderedBody(array_values($body));

        return $body;
    }
}
