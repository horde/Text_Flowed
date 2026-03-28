<?php

declare(strict_types=1);

/**
 * Provides methods for manipulating text using the encoding described in RFC 3676 ('flowed' text).
 *
 * This class is based on the Text::Flowed perl module (Version 0.14) found
 * in the CPAN perl repository. This module is released under the Perl
 * license, which is compatible with the LGPL.
 *
 * Copyright 2002-2026 Philip Mak
 * Copyright 2004-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Text_Flowed
 */

namespace Horde\Text\Flowed;

use Horde\Util\HordeString;

class TextFlowed
{
    protected int $maxlength = 78;
    protected int $optlength = 72;
    protected string $text;
    protected array $output = [];
    protected ?string $formatType = null;
    protected string $charset;
    protected bool $delsp = false;
    protected QuoteMode $quoteMode;

    public function __construct(
        string $text,
        string $charset = 'UTF-8',
        QuoteMode $quoteMode = QuoteMode::EmailConvention
    ) {
        $this->text = $text;
        $this->charset = $charset;
        $this->quoteMode = $quoteMode;
    }

    /**
     * Create TextFlowed instance for converting plain text to format=flowed.
     *
     * Uses StrictRfc mode where lines starting with ">" (without space after)
     * are treated as literal content requiring space-stuffing, per RFC 3676 §4.2.
     *
     * @param string $text Plain text content to convert
     * @param string $charset Character set of the text
     * @return self
     */
    public static function fromPlainText(string $text, string $charset = 'UTF-8'): self
    {
        return new self($text, $charset, QuoteMode::StrictRfc);
    }

    public function setMaxLength(int $max): void
    {
        $this->maxlength = $max;
    }

    public function setOptLength(int $opt): void
    {
        $this->optlength = $opt;
    }

    public function setDelSp(bool $delsp): void
    {
        $this->delsp = $delsp;
    }

    public function toFixed(bool $quote = false): string
    {
        $txt = '';

        $this->reformat(false, $quote);
        $lines = count($this->output) - 1;
        foreach ($this->output as $no => $line) {
            $txt .= $line['text'] . (($lines == $no) ? '' : "\n");
        }

        return $txt;
    }

    /**
     * Reformats the input string, and returns the output in an array format
     * with quote level information.
     *
     * @param bool $quote Add level of quoting to each line?
     * @return array<int, array{text: string, level: int}> Array with 'level' and 'text' keys.
     */
    public function toFixedArray(bool $quote = false): array
    {
        $this->reformat(false, $quote);
        return $this->output;
    }

    /**
     * Reformats the input string, where the string is 'format=fixed' plain
     * text as described in RFC 2646.
     *
     * @param bool $quote Add level of quoting to each line?
     * @param bool $wrap If true, wraps unquoted lines (default: true).
     * @return string The text converted to RFC 2646 'flowed' format.
     */
    public function toFlowed(bool $quote = false, bool $wrap = true): string
    {
        $txt = '';

        $this->reformat(true, $quote, $wrap);
        foreach ($this->output as $line) {
            $txt .= $line['text'] . "\n";
        }

        return $txt;
    }

    /**
     * Reformats the input string, where the string is 'format=flowed' plain
     * text as described in RFC 2646.
     *
     * @param bool $toflowed Convert to flowed?
     * @param bool $quote Add level of quoting to each line?
     * @param bool $wrap Wrap unquoted lines?
     */
    protected function reformat(bool $toflowed, bool $quote, bool $wrap = true): void
    {
        $formatType = implode('|', [$toflowed, $quote]);
        if ($formatType == $this->formatType) {
            return;
        }

        $this->output = [];
        $this->formatType = $formatType;

        /* Set variables used in regexps. */
        $delsp = ($toflowed && $this->delsp) ? 1 : 0;
        $opt = $this->optlength - 1 - $delsp;

        /* Process message line by line. */
        $text = preg_split("/\r?\n/", $this->text);
        $textCount = count($text) - 1;
        $skip = 0;

        foreach ($text as $no => $line) {
            if ($skip) {
                --$skip;
                continue;
            }

            /* Per RFC 2646 [4.3], the 'Usenet Signature Convention' line
             * (DASH DASH SP) is not considered flowed. Watch for this when
             * dealing with potentially flowed lines. */

            /* The next three steps come from RFC 2646 [4.2]. */
            /* STEP 1: Determine quote level for line. */
            $numQuotes = $this->numQuotes($line);
            if ($numQuotes) {
                $line = substr($line, $numQuotes);
            }

            /* Only combine lines if we are converting to flowed or if the
             * current line is quoted. */
            if (!$toflowed || $numQuotes) {
                /* STEP 2: Remove space stuffing from line. */
                $line = $this->unstuff($line);

                /* STEP 3: Should we interpret this line as flowed?
                 * While line is flowed (not empty and there is a space
                 * at the end of the line), and there is a next line, and the
                 * next line has the same quote depth, add to the current
                 * line. A line is not flowed if it is a signature line. */
                if ($line != '-- ') {
                    while (
                        !empty($line)
                        && (substr($line, -1) == ' ')
                        && ($textCount != $no)
                        && ($this->numQuotes($text[$no + 1]) == $numQuotes)
                    ) {
                        // RFC 3676 §4.3: Peek at next line to check for special cases
                        $nextQuoteDepth = $this->numQuotes($text[$no + 1]);
                        $nextLineContent = substr($text[$no + 1], $nextQuoteDepth);
                        $nextLineContent = $this->unstuff($nextLineContent);

                        // RFC 3676 §4.3: Don't join with signature separator
                        if ($nextLineContent == '-- ') {
                            break;
                        }

                        // RFC 3676 §4.2: Don't join across empty lines (paragraph breaks)
                        if (empty($nextLineContent)) {
                            break;
                        }

                        /* If DelSp is yes and this is flowed input, we need to
                         * remove the trailing space. */
                        if (!$toflowed && $this->delsp) {
                            $line = substr($line, 0, -1);
                        }
                        $line .= $this->unstuff(substr($text[++$no], $numQuotes));
                        ++$skip;
                    }
                }
            }

            /* Ensure line is fixed, since we already joined all flowed
             * lines. Remove all trailing ' ' from the line. */
            if ($line != '-- ') {
                $line = rtrim($line);
            }

            /* Increment quote depth if we're quoting. */
            if ($quote) {
                $numQuotes++;
            }

            /* The quote prefix for the line. */
            $quotestr = str_repeat('>', $numQuotes);

            if (empty($line)) {
                /* Line is empty. */
                $this->output[] = ['text' => $quotestr, 'level' => $numQuotes];
            } elseif (
                (!$wrap && !$numQuotes)
                || empty($this->maxlength)
                || ((HordeString::length($line, $this->charset) + $numQuotes) <= $this->maxlength)
            ) {
                /* Line does not require rewrapping. */
                $this->output[] = ['text' => $quotestr . $this->stuff($line, $numQuotes, $toflowed), 'level' => $numQuotes];
            } else {
                $min = $numQuotes + 1;

                /* Rewrap this paragraph. */
                while ($line) {
                    /* Stuff and re-quote the line. */
                    $line = $quotestr . $this->stuff($line, $numQuotes, $toflowed);
                    $lineLength = HordeString::length($line, $this->charset);
                    if ($lineLength <= $this->optlength) {
                        /* Remaining section of line is short enough. */
                        $this->output[] = ['text' => $line, 'level' => $numQuotes];
                        break;
                    } else {
                        $regex = [];
                        if ($min <= $opt) {
                            $regex[] = '^(.{' . $min . ',' . $opt . '}) (.*)';
                        }
                        if ($min <= $this->maxlength) {
                            $regex[] = '^(.{' . $min . ',' . $this->maxlength . '}) (.*)';
                        }
                        $regex[] = '^(.{' . $min . ',})? (.*)';

                        $m = HordeString::regexMatch($line, $regex, $this->charset);
                        if ($m) {
                            /* We need to wrap text at a certain number of
                             * *characters*, not a certain number of *bytes*;
                             * thus the need for a multibyte capable regex.
                             * If a multibyte regex isn't available, we are
                             * stuck with preg_match() (the function will
                             * still work - we are just left with shorter rows
                             * than expected if multibyte characters exist in
                             * the row).
                             *
                             * 1. Try to find a string as long as optlength.
                             * 2. Try to find a string as long as maxlength.
                             * 3. Take the first word. */
                            if (empty($m[1])) {
                                $m[1] = $m[2];
                                $m[2] = '';
                            }
                            $this->output[] = ['text' => $m[1] . ' ' . (($delsp) ? ' ' : ''), 'level' => $numQuotes];
                            $line = $m[2];
                        } elseif ($lineLength > 998) {
                            /* One excessively long word left on line. Be
                             * absolutely sure it does not exceed 998
                             * characters in length or else we must
                             * truncate. */
                            $this->output[] = ['text' => HordeString::substr($line, 0, 998, $this->charset), 'level' => $numQuotes];
                            $line = HordeString::substr($line, 998, null, $this->charset);
                        } else {
                            $this->output[] = ['text' => $line, 'level' => $numQuotes];
                            break;
                        }
                    }
                }
            }
        }
    }

    /**
     * Returns the number of leading '>' characters in the text input.
     *
     * In EmailConvention mode, any leading ">" is treated as quote marker.
     * In StrictRfc mode (RFC 3676 §4.2), ">" must be followed by space or
     * another ">" to be treated as quote; otherwise it's literal content.
     *
     * @param string $text The text to analyze.
     * @return int The number of leading quote characters.
     */
    protected function numQuotes(string $text): int
    {
        if ($this->quoteMode === QuoteMode::EmailConvention) {
            // Traditional email: all leading ">" are quotes
            return strspn($text, '>');
        }

        // StrictRfc mode: ">" must be followed by space or ">" to be quote
        $count = 0;
        $len = strlen($text);

        for ($i = 0; $i < $len; $i++) {
            if ($text[$i] === '>') {
                // Check if next char is space or another ">"
                if ($i + 1 < $len && $text[$i + 1] !== ' ' && $text[$i + 1] !== '>') {
                    // ">" followed by non-space, non-">" = literal content
                    return 0;
                }
                $count++;
            } elseif ($text[$i] === ' ') {
                // Spaces between ">" and content are allowed
                continue;
            } else {
                // Non-">" character = end of quote prefix
                break;
            }
        }

        return $count;
    }

    /**
     * Space-stuffs if it starts with ' ' or '>' or 'From ', or if
     * quote depth is non-zero (for aesthetic reasons so that there is a
     * space after the '>').
     *
     * @param string $text The text to stuff.
     * @param int $numQuotes The quote-level of this line.
     * @param bool $toflowed Are we converting to flowed text?
     * @return string The stuffed text.
     */
    protected function stuff(string $text, int $numQuotes, bool $toflowed): string
    {
        return ($toflowed && ($numQuotes || preg_match("/^(?: |>|From |From$)/", $text)))
            ? ' ' . $text
            : $text;
    }

    /**
     * Unstuffs a space stuffed line.
     *
     * @param string $text The text to unstuff.
     * @return string The unstuffed text.
     */
    protected function unstuff(string $text): string
    {
        return (!empty($text) && ($text[0] == ' '))
            ? substr($text, 1)
            : $text;
    }
}
