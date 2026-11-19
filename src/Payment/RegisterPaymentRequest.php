<?php

declare(strict_types=1);

namespace DPay\Payment;

use DPay\Blik\BlikAliasRegistration;
use DPay\Card\CardRecurringOperation;
use DPay\Card\CardRecurringRegistration;
use DPay\Currency;
use DPay\Money;
use DPay\Recurring\RecurringRegistration;
use DPay\Webhook\WebhookEventType;
use DPay\Webhook\WebhookTarget;
use InvalidArgumentException;

final class RegisterPaymentRequest
{
    /** Channels the API refuses as a direct `channel`. */
    private const FORBIDDEN_CHANNELS = ['tester', 'paypal', 'installment', 'paysafecard'];

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

    private ?RecurringRegistration $recurringRegistration = null;

    private ?string $recurringAlias = null;

    private ?WebhookTarget $webhook = null;

    private ?string $reference = null;

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

    /**
     * Direct payment channel (bank or method ID, e.g. `373` for MB WAY), skipping the dpay gateway. A channel requires
     * the payer's email and name (withPayer) and withAcceptTos(); it cannot be tester, paypal, installment or paysafecard.
     * An empty string is sent as before 0.3.0 and means no channel (the API turns it into null).
     */
    public function withChannel(string $channel): self
    {
        if (in_array($channel, self::FORBIDDEN_CHANNELS, true)) {
            throw new InvalidArgumentException(sprintf('Channel "%s" cannot be used as a direct payment channel', $channel));
        }
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

    /**
     * Payer's phone (MB WAY `+351...`, 9-15 characters). The currency must be the currency of the amount; since 0.3.0
     * `currency_code` is taken from the amount anyway (Money::of(500, Currency::EUR)).
     */
    public function withPhoneNumber(string $phoneNumber, string $currencyCode): self
    {
        $length = mb_strlen($phoneNumber);
        if ($length < 9 || $length > 15) {
            throw new InvalidArgumentException('Phone number must be 9-15 characters');
        }
        $this->assertAmountCurrency($currencyCode);
        $this->phoneNumber = $phoneNumber;
        $this->currencyCode = $currencyCode;

        return $this;
    }

    /**
     * Sends `currency_code` explicitly. Optional since 0.3.0: a non-PLN amount sends its currency on its own. The code
     * must be the currency of the amount.
     */
    public function withCurrencyCode(string $currencyCode): self
    {
        $this->assertAmountCurrency($currencyCode);
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
        if ($this->blikCode !== null || $this->registerBlikAlias !== null || $this->recurringRegistration !== null || $this->recurringAlias !== null) {
            throw new InvalidArgumentException('blik_alias cannot be combined with blik_code, alias registration or recurring payments');
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

    /**
     * Registers a recurring payment together with this payment. Requires the customer's BLIK code
     * (withBlikCode) and transactionType `transfers`; the amount may be 0 (consent only) or an initial fee.
     */
    public function withRecurringRegistration(RecurringRegistration $registration): self
    {
        $this->recurringRegistration = $registration;

        return $this;
    }

    /**
     * Charges a registered recurring payment server-to-server (no BLIK code). transactionType `transfers`,
     * amount above 0. The alias is appended to the checksum, binding the charge to that customer.
     */
    public function withRecurringAlias(string $alias): self
    {
        if ($alias === '' || strlen($alias) > 128) {
            throw new InvalidArgumentException('Recurring alias must be 1-128 characters');
        }
        $this->recurringAlias = $alias;

        return $this;
    }

    /**
     * Payer's user agent and IP for a recurring charge (optional there; BLIK code and alias payments set them
     * in withBlikCode / withBlikAlias).
     */
    public function withClientContext(string $userAgent, string $userIp): self
    {
        if (filter_var($userIp, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException(sprintf('Invalid user IP "%s"', $userIp));
        }
        $this->userAgent = $userAgent;
        $this->userIp = $userIp;

        return $this;
    }

    /**
     * Sends this payment's events (and later events of its refunds and recurring payment) also to this URL,
     * signed with the service's webhook secret. Not part of the checksum.
     */
    public function withWebhook(WebhookTarget $webhook): self
    {
        $webhook->assertEventsAllowed(WebhookEventType::PAYMENT_REGISTRATION, 'a payment registration');
        $this->webhook = $webhook;

        return $this;
    }

    /** Your reference of the payment (max 64 characters), returned as `references.merchant` in webhooks. */
    public function withReference(string $reference): self
    {
        $reference = trim($reference);
        if ($reference === '' || mb_strlen($reference) > 64 || preg_match('/[\x00-\x1F\x7F]/', $reference) === 1) {
            throw new InvalidArgumentException('Reference must be 1-64 characters without control characters');
        }
        $this->reference = $reference;

        return $this;
    }

    /** Address of `alias_update` notifications (BLIK OneClick and recurring payments), max 500 characters. */
    public function withAliasIpnUrl(string $url): self
    {
        if (mb_strlen($url) > 500 || filter_var($url, FILTER_VALIDATE_URL) === false) {
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
        $this->assertRecurringCombination();
        $this->assertDirectChannel();
        $this->assertEfakturaCombination();

        $body = [
            'service' => $service,
            'value' => $this->amount->toDecimal(),
            'transactionType' => $this->transactionType,
            'url_success' => $this->urls->getSuccess(),
            'url_fail' => $this->urls->getFail(),
        ];
        if ($this->urls->getIpn() !== null) {
            $body['url_ipn'] = $this->urls->getIpn();
        }

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
        $currencyCode = $this->currencyCode;
        if ($currencyCode === null && $this->amount->getCurrency() !== Currency::PLN) {
            // The API registers an amount without currency_code in PLN
            $currencyCode = $this->amount->getCurrency();
        }
        if ($currencyCode !== null) {
            $body['currency_code'] = $currencyCode;
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
        if ($this->recurringRegistration !== null) {
            $body['recurring_registration'] = $this->recurringRegistration->toArray();
        }
        if ($this->recurringAlias !== null) {
            $body['recurring_alias'] = $this->recurringAlias;
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
        if ($this->webhook !== null) {
            $body['webhook'] = $this->webhook->toArray();
        }
        if ($this->reference !== null) {
            $body['reference'] = $this->reference;
        }

        return $body;
    }

    private function assertAmountCurrency(string $currencyCode): void
    {
        Currency::assertValid($currencyCode);
        if ($currencyCode !== $this->amount->getCurrency()) {
            throw new InvalidArgumentException(sprintf(
                'Currency code "%s" does not match the amount currency "%s" - create the amount with Money::of(..., "%s")',
                $currencyCode,
                $this->amount->getCurrency(),
                $currencyCode
            ));
        }
    }

    /**
     * MB WAY (mb_way_direct) needs channel `373` and the full device info; any direct channel needs the payer's email,
     * name and the terms acceptance - otherwise the API answers 422.
     */
    private function assertDirectChannel(): void
    {
        $hasChannel = $this->channel !== null && $this->channel !== '';
        if ($this->transactionType === TransactionType::MB_WAY_DIRECT) {
            if (!$hasChannel) {
                throw new InvalidArgumentException('transactionType "mb_way_direct" requires a channel (withChannel("373"))');
            }
            if ($this->deviceInfo === null || $this->deviceInfo->getBrowserJavaEnabled() === null) {
                throw new InvalidArgumentException('transactionType "mb_way_direct" requires device info with browserJavaEnabled (withDeviceInfo)');
            }
        }
        if (!$hasChannel) {
            return;
        }
        $payer = $this->payer;
        if ($payer === null || $payer->getEmail() === null || (string) $payer->getFirstName() === '' || (string) $payer->getLastName() === '') {
            throw new InvalidArgumentException('A direct channel requires the payer\'s email, first name and last name (withPayer)');
        }
        if ($this->acceptTos === null) {
            throw new InvalidArgumentException('A direct channel requires the terms acceptance (withAcceptTos)');
        }
    }

    private function assertEfakturaCombination(): void
    {
        if ($this->efaktura === null) {
            return;
        }
        $conflicts = [
            'blik_code' => $this->blikCode,
            'blik_alias' => $this->blikAlias,
            'register_blik_alias' => $this->registerBlikAlias,
            'recurring_registration' => $this->recurringRegistration,
            'recurring_alias' => $this->recurringAlias,
            'register_card_recurring' => $this->cardRecurring,
            'card_recurring_alias' => $this->cardRecurringAlias,
        ];
        foreach ($conflicts as $field => $value) {
            if ($value !== null) {
                throw new InvalidArgumentException(sprintf('efaktura cannot be combined with %s', $field));
            }
        }
    }

    private function assertRecurringCombination(): void
    {
        if ($this->recurringRegistration === null && $this->recurringAlias === null) {
            return;
        }
        if ($this->recurringRegistration !== null && $this->recurringAlias !== null) {
            throw new InvalidArgumentException('recurring_registration cannot be combined with recurring_alias');
        }
        if ($this->transactionType !== TransactionType::TRANSFERS) {
            throw new InvalidArgumentException('Recurring payments require transactionType "transfers"');
        }
        $conflicts = [
            'blik_alias' => $this->blikAlias,
            'register_blik_alias' => $this->registerBlikAlias,
            'register_card_recurring' => $this->cardRecurring,
            'card_recurring_alias' => $this->cardRecurringAlias,
        ];
        if ($this->recurringRegistration !== null) {
            $conflicts['channel'] = $this->channel === '' ? null : $this->channel;
            if ($this->blikCode === null) {
                throw new InvalidArgumentException('recurring_registration requires the customer\'s BLIK code (withBlikCode)');
            }
        } else {
            $conflicts['blik_code'] = $this->blikCode;
            if ($this->amount->getMinor() <= 0) {
                throw new InvalidArgumentException('A recurring charge requires an amount above 0');
            }
        }
        foreach ($conflicts as $field => $value) {
            if ($value !== null) {
                throw new InvalidArgumentException(sprintf('%s cannot be combined with a recurring payment', $field));
            }
        }
    }
}
