<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp\Tool;

use Dotclear\App;
use Dotclear\Plugin\ClaudetteMcp\McpToolException;

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
        $id = $this->int($args, 'id');
        if ($id < 1) {
            throw new McpToolException('Missing entry id');
        }

        // Published entry
        $params            = $this->visibility('published');
        $params['post_id'] = $id;
        $rs                = App::blog()->getPosts($params);

        // Or any status limited to what user can see
        if ($rs->isEmpty()) {
            $params            = $this->visibility('all');
            $params['post_id'] = $id;
            $rs                = App::blog()->getPosts($params);
        }

        if ($rs->isEmpty()) {
            throw new McpToolException('Entry not found');
        }

        return $this->describe($rs, true);
    }
}
