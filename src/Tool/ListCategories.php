<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp\Tool;

use Dotclear\App;
use Dotclear\Plugin\ClaudetteMcp\McpTool;

/**
 * @brief       ClaudetteMcp tool: list categories.
 * @ingroup     ClaudetteMcp
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class ListCategories extends McpTool
{
    public function name(): string
    {
        return 'list_categories';
    }

    public function title(): string
    {
        return 'List blog categories';
    }

    public function description(): string
    {
        return 'List categories of the blog as a flat tree: "level" gives the depth of a category, a sub-category follows its parent.';
    }

    public function call(array $args): array
    {
        $rs = App::blog()->getCategories(['post_type' => 'post']);

        $categories = [];
        while ($rs->fetch()) {
            $categories[] = [
                'id'       => $rs->intField('cat_id'),
                'title'    => $rs->strField('cat_title'),
                'level'    => $rs->intField('level'),
                'nb_posts' => $rs->intField('nb_post'),
                'url'      => App::blog()->url() . App::url()->getURLFor('category', $rs->strField('cat_url')),
            ];
        }

        return ['categories' => $categories];
    }
}
