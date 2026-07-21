<?php

declare(strict_types=1);

namespace DPay\Blik;

use InvalidArgumentException;

final class BlikAliasRegistration
{
    private string $label;

    private string $type;

    public function __construct(string $label, string $type = BlikAliasType::UID)
    {
        if ($label === '' || mb_strlen($label) > 50) {
            throw new InvalidArgumentException('Alias label must be 1-50 characters');
        }
        BlikAliasType::assertValid($type);
        $this->label = $label;
        $this->type = $type;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return ['label' => $this->label, 'type' => $this->type];
    }
}
