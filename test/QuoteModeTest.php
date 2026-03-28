<?php

declare(strict_types=1);

/**
 * QuoteMode behavior comparison tests.
 *
 * Tests verify the differences between EmailConvention and StrictRfc modes
 * to ensure both modes work correctly in their respective contexts.
 *
 * @author     Horde LLC
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Text_Flowed
 * @subpackage UnitTests
 */

namespace Horde\Text\Flowed\Test;

use Horde\Text\Flowed\QuoteMode;
use Horde\Text\Flowed\TextFlowed;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TextFlowed::class)]
#[CoversClass(QuoteMode::class)]
class QuoteModeTest extends TestCase
{
    /**
     * Test that EmailConvention mode treats ">" without space as quote.
     */
    public function testEmailConventionSimpleQuote(): void
    {
        $text = ">quoted text";
        $flowed = new TextFlowed($text, 'UTF-8', QuoteMode::EmailConvention);
        $result = $flowed->toFlowed();

        $this->assertEquals(
            "> quoted text\n",
            $result,
            'EmailConvention: ">text" should be quote level 1'
        );
    }

    /**
     * Test that StrictRfc mode treats ">" without space as literal.
     */
    public function testStrictRfcSimpleQuote(): void
    {
        $text = ">quoted text";
        $flowed = new TextFlowed($text, 'UTF-8', QuoteMode::StrictRfc);
        $result = $flowed->toFlowed();

        $this->assertEquals(
            " >quoted text\n",
            $result,
            'StrictRfc: ">text" (no space) should be space-stuffed literal'
        );
    }

    /**
     * Test that both modes handle "> text" (with space) as quote.
     */
    public function testBothModesHandleQuoteWithSpace(): void
    {
        $text = "> quoted text";

        $emailMode = new TextFlowed($text, 'UTF-8', QuoteMode::EmailConvention);
        $strictMode = new TextFlowed($text, 'UTF-8', QuoteMode::StrictRfc);

        $emailResult = $emailMode->toFlowed();
        $strictResult = $strictMode->toFlowed();

        $this->assertEquals(
            "> quoted text\n",
            $emailResult,
            'EmailConvention: "> text" should be quote level 1'
        );

        $this->assertEquals(
            "> quoted text\n",
            $strictResult,
            'StrictRfc: "> text" (with space) should be quote level 1'
        );

        // Both modes should produce same output
        $this->assertEquals(
            $emailResult,
            $strictResult,
            'Both modes should handle "> text" identically'
        );
    }

    /**
     * Test multi-level quotes in both modes.
     */
    public function testBothModesMultiLevelQuotes(): void
    {
        // With proper spacing
        $text = ">> level 2\n> level 1\nunquoted";

        $emailMode = new TextFlowed($text, 'UTF-8', QuoteMode::EmailConvention);
        $strictMode = new TextFlowed($text, 'UTF-8', QuoteMode::StrictRfc);

        $emailResult = $emailMode->toFlowed();
        $strictResult = $strictMode->toFlowed();

        // Both should produce same result with proper spacing
        $this->assertEquals(
            $emailResult,
            $strictResult,
            'Both modes handle properly-spaced quotes identically'
        );

        $this->assertStringContainsString('>> level 2', $emailResult);
        $this->assertStringContainsString('> level 1', $emailResult);
    }

    /**
     * Test multi-level quotes WITHOUT spacing - modes differ.
     */
    public function testMultiLevelQuotesWithoutSpacing(): void
    {
        $text = ">>level 2\n>level 1";

        // EmailConvention: treats as quotes
        $emailMode = new TextFlowed($text, 'UTF-8', QuoteMode::EmailConvention);
        $emailResult = $emailMode->toFlowed();

        $this->assertStringContainsString(
            '>> level 2',
            $emailResult,
            'EmailConvention: ">>text" should be quote level 2'
        );
        $this->assertStringContainsString(
            '> level 1',
            $emailResult,
            'EmailConvention: ">text" should be quote level 1'
        );

        // StrictRfc: treats as literal content
        $strictMode = new TextFlowed($text, 'UTF-8', QuoteMode::StrictRfc);
        $strictResult = $strictMode->toFlowed();

        $this->assertStringContainsString(
            ' >>level 2',
            $strictResult,
            'StrictRfc: ">>text" should be space-stuffed literal'
        );
        $this->assertStringContainsString(
            ' >level 1',
            $strictResult,
            'StrictRfc: ">text" should be space-stuffed literal'
        );
    }

    /**
     * Test fromPlainText() uses StrictRfc mode.
     */
    public function testFromPlainTextUsesStrictMode(): void
    {
        $text = ">literal content";

        $fromPlainText = TextFlowed::fromPlainText($text);
        $explicitStrict = new TextFlowed($text, 'UTF-8', QuoteMode::StrictRfc);

        $plainResult = $fromPlainText->toFlowed();
        $strictResult = $explicitStrict->toFlowed();

        $this->assertEquals(
            $strictResult,
            $plainResult,
            'fromPlainText() should behave identically to StrictRfc mode'
        );

        $this->assertEquals(
            " >literal content\n",
            $plainResult,
            'fromPlainText() should space-stuff literal ">"'
        );
    }

    /**
     * Test default constructor uses EmailConvention mode.
     */
    public function testDefaultModeIsEmailConvention(): void
    {
        $text = ">quoted";

        $defaultMode = new TextFlowed($text);
        $explicitEmail = new TextFlowed($text, 'UTF-8', QuoteMode::EmailConvention);

        $defaultResult = $defaultMode->toFlowed();
        $emailResult = $explicitEmail->toFlowed();

        $this->assertEquals(
            $emailResult,
            $defaultResult,
            'Default mode should behave like EmailConvention'
        );

        $this->assertEquals(
            "> quoted\n",
            $defaultResult,
            'Default mode should treat ">" as quote marker'
        );
    }

    /**
     * Test quote detection with mixed spacing.
     *
     * @dataProvider mixedSpacingProvider
     */
    #[DataProvider('mixedSpacingProvider')]
    public function testQuoteDetectionWithMixedSpacing(
        string $input,
        string $expectedEmail,
        string $expectedStrict,
        string $description
    ): void {
        $emailMode = new TextFlowed($input, 'UTF-8', QuoteMode::EmailConvention);
        $strictMode = new TextFlowed($input, 'UTF-8', QuoteMode::StrictRfc);

        $this->assertEquals(
            $expectedEmail,
            $emailMode->toFlowed(),
            "EmailConvention: {$description}"
        );

        $this->assertEquals(
            $expectedStrict,
            $strictMode->toFlowed(),
            "StrictRfc: {$description}"
        );
    }

    public static function mixedSpacingProvider(): array
    {
        return [
            'no space after >' => [
                '>text',
                "> text\n",
                " >text\n",
                'Single > without space'
            ],
            'space after >' => [
                '> text',
                "> text\n",
                "> text\n",
                'Single > with space'
            ],
            'double > no space' => [
                '>>text',
                ">> text\n",
                " >>text\n",
                'Double >> without space'
            ],
            'double > with space' => [
                '>> text',
                ">> text\n",
                ">> text\n",
                'Double >> with space'
            ],
            'mixed quote depths' => [
                "> level1\n>> level2",
                "> level1\n>> level2\n",
                "> level1\n>> level2\n",
                'Properly spaced mixed depths'
            ],
            'triple > no space' => [
                '>>>text',
                ">>> text\n",
                " >>>text\n",
                'Triple >>> without space'
            ],
        ];
    }

    /**
     * Test toFixed() behavior with both modes.
     */
    public function testToFixedBehaviorBothModes(): void
    {
        // Flowed text: quote followed by flowed lines
        $text = "> line 1 \n> line 2";

        $emailMode = new TextFlowed($text, 'UTF-8', QuoteMode::EmailConvention);
        $strictMode = new TextFlowed($text, 'UTF-8', QuoteMode::StrictRfc);

        $emailFixed = $emailMode->toFixed();
        $strictFixed = $strictMode->toFixed();

        // With proper spacing, both modes should produce same result
        $this->assertEquals(
            ">line 1 line 2",
            $emailFixed,
            'EmailConvention: Should join flowed quoted lines'
        );

        $this->assertEquals(
            ">line 1 line 2",
            $strictFixed,
            'StrictRfc: Should join flowed quoted lines'
        );

        $this->assertEquals(
            $emailFixed,
            $strictFixed,
            'Both modes should produce identical toFixed() output for valid input'
        );
    }

    /**
     * Test toFixedArray() includes correct quote levels.
     */
    public function testToFixedArrayQuoteLevels(): void
    {
        $text = "unquoted\n> level1\n>> level2";

        $emailMode = new TextFlowed($text, 'UTF-8', QuoteMode::EmailConvention);
        $emailArray = $emailMode->toFixedArray();

        $this->assertEquals(0, $emailArray[0]['level'], 'First line: unquoted');
        $this->assertEquals(1, $emailArray[1]['level'], 'Second line: level 1');
        $this->assertEquals(2, $emailArray[2]['level'], 'Third line: level 2');

        $strictMode = new TextFlowed($text, 'UTF-8', QuoteMode::StrictRfc);
        $strictArray = $strictMode->toFixedArray();

        // With proper spacing, both should detect same levels
        $this->assertEquals($emailArray[0]['level'], $strictArray[0]['level']);
        $this->assertEquals($emailArray[1]['level'], $strictArray[1]['level']);
        $this->assertEquals($emailArray[2]['level'], $strictArray[2]['level']);
    }

    /**
     * Test edge case: ">" followed by ">" (no space between).
     */
    public function testConsecutiveQuoteMarkers(): void
    {
        $text = ">>";

        $emailMode = new TextFlowed($text, 'UTF-8', QuoteMode::EmailConvention);
        $emailResult = $emailMode->toFlowed();

        $this->assertStringContainsString(
            '>>',
            $emailResult,
            'EmailConvention: ">>" should be quote level 2'
        );

        $strictMode = new TextFlowed($text, 'UTF-8', QuoteMode::StrictRfc);
        $strictResult = $strictMode->toFlowed();

        $this->assertStringContainsString(
            '>>',
            $strictResult,
            'StrictRfc: ">>" (another >) should be quote level 2'
        );
    }

    /**
     * Test space stuffing preservation through round-trip.
     */
    public function testRoundTripPreservesContent(): void
    {
        // StrictRfc mode: generate flowed text
        $original = ">literal content\n> quoted content";
        $generator = TextFlowed::fromPlainText($original);
        $flowed = $generator->toFlowed();

        // Should space-stuff the literal ">"
        $this->assertStringContainsString(' >literal content', $flowed);
        // Should preserve the quoted line
        $this->assertStringContainsString('> quoted content', $flowed);

        // Parse it back (EmailConvention mode)
        $parser = new TextFlowed($flowed, 'UTF-8', QuoteMode::EmailConvention);
        $fixed = $parser->toFixed();

        // After parsing, space-stuffing removed, content preserved
        $this->assertStringContainsString('>literal content', $fixed);
        $this->assertStringContainsString('>quoted content', $fixed);
    }

    /**
     * Test that "From " space-stuffing works in both modes.
     */
    public function testFromSpaceStuffingBothModes(): void
    {
        $text = "From the sender";

        $emailMode = new TextFlowed($text, 'UTF-8', QuoteMode::EmailConvention);
        $strictMode = new TextFlowed($text, 'UTF-8', QuoteMode::StrictRfc);

        $emailResult = $emailMode->toFlowed();
        $strictResult = $strictMode->toFlowed();

        // Both modes should space-stuff "From "
        $this->assertEquals(
            " From the sender\n",
            $emailResult,
            'EmailConvention: Should space-stuff "From "'
        );

        $this->assertEquals(
            " From the sender\n",
            $strictResult,
            'StrictRfc: Should space-stuff "From "'
        );

        $this->assertEquals(
            $emailResult,
            $strictResult,
            'Both modes handle "From " identically'
        );
    }

    /**
     * Test signature separator handling in both modes.
     */
    public function testSignatureSeparatorBothModes(): void
    {
        $text = "Text before \n-- \nSignature";

        $emailMode = new TextFlowed($text, 'UTF-8', QuoteMode::EmailConvention);
        $strictMode = new TextFlowed($text, 'UTF-8', QuoteMode::StrictRfc);

        $emailResult = $emailMode->toFixed();
        $strictResult = $strictMode->toFixed();

        // Both modes should preserve signature separator
        $this->assertStringContainsString(
            "-- \n",
            $emailResult,
            'EmailConvention: Should preserve signature separator'
        );

        $this->assertStringContainsString(
            "-- \n",
            $strictResult,
            'StrictRfc: Should preserve signature separator'
        );

        $this->assertEquals(
            $emailResult,
            $strictResult,
            'Both modes handle signature separator identically'
        );
    }

    /**
     * Test DelSp parameter works in both modes.
     */
    public function testDelSpBothModes(): void
    {
        $text = "word1 \nword2";

        $emailMode = new TextFlowed($text, 'UTF-8', QuoteMode::EmailConvention);
        $emailMode->setDelSp(true);

        $strictMode = new TextFlowed($text, 'UTF-8', QuoteMode::StrictRfc);
        $strictMode->setDelSp(true);

        $emailResult = $emailMode->toFixed();
        $strictResult = $strictMode->toFixed();

        // Both should remove trailing space with DelSp=yes
        $this->assertEquals(
            "word1word2",
            $emailResult,
            'EmailConvention: DelSp should remove trailing space'
        );

        $this->assertEquals(
            "word1word2",
            $strictResult,
            'StrictRfc: DelSp should remove trailing space'
        );

        $this->assertEquals(
            $emailResult,
            $strictResult,
            'DelSp behavior should be identical in both modes'
        );
    }

    /**
     * Test empty lines preserved in both modes.
     */
    public function testEmptyLinesPreservedBothModes(): void
    {
        $text = "Paragraph 1 \n\nParagraph 2";

        $emailMode = new TextFlowed($text, 'UTF-8', QuoteMode::EmailConvention);
        $strictMode = new TextFlowed($text, 'UTF-8', QuoteMode::StrictRfc);

        $emailResult = $emailMode->toFixed();
        $strictResult = $strictMode->toFixed();

        // Both should preserve empty line
        $this->assertStringContainsString(
            "\n\n",
            $emailResult,
            'EmailConvention: Should preserve empty lines'
        );

        $this->assertStringContainsString(
            "\n\n",
            $strictResult,
            'StrictRfc: Should preserve empty lines'
        );

        $this->assertEquals(
            $emailResult,
            $strictResult,
            'Empty line handling should be identical in both modes'
        );
    }

    /**
     * Test charset parameter works with both modes.
     */
    public function testCharsetHandlingBothModes(): void
    {
        $text = "Ü special char";

        $emailMode = new TextFlowed($text, 'UTF-8', QuoteMode::EmailConvention);
        $strictMode = new TextFlowed($text, 'UTF-8', QuoteMode::StrictRfc);

        $emailResult = $emailMode->toFlowed();
        $strictResult = $strictMode->toFlowed();

        // Both should preserve special characters
        $this->assertStringContainsString('Ü', $emailResult);
        $this->assertStringContainsString('Ü', $strictResult);

        $this->assertEquals(
            $emailResult,
            $strictResult,
            'Charset handling should be identical when no quotes involved'
        );
    }
}
