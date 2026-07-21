<?php

declare(strict_types=1);

namespace DPay\Exception;

final class PaymentRejectedException extends ApiErrorException
{
    private ?string $transactionId = null;

    /**
     * @param array<mixed> $data
     */
    public static function fromResponse(array $data): self
    {
        $message = isset($data['msg']) && is_string($data['msg']) ? $data['msg'] : 'Payment rejected';
        $additional = is_array($data['additionalInfo'] ?? null) ? $data['additionalInfo'] : [];
        $errorCode = isset($additional['error']) && is_string($additional['error']) ? $additional['error'] : null;
        $rawBody = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $exception = new self($message, 200, $errorCode, [], $rawBody === false ? '' : $rawBody);
        $transactionId = $data['transactionId'] ?? null;
        if (is_scalar($transactionId)) {
            $exception->transactionId = (string) $transactionId;
        }

        return $exception;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }
}
