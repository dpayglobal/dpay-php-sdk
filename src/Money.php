<?php

declare(strict_types=1);

namespace DPay;

use InvalidArgumentException;

final class Money
{
    private int $minor;

    private string $currency;

    private function __construct(int $minor, string $currency)
    {
        $this->minor = $minor;
        $this->currency = $currency;
    }

    public static function pln(int $minor): self
    {
        return new self($minor, Currency::PLN);
    }

    public static function of(int $minor, string $currency): self
    {
        Currency::assertValid($currency);

        return new self($minor, $currency);
    }

    public static function fromDecimal(string $decimal, string $currency): self
    {
        Currency::assertValid($currency);
        if (preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $decimal, $matches) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid money amount "%s"', $decimal));
        }
        $fraction = str_pad($matches[3] ?? '', 2, '0');
        $minor = ((int) $matches[2]) * 100 + (int) $fraction;

        return new self($matches[1] === '-' ? -$minor : $minor, $currency);
    }

    /**
     * @param int|float|string $value
     */
    public static function fromApiNumber($value, string $currency): self
    {
        if (is_int($value)) {
            return self::of($value * 100, $currency);
        } elseif (is_float($value)) {
            return self::of((int) round($value * 100), $currency);
        } elseif (is_string($value)) {
            return self::fromDecimal($value, $currency);
        } else {
            throw new InvalidArgumentException('Money value must be int, float or string');
        }
    }

    /**
     * @param mixed $value
     */
    public static function tryFromApiNumber($value, string $currency): ?self
    {
        if (!Currency::isValid($currency)) {
            return null;
        }
        if (is_int($value)) {
            return new self($value * 100, $currency);
        }
        if (is_float($value)) {
            return new self((int) round($value * 100), $currency);
        }
        if (is_string($value)) {
            if (preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $value, $matches) !== 1) {
                return null;
            }
            $fraction = str_pad($matches[3] ?? '', 2, '0');
            $minor = ((int) $matches[2]) * 100 + (int) $fraction;

            return new self($matches[1] === '-' ? -$minor : $minor, $currency);
        }

        return null;
    }

    public function getMinor(): int
    {
        return $this->minor;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function toDecimal(): string
    {
        $abs = abs($this->minor);

        return sprintf('%s%d.%02d', $this->minor < 0 ? '-' : '', intdiv($abs, 100), $abs % 100);
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor && $this->currency === $other->currency;
    }
}
