<?php

declare(strict_types=1);

namespace DPay\Http;

use DPay\Exception\TransportException;

final class CurlHttpClient implements HttpClientInterface
{
    private int $timeout;

    public function __construct(int $timeout = 30)
    {
        $this->timeout = $timeout;
    }

    public function request(ApiRequest $request): ApiResponse
    {
        $handle = curl_init($request->getUrl());
        if ($handle === false) {
            throw new TransportException('Unable to initialize cURL');
        }

        $responseHeaders = [];
        $headerLines = [];
        foreach ($request->getHeaders() as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $request->getMethod(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_HEADERFUNCTION => static function ($resource, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[trim($parts[0])] = trim($parts[1]);
                }

                return strlen($line);
            },
        ]);

        if ($request->getBody() !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $request->getBody());
        }

        $body = curl_exec($handle);
        if ($body === false) {
            $message = sprintf('cURL error %d: %s', curl_errno($handle), curl_error($handle));
            curl_close($handle);

            throw new TransportException($message);
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return new ApiResponse($status, $responseHeaders, (string) $body);
    }
}
