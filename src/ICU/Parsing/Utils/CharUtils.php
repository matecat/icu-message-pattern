<?php

declare(strict_types=1);

/**
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 26/02/26
 *
 */

namespace Matecat\ICU\Parsing\Utils;

use Matecat\ICU\MessagePattern;
use Matecat\ICU\Tokens\Part;

/**
 * Static character and string utility methods used by the ICU MessagePattern parser.
 *
 * All methods are pure functions that operate on the data supplied via parameters
 * (the message string, the pre-split chars array, and the message length).
 *
 * @internal This class is not part of the public API and may change between versions.
 */
final class CharUtils
{
    /**
     * Unicode Pattern_White_Space character class fragment for regex.
     */
    public const string PATTERN_WHITE_SPACE = '\x{0009}-\x{000D}\x{0020}\x{0085}\x{200E}\x{200F}\x{2028}\x{2029}';

    /**
     * Unicode Pattern_Syntax character class fragment for regex (excluding some ranges).
     */
    public const string PATTERN_IDENTIFIER =
        '\x{0021}-\x{002F}\x{003A}-\x{0040}\x{005B}-\x{005E}\x{0060}\x{007B}-\x{007E}\x{00A1}-\x{00A7}\x{00A9}' .
        '\x{00AB}\x{00AC}\x{00AE}\x{00B0}\x{00B1}\x{00B6}\x{00BB}\x{00BF}\x{00D7}\x{00F7}\x{2010}-\x{2027}' .
        '\x{2030}-\x{203E}\x{2041}-\x{2053}\x{2055}-\x{205E}\x{2190}-\x{245F}\x{2500}-\x{2775}\x{2794}-\x{2BFF}' .
        '\x{2E00}-\x{2E7F}\x{3001}-\x{3003}\x{3008}-\x{3020}\x{3030}\x{FD3E}\x{FD3F}\x{FE45}\x{FE46}';

    /**
     * Returns the character at the given index from the pre-split chars array.
     *
     * @param string[] $chars The pre-split characters array.
     * @param int $index The character index.
     * @return string The character, or '' if index is out of bounds.
     */
    public static function charAt(array $chars, int $index): string
    {
        return $chars[$index] ?? '';
    }

    /**
     * Finds the position of the first occurrence of a substring in the message string.
     *
     * @param string $msg The message string.
     * @param string $needle The substring to search for.
     * @param int $offset The position to start searching from.
     * @return int|false The position, or false if not found.
     */
    public static function indexOf(string $msg, string $needle, int $offset = 0): int|false
    {
        return mb_strpos($msg, $needle, $offset);
    }

    /**
     * Returns true if the pattern starts with the given string at the given index.
     *
     * @param string $msg The message string.
     * @param string[] $chars The pre-split characters array.
     * @param int $msgLength The message length in characters.
     * @param string $needle The string to look for.
     * @param int $index The index at which to begin searching.
     * @return bool True if the pattern starts with needle at index.
     */
    public static function startsWithAt(string $msg, array $chars, int $msgLength, string $needle, int $index): bool
    {
        $needleLen = mb_strlen($needle);

        if ($index + $needleLen > $msgLength) {
            return false; // @codeCoverageIgnore
        }

        if ($needleLen === 1) {
            return $chars[$index] === $needle;
        }

        return mb_substr($msg, $index, $needleLen) === $needle;
    }

    /**
     * Skips over consecutive whitespace characters starting from the given index.
     *
     * @param string $msg The message string.
     * @param int $index The starting position.
     * @return int The updated index after skipping whitespace.
     */
    public static function skipWhiteSpace(string $msg, int $index): int
    {
        $byteOffset = strlen(mb_substr($msg, 0, $index));
        if (preg_match('#\G[' . self::PATTERN_WHITE_SPACE . ']+#xu', $msg, $m, 0, $byteOffset)) {
            return $index + mb_strlen($m[0]);
        }
        return $index;
    }

    /**
     * Skips over an identifier (non-syntax, non-whitespace chars) starting from the given index.
     *
     * @param string $msg The message string.
     * @param int $index The starting position.
     * @return int The new position after skipping the identifier.
     */
    public static function skipIdentifier(string $msg, int $index): int
    {
        $byteOffset = strlen(mb_substr($msg, 0, $index));
        if (
            preg_match(
                '#\G[^' . self::PATTERN_WHITE_SPACE . self::PATTERN_IDENTIFIER . ']+#xu',
                $msg,
                $m,
                0,
                $byteOffset
            )
        ) {
            return $index + mb_strlen($m[0]);
        }
        return $index;
    }

    /**
     * Skips over a numeric value token starting at the given index.
     *
     * @param string $msg The message string.
     * @param string[] $chars The pre-split characters array.
     * @param int $msgLength The message length in characters.
     * @param int $index The starting index.
     * @return int The index of the first non-numeric character.
     */
    public static function skipDouble(string $msg, array $chars, int $msgLength, int $index): int
    {
        while ($index < $msgLength) {
            $c = $chars[$index] ?? '';
            if (
                (mb_ord($c) < 0x30 && !str_contains("+-.", $c)) ||
                (mb_ord($c) > 0x39 && $c !== 'e' && $c !== 'E' && !self::startsWithAt($msg, $chars, $msgLength, "∞", $index))
            ) {
                break;
            }
            $index++;
        }
        return $index;
    }

    /**
     * Tests whether a character is valid for an argument type identifier (A–Z / a–z).
     */
    public static function isArgTypeChar(?string $c): bool
    {
        if (empty($c)) {
            return false; // @codeCoverageIgnore
        }
        return ctype_alpha($c);
    }

    /**
     * Tests whether a string is a "pattern identifier" (no Pattern_Syntax or Pattern_White_Space).
     */
    public static function isIdentifier(string $s): bool
    {
        return (bool)preg_match('/^[^' . self::PATTERN_WHITE_SPACE . self::PATTERN_IDENTIFIER . ']+$/u', $s);
    }

    /**
     * Tests whether the argument type string is "choice" (case-insensitive) at the given index.
     *
     * @param string[] $chars The pre-split characters array.
     */
    public static function isChoice(string $msg, array $chars, int $msgLength, int $index): bool
    {
        return self::startsWithAt($msg, $chars, $msgLength, 'choice', $index)
            || self::startsWithAt($msg, $chars, $msgLength, 'CHOICE', $index);
    }

    /**
     * Tests whether the argument type string is "plural" (case-insensitive) at the given index.
     *
     * @param string[] $chars The pre-split characters array.
     */
    public static function isPlural(string $msg, array $chars, int $msgLength, int $index): bool
    {
        return self::startsWithAt($msg, $chars, $msgLength, 'plural', $index)
            || self::startsWithAt($msg, $chars, $msgLength, 'PLURAL', $index);
    }

    /**
     * Tests whether the argument type string is "select" (case-insensitive) at the given index.
     *
     * @param string[] $chars The pre-split characters array.
     */
    public static function isSelect(string $msg, array $chars, int $msgLength, int $index): bool
    {
        return self::startsWithAt($msg, $chars, $msgLength, 'select', $index)
            || self::startsWithAt($msg, $chars, $msgLength, 'SELECT', $index);
    }

    /**
     * Tests whether the argument type suffix is "ordinal" (case-insensitive) at the given index.
     *
     * @param string[] $chars The pre-split characters array.
     */
    public static function isOrdinal(string $msg, array $chars, int $msgLength, int $index): bool
    {
        return self::startsWithAt($msg, $chars, $msgLength, 'ordinal', $index)
            || self::startsWithAt($msg, $chars, $msgLength, 'ORDINAL', $index);
    }

    /**
     * Appends a string segment to the output, reducing consecutive apostrophes.
     * Doubled apostrophes (escaped single quotes) are treated as a single apostrophe.
     */
    public static function appendReducedApostrophes(string $s, int $start, int $limit, string &$out): void
    {
        $doubleApos = -1;
        while (true) {
            $i = mb_strpos($s, "'", $start);
            if ($i === false || $i >= $limit) {
                $out .= mb_substr($s, $start, $limit - $start);
                break;
            }
            if ($i === $doubleApos) {
                $out .= "'";
                $start++;
                $doubleApos = -1;
            } else {
                $out .= mb_substr($s, $start, $i - $start);
                $doubleApos = $start = $i + 1;
            }
        }
    }

    /**
     * Generates a preview of the message text for use in error messages.
     * (Named "prefix" in the original ICU4J MessagePattern.java.)
     *
     * @param string $msg The message string.
     * @param int|null $start The starting index. Defaults to 0.
     * @return string A quoted, possibly truncated preview of the message text.
     */
    public static function errorContext(string $msg, ?int $start = null): string
    {
        $max = 24;
        $start = $start ?? 0;

        $prefix = $start === 0 ? '"' : '[at pattern index ' . $start . '] "';
        $substring = mb_substr($msg, $start);

        if (mb_strlen($substring) <= $max) {
            return $prefix . $substring . '"';
        }

        return $prefix . mb_substr($msg, $start, $max - 4) . ' ..."';
    }
}

