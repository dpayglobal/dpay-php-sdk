<?php

declare(strict_types=1);

namespace DPay\Tests\Payment;

use DPay\Payment\Transaction;
use DPay\Payment\TransactionStatus;
use PHPUnit\Framework\TestCase;

final class TransactionTest extends TestCase
{
    /**
     * @return array{status: string, transaction: array<string, mixed>, payer: array<mixed>, refunds: array<int, array<string, mixed>>}
     */
    private function fixture(): array
    {
        return [
            'status' => 'success',
            'transaction' => [
                'id' => 'abc-def-123-456',
                'value' => '29.99',
                'rate' => 2,
                'minimal_fee' => 20,
                'permanent_fee' => 0,
                'status' => 'paid',
                'payment_method' => 'blik',
                'urls' => [
                    'success' => 'https://shop.example/ok',
                    'fail' => 'https://shop.example/fail',
                    'ipn' => 'https://shop.example/ipn',
                ],
                'creation_date' => '2026-01-15 12:30:00',
                'payment_date' => '2026-01-15 12:31:45',
                'settled' => true,
                'refunded' => false,
                'refunded_amount' => 10.00,
                'available_refund_amount' => 19.99,
                'fully_refunded' => false,
                'direct' => false,
            ],
            'payer' => [],
            'refunds' => [
                [
                    'payment_id' => 'ZWROT-abc-def-123-456-QWERTY12',
                    'value' => -10.00,
                    'status' => 'paid',
                    'creation_date' => '2026-01-16 09:00:00',
                    'payment_date' => '2026-01-16 09:00:00',
                ],
            ],
        ];
    }

    public function testMapsTransaction(): void
    {
        $transaction = Transaction::fromArray($this->fixture());

        self::assertSame('abc-def-123-456', $transaction->getId());
        self::assertSame(2999, $transaction->getValue()->getMinor());
        self::assertSame(TransactionStatus::PAID, $transaction->getStatus());
        self::assertTrue($transaction->isPaid());
        self::assertSame('blik', $transaction->getPaymentMethod());
        self::assertSame('2026-01-15 12:30:00', $transaction->getCreationDate());
        self::assertTrue($transaction->isSettled());
        self::assertSame(1000, $transaction->getRefundedAmount()->getMinor());
        self::assertSame(1999, $transaction->getAvailableRefundAmount()->getMinor());
        self::assertFalse($transaction->isFullyRefunded());
        self::assertNull($transaction->getGatewayId());
    }

    public function testMapsRefunds(): void
    {
        $refunds = Transaction::fromArray($this->fixture())->getRefunds();

        self::assertCount(1, $refunds);
        self::assertSame('ZWROT-abc-def-123-456-QWERTY12', $refunds[0]->getPaymentId());
        self::assertSame(-1000, $refunds[0]->getValue()->getMinor());
        self::assertTrue($refunds[0]->getValue()->isNegative());
        self::assertSame('paid', $refunds[0]->getStatus());
    }

    public function testUnknownStatusIsPreserved(): void
    {
        $data = $this->fixture();
        $data['transaction']['status'] = 'brand_new_status';

        $transaction = Transaction::fromArray($data);

        self::assertSame('brand_new_status', $transaction->getStatus());
        self::assertFalse($transaction->isPaid());
    }

    public function testMissingOptionalFieldsHaveDefaults(): void
    {
        $transaction = Transaction::fromArray([
            'status' => 'success',
            'transaction' => ['id' => 'x', 'value' => '1.00', 'status' => 'created'],
        ]);

        self::assertSame([], $transaction->getRefunds());
        self::assertSame([], $transaction->getPayer());
        self::assertSame(0, $transaction->getRefundedAmount()->getMinor());
        self::assertFalse($transaction->isSettled());
        self::assertNull($transaction->getPaymentDate());
    }

    public function testCapturedCountsAsPaid(): void
    {
        $data = $this->fixture();
        $data['transaction']['status'] = 'captured';

        self::assertTrue(Transaction::fromArray($data)->isPaid());
    }

    public function testMalformedNumericStringsFallBackToZeroWithoutThrowing(): void
    {
        $data = $this->fixture();
        $data['transaction']['value'] = '1e3';
        $data['transaction']['refunded_amount'] = 'N/A';

        $transaction = Transaction::fromArray($data);

        self::assertSame(0, $transaction->getValue()->getMinor());
        self::assertSame(0, $transaction->getRefundedAmount()->getMinor());
    }

    public function testMapsFeesAndUrls(): void
    {
        $transaction = Transaction::fromArray($this->fixture());

        self::assertSame('2', $transaction->getRate());
        self::assertSame(20, $transaction->getMinimalFee());
        self::assertSame(0, $transaction->getPermanentFee());
        $urls = $transaction->getUrls();
        self::assertNotNull($urls);
        self::assertSame('https://shop.example/ok', $urls->getSuccess());
        self::assertSame('https://shop.example/fail', $urls->getFail());
        self::assertSame('https://shop.example/ipn', $urls->getIpn());
    }

    public function testFeesFromDatabaseStringsAndMissingIpn(): void
    {
        $fixture = $this->fixture();
        $fixture['transaction']['rate'] = '1.49';
        $fixture['transaction']['minimal_fee'] = '30';
        unset($fixture['transaction']['permanent_fee']);
        $fixture['transaction']['urls'] = ['success' => 'https://shop.example/ok', 'fail' => 'https://shop.example/fail', 'ipn' => null];

        $transaction = Transaction::fromArray($fixture);

        self::assertSame('1.49', $transaction->getRate());
        self::assertSame(30, $transaction->getMinimalFee());
        self::assertNull($transaction->getPermanentFee());
        $urls = $transaction->getUrls();
        self::assertNotNull($urls);
        self::assertNull($urls->getIpn());
    }

    public function testMissingFeesAndUrlsAreNull(): void
    {
        $transaction = Transaction::fromArray(['transaction' => ['id' => 'x', 'value' => '1.00', 'status' => 'created']]);

        self::assertNull($transaction->getRate());
        self::assertNull($transaction->getMinimalFee());
        self::assertNull($transaction->getUrls());
    }
}
