<?php

declare(strict_types=1);

namespace DPay\Payment;

use InvalidArgumentException;

final class PayoutInstruction
{
    /** @var array<int, PayoutPosition> */
    private array $positions;

    private string $feeMode;

    /**
     * @param array<int, mixed> $positions
     */
    private function __construct(array $positions, string $feeMode)
    {
        if ($positions === []) {
            throw new InvalidArgumentException('Payout instruction requires at least one position');
        }
        $validated = [];
        foreach ($positions as $position) {
            if (!$position instanceof PayoutPosition) {
                throw new InvalidArgumentException('Positions must be PayoutPosition instances');
            }
            $validated[] = $position;
        }
        PayoutFeeMode::assertValid($feeMode);
        $this->positions = $validated;
        $this->feeMode = $feeMode;
    }

    /**
     * @param array<int, PayoutPosition> $positions
     */
    public static function create(array $positions, string $feeMode = PayoutFeeMode::NET): self
    {
        return new self($positions, $feeMode);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'fee_mode' => $this->feeMode,
            'positions' => array_map(
                static fn (PayoutPosition $position): array => $position->toArray(),
                $this->positions
            ),
        ];
    }
}
