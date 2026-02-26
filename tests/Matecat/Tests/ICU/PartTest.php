<?php

namespace Matecat\Tests\ICU;

use Matecat\ICU\Exceptions\InvalidArgumentException;
use Matecat\ICU\Exceptions\OutOfBoundsException;
use Matecat\ICU\MessagePattern;
use Matecat\ICU\Tokens\ArgType;
use Matecat\ICU\Tokens\TokenType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests the functionality of the `Part` class.
 *
 * This test suite validates the behavior of Part objects, including
 * their string representation and various accessor methods.
 */
final class PartTest extends TestCase
{
    /**
     * Tests Part __toString() method with ARG_START.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testPartToStringWithArgStart(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('{name}');

        // Find the ARG_START part
        $argStartPart = null;
        foreach ($pattern as $part) {
            if ($part->getType() === TokenType::ARG_START) {
                $argStartPart = $part;
                break;
            }
        }

        self::assertNotNull($argStartPart);
        $stringRepresentation = (string)$argStartPart;

        // Should contain the type name and arg type name (NONE for simple {name})
        self::assertStringContainsString('ARG_START', $stringRepresentation);
        self::assertStringContainsString('NONE', $stringRepresentation);
        self::assertStringContainsString('@0', $stringRepresentation);
    }

    /**
     * Tests Part __toString() method with ARG_LIMIT.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testPartToStringWithArgLimit(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('{name}');

        // Find the ARG_LIMIT part
        $argLimitPart = null;
        foreach ($pattern as $part) {
            if ($part->getType() === TokenType::ARG_LIMIT) {
                $argLimitPart = $part;
                break;
            }
        }

        self::assertNotNull($argLimitPart);
        $stringRepresentation = (string)$argLimitPart;

        // Should contain the type name and value
        self::assertStringContainsString('ARG_LIMIT', $stringRepresentation);
        self::assertStringContainsString('@', $stringRepresentation);
    }

    /**
     * Tests Part __toString() method with value-based part (MSG_START).
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testPartToStringWithMsgStart(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('Hello {name} world');

        // Find the MSG_START part (should be at index 0)
        $msgStartPart = $pattern->parts()->getPart(0);

        self::assertSame(TokenType::MSG_START, $msgStartPart->getType());
        $stringRepresentation = (string)$msgStartPart;

        // Should contain the type name and the value
        self::assertStringContainsString('MSG_START', $stringRepresentation);
        self::assertStringContainsString('0', $stringRepresentation);
        self::assertStringContainsString('@0', $stringRepresentation);
    }

    /**
     * Tests Part __toString() method with INSERT_CHAR part.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testPartToStringWithInsertChar(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse("I don't like it");

        // Find INSERT_CHAR part (apostrophe auto-quoting)
        $insertCharPart = null;
        foreach ($pattern as $part) {
            if ($part->getType() === TokenType::INSERT_CHAR) {
                $insertCharPart = $part;
                break;
            }
        }

        self::assertNotNull($insertCharPart);
        $stringRepresentation = (string)$insertCharPart;

        // Should contain INSERT_CHAR and the character code
        self::assertStringContainsString('INSERT_CHAR', $stringRepresentation);
        self::assertStringContainsString('39', $stringRepresentation); // 0x27 = 39
    }

    /**
     * Tests Part __toString() method with plural argument.
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testPartToStringWithPluralArgument(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('{count, plural, one{# item} other{# items}}');

        // Find the ARG_START part for the plural argument
        $argStartPart = null;
        foreach ($pattern as $part) {
            if ($part->getType() === TokenType::ARG_START && $part->getArgType() === ArgType::PLURAL) {
                $argStartPart = $part;
                break;
            }
        }

        self::assertNotNull($argStartPart);
        $stringRepresentation = (string)$argStartPart;

        // Should contain ARG_START and PLURAL
        self::assertStringContainsString('ARG_START', $stringRepresentation);
        self::assertStringContainsString('PLURAL', $stringRepresentation);
    }
}
