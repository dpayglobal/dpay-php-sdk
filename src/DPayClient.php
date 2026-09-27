<?php

declare(strict_types=1);

namespace DPay;

use DPay\Bank\BankService;
use DPay\Blik\BlikService;
use DPay\Card\CardService;
use DPay\Http\CurlHttpClient;
use DPay\Internal\ApiRequestor;
use DPay\Payment\PaymentService;
use DPay\Payout\PayoutService;
use DPay\Recurring\RecurringService;
use DPay\Refund\RefundService;
use DPay\Webhook\EventService;

final class DPayClient
{
    public const VERSION = Version::SDK;

    public PaymentService $payments;

    public RefundService $refunds;

    public BankService $banks;

    public BlikService $blik;

    public CardService $cards;

    public PayoutService $payouts;

    public RecurringService $recurring;

    public EventService $events;

    private Config $config;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(array $options)
    {
        $this->config = Config::fromArray($options);
        $httpClient = $this->config->httpClient() ?? new CurlHttpClient($this->config->timeout());
        $api = new ApiRequestor($this->config, $httpClient);

        $this->payments = new PaymentService($api);
        $this->refunds = new RefundService($api);
        $this->banks = new BankService($api);
        $this->blik = new BlikService($api);
        $this->cards = new CardService($api);
        $this->payouts = new PayoutService($api);
        $this->recurring = new RecurringService($api);
        $this->events = new EventService($api);
    }

    public function getConfig(): Config
    {
        return $this->config;
    }
}
