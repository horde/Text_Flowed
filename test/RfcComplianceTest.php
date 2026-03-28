<?php

declare(strict_types=1);

/**
 * RFC 2646/3676 compliance tests for modern TextFlowed.
 *
 * Tests verify compliance with:
 * - RFC 2646: The Text/Plain Format Parameter (obsoleted by RFC 3676)
 * - RFC 3676: The Text/Plain Format and DelSp Parameters
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Text_Flowed
 * @subpackage UnitTests
 */

namespace Horde\Text\Flowed\Test;

use Horde\Text\Flowed\QuoteMode;
use Horde\Text\Flowed\TextFlowed;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TextFlowed::class)]
#[CoversClass(QuoteMode::class)]
class RfcComplianceTest extends TestCase
{
    /**
     * RFC 3676 Section 4.3: Usenet Signature Convention
     *
     * "A line consisting of the three characters DASH DASH SP (0x2D 0x2D 0x20)
     * is not considered flowed (that is, not a soft line break). This is the
     * Usenet signature convention and allows the receiver to separate the body
     * of the message from the signature."
     */
    public function testSignatureSeparatorNotFlowed(): void
    {
        // Signature separator should not join with previous line
        $text = "Line before \n-- \nSignature line";
        $flowed = new TextFlowed($text);
        $result = $flowed->toFixed();

        $this->assertEquals(
            "Line before\n-- \nSignature line",
            $result,
            'Signature separator should remain on separate line'
        );

        // Even with trailing space on previous line, should not join
        $text2 = "Paragraph text \n-- \nJohn Doe";
        $flowed2 = new TextFlowed($text2);
        $result2 = $flowed2->toFixed();

        $this->assertEquals(
            "Paragraph text\n-- \nJohn Doe",
            $result2,
            'Flowed line before signature should not join with separator'
        );
    }

    /**
     * RFC 3676 Section 4.3: Quoted Signature Separator
     *
     * "An (optionally quoted) line consisting of DASH DASH SP is not
     * considered flowed."
     */
    public function testQuotedSignatureSeparator(): void
    {
        $text = ">Quoted text \n>-- \n>Signature";
        $flowed = new TextFlowed($text);
        $result = $flowed->toFixed();

        $this->assertEquals(
            ">Quoted text\n>-- \n>Signature",
            $result,
            'Quoted signature separator should not be treated as flowed'
        );
    }

    /**
     * RFC 3676 Section 4.2: Generating Format=Flowed
     *
     * "If the paragraph is longer than the maximum line length,
     * then the paragraph is broken into multiple lines. The maximum
     * line length is 78 characters."
     */
    public function testMaxLineLengthEnforcement(): void
    {
        // Create text that exceeds 78 characters
        $longLine = str_repeat('word ', 20); // ~100 chars
        $flowed = new TextFlowed($longLine);
        $flowed->setMaxLength(78);

        $result = $flowed->toFlowed();
        $lines = explode("\n", trim($result));

        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(
                78,
                strlen($line),
                'Lines should not exceed 78 characters'
            );
        }
    }

    /**
     * RFC 2822 Section 2.1.1: Line Length Limits
     * Referenced by RFC 3676 Section 4.2
     *
     * "Each line of characters MUST be no more than 998 characters,
     * and SHOULD be no more than 78 characters, excluding the CRLF."
     */
    public function test998CharacterAbsoluteLimit(): void
    {
        // Create a single word exceeding 998 characters
        $veryLongWord = str_repeat('x', 1200);
        $flowed = new TextFlowed($veryLongWord);

        $result = $flowed->toFlowed();
        $lines = explode("\n", $result);

        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(
                999, // Allow one extra for trailing space
                strlen($line),
                'Lines must not exceed 998 characters per RFC 2822'
            );
        }
    }

    /**
     * RFC 3676 Section 4.2: Space-Stuffing
     *
     * "If the first character of a line is a quote mark (">"), the line
     * MUST be space-stuffed. Space-stuffing is the addition of a space
     * at the beginning of the line."
     *
     * Note: Uses StrictRfc mode where ">" without space is literal content.
     */
    public function testSpaceStuffingQuoteMark(): void
    {
        $text = ">starts with quote mark";
        $flowed = TextFlowed::fromPlainText($text);
        $result = $flowed->toFlowed();

        $this->assertEquals(
            " >starts with quote mark\n",
            $result,
            'Line starting with > must be space-stuffed in StrictRfc mode'
        );
    }

    /**
     * Test EmailConvention mode treats ">" as quote marker.
     *
     * In EmailConvention mode (default), ">text" is treated as
     * quote level 1, matching common email client behavior.
     */
    public function testEmailConventionQuoteDetection(): void
    {
        $text = ">starts with quote mark";
        $flowed = new TextFlowed($text, 'UTF-8', QuoteMode::EmailConvention);
        $result = $flowed->toFlowed();

        $this->assertEquals(
            "> starts with quote mark\n",
            $result,
            'EmailConvention mode treats > as quote marker'
        );

        // Also test default constructor uses EmailConvention
        $flowed2 = new TextFlowed($text);
        $result2 = $flowed2->toFlowed();

        $this->assertEquals(
            "> starts with quote mark\n",
            $result2,
            'Default mode should be EmailConvention'
        );
    }

    /**
     * RFC 3676 Section 4.2: Space-Stuffing for "From "
     *
     * "If a line starts with 'From ' (0x46 0x72 0x6F 0x6D 0x20),
     * the line MUST be space-stuffed."
     */
    public function testSpaceStuffingFrom(): void
    {
        $text = "From the beginning";
        $flowed = new TextFlowed($text);
        $result = $flowed->toFlowed();

        $this->assertEquals(
            " From the beginning\n",
            $result,
            'Line starting with "From " must be space-stuffed'
        );

        // Also test "From" at end of line
        $text2 = "From";
        $flowed2 = new TextFlowed($text2);
        $result2 = $flowed2->toFlowed();

        $this->assertEquals(
            " From\n",
            $result2,
            'Line containing only "From" must be space-stuffed'
        );
    }

    /**
     * RFC 3676 Section 4.2: Space-Stuffing for Leading Space
     *
     * "If the first character of a line is a space, the line MUST be
     * space-stuffed."
     */
    public function testSpaceStuffingLeadingSpace(): void
    {
        $text = " indented text";
        $flowed = new TextFlowed($text);
        $result = $flowed->toFlowed();

        $this->assertEquals(
            "  indented text\n",
            $result,
            'Line with leading space must be space-stuffed'
        );
    }

    /**
     * RFC 3676 Section 4.3: Interpreting Format=Flowed
     *
     * "If the first character of a line is a space, it is deleted.
     * This is the space-unstuffing procedure."
     */
    public function testSpaceUnstuffing(): void
    {
        // Space-stuffed input should have space removed
        $text = " From line\n regular line";
        $flowed = new TextFlowed($text);
        $result = $flowed->toFixed();

        $this->assertEquals(
            "From line\nregular line",
            $result,
            'Leading space should be removed (unstuffed)'
        );
    }

    /**
     * RFC 3676 Section 4.3: Flowed Line Detection
     *
     * "If the line ends with a space, the line is flowed.
     * Otherwise it is fixed."
     */
    public function testFlowedLineDetection(): void
    {
        // Trailing space = flowed (should join with next line)
        $text = "Line one \nLine two";
        $flowed = new TextFlowed($text);
        $result = $flowed->toFixed();

        $this->assertEquals(
            "Line one Line two",
            $result,
            'Lines with trailing space should be joined (flowed)'
        );

        // No trailing space = fixed (should not join)
        $text2 = "Line one\nLine two";
        $flowed2 = new TextFlowed($text2);
        $result2 = $flowed2->toFixed();

        $this->assertEquals(
            "Line one\nLine two",
            $result2,
            'Lines without trailing space should remain separate (fixed)'
        );
    }

    /**
     * RFC 3676 Section 4.3: Quote Depth Changes
     *
     * "If a change in quoting depth occurs on a flowed line, this is an
     * improperly formatted message. The receiver SHOULD handle this error
     * by using the 'quote-depth-wins' rule, which is to ignore the flowed
     * indicator and treat the line as fixed."
     */
    public function testQuoteDepthChangeStopsFlowing(): void
    {
        // Flowed line followed by different quote depth should not join
        $text = "unquoted line \n>quoted line";
        $flowed = new TextFlowed($text);
        $result = $flowed->toFixed();

        $this->assertEquals(
            "unquoted line\n>quoted line",
            $result,
            'Quote depth change should prevent line joining'
        );

        // Different quote levels should not join
        $text2 = ">level 1 \n>>level 2";
        $flowed2 = new TextFlowed($text2);
        $result2 = $flowed2->toFixed();

        $this->assertEquals(
            ">level 1\n>>level 2",
            $result2,
            'Different quote levels should not join'
        );
    }

    /**
     * RFC 3676 Section 4.3: Same Quote Depth Flows Together
     *
     * "Lines at the same quote depth whose lines all end with
     * spaces are flowed together."
     */
    public function testSameQuoteDepthFlowsTogether(): void
    {
        $text = ">quoted one \n>quoted two \n>quoted three";
        $flowed = new TextFlowed($text);
        $result = $flowed->toFixed();

        $this->assertEquals(
            ">quoted one quoted two quoted three",
            $result,
            'Same quote depth with trailing spaces should flow together'
        );

        // Multi-level quotes should also flow at same depth
        $text2 = ">>deep one \n>>deep two";
        $flowed2 = new TextFlowed($text2);
        $result2 = $flowed2->toFixed();

        $this->assertEquals(
            ">>deep one deep two",
            $result2,
            'Multi-level quotes at same depth should flow together'
        );
    }

    /**
     * RFC 3676 Section 4.1: The DelSp Parameter
     *
     * "The DelSp parameter determines whether trailing spaces are deleted
     * from lines. If DelSp is 'yes', any trailing spaces are deleted from
     * the line before concatenating to the next line. If DelSp is 'no'
     * (the default), no deletion is performed."
     */
    public function testDelSpParameterYes(): void
    {
        // With DelSp=yes, trailing space should be deleted before joining
        $text = "word1 \nword2";
        $flowed = new TextFlowed($text);
        $flowed->setDelSp(true);

        $result = $flowed->toFixed();

        // With DelSp, the trailing space is removed, so "word1word2"
        $this->assertEquals(
            "word1word2",
            $result,
            'DelSp=yes should delete trailing space before joining'
        );
    }

    /**
     * RFC 3676 Section 4.1: DelSp Default Behavior
     *
     * "The default value is 'no'."
     */
    public function testDelSpParameterNo(): void
    {
        // With DelSp=no (default), trailing space remains
        $text = "word1 \nword2";
        $flowed = new TextFlowed($text);
        // Don't set DelSp, use default (no)

        $result = $flowed->toFixed();

        // Without DelSp, space remains: "word1 word2"
        $this->assertEquals(
            "word1 word2",
            $result,
            'DelSp=no (default) should preserve trailing space when joining'
        );
    }

    /**
     * RFC 3676 Section 4.2: DelSp in Generation
     *
     * "When generating Format=Flowed text with DelSp='yes', each soft
     * line break is represented by a SP CRLF sequence."
     */
    public function testDelSpGenerationAddsSpace(): void
    {
        // When generating flowed with DelSp, spaces should be added
        $text = "This is a long line that needs wrapping to demonstrate DelSp space addition";
        $flowed = new TextFlowed($text);
        $flowed->setDelSp(true);
        $flowed->setMaxLength(30);

        $result = $flowed->toFlowed();
        $lines = explode("\n", trim($result));

        // Each wrapped line (except last) should have trailing space
        for ($i = 0; $i < count($lines) - 1; $i++) {
            $this->assertMatchesRegularExpression(
                '/\s$/',
                $lines[$i],
                'DelSp=yes should add trailing space on generated flowed lines'
            );
        }
    }

    /**
     * RFC 3676 Section 4.2: Empty Lines
     *
     * "An empty line is represented by an empty line. This is a fixed line
     * and never flowed."
     */
    public function testEmptyLinesAreFixed(): void
    {
        $text = "Paragraph one \n\nParagraph two";
        $flowed = new TextFlowed($text);
        $result = $flowed->toFixed();

        // Empty line should remain as paragraph separator
        $this->assertStringContainsString(
            "\n\n",
            $result,
            'Empty lines should remain as paragraph separators'
        );
    }

    /**
     * RFC 3676 Section 4.3: Multi-level Quote Depth
     *
     * "Each '>' character indicates one level of quoting."
     */
    public function testMultiLevelQuoting(): void
    {
        $text = ">>>triple quoted \n>>>continues here";
        $flowed = new TextFlowed($text);
        $result = $flowed->toFixed();

        $this->assertEquals(
            ">>>triple quoted continues here",
            $result,
            'Multi-level quotes should flow together at same depth'
        );

        // Test mixed depths don't flow
        $text2 = ">>double \n>>>triple";
        $flowed2 = new TextFlowed($text2);
        $result2 = $flowed2->toFixed();

        $this->assertEquals(
            ">>double\n>>>triple",
            $result2,
            'Different quote depths should not flow together'
        );
    }

    /**
     * RFC 3676 Section 4.5: Quoting Text
     *
     * "When quoting text, each line of the quoted text is prefixed with
     * a quote mark '>' and a space."
     */
    public function testAddingQuoteLevel(): void
    {
        $text = "Original text\nSecond line";
        $flowed = new TextFlowed($text);
        $result = $flowed->toFlowed(true); // true = add quote level

        $lines = explode("\n", trim($result));
        foreach ($lines as $line) {
            if (!empty($line)) {
                $this->assertStringStartsWith(
                    '>',
                    $line,
                    'Each line should be prefixed with quote mark when quoting'
                );
            }
        }
    }

    /**
     * Test toFixedArray() returns proper structure
     *
     * RFC 3676 requires quote level tracking for proper display.
     */
    public function testToFixedArrayStructure(): void
    {
        $text = "unquoted\n>quoted\n>>double";
        $flowed = new TextFlowed($text);
        $result = $flowed->toFixedArray();

        $this->assertIsArray($result, 'toFixedArray should return array');
        $this->assertGreaterThan(0, count($result), 'Array should not be empty');

        // Check structure of first element
        $this->assertArrayHasKey('text', $result[0], 'Each element should have text key');
        $this->assertArrayHasKey('level', $result[0], 'Each element should have level key');

        // Verify quote levels
        $this->assertEquals(0, $result[0]['level'], 'First line should be level 0');
        $this->assertEquals(1, $result[1]['level'], 'Second line should be level 1');
        $this->assertEquals(2, $result[2]['level'], 'Third line should be level 2');
    }

    /**
     * RFC 3676 Section 4.2: Optimal Line Length
     *
     * "Lines SHOULD be shorter than 80 characters. The recommended maximum
     * is 78 characters."
     */
    public function testOptimalLineLength(): void
    {
        $longText = str_repeat('word ', 30);
        $flowed = new TextFlowed($longText);
        $flowed->setOptLength(72); // Preferred break point
        $flowed->setMaxLength(78); // Hard limit

        $result = $flowed->toFlowed();
        $lines = explode("\n", trim($result));

        // Most lines should be near opt length, not max length
        $nearOptCount = 0;
        foreach ($lines as $line) {
            $len = strlen($line);
            if ($len >= 70 && $len <= 74) {
                $nearOptCount++;
            }
        }

        $this->assertGreaterThan(
            0,
            $nearOptCount,
            'Some lines should break near optimal length, not just at max'
        );
    }

    /**
     * RFC 3676 Section 4.4: Trailing Whitespace
     *
     * "Trailing whitespace on a fixed line is not significant.
     * A receiver MAY strip trailing whitespace from fixed lines."
     */
    public function testTrailingWhitespaceOnFixedLines(): void
    {
        // Fixed line (no trailing space before newline in flowed format)
        $text = "Fixed line\nAnother fixed";
        $flowed = new TextFlowed($text);
        $result = $flowed->toFixed();

        $lines = explode("\n", $result);
        foreach ($lines as $line) {
            if (!empty($line)) {
                $this->assertStringEndsNotWith(
                    ' ',
                    $line,
                    'Fixed lines should not have trailing whitespace'
                );
            }
        }
    }

    /**
     * RFC 3676 Section 4.2: Space Stuffing and Quote Depth
     *
     * "For aesthetic reasons, when generating quoted flowed lines, a space
     * is always added after the quote marks, so the line begins with
     * '> ' (or '>> ', etc.)"
     */
    public function testQuoteMarksFollowedBySpace(): void
    {
        $text = "quoted text";
        $flowed = new TextFlowed($text);
        $result = $flowed->toFlowed(true); // Add quote level

        $this->assertStringStartsWith(
            '>',
            $result,
            'Quoted text should start with >'
        );

        // After quote mark, if there's text, there should be a space
        // (unless the line is space-stuffed, which adds another space)
        $this->assertMatchesRegularExpression(
            '/^>\s/',
            $result,
            'Quote mark should be followed by space'
        );
    }
}
