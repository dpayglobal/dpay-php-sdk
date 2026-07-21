<?php

declare(strict_types=1);

namespace DPay\Card;

use DPay\Payment\DeviceInfo;

final class GooglePayRequest
{
    private string $token;

    private DeviceInfo $deviceInfo;

    private ?string $email = null;

    private ?int $channelId = null;

    private function __construct(string $token, DeviceInfo $deviceInfo)
    {
        $this->token = $token;
        $this->deviceInfo = $deviceInfo;
    }

    public static function create(string $token, DeviceInfo $deviceInfo): self
    {
        return new self($token, $deviceInfo);
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
        $body['xPayType'] = 'GOOGLE_PAY';
        $body['xPayToken'] = $this->token;
        $body['deviceInfo'] = $this->deviceInfo->toArray();

        return $body;
    }
}
