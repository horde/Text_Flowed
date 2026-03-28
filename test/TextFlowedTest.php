<?php

declare(strict_types=1);

/**
 * Modern TextFlowed tests.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Text_Flowed
 * @subpackage UnitTests
 */

namespace Horde\Text\Flowed\Test;

use Horde\Text\Flowed\TextFlowed;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TextFlowed::class)]
class TextFlowedTest extends TestCase
{
    public function testFixedToFlowed(): void
    {
        $flowed = new TextFlowed("Hello, world!");
        $this->assertEquals(
            "Hello, world!\n",
            $flowed->toFlowed()
        );

        $flowed = new TextFlowed("Hello, \nworld!");
        $this->assertEquals(
            "Hello,\nworld!\n",
            $flowed->toFlowed()
        );

        $flowed = new TextFlowed("Hello, \n world!");
        $this->assertEquals(
            "Hello,\n  world!\n",
            $flowed->toFlowed()
        );

        $flowed = new TextFlowed("From");
        $this->assertEquals(
            " From\n",
            $flowed->toFlowed()
        );

        // See Bug #2969
        $flowed = new TextFlowed("   >--------------------------------------------------------------------------------------------------------------------------------");
        $this->assertEquals(
            "    \n>-------------------------------------------------------------------------------------------------------------------------------- \n",
            $flowed->toFlowed()
        );
    }

    public function testFlowedWrap(): void
    {
        $text = <<<EOT
            >this is a long line this is a long line this is a long line this is a long line this is a long line this is a long line
            this is a long line this is a long line this is a long line this is a long line this is a long line this is a long line
            EOT;
        $expected = <<<EOT
            > this is a long line this is a long line this is a long line this is a 
            > long line this is a long line this is a long line
            this is a long line this is a long line this is a long line this is a long line this is a long line this is a long line

            EOT;

        $flowed = new TextFlowed($text);
        $flowed->setMaxLength(70);
        $this->assertEquals(
            $expected,
            $flowed->toFlowed(false, false)
        );
    }

    public function testFlowedToFixed(): void
    {
        $flowed = new TextFlowed(">line 1 \n>line 2 \n>line 3");
        $this->assertEquals(
            ">line 1 line 2 line 3",
            $flowed->toFixed()
        );

        // See Bug #4832
        $flowed = new TextFlowed("line 1\n>from line 2\nline 3");
        $this->assertEquals(
            "line 1\n>from line 2\nline 3",
            $flowed->toFixed()
        );

        $flowed = new TextFlowed("line 1\n From line 2\nline 3");
        $this->assertEquals(
            "line 1\nFrom line 2\nline 3",
            $flowed->toFixed()
        );
    }
}
