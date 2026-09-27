<?php

declare(strict_types=1);

namespace DPay\Tests\Support;

use UnexpectedValueException;

/**
 * Typed reader of tests/Fixtures/api_vectors.json - checksum and webhook vectors shared by all dpay SDKs.
 */
final class ApiVectors
{
    /** @var array<mixed>|null */
    private static ?array $data = null;

    public static function service(): string
    {
        return self::string(self::data(), 'service');
    }

    public static function secretHash(): string
    {
        return self::string(self::data(), 'secret_hash');
    }

    public static function transactionId(): string
    {
        return self::string(self::data(), 'transaction_id');
    }

    /**
     * @return list<array{name: string, fields: list<string>, checksum: string}>
     */
    public static function secretSecond(): array
    {
        $vectors = [];
        foreach (self::list(self::data(), 'secret_second') as $vector) {
            $vectors[] = ['name' => self::string($vector, 'name'), 'fields' => self::strings($vector, 'fields'), 'checksum' => self::string($vector, 'checksum')];
        }

        return $vectors;
    }

    /**
     * @return list<array{name: string, operation: string, amount: string|null, checksum: string}>
     */
    public static function operation(): array
    {
        $vectors = [];
        foreach (self::list(self::data(), 'operation') as $vector) {
            $amount = $vector['amount'] ?? null;
            $vectors[] = [
                'name' => self::string($vector, 'name'),
                'operation' => self::string($vector, 'operation'),
                'amount' => is_string($amount) ? $amount : null,
                'checksum' => self::string($vector, 'checksum'),
            ];
        }

        return $vectors;
    }

    /**
     * @return list<array{name: string, body: array<mixed>, checksum: string}>
     */
    public static function orderedBody(): array
    {
        $vectors = [];
        foreach (self::list(self::data(), 'ordered_body') as $vector) {
            $body = $vector['body'] ?? null;
            if (!is_array($body)) {
                throw new UnexpectedValueException('ordered_body.body must be an object');
            }
            $vectors[] = ['name' => self::string($vector, 'name'), 'body' => $body, 'checksum' => self::string($vector, 'checksum')];
        }

        return $vectors;
    }

    /**
     * @return array{secret: string, old_secret: string, id: string, timestamp: int, body: string, signature: string, rotation_signature: string}
     */
    public static function webhook(): array
    {
        $webhook = self::data()['webhook'] ?? null;
        if (!is_array($webhook) || !is_int($webhook['timestamp'] ?? null)) {
            throw new UnexpectedValueException('webhook vector is invalid');
        }

        return [
            'secret' => self::string($webhook, 'secret'),
            'old_secret' => self::string($webhook, 'old_secret'),
            'id' => self::string($webhook, 'id'),
            'timestamp' => $webhook['timestamp'],
            'body' => self::string($webhook, 'body'),
            'signature' => self::string($webhook, 'signature'),
            'rotation_signature' => self::string($webhook, 'rotation_signature'),
        ];
    }

    /**
     * @return array<mixed>
     */
    private static function data(): array
    {
        if (self::$data === null) {
            $raw = file_get_contents(__DIR__ . '/../Fixtures/api_vectors.json');
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($decoded)) {
                throw new UnexpectedValueException('tests/Fixtures/api_vectors.json is missing or invalid');
            }
            self::$data = $decoded;
        }

        return self::$data;
    }

    /**
     * @param array<mixed> $data
     * @return list<array<mixed>>
     */
    private static function list(array $data, string $key): array
    {
        $items = $data[$key] ?? null;
        if (!is_array($items)) {
            throw new UnexpectedValueException(sprintf('%s must be a list', $key));
        }
        $list = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new UnexpectedValueException(sprintf('%s must contain objects', $key));
            }
            $list[] = $item;
        }

        return $list;
    }

    /**
     * @param array<mixed> $data
     */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value)) {
            throw new UnexpectedValueException(sprintf('%s must be a string', $key));
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     * @return list<string>
     */
    private static function strings(array $data, string $key): array
    {
        $values = $data[$key] ?? null;
        if (!is_array($values)) {
            throw new UnexpectedValueException(sprintf('%s must be a list', $key));
        }
        $strings = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new UnexpectedValueException(sprintf('%s must contain strings', $key));
            }
            $strings[] = $value;
        }

        return $strings;
    }
}
