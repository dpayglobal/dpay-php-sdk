<?php

declare(strict_types=1);

namespace DPay\Tests\Recurring;

use DPay\Recurring\RecurringRegistration;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RecurringRegistrationTest extends TestCase
{
    private const TERMS = 'https://shop.example/terms';

    public function testModelOSendsNoFrequencyOrLimits(): void
    {
        $registration = RecurringRegistration::create('Abonament', RecurringRegistration::MODEL_O, self::TERMS)
            ->withAlias('SUB-0001')
            ->withMethods([RecurringRegistration::METHOD_BLIK])
            ->withTermsVersion('2026-09');

        self::assertSame([
            'label' => 'Abonament',
            'alias' => 'SUB-0001',
            'model' => 'O',
            'methods' => ['blik'],
            'terms_url' => self::TERMS,
            'terms_version' => '2026-09',
        ], $registration->toArray());
    }

    public function testModelORejectsLimits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('frequency is not allowed in recurring model O');

        RecurringRegistration::create('Abonament', RecurringRegistration::MODEL_O, self::TERMS)->withFrequency('1M')->toArray();
    }

    public function testModelARequiresTheFullTerms(): void
    {
        $registration = RecurringRegistration::create('Abonament', RecurringRegistration::MODEL_A, self::TERMS)
            ->withFrequency('1M')
            ->withLimitAmt(5999)
            ->withTotLimitAmt(71988)
            ->withExpirationDate('2027-09-30')
            ->withInitDate('2026-11-01');

        self::assertSame(
            ['label', 'model', 'frequency', 'limit_amt', 'tot_limit_amt', 'expiration_date', 'init_date', 'terms_url'],
            array_keys($registration->toArray())
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('init_date is required in recurring model A');
        RecurringRegistration::create('Abonament', RecurringRegistration::MODEL_A, self::TERMS)
            ->withFrequency('1M')->withLimitAmt(5999)->withTotLimitAmt(71988)->withExpirationDate('2027-09-30')
            ->toArray();
    }

    public function testQuarterlyFrequencyIsNotABlikFrequency(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RecurringRegistration::create('Abonament', RecurringRegistration::MODEL_M, self::TERMS)->withFrequency('1Q');
    }

    public function testTermsUrlIsRequired(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RecurringRegistration::create('Abonament', RecurringRegistration::MODEL_O, 'not a url');
    }
}
