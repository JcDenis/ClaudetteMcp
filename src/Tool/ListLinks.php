<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp\Tool;

/**
 * @brief       ClaudetteMcp tool: list blogroll links.
 * @ingroup     ClaudetteMcp
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class ListLinks extends BlogrollTool
{
    public function name(): string
    {
        return 'list_links';
    }

    public function title(): string
    {
        return 'List blogroll links';
    }

    public function description(): string
    {
        return 'List links and link categories of the blog blogroll, in display order. ' .
            'A link belongs to the last category before it. Offline items are not shown on the blog.';
    }

    public function call(array $args): array
    {
        return ['items' => array_values($this->items())];
    }
}
