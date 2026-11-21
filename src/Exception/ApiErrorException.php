<?php

declare(strict_types=1);

namespace DPay\Exception;

use DPay\Card\CardError;
use RuntimeException;

class ApiErrorException extends RuntimeException implements ExceptionInterface
{
    private int $httpStatus;

    private ?string $errorCode;

    /** @var array<string, array<int, string>> */
    private array $fieldErrors;

    private string $rawBody;

    private ?string $reason;

    private ?string $transactionId;

    private ?CardError $cardError;

    /**
     * @param array<string, array<int, string>> $fieldErrors
     */
    public function __construct(
        string $message,
        int $httpStatus,
        ?string $errorCode = null,
        array $fieldErrors = [],
        string $rawBody = '',
        ?string $reason = null,
        ?string $transactionId = null,
        ?CardError $cardError = null
    ) {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
        $this->errorCode = $errorCode;
        $this->fieldErrors = $fieldErrors;
        $this->rawBody = $rawBody;
        $this->reason = $reason;
        $this->transactionId = $transactionId;
        $this->cardError = $cardError;
    }

    /**
     * Transaction created before the error, when the API returned one (`transactionId`, e.g. a declined charge on a
     * saved card or a declined payment) - use it to query the transaction.
     */
    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    /** Card decline details (`cardError` of a Payments API error, `error` of a declined card operation). */
    public function getCardError(): ?CardError
    {
        return $this->cardError;
    }

    /** Detailed reason next to the code, e.g. `https_required` for WEBHOOK_URL_INVALID. */
    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function getFieldErrors(): array
    {
        return $this->fieldErrors;
    }

    public function getRawBody(): string
    {
        return $this->rawBody;
    }
}
