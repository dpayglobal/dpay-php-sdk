<?php

declare(strict_types=1);

namespace DPay\Bank;

use DPay\Internal\ApiRequestor;
use DPay\Internal\BaseUrls;

final class BankService
{
    private ApiRequestor $api;

    public function __construct(ApiRequestor $api)
    {
        $this->api = $api;
    }

    /**
     * @return array<int, Bank>
     */
    public function all(): array
    {
        $data = $this->api->getJson(BaseUrls::PANEL, '/api/v1/pbl/banks');

        return $this->mapBanks($data);
    }

    /**
     * @return array<int, Bank>
     */
    public function forService(?int $timestamp = null): array
    {
        $body = [
            'service' => $this->api->config()->service(),
            'timestamp' => $timestamp ?? time(),
        ];
        $body['checksum'] = $this->api->checksum()->orderedBody(array_values($body));

        $data = $this->api->postJson(BaseUrls::PANEL, '/api/v1/pbl/banks', $body);

        return $this->mapBanks($data);
    }

    /**
     * @param array<mixed> $data
     * @return array<int, Bank>
     */
    private function mapBanks(array $data): array
    {
        $banks = [];
        foreach ($data as $bank) {
            if (is_array($bank)) {
                /** @var array<string, mixed> $bankData */
                $bankData = $bank;
                $banks[] = Bank::fromArray($bankData);
            }
        }

        return $banks;
    }
}
