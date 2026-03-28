<?php

declare(strict_types=1);

/**
 * Copyright 2002-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2.1). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL-2.1
 * @package  Text_Flowed
 */

namespace Horde\Text\Flowed;

/**
 * Represents a single line in format=flowed text with its quote level.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2002-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL-2.1
 * @package   Text_Flowed
 */
final readonly class FlowedLine
{
    public function __construct(
        public string $text,
        public int $level
    ) {}

    /**
     * Convert to legacy array format for backward compatibility.
     *
     * @return array{text: string, level: int}
     */
    public function toArray(): array
    {
        return ['text' => $this->text, 'level' => $this->level];
    }
}
