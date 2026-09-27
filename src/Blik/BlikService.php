<?php

declare(strict_types=1);

namespace DPay\Blik;

use DPay\Internal\ApiRequestor;
use DPay\Internal\BaseUrls;

final class BlikService
{
    private ApiRequestor $api;

    public function __construct(ApiRequestor $api)
    {
        $this->api = $api;
    }

    public function alias(string $aliasValue, string $aliasType = BlikAliasType::UID): BlikAlias
    {
        BlikAliasType::assertValid($aliasType);
        $service = $this->api->config()->service();
        $body = [
            'service' => $service,
            'alias_value' => $aliasValue,
            'alias_type' => $aliasType,
        ];
        $body['checksum'] = $this->api->checksum()->secretSecond($service, [$aliasValue]);

        $data = $this->api->postJson(BaseUrls::API_PAYMENTS, '/api/v1_0/payments/blik/aliases', $body);

        return BlikAlias::fromArray(is_array($data['data'] ?? null) ? $data['data'] : []);
    }

    public function unregisterAlias(string $aliasValue, string $aliasType = BlikAliasType::UID, ?string $reason = null): void
    {
        BlikAliasType::assertValid($aliasType);
        $service = $this->api->config()->service();
        $body = [
            'service' => $service,
            'alias_value' => $aliasValue,
            'alias_type' => $aliasType,
        ];
        if ($reason !== null) {
            $body['reason'] = $reason;
        }
        $body['checksum'] = $this->api->checksum()->secretSecond($service, [$aliasValue]);

        $this->api->postJson(BaseUrls::API_PAYMENTS, '/api/v1_0/payments/blik/aliases/unregister', $body);
    }
}
