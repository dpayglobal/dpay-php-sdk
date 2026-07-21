<?php

declare(strict_types=1);

namespace DPay\Payment;

use DPay\Blik\BlikAliasRegistration;
use DPay\Blik\BlikRecurringRegistration;
use DPay\Card\CardRecurringOperation;
use DPay\Card\CardRecurringRegistration;
use DPay\Currency;
use DPay\Money;
use InvalidArgumentException;

final class RegisterPaymentRequest
{
    private Money $amount;

    private string $transactionType;

    private ReturnUrls $urls;

    private ?string $description = null;

    private ?string $custom = null;

    private ?Payer $payer = null;

    private ?bool $acceptTos = null;

    private ?string $channel = null;

    private ?bool $creditCard = null;

    private ?bool $paysafecard = null;

    private ?bool $blik = null;

    private ?bool $installment = null;

    private ?bool $paypal = null;

    private ?bool $noBanks = null;

    private ?string $phoneNumber = null;

    private ?string $currencyCode = null;

    private ?string $partnerPlatform = null;

    private ?string $userAgent = null;

    private ?string $userIp = null;

    private ?string $blikCode = null;

    private ?string $blikAlias = null;

    private ?BlikAliasRegistration $registerBlikAlias = null;

    private ?BlikRecurringRegistration $registerBlikRecurringAlias = null;

    private ?string $aliasIpnUrl = null;

    private ?bool $noDelay = null;

    private ?CardRecurringRegistration $cardRecurring = null;

    private ?string $cardRecurringAlias = null;

    private ?bool $authorizeOnly = null;

    private ?string $cardRecurringOperation = null;

    private ?PayoutInstruction $payout = null;

    /** @var array<mixed>|null */
    private ?array $billingAddress = null;

    /** @var array<mixed>|null */
    private ?array $shippingAddress = null;

    private ?DeviceInfo $deviceInfo = null;

    /** @var array<int, array<mixed>>|null */
    private ?array $products = null;

    private ?bool $efaktura = null;

    private ?InvoiceDetails $invoice = null;

    private function __construct(Money $amount, string $transactionType, ReturnUrls $urls)
    {
        TransactionType::assertValid($transactionType);
        $this->amount = $amount;
        $this->transactionType = $transactionType;
        $this->urls = $urls;
    }

    public static function create(Money $amount, string $transactionType, ReturnUrls $urls): self
    {
        return new self($amount, $transactionType, $urls);
    }

    public function withDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function withCustom(string $custom): self
    {
        $this->custom = $custom;

        return $this;
    }

    public function withPayer(Payer $payer): self
    {
        $this->payer = $payer;

        return $this;
    }

    public function withAcceptTos(bool $acceptTos): self
    {
        $this->acceptTos = $acceptTos;

        return $this;
    }

    public function withChannel(string $channel): self
    {
        $this->channel = $channel;

        return $this;
    }

    public function withCreditCard(bool $enabled): self
    {
        $this->creditCard = $enabled;

        return $this;
    }

    public function withPaysafecard(bool $enabled): self
    {
        $this->paysafecard = $enabled;

        return $this;
    }

    public function withBlik(bool $enabled): self
    {
        $this->blik = $enabled;

        return $this;
    }

    public function withInstallment(bool $enabled): self
    {
        $this->installment = $enabled;

        return $this;
    }

    public function withPaypal(bool $enabled): self
    {
        $this->paypal = $enabled;

        return $this;
    }

    public function withNoBanks(bool $disabled): self
    {
        $this->noBanks = $disabled;

        return $this;
    }

    public function withPhoneNumber(string $phoneNumber, string $currencyCode): self
    {
        Currency::assertValid($currencyCode);
        $this->phoneNumber = $phoneNumber;
        $this->currencyCode = $currencyCode;

        return $this;
    }

    public function withCurrencyCode(string $currencyCode): self
    {
        Currency::assertValid($currencyCode);
        $this->currencyCode = $currencyCode;

        return $this;
    }

    public function withPartnerPlatform(string $partnerPlatform): self
    {
        if (preg_match('/^[A-Z0-9]{1,64}$/', $partnerPlatform) !== 1) {
            throw new InvalidArgumentException('Partner platform must match ^[A-Z0-9]{1,64}$');
        }
        $this->partnerPlatform = $partnerPlatform;

        return $this;
    }

    public function withBlikCode(string $blikCode, string $userAgent, string $userIp): self
    {
        if (preg_match('/^\d{6}$/', $blikCode) !== 1) {
            throw new InvalidArgumentException('BLIK code must be exactly 6 digits');
        }
        if ($this->blikAlias !== null) {
            throw new InvalidArgumentException('blik_code cannot be combined with blik_alias');
        }
        $this->blikCode = $blikCode;
        $this->userAgent = $userAgent;
        $this->userIp = $userIp;

        return $this;
    }

    public function withBlikAlias(string $aliasValue, string $userAgent, string $userIp): self
    {
        if ($this->blikCode !== null || $this->registerBlikAlias !== null || $this->registerBlikRecurringAlias !== null) {
            throw new InvalidArgumentException('blik_alias cannot be combined with blik_code or alias registration');
        }
        $this->blikAlias = $aliasValue;
        $this->userAgent = $userAgent;
        $this->userIp = $userIp;

        return $this;
    }

    public function withRegisterBlikAlias(BlikAliasRegistration $registration): self
    {
        if ($this->blikAlias !== null) {
            throw new InvalidArgumentException('register_blik_alias cannot be combined with blik_alias');
        }
        $this->registerBlikAlias = $registration;

        return $this;
    }

    public function withRegisterBlikRecurringAlias(BlikRecurringRegistration $registration): self
    {
        if ($this->blikAlias !== null) {
            throw new InvalidArgumentException('register_blik_recurring_alias cannot be combined with blik_alias');
        }
        $this->registerBlikRecurringAlias = $registration;

        return $this;
    }

    public function withAliasIpnUrl(string $url): self
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException(sprintf('Invalid alias IPN URL "%s"', $url));
        }
        $this->aliasIpnUrl = $url;

        return $this;
    }

    public function withNoDelay(bool $noDelay): self
    {
        $this->noDelay = $noDelay;

        return $this;
    }

    public function withCardRecurring(CardRecurringRegistration $registration): self
    {
        if ($this->cardRecurringAlias !== null) {
            throw new InvalidArgumentException('register_card_recurring cannot be combined with card_recurring_alias');
        }
        $this->cardRecurring = $registration;

        return $this;
    }

    public function withCardRecurringAlias(string $alias): self
    {
        if ($this->cardRecurring !== null) {
            throw new InvalidArgumentException('card_recurring_alias cannot be combined with register_card_recurring');
        }
        $this->cardRecurringAlias = $alias;

        return $this;
    }

    public function withAuthorizeOnly(bool $authorizeOnly): self
    {
        $this->authorizeOnly = $authorizeOnly;

        return $this;
    }

    public function withCardRecurringOperation(string $operation): self
    {
        CardRecurringOperation::assertValid($operation);
        $this->cardRecurringOperation = $operation;

        return $this;
    }

    public function withPayout(PayoutInstruction $payout): self
    {
        $this->payout = $payout;

        return $this;
    }

    /**
     * @param array<mixed> $billingAddress
     */
    public function withBillingAddress(array $billingAddress): self
    {
        $this->billingAddress = $billingAddress;

        return $this;
    }

    /**
     * @param array<mixed> $shippingAddress
     */
    public function withShippingAddress(array $shippingAddress): self
    {
        $this->shippingAddress = $shippingAddress;

        return $this;
    }

    public function withDeviceInfo(DeviceInfo $deviceInfo): self
    {
        $this->deviceInfo = $deviceInfo;

        return $this;
    }

    /**
     * @param array<int, array<mixed>> $products
     */
    public function withProducts(array $products): self
    {
        $this->products = $products;

        return $this;
    }

    public function withEfaktura(?InvoiceDetails $invoice = null): self
    {
        if ($this->transactionType !== TransactionType::TRANSFERS) {
            throw new InvalidArgumentException('efaktura is allowed only for transactionType "transfers"');
        }
        $this->efaktura = true;
        $this->invoice = $invoice;

        return $this;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    /**
     * @return array<string, mixed>
     */
    public function toBody(string $service): array
    {
        $body = [
            'service' => $service,
            'value' => $this->amount->toDecimal(),
            'transactionType' => $this->transactionType,
            'url_success' => $this->urls->getSuccess(),
            'url_fail' => $this->urls->getFail(),
            'url_ipn' => $this->urls->getIpn(),
        ];

        if ($this->description !== null) {
            $body['description'] = $this->description;
        }
        if ($this->custom !== null) {
            $body['custom'] = $this->custom;
        }
        if ($this->payer !== null) {
            if ($this->payer->getEmail() !== null) {
                $body['email'] = $this->payer->getEmail();
            }
            if ($this->payer->getFirstName() !== null) {
                $body['client_name'] = $this->payer->getFirstName();
            }
            if ($this->payer->getLastName() !== null) {
                $body['client_surname'] = $this->payer->getLastName();
            }
        }
        if ($this->acceptTos !== null) {
            $body['accept_tos'] = $this->acceptTos;
        }
        if ($this->channel !== null) {
            $body['channel'] = $this->channel;
        }
        foreach ([
            'creditcard' => $this->creditCard,
            'paysafecard' => $this->paysafecard,
            'blik' => $this->blik,
            'installment' => $this->installment,
            'paypal' => $this->paypal,
            'nobanks' => $this->noBanks,
        ] as $key => $flag) {
            if ($flag !== null) {
                $body[$key] = (int) $flag;
            }
        }
        if ($this->phoneNumber !== null) {
            $body['phone_number'] = $this->phoneNumber;
        }
        if ($this->currencyCode !== null) {
            $body['currency_code'] = $this->currencyCode;
        }
        if ($this->partnerPlatform !== null) {
            $body['partner_platform'] = $this->partnerPlatform;
        }
        if ($this->userAgent !== null) {
            $body['user_agent'] = $this->userAgent;
        }
        if ($this->userIp !== null) {
            $body['user_ip'] = $this->userIp;
        }
        if ($this->blikCode !== null) {
            $body['blik_code'] = $this->blikCode;
        }
        if ($this->blikAlias !== null) {
            $body['blik_alias'] = $this->blikAlias;
        }
        if ($this->registerBlikAlias !== null) {
            $body['register_blik_alias'] = $this->registerBlikAlias->toArray();
        }
        if ($this->registerBlikRecurringAlias !== null) {
            $body['register_blik_recurring_alias'] = $this->registerBlikRecurringAlias->toArray();
        }
        if ($this->aliasIpnUrl !== null) {
            $body['alias_ipn_url'] = $this->aliasIpnUrl;
        }
        if ($this->noDelay !== null) {
            $body['no_delay'] = $this->noDelay;
        }
        if ($this->cardRecurring !== null) {
            $body['register_card_recurring'] = $this->cardRecurring->toArray();
        }
        if ($this->cardRecurringAlias !== null) {
            $body['card_recurring_alias'] = $this->cardRecurringAlias;
        }
        if ($this->authorizeOnly !== null) {
            $body['authorize_only'] = $this->authorizeOnly;
        }
        if ($this->cardRecurringOperation !== null) {
            $body['card_recurring_operation'] = $this->cardRecurringOperation;
        }
        if ($this->payout !== null) {
            $body['payout'] = $this->payout->toArray();
        }
        if ($this->billingAddress !== null) {
            $body['billing_address'] = $this->billingAddress;
        }
        if ($this->shippingAddress !== null) {
            $body['shipping_address'] = $this->shippingAddress;
        }
        if ($this->deviceInfo !== null) {
            $body['device_info'] = $this->deviceInfo->toArray();
        }
        if ($this->products !== null) {
            $body['products'] = $this->products;
        }
        if ($this->efaktura !== null) {
            $body['efaktura'] = $this->efaktura;
        }
        if ($this->invoice !== null) {
            $body['invoice'] = $this->invoice->toArray();
        }

        return $body;
    }
}
