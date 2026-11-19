<?php

declare(strict_types=1);

namespace DPay\Internal;

use InvalidArgumentException;

/**
 * The trimming the panel API applies to every request string before it verifies the checksum (Laravel 13 global
 * `TrimStrings` middleware, `Illuminate\Support\Str::trim()`). A signed string with any of these characters at
 * either end would be hashed by the API without them and answered 401 "Unauthorized request", so the SDK sends and
 * signs the trimmed value. Characters inside the string are kept.
 */
final class ServerTrim
{
    /**
     * Code points removed from both ends: `\s` of PCRE2 in UTF/UCP mode (tab, LF, VT, FF, CR, space, NEL, the Z
     * category), NUL and Laravel's `Str::INVISIBLE_CHARACTERS`.
     */
    public const CODE_POINTS = [
        0x0000, 0x0009, 0x000A, 0x000B, 0x000C, 0x000D, 0x0020, 0x0085, 0x00A0, 0x00AD, 0x034F, 0x061C, 0x115F,
        0x1160, 0x1680, 0x17B4, 0x17B5, 0x180E, 0x2000, 0x2001, 0x2002, 0x2003, 0x2004, 0x2005, 0x2006, 0x2007,
        0x2008, 0x2009, 0x200A, 0x200B, 0x200C, 0x200D, 0x200E, 0x200F, 0x2028, 0x2029, 0x202F, 0x205F, 0x2060,
        0x2061, 0x2062, 0x2063, 0x2064, 0x2065, 0x206A, 0x206B, 0x206C, 0x206D, 0x206E, 0x206F, 0x2800, 0x3000,
        0x3164, 0xFEFF, 0xFFA0, 0x1D159, 0x1D173, 0x1D174, 0x1D175, 0x1D176, 0x1D177, 0x1D178, 0x1D179, 0x1D17A,
        0xE0020,
    ];

    /** @var array<string, true>|null UTF-8 encoded CODE_POINTS */
    private static ?array $characters = null;

    public static function trim(string $value): string
    {
        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false) {
            // Not valid UTF-8 - the request cannot be encoded as JSON anyway
            return $value;
        }
        $set = self::characters();
        $start = 0;
        $end = count($characters);
        while ($start < $end && isset($set[$characters[$start]])) {
            ++$start;
        }
        while ($end > $start && isset($set[$characters[$end - 1]])) {
            --$end;
        }

        return implode('', array_slice($characters, $start, $end - $start));
    }

    /**
     * Trims every string in the array, nested arrays included (keys and order are kept).
     *
     * @param array<mixed> $data
     * @return array<mixed>
     */
    public static function trimStrings(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = self::trim($value);
            } elseif (is_array($value)) {
                $data[$key] = self::trimStrings($value);
            }
        }

        return $data;
    }

    /**
     * The trimmed value of a required field; throws when nothing is left (the API would answer 422).
     */
    public static function required(string $value, string $field): string
    {
        $trimmed = self::trim($value);
        if ($trimmed === '') {
            throw new InvalidArgumentException(sprintf('%s must not be empty', $field));
        }

        return $trimmed;
    }

    /**
     * @return array<string, true>
     */
    private static function characters(): array
    {
        if (self::$characters === null) {
            self::$characters = [];
            foreach (self::CODE_POINTS as $codePoint) {
                self::$characters[self::utf8($codePoint)] = true;
            }
        }

        return self::$characters;
    }

    private static function utf8(int $codePoint): string
    {
        if ($codePoint < 0x80) {
            return chr($codePoint);
        }
        if ($codePoint < 0x800) {
            return chr(0xC0 | ($codePoint >> 6)) . chr(0x80 | ($codePoint & 0x3F));
        }
        if ($codePoint < 0x10000) {
            return chr(0xE0 | ($codePoint >> 12)) . chr(0x80 | (($codePoint >> 6) & 0x3F)) . chr(0x80 | ($codePoint & 0x3F));
        }

        return chr(0xF0 | ($codePoint >> 18)) . chr(0x80 | (($codePoint >> 12) & 0x3F))
            . chr(0x80 | (($codePoint >> 6) & 0x3F)) . chr(0x80 | ($codePoint & 0x3F));
    }
}
