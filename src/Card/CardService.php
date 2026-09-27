<?php

declare(strict_types=1);

namespace DPay\Card;

use DPay\Exception\CardPaymentException;
use DPay\Internal\ApiRequestor;
use DPay\Internal\BaseUrls;
use DPay\Money;
use DPay\Webhook\WebhookEventType;
use DPay\Webhook\WebhookTarget;

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

    /**
     * Captures a pre-authorised amount (partial captures allowed up to the authorisation). Signed with
     * sha256(capture|service|transaction_id|amount|hash). Optional webhook target for `payment.captured`.
     */
    public function capture(string $transactionId, Money $amount, ?WebhookTarget $webhook = null): CardPaymentResult
    {
        $service = $this->api->config()->service();
        $body = ['service' => $service, 'amount' => (float) $amount->toDecimal()];
        if ($webhook !== null) {
            $webhook->assertEventsAllowed(WebhookEventType::CAPTURE, 'a card capture');
            $body['webhook'] = $webhook->toArray();
        }
        $body['checksum'] = $this->api->checksum()->operation('capture', $service, $transactionId, $amount->toDecimal());

        return $this->post($transactionId, '/capture', $body);
    }

    /**
     * Cancels the pre-authorisation, the whole uncaptured remainder without an amount. Signed with
     * sha256(cancellation|service|transaction_id|amount|hash) - empty amount segment without an amount.
     */
    public function cancel(string $transactionId, ?Money $amount = null): CardPaymentResult
    {
        $service = $this->api->config()->service();
        $body = ['service' => $service];
        if ($amount !== null) {
            $body['amount'] = (float) $amount->toDecimal();
        }
        $body['checksum'] = $this->api->checksum()->operation(
            'cancellation',
            $service,
            $transactionId,
            $amount === null ? null : $amount->toDecimal()
        );

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
