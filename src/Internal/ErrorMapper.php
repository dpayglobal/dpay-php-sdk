<?php

declare(strict_types=1);

namespace DPay\Internal;

use DPay\Exception\ApiErrorException;
use DPay\Exception\ApiServerException;
use DPay\Exception\AuthenticationException;
use DPay\Exception\InvalidRequestException;
use DPay\Exception\NotFoundException;
use DPay\Exception\PermissionException;
use DPay\Exception\RateLimitException;
use DPay\Http\ApiResponse;

final class ErrorMapper
{
    public function map(ApiResponse $response): ApiErrorException
    {
        $status = $response->getStatus();
        $rawBody = $response->getBody();
        $data = $response->decodeJson() ?? [];

        $message = 'Unexpected API error';
        if (isset($data['message']) && is_string($data['message'])) {
            $message = $data['message'];
        } elseif (isset($data['msg']) && is_string($data['msg'])) {
            $message = $data['msg'];
        }

        $errorCode = null;
        if (isset($data['code']) && is_string($data['code'])) {
            $errorCode = $data['code'];
        } elseif (isset($data['errorcode']) && is_string($data['errorcode'])) {
            $errorCode = $data['errorcode'];
        }
        $reason = isset($data['reason']) && is_string($data['reason']) ? $data['reason'] : null;

        $fieldErrors = $this->normalizeFieldErrors($data['errors'] ?? null);

        if ($status === 429) {
            return new RateLimitException(
                $message,
                $status,
                $this->intHeader($response, 'Retry-After'),
                $this->intHeader($response, 'X-RateLimit-Limit'),
                $this->intHeader($response, 'X-RateLimit-Remaining'),
                $rawBody
            );
        }

        switch (true) {
            case $status === 401:
                return new AuthenticationException($message, $status, $errorCode, $fieldErrors, $rawBody, $reason);
            case $status === 403:
                return new PermissionException($message, $status, $errorCode, $fieldErrors, $rawBody, $reason);
            case $status === 404:
                return new NotFoundException($message, $status, $errorCode, $fieldErrors, $rawBody, $reason);
            case $status === 400 || $status === 422:
                return new InvalidRequestException($message, $status, $errorCode, $fieldErrors, $rawBody, $reason);
            case $status >= 500:
                return new ApiServerException($message, $status, $errorCode, $fieldErrors, $rawBody, $reason);
            default:
                return new ApiErrorException($message, $status, $errorCode, $fieldErrors, $rawBody, $reason);
        }
    }

    /**
     * @param mixed $errors
     * @return array<string, array<int, string>>
     */
    private function normalizeFieldErrors($errors): array
    {
        if (!is_array($errors)) {
            return [];
        }

        $normalized = [];
        foreach ($errors as $field => $messages) {
            if (is_string($messages)) {
                $normalized[(string) $field] = [$messages];
            } elseif (is_array($messages)) {
                $fieldMessages = [];
                foreach ($messages as $singleMessage) {
                    if (is_scalar($singleMessage)) {
                        $fieldMessages[] = (string) $singleMessage;
                    }
                }
                $normalized[(string) $field] = $fieldMessages;
            }
        }

        return $normalized;
    }

    private function intHeader(ApiResponse $response, string $name): ?int
    {
        $value = $response->getHeader($name);

        return $value === null ? null : (int) $value;
    }
}
