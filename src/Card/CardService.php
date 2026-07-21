<?php

declare(strict_types=1);

namespace DPay\Card;

use DPay\Exception\CardPaymentException;
use DPay\Internal\ApiRequestor;
use DPay\Internal\BaseUrls;
use DPay\Money;

final class CardService
{
    private ApiRequestor $api;

    public function __construct(ApiRequestor $api)
    {
        $this->api = $api;
    }

    public function publicKey(): string
    {
        return trim($this->api->getText(BaseUrls::API_PAYMENTS, '/api/v1_0/cards/public-key'));
    }

    public function payOtp(string $transactionId, CardPaymentRequest $request): CardPaymentResult
    {
        return $this->post($transactionId, '/pay/card-otp', $request->toBody());
    }

    public function preAuth(string $transactionId, CardPaymentRequest $request): CardPaymentResult
    {
        return $this->post($transactionId, '/pay/card-pre-auth', $request->toBody());
    }

    public function capture(string $transactionId, Money $amount): CardPaymentResult
    {
        return $this->post($transactionId, '/capture', ['amount' => (float) $amount->toDecimal()]);
    }

    public function cancel(string $transactionId, ?Money $amount = null): CardPaymentResult
    {
        $body = $amount === null ? [] : ['amount' => (float) $amount->toDecimal()];

        return $this->post($transactionId, '/cancellation', $body);
    }

    public function googlePay(string $transactionId, GooglePayRequest $request): CardPaymentResult
    {
        return $this->post($transactionId, '/pay/google-pay', $request->toBody());
    }

    public function applePay(string $transactionId, ApplePayRequest $request): CardPaymentResult
    {
        return $this->post($transactionId, '/pay/apple-pay', $request->toBody());
    }

    /**
     * @param array<string, mixed> $body
     */
    private function post(string $transactionId, string $suffix, array $body): CardPaymentResult
    {
        $path = '/api/v1_0/cards/payment/' . rawurlencode($transactionId) . $suffix;
        $data = $this->api->postJson(BaseUrls::API_PAYMENTS, $path, $body);

        if (($data['success'] ?? false) !== true) {
            throw CardPaymentException::fromResponse($data);
        }

        return CardPaymentResult::fromArray($data);
    }
}
