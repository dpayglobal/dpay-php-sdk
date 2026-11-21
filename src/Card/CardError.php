<?php

declare(strict_types=1);

namespace DPay\Card;

/**
 * Card decline details: the `error` object of a declined card operation (HTTP 200, `success: false`) and the
 * `cardError` object of a Payments API error (e.g. a declined charge on a saved card). Every field can be null.
 */
final class CardError
{
    private ?string $code;

    private ?string $declineCode;

    private ?string $declineMessage;

    private function __construct(?string $code, ?string $declineCode, ?string $declineMessage)
    {
        $this->code = $code;
        $this->declineCode = $declineCode;
        $this->declineMessage = $declineMessage;
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(self::string($data, 'code'), self::string($data, 'declineCode'), self::string($data, 'declineMessage'));
    }

    /** Stable dpay reason, e.g. `payment_declined`, `invalid_card`, `card_already_registered`. */
    public function getCode(): ?string
    {
        return $this->code;
    }

    /** Two-character card network response code (ISO 8583), e.g. `05`, `51`; null when not from an authorisation. */
    public function getDeclineCode(): ?string
    {
        return $this->declineCode;
    }

    /** English description of the decline code - localise from getDeclineCode(), not from this text. */
    public function getDeclineMessage(): ?string
    {
        return $this->declineMessage;
    }

    /**
     * @param array<mixed> $data
     */
    private static function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) || is_int($value) ? (string) $value : null;
    }
}
