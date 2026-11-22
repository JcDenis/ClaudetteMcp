<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp\Tool;

use Dotclear\App;
use Dotclear\Plugin\blogroll\Blogroll;
use Dotclear\Plugin\blogroll\Status\Link;
use Dotclear\Plugin\ClaudetteMcp\McpTool;

/**
 * @brief       ClaudetteMcp blogroll tools helper.
 * @ingroup     ClaudetteMcp
 *
 * Links come from the official plugin blogroll. Its classes are used only
 * once the tool is checked as available: the plugin may be disabled or removed.
 *
 * In the blogroll table, a category is a row without title nor URL, its title is in the description.
 * A link belongs to the last category before it, in position order.
 *
 * @phpstan-type Item array{id: int, kind: string, title: string, url: string, description: string, lang: string, xfn: string, category: string, status: string}
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
abstract class BlogrollTool extends McpTool
{
    /**
     * Blogroll requires the plugin and its permission, as its admin page.
     */
    public function available(): bool
    {
        return App::plugins()->moduleExists('blogroll')
            && class_exists(Blogroll::class)
            && $this->can(Blogroll::PERMISSION_BLOGROLL, App::auth()::PERMISSION_ADMIN, App::auth()::PERMISSION_CONTENT_ADMIN);
    }

    protected function blogroll(): Blogroll
    {
        return new Blogroll(App::blog());
    }

    /**
     * Get all links and categories in position order, by ID.
     *
     * @return  array<int, Item>
     */
    protected function items(): array
    {
        $rs    = $this->blogroll()->getLinks();
        $items = [];
        while ($rs->fetch()) {
            $is_cat                         = $rs->boolField('is_cat');
            $items[$rs->intField('link_id')] = [
                'id'          => $rs->intField('link_id'),
                'kind'        => $is_cat ? 'category' : 'link',
                'title'       => $is_cat ? $rs->strField('link_desc') : $rs->strField('link_title'),
                'url'         => $is_cat ? '' : $rs->strField('link_href'),
                'description' => $is_cat ? '' : $rs->strField('link_desc'),
                'lang'        => $is_cat ? '' : $rs->strField('link_lang'),
                'xfn'         => $is_cat ? '' : $rs->strField('link_xfn'),
                'category'    => !$is_cat && is_string($cat = $rs->field('cat_title')) ? $cat : '',
                'status'      => $rs->intField('link_status') === Link::ONLINE ? 'online' : 'offline',
            ];
        }

        return $items;
    }
}
