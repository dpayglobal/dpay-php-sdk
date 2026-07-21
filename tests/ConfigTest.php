<?php

declare(strict_types=1);

namespace DPay\Tests;

use DPay\Config;
use DPay\Tests\Support\MockHttpClient;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testMinimalConfig(): void
    {
        $config = Config::fromArray(['service' => 'MyShop', 'secret_hash' => 'secret123']);
        self::assertSame('MyShop', $config->service());
        self::assertSame('secret123', $config->secretHash());
        self::assertSame(30, $config->timeout());
        self::assertNull($config->httpClient());
        self::assertSame('https://panel.dpay.pl', $config->baseUrls()->resolve('panel'));
    }

    public function testFullConfig(): void
    {
        $client = new MockHttpClient();
        $config = Config::fromArray([
            'service' => 'MyShop',
            'secret_hash' => 'secret123',
            'timeout' => 5,
            'http_client' => $client,
            'base_urls' => ['api_payments' => 'https://mock.local/'],
        ]);
        self::assertSame(5, $config->timeout());
        self::assertSame($client, $config->httpClient());
        self::assertSame('https://mock.local', $config->baseUrls()->resolve('api_payments'));
        self::assertSame('https://panel.dpay.pl', $config->baseUrls()->resolve('panel'));
    }

    public function testMissingServiceThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Config::fromArray(['secret_hash' => 'secret123']);
    }

    public function testEmptySecretThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Config::fromArray(['service' => 'MyShop', 'secret_hash' => '']);
    }

    public function testUnknownOptionThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Config::fromArray(['service' => 'MyShop', 'secret_hash' => 'x', 'secretHash' => 'typo']);
    }

    public function testUnknownBaseUrlKeyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Config::fromArray(['service' => 'MyShop', 'secret_hash' => 'x', 'base_urls' => ['gate' => 'https://x']]);
    }
}
