<?php

declare(strict_types=1);

namespace DPay\Exception;

final class CardPaymentException extends ApiErrorException
{
    /**
     * @param array<mixed> $data
     */
    public static function fromResponse(array $data): self
    {
        $message = isset($data['message']) && is_string($data['message']) ? $data['message'] : 'Card payment failed';
        $rawBody = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return new self($message, 200, $message, [], $rawBody === false ? '' : $rawBody);
    }
}
