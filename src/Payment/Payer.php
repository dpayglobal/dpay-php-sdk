<?php

declare(strict_types=1);

namespace DPay\Payment;

use InvalidArgumentException;

final class Payer
{
    private ?string $email = null;

    private ?string $firstName = null;

    private ?string $lastName = null;

    private function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
    }

    public function withEmail(string $email): self
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException(sprintf('Invalid email "%s"', $email));
        }
        $this->email = $email;

        return $this;
    }

    public function withName(string $firstName, string $lastName): self
    {
        $this->firstName = $firstName;
        $this->lastName = $lastName;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }
}
