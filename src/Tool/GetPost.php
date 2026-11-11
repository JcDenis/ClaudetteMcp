<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp\Tool;

/**
 * @brief       ClaudetteMcp tool: get an entry.
 * @ingroup     ClaudetteMcp
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class GetPost extends PostTool
{
    public function name(): string
    {
        return 'get_post';
    }

    public function title(): string
    {
        return 'Read a blog entry';
    }

    public function description(): string
    {
        return 'Get an entry of the blog with its excerpt and content, in the source format of the entry ' .
            '(xhtml, wiki, markdown). An entry which is not published is readable only by its author or by a content administrator.';
    }

    public function properties(): array
    {
        return [
            'id' => [
                'type'        => 'integer',
                'description' => 'The entry ID (see list_posts).',
            ],
        ];
    }

    public function required(): array
    {
        return ['id'];
    }

    public function call(array $args): array
    {
        $rs = $this->fetch($this->int($args, 'id'));

        return $this->describe($rs, true);
    }
}
