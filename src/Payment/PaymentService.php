<?php

declare(strict_types=1);

namespace DPay\Payment;

use DPay\Exception\PaymentRejectedException;
use DPay\Internal\ApiRequestor;
use DPay\Internal\BaseUrls;

final class PaymentService
{
    private ApiRequestor $api;

    public function __construct(ApiRequestor $api)
    {
        $this->api = $api;
    }

    public function register(RegisterPaymentRequest $request): RegisteredPayment
    {
        $service = $this->api->config()->service();
        $body = $request->toBody($service);
        $body['checksum'] = $this->api->checksum()->secretSecond($service, [
            $this->stringField($body, 'value'),
            $this->stringField($body, 'url_success'),
            $this->stringField($body, 'url_fail'),
            $this->stringField($body, 'url_ipn'),
        ]);

        $data = $this->api->postJson(BaseUrls::API_PAYMENTS, '/api/v1_0/payments/register', $body);

        if (($data['error'] ?? false) === true || ($data['status'] ?? true) === false) {
            throw PaymentRejectedException::fromResponse($data);
        }

        return RegisteredPayment::fromArray($data);
    }

    public function details(string $transactionId): Transaction
    {
        $service = $this->api->config()->service();
        $body = [
            'service' => $service,
            'transaction_id' => $transactionId,
        ];
        $body['checksum'] = $this->api->checksum()->orderedBody(array_values($body));

        $data = $this->api->postJson(BaseUrls::PANEL, '/api/v1/pbl/details', $body);

        return Transaction::fromArray($data);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function stringField(array $body, string $key): string
    {
        $value = $body[$key] ?? null;

        return is_scalar($value) ? (string) $value : '';
    }
}
