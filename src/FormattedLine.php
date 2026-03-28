<?php

declare(strict_types=1);

/**
 * Represents a line in formatted text with its quote level.
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

final readonly class FormattedLine
{
    public function __construct(
        public string $text,
        public int $level,
    ) {}
}
