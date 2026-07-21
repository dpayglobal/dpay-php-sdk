<?php

declare(strict_types=1);

namespace DPay\Card;

use DPay\Currency;
use DPay\Money;

final class DccOffer
{
    /** @var array<mixed> */
    private array $raw;

    private string $currencyConversionId;

    private Money $originalAmount;

    private Money $convertedAmount;

    private float $exchangeRate;

    private string $validUntil;

    private string $declarationText;

    /** @var array<int, DccMarkup> */
    private array $markup;

    private bool $europeanEconomicArea;

    /**
     * @param array<mixed> $raw
     */
    private function __construct(array $raw)
    {
        $this->raw = $raw;
        $this->currencyConversionId = isset($raw['currencyConversionId']) && is_scalar($raw['currencyConversionId'])
            ? (string) $raw['currencyConversionId']
            : '';
        $this->originalAmount = Money::tryFromApiNumber(
            $raw['originalAmount'] ?? 0,
            is_string($raw['originalCurrency'] ?? null) ? $raw['originalCurrency'] : Currency::PLN
        ) ?? Money::pln(0);
        $this->convertedAmount = Money::tryFromApiNumber(
            $raw['convertedAmount'] ?? 0,
            is_string($raw['convertedCurrency'] ?? null) ? $raw['convertedCurrency'] : Currency::PLN
        ) ?? Money::pln(0);
        $exchangeRate = $raw['exchangeRate'] ?? 0;
        $this->exchangeRate = is_numeric($exchangeRate) ? (float) $exchangeRate : 0.0;
        $this->validUntil = isset($raw['validUntil']) && is_scalar($raw['validUntil']) ? (string) $raw['validUntil'] : '';
        $this->declarationText = isset($raw['declarationText']) && is_scalar($raw['declarationText'])
            ? (string) $raw['declarationText']
            : '';
        $this->europeanEconomicArea = (bool) ($raw['europeanEconomicArea'] ?? false);
        $this->markup = [];
        foreach (is_array($raw['markup'] ?? null) ? $raw['markup'] : [] as $markup) {
            if (is_array($markup)) {
                $this->markup[] = DccMarkup::fromArray($markup);
            }
        }
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function getCurrencyConversionId(): string
    {
        return $this->currencyConversionId;
    }

    public function getOriginalAmount(): Money
    {
        return $this->originalAmount;
    }

    public function getConvertedAmount(): Money
    {
        return $this->convertedAmount;
    }

    public function getExchangeRate(): float
    {
        return $this->exchangeRate;
    }

    public function getValidUntil(): string
    {
        return $this->validUntil;
    }

    public function getDeclarationText(): string
    {
        return $this->declarationText;
    }

    /**
     * @return array<int, DccMarkup>
     */
    public function getMarkup(): array
    {
        return $this->markup;
    }

    public function isEuropeanEconomicArea(): bool
    {
        return $this->europeanEconomicArea;
    }

    /**
     * @return array<mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }
}
