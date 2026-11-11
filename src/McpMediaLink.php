<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp;

/**
 * @brief       ClaudetteMcp media link type.
 * @ingroup     ClaudetteMcp
 *
 * A media link type is a way to link a media to an entry (table post_media of Dotclear core).
 * Descriptions are read by the assistant, they are not translated.
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class McpMediaLink
{
    /**
     * @param   string  $type           The link type as saved in database (ex: attachment)
     * @param   string  $description    What this link is used for, as a short sentence without ending dot
     * @param   bool    $single         An entry has only one media of this type: linking another one replaces it
     */
    public function __construct(
        public readonly string $type,
        public readonly string $description,
        public readonly bool $single = false,
    ) {
    }
}
