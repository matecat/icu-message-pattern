<?php
// tests/Utils/ICU/MessagePatternTest.php

declare(strict_types=1);

namespace Matecat\Tests\ICU;

use Exception;
use Matecat\ICU\Exceptions\InvalidArgumentException;
use Matecat\ICU\Exceptions\OutOfBoundsException;
use Matecat\ICU\MessagePattern;
use Matecat\ICU\Tokens\ArgType;
use Matecat\ICU\Tokens\Part;
use Matecat\ICU\Tokens\TokenType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests the functionality of the `MessagePattern` class.
 *
 * @ref https://github.com/unicode-org/icu/blob/4ebbe0c828056017f47d2ba3b8e44d44367282c5/icu4j/samples/src/main/java/com/ibm/icu/samples/text/messagepattern/MessagePatternDemo.java
 *
 * This test suite validates various use cases of parsing and handling message patterns. Each test case ensures
 * that the `MessagePattern` behaves as expected for different styles such as empty patterns, named and numbered
 * arguments, choice style, plural style, and nested structures.
 *
 * The following key features are tested:
 *
 * - Parsing of empty message patterns.
 * - Validating argument names for correct formatting.
 * - Handling of simple message patterns with both named and numbered arguments.
 * - Parsing and validation of choice-style message patterns.
 * - Parsing and validating plural styles, including offsets and `REPLACE_NUMBER` constructs.
 * - Handling select-style and selectordinal-style patterns.
 * - Parsing and analyzing nested select and plural message patterns.
 * - Auto-quoting of apostrophes in message patterns.
 * - Choice-style parsing with special operators such as infinity and less-than-or-equal.
 */
final class MessagePatternTest extends TestCase
{

    /**
     * Tests parsing an empty message pattern.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testParseEmpty(): void
    {
        $pattern = new MessagePattern();
        self::assertEquals(2, $pattern->parse('Hi')->countParts());
    }

    /**
     * Tests parsing a simple message pattern with an argument.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testParse(): void
    {
        $pattern = new MessagePattern();
        self::assertTrue($pattern->parse('Hi {0}')->countParts() > 2);
    }

    /**
     * Tests the validation of argument names.
     *
     * @return void
     */
    #[Test]
    public function testValidateArgumentName(): void
    {
        self::assertSame(0, MessagePattern::validateArgumentName('0'));
        self::assertSame(12, MessagePattern::validateArgumentName('12'));

        self::assertSame(MessagePattern::ARG_NAME_NOT_VALID, MessagePattern::validateArgumentName('01'));
        self::assertSame(MessagePattern::ARG_NAME_NOT_NUMBER, MessagePattern::validateArgumentName('name'));
        self::assertSame(MessagePattern::ARG_NAME_NOT_VALID, MessagePattern::validateArgumentName('bad name'));
    }

    /**
     * Tests parsing a pattern with both named and numbered arguments.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testParseSimpleNamedAndNumberedArgs(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('Hi {0} {name}');

        self::assertTrue($pattern->hasNumberedArguments());
        self::assertTrue($pattern->hasNamedArguments());

        self::assertSame(TokenType::MSG_START, $pattern->getPartType(0));
        self::assertSame(TokenType::MSG_LIMIT, $pattern->getPartType($pattern->countParts() - 1));

        $limit = $pattern->getLimitPartIndex(0);
        self::assertSame($pattern->countParts() - 1, $limit);

        $argNameFound = false;
        for ($i = 0; $i < $pattern->countParts(); $i++) {
            $part = $pattern->getPart($i);
            if ($part->getType() === TokenType::ARG_NAME) {
                $argNameFound = true;
                self::assertSame('name', $pattern->getSubstring($part));
                break;
            }
        }

        self::assertTrue($argNameFound);
    }

    /**
     * Tests parsing a choice-style pattern.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testParseChoiceStyle(): void
    {
        $pattern = new MessagePattern();
        $pattern->parseChoiceStyle('0#no|1#one|2#two');

        $countNumeric = 0;
        $countSelectors = 0;
        for ($i = 0; $i < $pattern->countParts(); $i++) {
            $part = $pattern->getPart($i);
            if ($part->getType()->hasNumericValue()) {
                self::assertSame(floatval($countNumeric), $pattern->getNumericValue($part));
                $countNumeric++;
            }
            if ($part->getType() === TokenType::ARG_SELECTOR) {
                $countSelectors++;
            }
        }

        self::assertTrue($countSelectors === 3);
    }

    /**
     * Tests parsing a plural-style pattern with offset.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testParsePluralStyleAndOffset(): void
    {
        $pattern = new MessagePattern();
        $pattern->parsePluralStyle('offset:1 one{# item} other{# items}');

        self::assertSame(1.0, $pattern->getPluralOffset(0));

        $hasReplaceNumber = false;
        for ($i = 0; $i < $pattern->countParts(); $i++) {
            if ($pattern->getPartType($i) === TokenType::REPLACE_NUMBER) {
                $hasReplaceNumber = true;
                break;
            }
        }

        self::assertTrue($hasReplaceNumber);
    }

    /**
     * Tests parsing a select-style pattern.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testParseSelectStyle(): void
    {
        $pattern = new MessagePattern();
        $pattern->parseSelectStyle('male{He} female{She} other{They}');

        $hasOtherSelector = false;
        for ($i = 0; $i < $pattern->countParts(); $i++) {
            $part = $pattern->getPart($i);
            if ($part->getType() === TokenType::ARG_SELECTOR && $pattern->getSubstring($part) === 'other') {
                $hasOtherSelector = true;
                break;
            }
        }

        self::assertTrue($hasOtherSelector);
    }

    /**
     * Tests auto-quoting of apostrophes in a message pattern.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testAutoQuoteApostropheDeep(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse("I don't {name}");

        self::assertSame("I don''t {name}", $pattern->autoQuoteApostropheDeep());
    }

    /**
     * Tests parsing a plural argument within a MessageFormat pattern.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testParsePluralInMessageFormatPattern(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('{count, plural, one{# file} other{# files}}');

        $argStartFound = false;
        $argType = null;
        for ($i = 0; $i < $pattern->countParts(); $i++) {
            $part = $pattern->getPart($i);
            if ($part->getType() === TokenType::ARG_START) {
                $argStartFound = true;
                $argType = $part->getArgType();
                break;
            }
        }

        self::assertTrue($argStartFound);
        self::assertSame(ArgType::PLURAL, $argType);
    }

    /**
     * Tests parsing nested select and plural patterns.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testParseNestedSelectAndPlural(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('{gender, select, female{{count, plural, one{# file} other{# files}}} other{No files}}');

        $hasSelect = false;
        $hasPlural = false;
        $hasReplaceNumber = false;

        for ($i = 0; $i < $pattern->countParts(); $i++) {
            $part = $pattern->getPart($i);
            if ($part->getType() === TokenType::ARG_START) {
                if ($part->getArgType() === ArgType::SELECT) {
                    $hasSelect = true;
                }
                if ($part->getArgType() === ArgType::PLURAL) {
                    $hasPlural = true;
                }
            }
            if ($part->getType() === TokenType::REPLACE_NUMBER) {
                $hasReplaceNumber = true;
            }
        }

        self::assertTrue($hasSelect);
        self::assertTrue($hasPlural);
        self::assertTrue($hasReplaceNumber);
    }

    /**
     * Tests parsing a selectordinal-style pattern.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testParseSelectOrdinalStyle(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('{pos, selectordinal, one{#st} two{#nd} few{#rd} other{#th}}');

        $hasSelectOrdinal = false;
        for ($i = 0; $i < $pattern->countParts(); $i++) {
            $part = $pattern->getPart($i);
            if ($part->getType() === TokenType::ARG_START && $part->getArgType() === ArgType::SELECTORDINAL) {
                $hasSelectOrdinal = true;
                break;
            }
        }

        self::assertTrue($hasSelectOrdinal);
    }

    /**
     * Tests parsing a choice-style pattern with infinity and less-than-or-equal operators.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testParseChoiceStyleWithInfinityAndLeq(): void
    {
        $pattern = new MessagePattern();
        $pattern->parseChoiceStyle('0#none|1<single|∞≤many');

        $selectorCount = 0;
        for ($i = 0; $i < $pattern->countParts(); $i++) {
            if ($pattern->getPartType($i) === TokenType::ARG_SELECTOR) {
                $selectorCount++;
            }
        }
        self::assertEquals(12, $pattern->countParts());
        self::assertSame(3, $selectorCount);

        /*
         * JAVA ICU object printed
         *
         * message: 0#none|1<single|∞≤many
         *
         * 0: ARG_INT(0)@0="0"=0.0
         * 1: ARG_SELECTOR(0)@1="#"
         * 2:   MSG_START(1)@2
         * 3:   MSG_LIMIT(1)@6="|"
         * 4: ARG_INT(1)@7="1"=1.0
         * 5: ARG_SELECTOR(0)@8="<"
         * 6:   MSG_START(1)@9
         * 7:   MSG_LIMIT(1)@15="|"
         * 8: ARG_DOUBLE(0)@16="∞"=Infinity
         * 9: ARG_SELECTOR(0)@17="≤"
         *10:   MSG_START(1)@18
         *11:   MSG_LIMIT(1)@22
         */

        $expected = [
            [TokenType::ARG_INT, 0, 1, 0, '0', 0.0],
            [TokenType::ARG_SELECTOR, 1, 1, 0, '#', null],
            [TokenType::MSG_START, 2, 0, 1, '', null],
            [TokenType::MSG_LIMIT, 6, 1, 1, '|', null],
            [TokenType::ARG_INT, 7, 1, 1, '1', 1.0],
            [TokenType::ARG_SELECTOR, 8, 1, 0, '<', null],
            [TokenType::MSG_START, 9, 0, 1, '', null],
            [TokenType::MSG_LIMIT, 15, 1, 1, '|', null],
            [TokenType::ARG_DOUBLE, 16, 1, 0, '∞', INF],
            [TokenType::ARG_SELECTOR, 17, 1, 0, '≤', null],
            [TokenType::MSG_START, 18, 0, 1, '', null],
            [TokenType::MSG_LIMIT, 22, 0, 1, '', null],
        ];

        self::assertSame(count($expected), $pattern->countParts());

        foreach ($pattern as $i => $part) {
            [$type, $index, $length, $value, $substring, $numeric] = $expected[$i];

            self::assertSame($type, $part->getType(), "Part #$i type");
            self::assertSame($index, $part->getIndex(), "Part #$i index");
            self::assertSame($length, $part->getLength(), "Part #$i length");
            self::assertSame($value, $part->getValue(), "Part #$i value");

            self::assertSame($substring, $pattern->getSubstring($part), "Part #$i substring");

            if ($numeric !== null) {
                self::assertSame($numeric, $pattern->getNumericValue($part), "Part #$i numeric");
            }
        }
    }

    /**
     * Tests parsing a complex pattern with quoted literals.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testParseComplexQuotedPattern(): void
    {
        $pattern = new MessagePattern();
        $input = <<<'MSG'
I don't {a,plural,other{w'{'on't #'#'}} and {b,select,other{shan't'}'}} '{'''know'''}' and {c,choice,0#can't'|'}{z,number,#'#'###.00'}'}.
MSG;

        $pattern->parse($input);

        /*
         * Java ICU object printed
         * 0: MSG_START(0)@0
         * 1: INSERT_CHAR(39)@6
         * 2: ARG_START(PLURAL)@8="{"
         * 3: ARG_NAME(0)@9="a"
         * 4: ARG_SELECTOR(0)@18="other"
         * 5: MSG_START(1)@23="{"
         * 6: SKIP_SYNTAX(0)@25="'"
         * 7: SKIP_SYNTAX(0)@27="'"
         * 8: INSERT_CHAR(39)@31
         * 9: REPLACE_NUMBER(0)@33="#"
         * 10: SKIP_SYNTAX(0)@34="'"
         * 11: SKIP_SYNTAX(0)@36="'"
         * 12: MSG_LIMIT(1)@37="}"
         * 13: ARG_LIMIT(PLURAL)@38="}"
         * 14: ARG_START(SELECT)@44="{"
         * 15: ARG_NAME(0)@45="b"
         * 16: ARG_SELECTOR(0)@54="other"
         * 17: MSG_START(1)@59="{"
         * 18: INSERT_CHAR(39)@65
         * 19: SKIP_SYNTAX(0)@66="'"
         * 20: SKIP_SYNTAX(0)@68="'"
         * 21: MSG_LIMIT(1)@69="}"
         * 22: ARG_LIMIT(SELECT)@70="}"
         * 23: SKIP_SYNTAX(0)@72="'"
         * 24: SKIP_SYNTAX(0)@75="'"
         * 25: SKIP_SYNTAX(0)@76="'"
         * 26: SKIP_SYNTAX(0)@82="'"
         * 27: SKIP_SYNTAX(0)@83="'"
         * 28: SKIP_SYNTAX(0)@85="'"
         * 29: ARG_START(CHOICE)@91="{"
         * 30: ARG_NAME(0)@92="c"
         * 31: ARG_INT(0)@101="0"=0.0
         * 32: ARG_SELECTOR(0)@102="#"
         * 33: MSG_START(1)@103
         * 34: INSERT_CHAR(39)@107
         * 35: SKIP_SYNTAX(0)@108="'"
         * 36: SKIP_SYNTAX(0)@110="'"
         * 37: MSG_LIMIT(1)@111
         * 38: ARG_LIMIT(CHOICE)@111="}"
         * 39: ARG_START(SIMPLE)@112="{"
         * 40: ARG_NAME(0)@113="z"
         * 41: ARG_TYPE(0)@115="number"
         * 42: ARG_STYLE(0)@122="#'#'###.00'}'"
         * 43: ARG_LIMIT(SIMPLE)@135="}"
         * 44: MSG_LIMIT(0)@137
         */

        $expected = [
            [TokenType::MSG_START, 0, 0, 0, '', null, null],
            [TokenType::INSERT_CHAR, 6, 0, 39, '', null, null],
            [TokenType::ARG_START, 8, 1, 0, '{', null, ArgType::PLURAL],
            [TokenType::ARG_NAME, 9, 1, 0, 'a', null, null],
            [TokenType::ARG_SELECTOR, 18, 5, 0, 'other', null, null],
            [TokenType::MSG_START, 23, 1, 1, '{', null, null],
            [TokenType::SKIP_SYNTAX, 25, 1, 0, "'", null, null],
            [TokenType::SKIP_SYNTAX, 27, 1, 0, "'", null, null],
            [TokenType::INSERT_CHAR, 31, 0, 39, '', null, null],
            [TokenType::REPLACE_NUMBER, 33, 1, 0, '#', null, null],
            [TokenType::SKIP_SYNTAX, 34, 1, 0, "'", null, null],
            [TokenType::SKIP_SYNTAX, 36, 1, 0, "'", null, null],
            [TokenType::MSG_LIMIT, 37, 1, 1, '}', null, null],
            [TokenType::ARG_LIMIT, 38, 1, 0, '}', null, ArgType::PLURAL],
            [TokenType::ARG_START, 44, 1, 0, '{', null, ArgType::SELECT],
            [TokenType::ARG_NAME, 45, 1, 0, 'b', null, null],
            [TokenType::ARG_SELECTOR, 54, 5, 0, 'other', null, null],
            [TokenType::MSG_START, 59, 1, 1, '{', null, null],
            [TokenType::INSERT_CHAR, 65, 0, 39, '', null, null],
            [TokenType::SKIP_SYNTAX, 66, 1, 0, "'", null, null],
            [TokenType::SKIP_SYNTAX, 68, 1, 0, "'", null, null],
            [TokenType::MSG_LIMIT, 69, 1, 1, '}', null, null],
            [TokenType::ARG_LIMIT, 70, 1, 0, '}', null, ArgType::SELECT],
            [TokenType::SKIP_SYNTAX, 72, 1, 0, "'", null, null],
            [TokenType::SKIP_SYNTAX, 75, 1, 0, "'", null, null],
            [TokenType::SKIP_SYNTAX, 76, 1, 0, "'", null, null],
            [TokenType::SKIP_SYNTAX, 82, 1, 0, "'", null, null],
            [TokenType::SKIP_SYNTAX, 83, 1, 0, "'", null, null],
            [TokenType::SKIP_SYNTAX, 85, 1, 0, "'", null, null],
            [TokenType::ARG_START, 91, 1, 0, '{', null, ArgType::CHOICE],
            [TokenType::ARG_NAME, 92, 1, 0, 'c', null, null],
            [TokenType::ARG_INT, 101, 1, 0, '0', 0.0, null],
            [TokenType::ARG_SELECTOR, 102, 1, 0, '#', null, null],
            [TokenType::MSG_START, 103, 0, 1, '', null, null],
            [TokenType::INSERT_CHAR, 107, 0, 39, '', null, null],
            [TokenType::SKIP_SYNTAX, 108, 1, 0, "'", null, null],
            [TokenType::SKIP_SYNTAX, 110, 1, 0, "'", null, null],
            [TokenType::MSG_LIMIT, 111, 0, 1, '', null, null],
            [TokenType::ARG_LIMIT, 111, 1, 0, '}', null, ArgType::CHOICE],
            [TokenType::ARG_START, 112, 1, 0, '{', null, ArgType::SIMPLE],
            [TokenType::ARG_NAME, 113, 1, 0, 'z', null, null],
            [TokenType::ARG_TYPE, 115, 6, 0, 'number', null, null],
            [TokenType::ARG_STYLE, 122, 13, 0, "#'#'###.00'}'", null, null],
            [TokenType::ARG_LIMIT, 135, 1, 0, '}', null, ArgType::SIMPLE],
            [TokenType::MSG_LIMIT, 137, 0, 0, '', null, null],
        ];

        self::assertSame(count($expected), $pattern->countParts());

        foreach ($pattern as $i => $part) {
            [$type, $index, $length, $value, $substring, $numeric, $argType] = $expected[$i];

            self::assertSame($type, $part->getType(), "Part #$i type");
            self::assertSame($index, $part->getIndex(), "Part #$i index");
            self::assertSame($length, $part->getLength(), "Part #$i length");
            self::assertSame($substring, $pattern->getSubstring($part), "Part #$i substring");

            if ($argType !== null) {
                self::assertSame($argType, $part->getArgType(), "Part #$i argType");
            } else {
                self::assertSame($value, $part->getValue(), "Part #$i value");
            }

            if ($numeric !== null) {
                self::assertSame($numeric, $pattern->getNumericValue($part), "Part #$i numeric");
            }
        }
    }

    /**
     * Tests parsing a plural-style pattern with an explicit selector.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testParsePluralStyleWithExplicitSelector(): void
    {
        $pattern = new MessagePattern();
        $pattern->parsePluralStyle('=0{none} one{# item} other{# items}');

        $numericValues = [];
        for ($i = 0; $i < $pattern->countParts(); $i++) {
            $part = $pattern->getPart($i);
            if ($part->getType()->hasNumericValue()) {
                $numericValues[] = $pattern->getNumericValue($part);
            }
        }

        self::assertTrue(in_array(0.0, $numericValues, true));
    }

    /**
     * Tests auto-quoting with quoted literals.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testAutoQuoteApostropheWithQuotedLiterals(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse("He said it's(?!) '{name}' and it's ok");

        self::assertSame("He said it''s(?!) '{name}' and it''s ok", $pattern->autoQuoteApostropheDeep());
    }

    /**
     * Tests the clear() method to reset the pattern.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testClear(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('Hi {0}');
        self::assertGreaterThan(0, $pattern->countParts());

        $pattern->clear();
        self::assertSame(0, $pattern->countParts());
        self::assertSame('', $pattern->getPatternString());
        self::assertFalse($pattern->hasNamedArguments());
        self::assertFalse($pattern->hasNumberedArguments());
    }

    /**
     * Tests clearPatternAndSetApostropheMode() method.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testClearPatternAndSetApostropheMode(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('Hi {0}');

        $pattern->clearPatternAndSetApostropheMode(MessagePattern::APOSTROPHE_DOUBLE_REQUIRED);
        self::assertSame(0, $pattern->countParts());
        self::assertSame(MessagePattern::APOSTROPHE_DOUBLE_REQUIRED, $pattern->getApostropheMode());
    }

    /**
     * Tests getting the apostrophe mode when set to DOUBLE_REQUIRED.
     *
     * @return void
     */
    #[Test]
    public function testGetApostropheModeDoubleRequired(): void
    {
        $pattern = new MessagePattern(null, MessagePattern::APOSTROPHE_DOUBLE_REQUIRED);
        self::assertSame(MessagePattern::APOSTROPHE_DOUBLE_REQUIRED, $pattern->getApostropheMode());
    }

    /**
     * Tests the partSubstringMatches() method.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testPartSubstringMatches(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('{name}');

        $part = $pattern->getPart(2);
        self::assertTrue($pattern->partSubstringMatches($part, 'name'));
        self::assertFalse($pattern->partSubstringMatches($part, 'other'));
        self::assertFalse($pattern->partSubstringMatches($part, 'names'));
    }

    /**
     * Tests getNumericValue() with a non-numeric part.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testGetNumericValueWithNonNumeric(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('{name}');

        $part = $pattern->getPart(1);
        self::assertSame(MessagePattern::NO_NUMERIC_VALUE, $pattern->getNumericValue($part));
    }

    /**
     * Tests getPluralOffset() when no offset is specified.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testGetPluralOffsetWithoutOffset(): void
    {
        $pattern = new MessagePattern();
        $pattern->parsePluralStyle('one{# item} other{# items}');

        self::assertSame(0.0, $pattern->getPluralOffset(0));
    }

    /**
     * Tests getPatternIndex() method.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testGetPatternIndex(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('Hi {0}');

        self::assertSame(0, $pattern->getPatternIndex(0));
    }

    /**
     * Tests that getPart() throws OutOfBoundsException for invalid index.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testGetPartOutOfBounds(): void
    {
        $this->expectException(OutOfBoundsException::class);

        $pattern = new MessagePattern();
        $pattern->parse('Hi');
        $pattern->getPart(999);
    }

    /**
     * Tests that unmatched opening braces throw InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testUnmatchedOpeningBrace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unmatched '{'");

        $pattern = new MessagePattern();
        $pattern->parse('Hi {name');
    }

    /**
     * Tests that bad argument syntax without comma or brace throws InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testBadArgumentSyntaxNoCommaOrBrace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bad argument syntax');

        $pattern = new MessagePattern();
        $pattern->parse('Hi {name?}');
    }

    /**
     * Tests that invalid characters in argument syntax throw InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testBadArgumentSyntaxInvalidChar(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bad argument syntax');

        $pattern = new MessagePattern();
        $pattern->parse('Hi {na me}');
    }

    /**
     * Tests that overly large argument numbers throw OutOfBoundsException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testArgumentNumberTooLarge(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Argument number too large');

        $pattern = new MessagePattern();
        $pattern->parse('{99999999999999999999}');
    }

    /**
     * Tests that overly long argument names throw OutOfBoundsException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testArgumentNameTooLong(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Argument name too long');

        $pattern = new MessagePattern();
        $longName = str_repeat('a', Part::MAX_LENGTH + 1);
        $pattern->parse("{{$longName}}");
    }

    /**
     * Tests that overly long argument type names throw OutOfBoundsException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testArgumentTypeNameTooLong(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Argument type name too long');

        $pattern = new MessagePattern();
        $longType = str_repeat('a', Part::MAX_LENGTH + 1);
        $pattern->parse("{name, $longType}");
    }

    /**
     * Tests that missing style for complex argument throws InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testNoStyleForComplexArgument(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No style field for complex argument');

        $pattern = new MessagePattern();
        $pattern->parse('{name, plural}');
    }

    /**
     * Tests that unterminated quoted literal in simple style throws InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testSimpleStyleQuotedLiteralUnterminated(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quoted literal argument style text reaches to the end');

        $pattern = new MessagePattern();
        $pattern->parse("{name, number, '#.00}");
    }

    /**
     * Tests that overly long simple style throws OutOfBoundsException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testSimpleStyleTooLong(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Argument style text too long');

        $pattern = new MessagePattern();
        $longStyle = str_repeat('a', Part::MAX_LENGTH + 1);
        $pattern->parse("{name, number, $longStyle}");
    }

    /**
     * Tests that missing pattern in choice style throws InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testChoiceStyleMissingPattern(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing choice argument pattern');

        $pattern = new MessagePattern();
        $pattern->parseChoiceStyle('');
    }

    /**
     * Tests that bad syntax in choice style throws InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testChoiceStyleBadSyntax(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bad choice pattern syntax');

        $pattern = new MessagePattern();
        $pattern->parseChoiceStyle('abc#test');
    }

    /**
     * Tests that overly long choice numbers throw OutOfBoundsException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testChoiceNumberTooLong(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Choice number too long');

        $pattern = new MessagePattern();
        $longNumber = str_repeat('9', Part::MAX_LENGTH + 1);
        $pattern->parseChoiceStyle("$longNumber#test");
    }

    /**
     * Tests that invalid choice style separator throws InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testChoiceStyleInvalidSeparator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected choice separator');

        $pattern = new MessagePattern();
        $pattern->parseChoiceStyle('0?test');
    }

    /**
     * Tests that missing 'other' in plural style throws InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testPluralStyleMissingOther(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Missing 'other' keyword");

        $pattern = new MessagePattern();
        $pattern->parsePluralStyle('one{# item}');
    }

    /**
     * Tests that bad syntax in explicit plural selector throws InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testPluralStyleExplicitSelectorBadSyntax(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bad plural pattern syntax');

        $pattern = new MessagePattern();
        $pattern->parsePluralStyle('={none} other{items}');
    }

    /**
     * Tests that offset not first in plural style throws InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testPluralStyleOffsetNotFirst(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Plural argument 'offset:' (if present) must precede");

        $pattern = new MessagePattern();
        $pattern->parsePluralStyle('one{# item} offset:1 other{# items}');
    }

    /**
     * Tests that missing offset value in plural style throws InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testPluralStyleOffsetMissingValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Missing value for plural 'offset:'");

        $pattern = new MessagePattern();
        $pattern->parsePluralStyle('offset: other{# items}');
    }

    /**
     * Tests that overly long offset value in plural style throws OutOfBoundsException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testPluralStyleOffsetValueTooLong(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Plural offset value too long');

        $pattern = new MessagePattern();
        $longNumber = str_repeat('9', Part::MAX_LENGTH + 1);
        $pattern->parsePluralStyle("offset:$longNumber other{# items}");
    }

    /**
     * Tests that overly long selector in plural style throws OutOfBoundsException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testPluralStyleSelectorTooLong(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Argument selector too long');

        $pattern = new MessagePattern();
        $longSelector = str_repeat('a', Part::MAX_LENGTH + 1);
        $pattern->parsePluralStyle("$longSelector{test} other{items}");
    }

    /**
     * Tests that missing message after selector in select style throws InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testSelectStyleNoMessageAfterSelector(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No message fragment after select selector');

        $pattern = new MessagePattern();
        $pattern->parseSelectStyle('male other{They}');
    }

    /**
     * Tests that plural without 'other' in message pattern throws InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testMessagePatternPluralWithoutOther(): void
    {
        // Test using full message pattern syntax with plural style but NO other keyword
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Missing 'other' keyword");

        $pattern = new MessagePattern();
        // This pattern has a plural style with 'one' selector but NO 'other' selector
        $pattern->parse('{count, plural, one{# item}}');
    }

    /**
     * Tests that plural with 'few' but without 'other' throws InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testMessagePatternPluralFewWithoutOther(): void
    {
        // Test with multiple selectors but still missing 'other' keyword
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Missing 'other' keyword");

        $pattern = new MessagePattern();
        // Russian plural form with one/few/many but NO 'other'
        $pattern->parse('{count, plural, one{# item} few{# items} many{# items}}');
    }

    /**
     * Tests auto-quoting when no changes are needed.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testAutoQuoteApostropheDeepNoChanges(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('Hi {name} test');

        self::assertSame('Hi {name} test', $pattern->autoQuoteApostropheDeep());
    }

    /**
     * Tests parsing in DOUBLE_REQUIRED mode with quoting.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testParseDoubleRequiredModeWithQuoting(): void
    {
        $pattern = new MessagePattern(null, MessagePattern::APOSTROPHE_DOUBLE_REQUIRED);
        $pattern->parse("It's 'quoted'");

        self::assertGreaterThan(2, $pattern->countParts());
    }

    /**
     * Tests parsing a simple argument with type and style.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testParseSimpleArgWithType(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('{name, number, #.00}');

        $hasArgType = false;
        $hasArgStyle = false;
        for ($i = 0; $i < $pattern->countParts(); $i++) {
            $type = $pattern->getPartType($i);
            if ($type === TokenType::ARG_TYPE) {
                $hasArgType = true;
            }
            if ($type === TokenType::ARG_STYLE) {
                $hasArgStyle = true;
            }
        }

        self::assertTrue($hasArgType);
        self::assertTrue($hasArgStyle);
    }

    /**
     * Tests parsing nested braces in simple style.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testParseNestedBracesInSimpleStyle(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('{name, number, {nested}}');

        $styleFound = false;
        for ($i = 0; $i < $pattern->countParts(); $i++) {
            if ($pattern->getPartType($i) === TokenType::ARG_STYLE) {
                $styleFound = true;
                break;
            }
        }

        self::assertTrue($styleFound);
    }

    /**
     * Tests parsing negative double values in choice style.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testParseNegativeDoubleInChoice(): void
    {
        $pattern = new MessagePattern();
        $pattern->parseChoiceStyle('-5.5#negative|0#zero|5.5#positive');

        $numericValues = [];
        for ($i = 0; $i < $pattern->countParts(); $i++) {
            $part = $pattern->getPart($i);
            if ($part->getType()->hasNumericValue()) {
                $numericValues[] = $pattern->getNumericValue($part);
            }
        }

        self::assertContains(-5.5, $numericValues);
        self::assertContains(0.0, $numericValues);
        self::assertContains(5.5, $numericValues);
    }

    /**
     * Tests parsing large integer values.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testParseLargeIntegerValue(): void
    {
        $pattern = new MessagePattern();
        $pattern->parseChoiceStyle('999999999999999#huge');

        $hasDouble = false;
        for ($i = 0; $i < $pattern->countParts(); $i++) {
            if ($pattern->getPartType($i) === TokenType::ARG_DOUBLE) {
                $hasDouble = true;
                break;
            }
        }

        self::assertTrue($hasDouble);
    }

    /**
     * Tests parsing negative infinity in choice style.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testParseNegativeInfinity(): void
    {
        $pattern = new MessagePattern();
        $pattern->parseChoiceStyle('-∞#negative infinity|0#zero');

        $hasNegInf = false;
        for ($i = 0; $i < $pattern->countParts(); $i++) {
            $part = $pattern->getPart($i);
            if ($part->getType() === TokenType::ARG_DOUBLE) {
                $value = $pattern->getNumericValue($part);
                if ($value === -INF) {
                    $hasNegInf = true;
                    break;
                }
            }
        }

        self::assertTrue($hasNegInf);
    }

    /**
     * Tests that bad infinity syntax throws InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testBadSyntaxInfinity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bad syntax for numeric value');

        $pattern = new MessagePattern();
        $pattern->parseChoiceStyle('∞5#bad');
    }

    /**
     * Tests validation of argument names with leading zeros.
     *
     * @return void
     */
    #[Test]
    public function testValidateArgumentNameLeadingZero(): void
    {
        self::assertSame(MessagePattern::ARG_NAME_NOT_VALID, MessagePattern::validateArgumentName('01'));
        self::assertSame(MessagePattern::ARG_NAME_NOT_VALID, MessagePattern::validateArgumentName('0123'));
    }

    /**
     * Tests validation of empty argument names.
     *
     * @return void
     */
    #[Test]
    public function testValidateArgumentNameEmpty(): void
    {
        self::assertSame(MessagePattern::ARG_NAME_NOT_VALID, MessagePattern::validateArgumentName(''));
    }

    /**
     * Tests validation of mixed alphanumeric argument names.
     *
     * @return void
     */
    #[Test]
    public function testValidateArgumentNameMixed(): void
    {
        self::assertSame(MessagePattern::ARG_NAME_NOT_NUMBER, MessagePattern::validateArgumentName('test123'));
        self::assertSame(MessagePattern::ARG_NAME_NOT_NUMBER, MessagePattern::validateArgumentName('a1'));
    }

    /**
     * Tests parsing plural with positive explicit number.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testParsePluralWithPositiveExplicitNumber(): void
    {
        $pattern = new MessagePattern();
        $pattern->parsePluralStyle('=5{exactly five} other{not five}');

        $found = false;
        for ($i = 0; $i < $pattern->countParts(); $i++) {
            $part = $pattern->getPart($i);
            if ($part->getType()->hasNumericValue() && $pattern->getNumericValue($part) === 5.0) {
                $found = true;
                break;
            }
        }

        self::assertTrue($found);
    }

    /**
     * Tests that excessive nesting throws OutOfBoundsException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testExcessiveNesting(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Nesting level exceeds maximum value');

        $pattern = new MessagePattern();
        $nested = str_repeat('{a,select,other{', 300) . 'text' . str_repeat('}}', 300);
        $pattern->parse($nested);
    }

    /**
     * Tests that unmatched nested braces throw InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testUnmatchedNestedBraces(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unmatched '{' braces in message ");

        $pattern = new MessagePattern();
        $nested = str_repeat('{a,select,other{', 10) . 'text' . str_repeat('}}', 9);
        $pattern->parse($nested);
    }

    /**
     * Tests that unmatched braces throw InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testUnmatchedBraces(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unmatched '{' braces in message ");

        $pattern = new MessagePattern();
        $nested = 'The house is red {';
        $pattern->parse($nested);
    }

    /**
     * Tests that unmatched braces after argument throw InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testUnmatchedBracesAfterArgument(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unmatched '{' braces in message ");

        $pattern = new MessagePattern();
        $nested = 'The house is red {name, select ';
        $pattern->parse($nested);
    }

    /**
     * Tests that bad argument syntax throws InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testBadArgumentSyntax(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Bad argument syntax: ");

        $pattern = new MessagePattern();
        $nested = 'The house is red {name, select {';
        $pattern->parse($nested);
    }

    /**
     * Tests parsing choice argument in MessageFormat.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testChoiceInMessageFormat(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('{num, choice, 0#none|1#one|2#many}');

        $hasChoice = false;
        for ($i = 0; $i < $pattern->countParts(); $i++) {
            $part = $pattern->getPart($i);
            if ($part->getType() === TokenType::ARG_START && $part->getArgType(
                ) === ArgType::CHOICE) {
                $hasChoice = true;
                break;
            }
        }

        self::assertTrue($hasChoice);
    }

    /**
     * Tests parsing plural with explicit decimal value.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testParsePluralExplicitDecimal(): void
    {
        $pattern = new MessagePattern();
        $pattern->parsePluralStyle('=1.5{one and half} other{not one and half}');

        $found = false;
        for ($i = 0; $i < $pattern->countParts(); $i++) {
            $part = $pattern->getPart($i);
            if ($part->getType()->hasNumericValue() && $pattern->getNumericValue($part) === 1.5) {
                $found = true;
                break;
            }
        }

        self::assertTrue($found);
    }

    /**
     * Tests that bad syntax with ending curly in choice style throws InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testChoiceStyleBadSyntaxEndingCurly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bad choice pattern syntax');

        $pattern = new MessagePattern();
        $pattern->parseChoiceStyle('0#test}');
    }

    /**
     * Tests that leading zero number in argument throws InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testParseWithLeadingZeroNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bad argument syntax');

        $pattern = new MessagePattern();
        $pattern->parse('{01}');
    }

    /**
     * Tests parsing plural style with decimal offset.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testParsePluralStyleWithDecimalOffset(): void
    {
        $pattern = new MessagePattern();
        $pattern->parsePluralStyle('offset:2.5 one{# item} other{# items}');

        self::assertSame(2.5, $pattern->getPluralOffset(0));
    }


    /**
     * Tests that too many numeric values throw OutOfBoundsException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testTooManyNumericValues(): void
    {
        $isCoverage = (bool)count(array_filter($_SERVER['argv'], fn($arg) => str_contains($arg, 'coverage')));

        if ($isCoverage) {
            $this->markTestSkipped(
                'This test is very expensive when coverage is enabled.',
            );
        }

        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Too many numeric values');

        $pattern = new MessagePattern();
        $choices = "";
        for ($i = 0; $i <= (Part::MAX_VALUE + 1); $i++) {
            $choices .= "$i.5#value$i|";
        }
        $choices = rtrim($choices, '|');
        $pattern->parseChoiceStyle($choices);
    }

    /**
     * Tests parsing of invalid numeric values.
     *
     */
    #[Test]
    public function testInvalidNumericValues(): void
    {
        $pattern = new MessagePattern("This {9abc} has {items} items");
        foreach ($pattern as $position => $part) {
            self::assertFalse($part->getType()->hasNumericValue());
            if ($part->getType() == TokenType::ARG_NAME) {
                if ($position < 4) {
                    $this->assertEquals("9abc", $pattern->getSubstring($part));
                    $this->assertTrue($pattern->partSubstringMatches($part, "9abc"));
                } else {
                    $this->assertEquals("items", $pattern->getSubstring($part));
                    $this->assertTrue($pattern->partSubstringMatches($part, "items"));
                }
            }
        }
    }

    /**
     * @return array<string[]>
     */
    public static function gimmeBadICUPatterns(): array
    {
        // A pattern with an unterminated quote inside a complex argument
        return [
            ['{rank, plural, =+ {# points} one {# point} other {# points}'],
            ['{rank, plural, =- {# points} one {# point} other {# points}'],
        ];
    }

    /**
     * Tests that invalid number value throws InvalidArgumentException.
     *
     * @param string $patternString The pattern string to test.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[DataProvider('gimmeBadICUPatterns')]
    #[Test]
    public function testInvalidNumberValueForValue(string $patternString): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bad syntax for numeric value.');

        new MessagePattern($patternString);
    }

    /**
     * Tests parsing plural with explicit positive number.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testParsePluralWithExplicitPlusNumber(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('{rank, plural, =+1 {# points} one {# point} other {# points}}');

        $numericValues = [];
        $hasReplaceNumber = false;

        for ($i = 0; $i < $pattern->countParts(); $i++) {
            $part = $pattern->getPart($i);
            if ($part->getType()->hasNumericValue()) {
                $numericValues[] = $pattern->getNumericValue($part);
            }
            if ($part->getType() === TokenType::REPLACE_NUMBER) {
                $hasReplaceNumber = true;
            }
        }

        self::assertTrue(in_array(1.0, $numericValues, true));
        self::assertTrue($hasReplaceNumber);
    }

    /**
     * Tests that overly long argument selector throws OutOfBoundsException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testArgumentSelectorTooLong(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Argument selector too long:');

        new MessagePattern(
            '{rank, plural, =' . str_repeat('9', Part::MAX_LENGTH + 1) . ' {# points} one {# point} other {# points}}'
        );
    }

    /**
     * Tests parsing unsupported date/time style.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testParseArgTypeUnsupportedLength(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('{name, date, short}');

        $hasSimple = false;
        for ($i = 0; $i < $pattern->countParts(); $i++) {
            $part = $pattern->getPart($i);
            if ($part->getType() === TokenType::ARG_START && $part->getArgType() === ArgType::SIMPLE) {
                $hasSimple = true;
                break;
            }
        }

        self::assertTrue($hasSimple);
    }

    /**
     * Tests unterminated quote at end with DOUBLE_OPTIONAL mode.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testUnterminatedQuoteAtEndWithDoubleOptional(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse("test 'quoted");

        $hasInsertChar = false;
        for ($i = 0; $i < $pattern->countParts(); $i++) {
            if ($pattern->getPartType($i) === TokenType::INSERT_CHAR) {
                $hasInsertChar = true;
                break;
            }
        }

        self::assertTrue($hasInsertChar);
    }

    /**
     * Tests unterminated apostrophe at end of pattern.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testUnterminatedApostropheAtEnd(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse("test'");

        $result = $pattern->autoQuoteApostropheDeep();
        self::assertStringContainsString("'", $result);
    }

    /**
     * Tests that empty selector in plural style throws InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testParsePluralBadSyntaxEmptySelector(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bad plural pattern syntax');

        $pattern = new MessagePattern();
        $pattern->parsePluralStyle('{test} other{items}');
    }

    /**
     * Tests that closing brace in selector throws InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testParseSelectorBadSyntaxClosingBrace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bad plural pattern syntax');

        $pattern = new MessagePattern();
        $pattern->parsePluralStyle('}');
    }

    /**
     * Tests parsing choice style with scientific notation.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testChoiceWithScientificNotation(): void
    {
        $pattern = new MessagePattern();
        $pattern->parseChoiceStyle('1e10#large|0#small');

        $hasLargeNumber = false;
        for ($i = 0; $i < $pattern->countParts(); $i++) {
            $part = $pattern->getPart($i);
            if ($part->getType()->hasNumericValue()) {
                $value = $pattern->getNumericValue($part);
                if ($value === 1e10) {
                    $hasLargeNumber = true;
                    break;
                }
            }
        }

        self::assertTrue($hasLargeNumber);
    }

    /**
     * Tests parsing complex nested choice patterns.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testParseComplexNestedChoice(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('{a,choice,0#zero|1#{b,choice,0#sub-zero|1#sub-one}}');

        $choiceCount = 0;
        for ($i = 0; $i < $pattern->countParts(); $i++) {
            $part = $pattern->getPart($i);
            if ($part->getType() === TokenType::ARG_START && $part->getArgType() === ArgType::CHOICE) {
                $choiceCount++;
            }
        }

        self::assertSame(2, $choiceCount);
    }

    /**
     * Tests parsing complex pattern with apostrophe and select.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testParseComplexApostropheAndSelectPattern(): void
    {
        $pattern = new MessagePattern();
        $input = "I don't '{know}' {gender,select,female{h''er}other{h'im}}.";
        $pattern->parse($input);

        // Expected structural markers based on ICU rules:
        // 0: MSG_START
        // 1: INSERT_CHAR (for the apostrophe in "don't")
        // 2: SKIP_SYNTAX (opening quote for '{know}')
        // 3: SKIP_SYNTAX (closing quote for '{know}')
        // 4: ARG_START (gender)
        // ... internal select parts ...

        // Assert structural markers:
        // 0: MSG_START(0)@0
        self::assertSame(TokenType::MSG_START, $pattern->getPartType(0));
        self::assertSame(0, $pattern->getPart(0)->getValue());

        // 1: INSERT_CHAR(39)@6 (apostrophe in don't)
        self::assertSame(TokenType::INSERT_CHAR, $pattern->getPartType(1));
        self::assertSame(0x27, $pattern->getPart(1)->getValue());
        self::assertSame(6, $pattern->getPart(1)->getIndex());

        // 2: SKIP_SYNTAX(0)@8 (opening quote for '{know}')
        self::assertSame(TokenType::SKIP_SYNTAX, $pattern->getPartType(2));
        self::assertSame(8, $pattern->getPart(2)->getIndex());

        // 3: SKIP_SYNTAX(0)@15 (closing quote for '{know}')
        self::assertSame(TokenType::SKIP_SYNTAX, $pattern->getPartType(3));
        self::assertSame(15, $pattern->getPart(3)->getIndex());

        // 4: ARG_START(SELECT)@17
        self::assertSame(TokenType::ARG_START, $pattern->getPartType(4));
        self::assertSame(ArgType::SELECT, $pattern->getPart(4)->getArgType());

        // 5: ARG_NAME(0)@18 ("gender")
        self::assertSame(TokenType::ARG_NAME, $pattern->getPartType(5));
        self::assertSame('gender', $pattern->getSubstring($pattern->getPart(5)));

        // 6: ARG_SELECTOR(0)@25 ("female")
        self::assertSame(TokenType::ARG_SELECTOR, $pattern->getPartType(6));
        self::assertSame('female', $pattern->getSubstring($pattern->getPart(6)));

        // 7: MSG_START(1)@31 ("{")
        self::assertSame(TokenType::MSG_START, $pattern->getPartType(7));
        self::assertSame(1, $pattern->getPart(7)->getValue());

        // 8: SKIP_SYNTAX(0)@33 (first ' in h''er)
        self::assertSame(TokenType::SKIP_SYNTAX, $pattern->getPartType(8));

        // 9: MSG_LIMIT(1)@37 ("}")
        self::assertSame(TokenType::MSG_LIMIT, $pattern->getPartType(9));

        // 10: ARG_SELECTOR(0)@38 ("other")
        self::assertSame(TokenType::ARG_SELECTOR, $pattern->getPartType(10));
        self::assertSame('other', $pattern->getSubstring($pattern->getPart(10)));

        // 11: MSG_START(1)@43 ("{")
        self::assertSame(TokenType::MSG_START, $pattern->getPartType(11));

        // 12: INSERT_CHAR(39)@46 (apostrophe in h'im)
        self::assertSame(TokenType::INSERT_CHAR, $pattern->getPartType(12));
        self::assertSame(0x27, $pattern->getPart(12)->getValue());

        // 13: MSG_LIMIT(1)@49 ("}")
        self::assertSame(TokenType::MSG_LIMIT, $pattern->getPartType(13));

        // 14: ARG_LIMIT(SELECT)@49 ("}")
        self::assertSame(TokenType::ARG_LIMIT, $pattern->getPartType(14));

        // 15: MSG_LIMIT(0)@51
        self::assertSame(TokenType::MSG_LIMIT, $pattern->getPartType(15));

        // Test auto-quoting logic for this specific pattern
        // "don't" -> "don''t"
        // "{gender...other{h'im}}" -> "{gender...other{h''im}}"
        $expectedAutoQuoted = "I don''t '{know}' {gender,select,female{h''er}other{h''im}}.";
        self::assertSame($expectedAutoQuoted, $pattern->autoQuoteApostropheDeep());
    }

    /**
     * Test appendReducedApostrophes with doubled apostrophes.
     *
     * @return void
     */
    #[Test]
    public function testAppendReducedApostrophes(): void
    {
        $input = "It''s a test''case";
        $out = '';
        MessagePattern::appendReducedApostrophes($input, 0, strlen($input), $out);
        // Doubled apostrophes should be reduced to single apostrophes
        self::assertSame("It's a test'case", $out);

        $out = '';
        MessagePattern::appendReducedApostrophes("test''test", 0, 10, $out);
        self::assertSame("test'test", $out);

        $out = '';
        MessagePattern::appendReducedApostrophes("no apostrophe", 0, 13, $out);
        self::assertSame("no apostrophe", $out);
    }

    /**
     * Test appendReducedApostrophes with no apostrophes.
     *
     * @return void
     */
    #[Test]
    public function testAppendReducedApostrophesNoApostrophes(): void
    {
        $input = "Hello world";
        $out = '';
        MessagePattern::appendReducedApostrophes($input, 0, strlen($input), $out);
        self::assertSame("Hello world", $out);
    }

    /**
     * Test appendReducedApostrophes with partial range.
     *
     * @return void
     */
    #[Test]
    public function testAppendReducedApostrophesPartialRange(): void
    {
        $input = "Hello''world''test";
        $out = '';
        MessagePattern::appendReducedApostrophes($input, 5, 13, $out);
        // Should only process from index 5 to 13: "''world"
        self::assertSame("'world", $out);
    }

    /**
     * Test appendReducedApostrophes with apostrophe at boundary.
     *
     * @return void
     */
    #[Test]
    public function testAppendReducedApostrophesApostropheAtBoundary(): void
    {
        $input = "test''";
        $out = '';
        MessagePattern::appendReducedApostrophes($input, 0, strlen($input), $out);
        self::assertSame("test'", $out);
    }

    /**
     * Test explicit numeric selector with MAX_LENGTH exceeded.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testPluralExplicitSelectorMaxLengthExceeded(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Argument selector too long');

        $pattern = new MessagePattern();
        $longNumber = str_repeat('9', Part::MAX_LENGTH + 1);
        $pattern->parse("{n, plural, =$longNumber{text} other{items}}");
    }

    /**
     * Test plural offset not first with other content.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testPluralOffsetNotFirst(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Plural argument 'offset:' (if present) must precede key-message pairs");

        $pattern = new MessagePattern();
        // offset must come first, before any other selectors
        $pattern->parse("{n, plural, one{# item} offset:1 other{# items}}");
    }

    /**
     * Test plural offset too long value.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testPluralOffsetValueTooLong(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Plural offset value too long');

        $pattern = new MessagePattern();
        $longOffset = str_repeat('9', Part::MAX_LENGTH + 1);
        $pattern->parse("{n, plural, offset:$longOffset one{# item} other{# items}}");
    }

    /**
     * Test keyword selector too long.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testKeywordSelectorTooLong(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Argument selector too long');

        $pattern = new MessagePattern();
        $longSelector = str_repeat('a', Part::MAX_LENGTH + 1);
        $pattern->parse("{n, select, $longSelector{text} other{items}}");
    }

    /**
     * Test auto-quoting with unterminated quoted literal in simple style.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testAutoQuoteUnterminatedQuotedLiteralSimpleStyle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quoted literal argument style text reaches to the end');

        $pattern = new MessagePattern();
        // Quoted literal that reaches end of message without closing quote
        $pattern->parse("{name, number, '#.00");
    }

    /**
     * Test select style with keyword selector too long.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testSelectStyleKeywordSelectorTooLong(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Argument selector too long');

        $pattern = new MessagePattern();
        $longSelector = str_repeat('x', Part::MAX_LENGTH + 1);
        $pattern->parseSelectStyle("$longSelector{text} other{default}");
    }

    /**
     * Test choice style with long selector number.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testChoiceStyleLongSelectorNumber(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Choice number too long');

        $pattern = new MessagePattern();
        $longNumber = str_repeat('9', Part::MAX_LENGTH + 1);
        $pattern->parseChoiceStyle("$longNumber#text");
    }

    /**
     * @return array<string[]>
     */
    public static function gimmeICUPatterns(): array
    {
        // A pattern with an unterminated quote inside a complex argument
        return [
            [
                "{0, select, other{quoted logic starts here: '{9}}",
                "{0, select, other{quoted logic starts here: '{9}}'"
            ],
            [
                "{0, plural,=0{You have no messages} one{You have one message} other{You have a message '# }}",
                "{0, plural,=0{You have no messages} one{You have one message} other{You have a message '# }}'"
            ],
            [
                "Hel'{o!",
                "Hel'{o!'",
            ]
        ];
    }

    /**
     * Tests unterminated quote auto-insertion in plural patterns.
     *
     * @param string $patternString The pattern string to test.
     * @param string $expected The expected result after auto-quoting.
     *
     * @return void
     * @throws OutOfBoundsException
     */
    #[DataProvider('gimmeICUPatterns')]
    #[Test]
    public function testUnterminatedQuoteAutoInsertionInPlural(string $patternString, string $expected): void
    {
        $pattern = new MessagePattern();

        try {
            $pattern->parse($patternString);
        } catch (Exception) {
            //to ignore the exception, the first two provided patterns are invalid ICU patterns
        }

        $hasAutoInsertedQuote = false;
        for ($i = 0; $i < $pattern->countParts(); $i++) {
            $part = $pattern->getPart($i);
            // Look for the INSERT_CHAR part specifically for the apostrophe (0x27)
            if ($part->getType() === TokenType::INSERT_CHAR && $part->getValue() === 0x27) {
                $hasAutoInsertedQuote = true;
                break;
            }
        }

        self::assertTrue(
            $hasAutoInsertedQuote,
            'Parser should have inserted an INSERT_CHAR part for the missing closing quote'
        );

        // Also verify that autoQuoteApostropheDeep handles it
        $result = $pattern->autoQuoteApostropheDeep();
        self::assertEquals($expected, $result);
    }

    /**
     * Tests that unclosed braces in simple pattern throw InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testSimplePatternUnclosedBracesException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unmatched '{' braces in message");

        $pattern = new MessagePattern();
        $pattern->parse('test {argName, number, currency');
    }

    /**
     * Tests the iterator implementation.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testIterator(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('Hi {0}');

        $count = 0;
        foreach ($pattern as $key => $part) {
            self::assertIsInt($key);
            self::assertInstanceOf(Part::class, $part);
            $count++;
        }

        self::assertSame($pattern->countParts(), $count);
    }

    /**
     * Tests the Part __toString() method.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testPartToString(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('{99, plural, one{# item} other{# items}}');

        // Part 0: MSG_START(0)@0
        self::assertSame('MSG_START(0)@0', (string)$pattern->getPart(0));

        // Part 1: ARG_START(PLURAL)@0
        self::assertSame('ARG_START(PLURAL)@0', (string)$pattern->getPart(1));

        // Part 2: ARG_NUMBER(99)@1
        self::assertSame('ARG_NUMBER(99)@1', (string)$pattern->getPart(2));

        // Part 4: ARG_SELECTOR(0)@12
        $selectorPart = $pattern->getPart(3);
        self::assertSame('ARG_SELECTOR(0)@13', (string)$selectorPart);
    }

    /**
     * Tests the Part getLimit() method.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testPartGetLimit(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('{name}');

        // Part 1: ARG_START is '{' at index 0, length 1
        $argStart = $pattern->getPart(1);
        self::assertSame(0, $argStart->getIndex());
        self::assertSame(1, $argStart->getLength());
        self::assertSame(1, $argStart->getLimit());

        // Part 2: ARG_NAME is 'name' at index 1, length 4
        $argName = $pattern->getPart(2);
        self::assertSame(1, $argName->getIndex());
        self::assertSame(4, $argName->getLength());
        self::assertSame(5, $argName->getLimit());

        // Part 3: ARG_LIMIT is '}' at index 5, length 1
        $argLimit = $pattern->getPart(3);
        self::assertSame(5, $argLimit->getIndex());
        self::assertSame(1, $argLimit->getLength());
        self::assertSame(6, $argLimit->getLimit());
    }

    /**
     * Tests that no exceptions are raised with UTF-8 selectors.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testKeywordSelectorNoExceptionsRaisedWithUTF8(): void
    {
        $this->expectNotToPerformAssertions();
        $pattern = new MessagePattern();
        $pattern->parse("{gender, select, male {He is punctual} female {She is punctual} other {They are punctual}}");
        $pattern->parse("{gender, select, male {Lui è puntuale} female {Lei è puntuale} other {Loro sono puntuali}}");
        $pattern->parse("{gender, select, male {他很守时。} female {她很守时。} other {他们很守时。}}");
        $pattern->parse("{gender, select, male {إنه ملتزم بالمواعيد} female {إنها ملتزمة بالمواعيد} other {إنهم ملتزمون بالمواعيد}}");
    }

    /**
     * Tests that unmatched curly braces at the end throw InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testUnmatchedCurlyBracesAtTheEndOfMessage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unmatched '{' braces in message");

        $pattern = new MessagePattern();
        $pattern->parse("{numItems, plural,=0{Non hai messaggi} one{Hai un messaggio} other{Hai # messaggi} ");
    }

}
