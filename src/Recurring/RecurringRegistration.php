<?php

declare(strict_types=1);

namespace DPay\Recurring;

use InvalidArgumentException;

/**
 * The `recurring_registration` object: registers a recurring payment (today BLIK, alias PAYID) together with a
 * payment that carries the customer's BLIK code. Models:
 *  - O (open): no frequency or limits, the merchant charges any amount within its active ranges (max 2000 PLN);
 *  - A (automatic): fixed amount, frequency, total limit, start and expiry date - all required;
 *  - M (manual): every charge is confirmed by the customer in the banking app; frequency and limits optional.
 */
final class RecurringRegistration
{
    public const MODEL_A = 'A';
    public const MODEL_M = 'M';
    public const MODEL_O = 'O';

    public const METHOD_BLIK = 'blik';

    private string $label;

    private string $model;

    private string $termsUrl;

    private ?string $alias = null;

    private ?string $termsVersion = null;

    /** @var array<int, string>|null */
    private ?array $methods = null;

    private ?string $frequency = null;

    private ?int $limitAmt = null;

    private ?int $totLimitAmt = null;

    private ?bool $limitAmtFixed = null;

    private ?string $expirationDate = null;

    private ?string $initDate = null;

    private function __construct(string $label, string $model, string $termsUrl)
    {
        if ($label === '' || mb_strlen($label) > 50) {
            throw new InvalidArgumentException('Recurring payment label must be 1-50 characters');
        }
        if (!in_array($model, [self::MODEL_A, self::MODEL_M, self::MODEL_O], true)) {
            throw new InvalidArgumentException(sprintf('Invalid recurring model "%s"', $model));
        }
        if (strlen($termsUrl) > 2048 || filter_var($termsUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException(sprintf('Invalid terms URL "%s"', $termsUrl));
        }
        $this->label = $label;
        $this->model = $model;
        $this->termsUrl = $termsUrl;
    }

    /**
     * @param string $termsUrl the merchant's terms the customer accepted (consent evidence)
     */
    public static function create(string $label, string $model, string $termsUrl): self
    {
        return new self($label, $model, $termsUrl);
    }

    /** Your own alias of the recurring payment (max 128 characters); without it dpay assigns one. */
    public function withAlias(string $alias): self
    {
        if ($alias === '' || strlen($alias) > 128) {
            throw new InvalidArgumentException('Recurring alias must be 1-128 characters');
        }
        $this->alias = $alias;

        return $this;
    }

    public function withTermsVersion(string $termsVersion): self
    {
        if ($termsVersion === '' || mb_strlen($termsVersion) > 64) {
            throw new InvalidArgumentException('Terms version must be 1-64 characters');
        }
        $this->termsVersion = $termsVersion;

        return $this;
    }

    /**
     * @param array<int, string> $methods today only `blik`
     */
    public function withMethods(array $methods): self
    {
        if ($methods === [] || count($methods) !== count(array_unique($methods))) {
            throw new InvalidArgumentException('Methods must be a non-empty list of distinct methods');
        }
        foreach ($methods as $method) {
            if ($method !== self::METHOD_BLIK) {
                throw new InvalidArgumentException(sprintf('Unsupported recurring method "%s"', $method));
            }
        }
        $this->methods = array_values($methods);

        return $this;
    }

    /** Frequency like `1M`, `2W`, `14D`, `1Y` (1-999 days, weeks, months or years). */
    public function withFrequency(string $frequency): self
    {
        if (preg_match('/^[1-9][0-9]{0,2}[DWMY]$/', $frequency) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid recurring frequency "%s"', $frequency));
        }
        $this->frequency = $frequency;

        return $this;
    }

    /** Single payment limit in minor units (grosz). */
    public function withLimitAmt(int $limitAmt): self
    {
        $this->limitAmt = $this->assertPositive($limitAmt, 'limit_amt');

        return $this;
    }

    /** Total limit of all payments in minor units (grosz). */
    public function withTotLimitAmt(int $totLimitAmt): self
    {
        $this->totLimitAmt = $this->assertPositive($totLimitAmt, 'tot_limit_amt');

        return $this;
    }

    public function withLimitAmtFixed(bool $fixed): self
    {
        $this->limitAmtFixed = $fixed;

        return $this;
    }

    /** YYYY-MM-DD, after today and at most 10 years ahead. */
    public function withExpirationDate(string $expirationDate): self
    {
        $this->expirationDate = $this->assertDate($expirationDate);

        return $this;
    }

    /** YYYY-MM-DD, the first charge date (today or later). */
    public function withInitDate(string $initDate): self
    {
        $this->initDate = $this->assertDate($initDate);

        return $this;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $this->assertModelRules();

        $data = ['label' => $this->label];
        if ($this->alias !== null) {
            $data['alias'] = $this->alias;
        }
        $data['model'] = $this->model;
        if ($this->frequency !== null) {
            $data['frequency'] = $this->frequency;
        }
        if ($this->limitAmt !== null) {
            $data['limit_amt'] = $this->limitAmt;
        }
        if ($this->totLimitAmt !== null) {
            $data['tot_limit_amt'] = $this->totLimitAmt;
        }
        if ($this->limitAmtFixed !== null) {
            $data['is_limit_amt_fixed'] = $this->limitAmtFixed;
        }
        if ($this->expirationDate !== null) {
            $data['expiration_date'] = $this->expirationDate;
        }
        if ($this->initDate !== null) {
            $data['init_date'] = $this->initDate;
        }
        if ($this->methods !== null) {
            $data['methods'] = $this->methods;
        }
        $data['terms_url'] = $this->termsUrl;
        if ($this->termsVersion !== null) {
            $data['terms_version'] = $this->termsVersion;
        }

        return $data;
    }

    private function assertModelRules(): void
    {
        if ($this->model === self::MODEL_O) {
            foreach (['frequency' => $this->frequency, 'limit_amt' => $this->limitAmt, 'tot_limit_amt' => $this->totLimitAmt, 'is_limit_amt_fixed' => $this->limitAmtFixed] as $field => $value) {
                if ($value !== null) {
                    throw new InvalidArgumentException(sprintf('%s is not allowed in recurring model O', $field));
                }
            }
        }
        if ($this->model === self::MODEL_A) {
            foreach (['frequency' => $this->frequency, 'limit_amt' => $this->limitAmt, 'tot_limit_amt' => $this->totLimitAmt, 'expiration_date' => $this->expirationDate, 'init_date' => $this->initDate] as $field => $value) {
                if ($value === null) {
                    throw new InvalidArgumentException(sprintf('%s is required in recurring model A', $field));
                }
            }
            if ($this->limitAmtFixed === false) {
                throw new InvalidArgumentException('Recurring model A requires a fixed amount (is_limit_amt_fixed = true)');
            }
        }
    }

    private function assertPositive(int $value, string $field): int
    {
        if ($value < 1) {
            throw new InvalidArgumentException(sprintf('%s must be at least 1 (minor units)', $field));
        }

        return $value;
    }

    private function assertDate(string $date): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            throw new InvalidArgumentException(sprintf('Date "%s" must be in YYYY-MM-DD format', $date));
        }

        return $date;
    }
}
