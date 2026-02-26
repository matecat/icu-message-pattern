<?php

declare(strict_types=1);

/**
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 26/02/26
 *
 */

namespace Matecat\ICU\Parsing;

use Matecat\ICU\Exceptions\OutOfBoundsException;
use Matecat\ICU\Tokens\Part;
use Matecat\ICU\Tokens\TokenType;

/**
 * Holds the mutable shared parsing state for the ICU MessagePattern parser.
 *
 * Both the public facade (MessagePattern) and the internal parser
 * (MessagePatternParser) operate on the same ParseContext instance,
 * eliminating cross-object state copying.
 *
 * @internal This class is not part of the public API and may change between versions.
 */
final class ParseContext
{
    public string $msg = '';
    public bool $hasArgNames = false;
    public bool $hasArgNumbers = false;
    public bool $needsAutoQuoting = false;

    /** @var Part[] */
    public array $parts = [];

    /** @var float[] */
    public array $numericValues = [];

    /** @var int[] */
    public array $limitPartIndexes = [];

    /** @var string[] Pre-split characters of $msg for O(1) random access. */
    public array $chars = [];

    public int $msgLength = 0;

    public string $aposMode;

    public function __construct(string $aposMode)
    {
        $this->aposMode = $aposMode;
    }

    /**
     * Resets all parsing state for a new pattern.
     */
    public function clear(): void
    {
        $this->msg = '';
        $this->chars = [];
        $this->msgLength = 0;
        $this->hasArgNames = false;
        $this->hasArgNumbers = false;
        $this->needsAutoQuoting = false;
        $this->parts = [];
        $this->numericValues = [];
        $this->limitPartIndexes = [];
    }

    /**
     * Prepares the context for parsing a new pattern string.
     */
    public function preParse(string $pattern): void
    {
        $this->msg = $pattern;
        $this->hasArgNames = false;
        $this->hasArgNumbers = false;
        $this->needsAutoQuoting = false;
        $this->parts = [];
        $this->numericValues = [];
        $this->limitPartIndexes = [];
        $this->chars = preg_split('//u', $pattern, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $this->msgLength = mb_strlen($pattern);
    }

    // ──────────────────────────────────────────────
    // Part management
    // ──────────────────────────────────────────────

    /**
     * Appends a new Part to the parts list.
     */
    public function addPart(TokenType $type, int $index, int $length, int $value): void
    {
        $this->parts[] = new Part($type, $index, $length, $value);
    }

    /**
     * Appends a limit Part and records the mapping from the start index.
     */
    public function addLimitPart(int $start, TokenType $type, int $index, int $length, int $value): void
    {
        $this->limitPartIndexes[$start] = count($this->parts);
        $this->addPart($type, $index, $length, $value);
    }

    /**
     * Appends a double-valued Part, recording the numeric value.
     *
     * @throws OutOfBoundsException
     */
    public function addArgDoublePart(float $numericValue, int $start, int $length): void
    {
        $numericIndex = count($this->numericValues);
        if ($numericIndex > Part::MAX_VALUE) {
            throw new OutOfBoundsException("Too many numeric values"); // @codeCoverageIgnore
        }
        $this->numericValues[] = $numericValue;
        $this->addPart(TokenType::ARG_DOUBLE, $start, $length, $numericIndex);
    }

    /**
     * Replaces the value of the Part at the given index.
     */
    public function replacePartValue(int $startIndex, int $newValue): void
    {
        $part = $this->parts[$startIndex] ?? null;
        if ($part === null) {
            return; // @codeCoverageIgnore
        }
        $this->parts[$startIndex] = new Part($part->getType(), $part->getIndex(), $part->getLength(), $newValue);
    }
}
