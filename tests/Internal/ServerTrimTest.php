<?php

declare(strict_types=1);

namespace DPay\Tests\Internal;

use DPay\Internal\ServerTrim;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * ServerTrim must remove exactly what Laravel 13 Str::trim() removes (the panel API's global TrimStrings).
 */
final class ServerTrimTest extends TestCase
{
    public function testTheSetHas65CodePoints(): void
    {
        self::assertCount(65, ServerTrim::CODE_POINTS);
        self::assertSame(ServerTrim::CODE_POINTS, array_values(array_unique(ServerTrim::CODE_POINTS)));
    }

    public function testEveryCodePointIsTrimmedFromBothEndsButKeptInside(): void
    {
        foreach (ServerTrim::CODE_POINTS as $codePoint) {
            $character = self::character($codePoint);
            $label = sprintf('U+%04X', $codePoint);
            self::assertSame('a' . $character . 'b', ServerTrim::trim($character . 'a' . $character . 'b' . $character), $label);
            self::assertSame('', ServerTrim::trim($character . $character), $label);
        }
    }

    public function testMatchesLaravelStrTrimOverEveryCodePoint(): void
    {
        $trimmedByLaravel = [];
        for ($codePoint = 0; $codePoint <= 0x10FFFF; ++$codePoint) {
            if ($codePoint >= 0xD800 && $codePoint <= 0xDFFF) {
                continue;
            }
            if (self::laravelTrim(self::character($codePoint) . 'x') === 'x') {
                $trimmedByLaravel[] = $codePoint;
            }
        }

        self::assertSame(ServerTrim::CODE_POINTS, $trimmedByLaravel);
    }

    /**
     * @dataProvider vectors
     */
    public function testVectors(string $input, string $expected): void
    {
        self::assertSame($expected, ServerTrim::trim($input));
        self::assertSame($expected, self::laravelTrim($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function vectors(): array
    {
        return [
            'ascii' => [" \t\n\r\x0B\x0C\0RET-1 \n", 'RET-1'],
            'trailing newline' => ["30\n", '30'],
            'nbsp and ideographic space' => ["\u{00A0}\u{3000}reklamacja\u{2028}", 'reklamacja'],
            'invisible' => ["\u{FEFF}\u{200B}zwrot\u{00AD}\u{E0020}", 'zwrot'],
            'inner kept' => ["\u{00A0}a  b\u{00A0}c\u{200B}", "a  b\u{00A0}c"],
            'not in the set' => ["\u{0008}\u{180F}\u{2066}x\u{2069}\u{FFFC}", "\u{0008}\u{180F}\u{2066}x\u{2069}\u{FFFC}"],
            'blank' => ["\u{3000} \u{FEFF}", ''],
            'empty' => ['', ''],
            'multibyte kept' => [' żółć ', 'żółć'],
        ];
    }

    public function testInvalidUtf8IsReturnedUnchanged(): void
    {
        self::assertSame(" \xC3\x28 ", ServerTrim::trim(" \xC3\x28 "));
    }

    public function testTrimStringsWalksNestedArraysAndKeepsOtherTypes(): void
    {
        self::assertSame(
            ['a' => 'x', 'n' => 1, 'b' => true, 'z' => null, 'w' => ['url' => 'https://e.x', 'events' => ['y']]],
            ServerTrim::trimStrings(['a' => ' x ', 'n' => 1, 'b' => true, 'z' => null, 'w' => ['url' => "https://e.x\u{200B}", 'events' => ["\ty"]]])
        );
    }

    public function testRequired(): void
    {
        self::assertSame('abc', ServerTrim::required("\u{00A0}abc\n", 'transaction_id'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('transaction_id must not be empty');
        ServerTrim::required("\u{3000}\u{FEFF}", 'transaction_id');
    }

    private static function character(int $codePoint): string
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

    /**
     * Illuminate\Support\Str::trim($value) of laravel/framework v13.40.0 (no charlist), verbatim.
     */
    private static function laravelTrim(string $value): string
    {
        $invisible = '\x{0009}\x{0020}\x{00A0}\x{00AD}\x{034F}\x{061C}\x{115F}\x{1160}\x{17B4}\x{17B5}\x{180E}\x{2000}'
            . '\x{2001}\x{2002}\x{2003}\x{2004}\x{2005}\x{2006}\x{2007}\x{2008}\x{2009}\x{200A}\x{200B}\x{200C}\x{200D}'
            . '\x{200E}\x{200F}\x{202F}\x{205F}\x{2060}\x{2061}\x{2062}\x{2063}\x{2064}\x{2065}\x{206A}\x{206B}\x{206C}'
            . '\x{206D}\x{206E}\x{206F}\x{3000}\x{2800}\x{3164}\x{FEFF}\x{FFA0}\x{1D159}\x{1D173}\x{1D174}\x{1D175}'
            . '\x{1D176}\x{1D177}\x{1D178}\x{1D179}\x{1D17A}\x{E0020}';
        $trimDefaultCharacters = " \n\r\t\v\0";
        $whitespace = '[\s' . $invisible . $trimDefaultCharacters . ']';

        return preg_replace('~^' . $whitespace . '+|' . $whitespace . '(?<!' . $whitespace . $whitespace . ')' . $whitespace . '*+$~u', '', $value) ?? trim($value);
    }
}
