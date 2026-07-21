<?php

declare(strict_types=1);

namespace DPay\Card;

use DPay\Payment\DeviceInfo;

final class CardPaymentRequest
{
    private DeviceInfo $deviceInfo;

    private ?string $email = null;

    private ?int $channelId = null;

    private ?string $cardHolderFirstName = null;

    private ?string $cardHolderLastName = null;

    private ?string $encryptedCardData = null;

    private ?bool $threeDsConfirmed = null;

    private ?string $dccDecision = null;

    private function __construct(DeviceInfo $deviceInfo)
    {
        $this->deviceInfo = $deviceInfo;
    }

    public static function create(DeviceInfo $deviceInfo): self
    {
        return new self($deviceInfo);
    }

    public function withEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function withChannelId(int $channelId): self
    {
        $this->channelId = $channelId;

        return $this;
    }

    public function withCardHolder(string $firstName, string $lastName): self
    {
        $this->cardHolderFirstName = $firstName;
        $this->cardHolderLastName = $lastName;

        return $this;
    }

    public function withEncryptedCardData(string $encryptedCardData): self
    {
        $this->encryptedCardData = $encryptedCardData;

        return $this;
    }

    public function withThreeDsConfirmed(bool $confirmed): self
    {
        $this->threeDsConfirmed = $confirmed;

        return $this;
    }

    public function withDccDecision(string $decision): self
    {
        DccDecision::assertValid($decision);
        $this->dccDecision = $decision;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toBody(): array
    {
        $body = [];
        if ($this->email !== null) {
            $body['email'] = $this->email;
        }
        if ($this->channelId !== null) {
            $body['channelId'] = $this->channelId;
        }
        if ($this->cardHolderFirstName !== null) {
            $body['cardHolderFirstName'] = $this->cardHolderFirstName;
        }
        if ($this->cardHolderLastName !== null) {
            $body['cardHolderLastName'] = $this->cardHolderLastName;
        }
        if ($this->encryptedCardData !== null) {
            $body['encryptedCardData'] = $this->encryptedCardData;
        }
        $body['deviceInfo'] = $this->deviceInfo->toArray();
        if ($this->threeDsConfirmed !== null) {
            $body['threeDsConfirmed'] = $this->threeDsConfirmed;
        }
        if ($this->dccDecision !== null) {
            $body['dccDecision'] = $this->dccDecision;
        }

        return $body;
    }
}
