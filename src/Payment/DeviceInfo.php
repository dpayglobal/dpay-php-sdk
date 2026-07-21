<?php

declare(strict_types=1);

namespace DPay\Payment;

use InvalidArgumentException;

final class DeviceInfo
{
    private string $browserAcceptHeader;

    private string $browserLanguage;

    private int $browserColorDepth;

    private int $browserScreenHeight;

    private int $browserScreenWidth;

    private int $browserTZ;

    private string $browserUserAgent;

    private string $systemFamily;

    private string $geoLocalization;

    private string $deviceId;

    private string $applicationName;

    private ?bool $browserJavaEnabled = null;

    private function __construct(
        string $browserAcceptHeader,
        string $browserLanguage,
        int $browserColorDepth,
        int $browserScreenHeight,
        int $browserScreenWidth,
        int $browserTZ,
        string $browserUserAgent,
        string $systemFamily,
        string $geoLocalization,
        string $deviceId,
        string $applicationName
    ) {
        if ($deviceId === '' || mb_strlen($deviceId) > 64) {
            throw new InvalidArgumentException('Device ID must be 1-64 characters');
        }
        if ($applicationName === '' || mb_strlen($applicationName) > 64) {
            throw new InvalidArgumentException('Application name must be 1-64 characters');
        }
        $this->browserAcceptHeader = $browserAcceptHeader;
        $this->browserLanguage = $browserLanguage;
        $this->browserColorDepth = $browserColorDepth;
        $this->browserScreenHeight = $browserScreenHeight;
        $this->browserScreenWidth = $browserScreenWidth;
        $this->browserTZ = $browserTZ;
        $this->browserUserAgent = $browserUserAgent;
        $this->systemFamily = $systemFamily;
        $this->geoLocalization = $geoLocalization;
        $this->deviceId = $deviceId;
        $this->applicationName = $applicationName;
    }

    public static function create(
        string $browserAcceptHeader,
        string $browserLanguage,
        int $browserColorDepth,
        int $browserScreenHeight,
        int $browserScreenWidth,
        int $browserTZ,
        string $browserUserAgent,
        string $systemFamily,
        string $geoLocalization,
        string $deviceId,
        string $applicationName
    ): self {
        return new self(
            $browserAcceptHeader,
            $browserLanguage,
            $browserColorDepth,
            $browserScreenHeight,
            $browserScreenWidth,
            $browserTZ,
            $browserUserAgent,
            $systemFamily,
            $geoLocalization,
            $deviceId,
            $applicationName
        );
    }

    public function withBrowserJavaEnabled(bool $enabled): self
    {
        $this->browserJavaEnabled = $enabled;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'browserAcceptHeader' => $this->browserAcceptHeader,
            'browserLanguage' => $this->browserLanguage,
            'browserColorDepth' => $this->browserColorDepth,
            'browserScreenHeight' => $this->browserScreenHeight,
            'browserScreenWidth' => $this->browserScreenWidth,
            'browserTZ' => $this->browserTZ,
            'browserUserAgent' => $this->browserUserAgent,
            'systemFamily' => $this->systemFamily,
            'geoLocalization' => $this->geoLocalization,
            'deviceID' => $this->deviceId,
            'applicationName' => $this->applicationName,
        ];
        if ($this->browserJavaEnabled !== null) {
            $data['browserJavaEnabled'] = $this->browserJavaEnabled ? 'true' : 'false';
        }

        return $data;
    }
}
