<?php

declare(strict_types=1);

namespace DPay\Card;

use DPay\Payment\DeviceInfo;

final class ApplePayRequest
{
    private string $xPayType;

    private ?string $token;

    private DeviceInfo $deviceInfo;

    private ?int $channelId = null;

    private function __construct(string $xPayType, ?string $token, DeviceInfo $deviceInfo)
    {
        $this->xPayType = $xPayType;
        $this->token = $token;
        $this->deviceInfo = $deviceInfo;
    }

    public static function init(DeviceInfo $deviceInfo): self
    {
        return new self('APPLE_PAY_INIT', null, $deviceInfo);
    }

    public static function pay(string $token, DeviceInfo $deviceInfo): self
    {
        return new self('APPLE_PAY', $token, $deviceInfo);
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
        if ($this->channelId !== null) {
            $body['channelId'] = $this->channelId;
        }
        $body['xPayType'] = $this->xPayType;
        if ($this->token !== null) {
            $body['xPayToken'] = $this->token;
        }
        $body['deviceInfo'] = $this->deviceInfo->toArray();

        return $body;
    }
}
