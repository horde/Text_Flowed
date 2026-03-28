<?php

declare(strict_types=1);

/**
 * Quote detection mode for format=flowed text processing.
 *
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

/**
 * Defines how lines starting with ">" are interpreted.
 */
enum QuoteMode
{
    /**
     * Email convention: ">" at line start is always treated as quote marker.
     *
     * Example: ">text" is quote level 1, content "text"
     *
     * This is the default mode and matches common email client behavior.
     * Use when processing already-quoted email or format=flowed text.
     */
    case EmailConvention;

    /**
     * RFC 3676 strict mode: ">" without following space is literal content.
     *
     * Per RFC 3676 Section 4.2, unquoted lines starting with ">" must be
     * space-stuffed when generating format=flowed. This mode enforces that
     * quotes must be followed by a space ("> text" not ">text").
     *
     * Example: ">text" is literal content ">text" (needs space-stuffing)
     * Example: "> text" is quote level 1, content "text"
     *
     * Use when generating format=flowed from plain text.
     */
    case StrictRfc;
}
