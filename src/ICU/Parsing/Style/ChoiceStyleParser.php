<?php

declare(strict_types=1);

/**
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 26/02/26
 *
 */

namespace Matecat\ICU\Parsing\Style;

use Closure;
use Matecat\ICU\Exceptions\BadChoicePatternSyntaxException;
use Matecat\ICU\Exceptions\InvalidArgumentException;
use Matecat\ICU\Exceptions\InvalidNumericValueException;
use Matecat\ICU\Exceptions\OutOfBoundsException;
use Matecat\ICU\Exceptions\UnmatchedBracesException;
use Matecat\ICU\Parsing\ParseContext;
use Matecat\ICU\Parsing\Utils\CharUtils;
use Matecat\ICU\Tokens\ArgType;
use Matecat\ICU\Tokens\Part;
use Matecat\ICU\Tokens\TokenType;

/**
 * Handles ChoiceFormat-style parsing for the ICU MessagePattern parser.
 *
 * @internal This class is not part of the public API and may change between versions.
 */
final class ChoiceStyleParser
{
    private ParseContext $ctx;
    private NumericParser $numericParser;

    /**
     * Callback to parseMessage on the main parser.
     * Signature: fn(int $index, int $msgStartLength, int $nestingLevel, ArgType $parentType): int
     * @var Closure(int, int, int, ArgType): int
     */
    private Closure $parseMessage;

    public function __construct(ParseContext $ctx, NumericParser $numericParser, Closure $parseMessage)
    {
        $this->ctx = $ctx;
        $this->numericParser = $numericParser;
        $this->parseMessage = $parseMessage;
    }

    /**
     * Parses a ChoiceFormat pattern.
     *
     * @throws InvalidArgumentException
     * @throws UnmatchedBracesException
     * @throws BadChoicePatternSyntaxException
     * @throws InvalidNumericValueException
     * @throws OutOfBoundsException
     */
    public function parse(int $index, int $nestingLevel, bool $inMessageFormatPattern): int
    {
        $start = $index;
        $index = CharUtils::skipWhiteSpace($this->ctx->msg, $index);
        $length = $this->ctx->msgLength;

        if ($index === $length || CharUtils::charAt($this->ctx->chars, $index) === '}') {
            throw new UnmatchedBracesException(CharUtils::errorContext($this->ctx->msg));
        }

        while (true) {
            $index = $this->parseSelector($index, $start);
            $index = $this->parseSeparator($index, $length, $start);
            $index = ($this->parseMessage)($index, 0, $nestingLevel + 1, ArgType::CHOICE);

            if ($index === $length) {
                return $index;
            }

            if (CharUtils::charAt($this->ctx->chars, $index) === '}') {
                if (!$inMessageFormatPattern) {
                    throw new BadChoicePatternSyntaxException(CharUtils::errorContext($this->ctx->msg, $start));
                }
                return $index;
            }

            $index = CharUtils::skipWhiteSpace($this->ctx->msg, $index + 1);
        }
    }

    /**
     * Parses a numeric selector in a choice pattern.
     *
     * @throws BadChoicePatternSyntaxException
     * @throws OutOfBoundsException
     * @throws InvalidNumericValueException
     */
    private function parseSelector(int $index, int $start): int
    {
        $numberIndex = $index;
        $index = CharUtils::skipDouble($this->ctx->msg, $this->ctx->chars, $this->ctx->msgLength, $index);
        $len = $index - $numberIndex;

        if ($len === 0) {
            throw new BadChoicePatternSyntaxException(CharUtils::errorContext($this->ctx->msg, $start));
        }
        if ($len > Part::MAX_LENGTH) {
            throw new OutOfBoundsException("Choice number too long: " . CharUtils::errorContext($this->ctx->msg, $numberIndex));
        }

        $this->numericParser->parseDoubleValue($numberIndex, $index, true);

        return CharUtils::skipWhiteSpace($this->ctx->msg, $index);
    }

    /**
     * Parses the separator (#, <, or ≤) in a choice pattern.
     *
     * @throws BadChoicePatternSyntaxException
     * @throws InvalidArgumentException
     */
    private function parseSeparator(int $index, int $length, int $start): int
    {
        if ($index === $length) {
            // @codeCoverageIgnoreStart
            throw new BadChoicePatternSyntaxException(CharUtils::errorContext($this->ctx->msg, $start));
            // @codeCoverageIgnoreEnd
        }

        $c = CharUtils::charAt($this->ctx->chars, $index);
        if (!($c === '#' || $c === '<' || CharUtils::startsWithAt($this->ctx->msg, $this->ctx->chars, $this->ctx->msgLength, "≤", $index))) {
            throw new InvalidArgumentException(
                "Expected choice separator (#<≤) instead of '$c' in choice pattern " . CharUtils::errorContext($this->ctx->msg, $start)
            );
        }

        $this->ctx->addPart(TokenType::ARG_SELECTOR, $index, 1, 0);
        return $index + 1;
    }
}


