<?php

declare(strict_types=1);

namespace DPay\Exception;

use DPay\Card\CardError;

/**
 * A card operation answered HTTP 200 with `success: false`. getMessage() and getErrorCode() return the API `message`
 * (e.g. `DCC_OFFER_EXPIRED`); getCardError() returns the decline details (`error`) when the decline carries a
 * recognised code.
 */
final class CardPaymentException extends ApiErrorException
{
    /**
     * @param array<mixed> $data
     */
    public static function fromResponse(array $data): self
    {
        $message = isset($data['message']) && is_string($data['message']) ? $data['message'] : 'Card payment failed';
        $rawBody = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $error = $data['error'] ?? null;

        return new self(
            $message,
            200,
            $message,
            [],
            $rawBody === false ? '' : $rawBody,
            null,
            null,
            is_array($error) ? CardError::fromArray($error) : null
        );
    }
}
