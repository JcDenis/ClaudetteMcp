<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp\Tool;

use Dotclear\App;

/**
 * @brief       ClaudetteMcp tool: list entries.
 * @ingroup     ClaudetteMcp
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class ListPosts extends PostTool
{
    public function name(): string
    {
        return 'list_posts';
    }

    public function title(): string
    {
        return 'List blog entries';
    }

    public function description(): string
    {
        return 'List entries of the blog, most recent first, without their content. ' .
            'Only published entries are listed by default. With another status or "all", ' .
            'entries of other authors are listed only if the user is a content administrator. ' .
            'Use get_post to read the content of an entry.';
    }

    public function properties(): array
    {
        return [
            'status' => [
                'type'        => 'string',
                'enum'        => ['published', 'unpublished', 'scheduled', 'pending', 'all'],
                'description' => 'Entries status, default: published.',
            ],
            'category_id' => [
                'type'        => 'integer',
                'description' => 'Limit to a category ID (see list_categories).',
            ],
            'tag' => [
                'type'        => 'string',
                'description' => 'Limit to a tag (see list_tags).',
            ],
            'search' => [
                'type'        => 'string',
                'description' => 'Limit to entries containing these words.',
            ],
            'limit' => [
                'type'        => 'integer',
                'description' => 'Entries per page, 1 to 50, default: 20.',
            ],
            'page' => [
                'type'        => 'integer',
                'description' => 'Page number, default: 1.',
            ],
        ];
    }

    public function call(array $args): array
    {
        $params = $this->visibility($this->str($args, 'status', 'published'));
        $limit  = $this->int($args, 'limit', 20, 1, 50);
        $page   = $this->int($args, 'page', 1, 1);

        if ($this->int($args, 'category_id') > 0) {
            $params['cat_id'] = $this->int($args, 'category_id');
        }

        if ($this->str($args, 'search') !== '') {
            $params['search'] = $this->str($args, 'search');
        }

        if ($this->str($args, 'tag') !== '') {
            $params['meta_type'] = 'tag';
            $params['meta_id']   = $this->str($args, 'tag');
            $total               = App::meta()->getPostsByMeta($params, true)->intField(0);
        } else {
            $total = App::blog()->getPosts($params, true)->intField(0);
        }

        $params['no_content'] = true;
        $params['order']      = 'post_dt DESC';
        $params['limit']      = [($page - 1) * $limit, $limit];

        $rs = isset($params['meta_id']) ? App::meta()->getPostsByMeta($params) : App::blog()->getPosts($params);

        $posts = [];
        while ($rs->fetch()) {
            $posts[] = $this->describe($rs);
        }

        return [
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
            'posts' => $posts,
        ];
    }
}
