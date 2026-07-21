<?php

declare(strict_types=1);

namespace DPay\Payout;

use DPay\Internal\ApiRequestor;
use DPay\Internal\BaseUrls;

final class PayoutService
{
    private ApiRequestor $api;

    public function __construct(ApiRequestor $api)
    {
        $this->api = $api;
    }

    public function details(int $withdrawId, ?int $timestamp = null): PayoutDetails
    {
        $body = ['service' => $this->api->config()->service()];
        if ($timestamp !== null) {
            $body['timestamp'] = $timestamp;
        }
        $body['withdraw_id'] = $withdrawId;
        $body['checksum'] = $this->api->checksum()->orderedBody(array_values($body));

        $data = $this->api->postJson(BaseUrls::PANEL, '/api/v1/pbl/withdraws/details', $body);

        return PayoutDetails::fromArray($data);
    }
}
