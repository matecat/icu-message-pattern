<?php

declare(strict_types=1);

/**
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 26/02/26
 *
 */

namespace Matecat\ICU\Parsing;

use Matecat\ICU\Exceptions\OutOfBoundsException;
use Matecat\ICU\MessagePattern;
use Matecat\ICU\Tokens\Part;
use Matecat\ICU\Tokens\TokenType;

/**
 * Provides read-only access to parsed pattern parts and numeric values.
 *
 * This composed object is returned by {@see MessagePattern::parts()} and contains
 * the accessor methods for querying the parsed token list.
 *
 * @internal This class is not part of the public API and may change between versions.
 */
final class PartAccessor
{
    private ParseContext $ctx;

    public function __construct(ParseContext $ctx)
    {
        $this->ctx = $ctx;
    }

    /**
     * Returns the number of "parts" created by parsing the pattern string.
     * Returns 0 if no pattern has been parsed or clear() was called.
     * @return int the number of pattern parts.
     */
    public function countParts(): int
    {
        return count($this->ctx->parts);
    }

    /**
     * Gets the i-th pattern "part".
     * @param int $i The index of the Part data. (0…countParts()-1)
     * @return Part the i-th pattern "part".
     * @throws OutOfBoundsException if the index i is outside the (0…countParts()-1) range
     */
    public function getPart(int $i): Part
    {
        if (!isset($this->ctx->parts[$i])) {
            throw new OutOfBoundsException('Part index out of range.');
        }
        return $this->ctx->parts[$i];
    }

    /**
     * Returns the Part.TokenType of the i-th pattern "part".
     * Convenience method for getPart(i)->getType().
     * @param int $i The index of the Part data. (0…countParts()-1)
     * @return TokenType The Part.TokenType of the i-th Part.
     * @throws OutOfBoundsException if the index i is outside the (0...countParts()-1) range
     */
    public function getPartType(int $i): TokenType
    {
        return $this->getPart($i)->getType();
    }

    /**
     * Returns the pattern index of the specified pattern "part".
     * Convenience method for getPart(partIndex)->getIndex().
     * @param int $partIndex The index of the Part data. (0...countParts()-1)
     * @return int The pattern index of this Part.
     * @throws OutOfBoundsException if partIndex is outside the (0...countParts()-1) range
     */
    public function getPatternIndex(int $partIndex): int
    {
        return $this->getPart($partIndex)->getIndex();
    }

    /**
     * Returns the substring of the pattern string indicated by the Part.
     * @param Part $part a part of this MessagePattern.
     * @return string the substring associated with part.
     */
    public function getSubstring(Part $part): string
    {
        return mb_substr($this->ctx->msg, $part->getIndex(), $part->getLength());
    }

    /**
     * Compares the part's substring with the input string s.
     * @param Part $part a part of this MessagePattern.
     * @param string $s a string.
     * @return bool true if getSubstring(part) == s.
     */
    public function partSubstringMatches(Part $part, string $s): bool
    {
        return $part->getLength() === mb_strlen($s)
            && mb_substr($this->ctx->msg, $part->getIndex(), $part->getLength()) === $s;
    }

    /**
     * Returns the numeric value associated with an ARG_INT or ARG_DOUBLE.
     * @param Part $part a part of this MessagePattern.
     * @return float the part's numeric value, or NO_NUMERIC_VALUE if this is not a numeric part.
     */
    public function getNumericValue(Part $part): float
    {
        $type = $part->getType();
        if ($type === TokenType::ARG_INT) {
            return (float)$part->getValue();
        }
        if ($type === TokenType::ARG_DOUBLE) {
            return $this->ctx->numericValues[$part->getValue()] ?? MessagePattern::NO_NUMERIC_VALUE;
        }
        return MessagePattern::NO_NUMERIC_VALUE;
    }

    /**
     * Returns the "offset:" value of a PluralFormat argument, or 0 if none is specified.
     * @param int $pluralStart the index of the first PluralFormat argument style part. (0...countParts()-1)
     * @return float the "offset:" value.
     * @throws OutOfBoundsException if pluralStart is outside the (0...countParts()-1) range
     */
    public function getPluralOffset(int $pluralStart): float
    {
        $part = $this->getPart($pluralStart);
        if ($part->getType()->hasNumericValue()) {
            return $this->getNumericValue($part);
        }
        return 0.0;
    }

    /**
     * Returns the index of the ARG|MSG_LIMIT part corresponding to the ARG|MSG_START at start.
     * @param int $start The index of some Part data (0...countParts()-1);
     *        this Part should be of TokenType ARG_START or MSG_START.
     * @return int The first i>start where getPart(i)->getType()==ARG|MSG_LIMIT at the same nesting level,
     *         or start itself if getPartType(msgStart)!=ARG|MSG_START.
     */
    public function getLimitPartIndex(int $start): int
    {
        return $this->ctx->limitPartIndexes[$start] ?? $start;
    }
}

