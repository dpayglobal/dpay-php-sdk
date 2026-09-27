<?php

declare(strict_types=1);

namespace DPay\Tests\Internal;

use DPay\Internal\ChecksumCalculator;
use DPay\Tests\Support\ApiVectors;
use PHPUnit\Framework\TestCase;

/**
 * Shared checksum vectors of all dpay SDKs (tests/Fixtures/api_vectors.json, synthetic data computed with the API code).
 */
final class ApiVectorsTest extends TestCase
{
    private function calculator(): ChecksumCalculator
    {
        return new ChecksumCalculator(ApiVectors::secretHash());
    }

    public function testSecretSecondVectors(): void
    {
        foreach (ApiVectors::secretSecond() as $vector) {
            self::assertSame($vector['checksum'], $this->calculator()->secretSecond(ApiVectors::service(), $vector['fields']), $vector['name']);
        }
    }

    public function testOperationVectors(): void
    {
        foreach (ApiVectors::operation() as $vector) {
            self::assertSame(
                $vector['checksum'],
                $this->calculator()->operation($vector['operation'], ApiVectors::service(), ApiVectors::transactionId(), $vector['amount']),
                $vector['name']
            );
        }
    }

    public function testOrderedBodyVectors(): void
    {
        foreach (ApiVectors::orderedBody() as $vector) {
            self::assertSame($vector['checksum'], $this->calculator()->orderedBody($vector['body']), $vector['name']);
        }
    }

    public function testOrderedBodySkipsChecksumAndCastsLikeTheApi(): void
    {
        $calculator = new ChecksumCalculator('h');

        self::assertSame(
            hash('sha256', 'a|1||b|h'),
            $calculator->orderedBody(['x' => 'a', 'checksum' => 'ignored', 'y' => true, 'z' => null, 'w' => ['v' => 'b']])
        );
    }
}
