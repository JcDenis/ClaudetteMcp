<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp\Tool;

use Dotclear\App;
use Dotclear\Plugin\ClaudetteMcp\McpTool;

/**
 * @brief       ClaudetteMcp tool: list tags.
 * @ingroup     ClaudetteMcp
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class ListTags extends McpTool
{
    public function name(): string
    {
        return 'list_tags';
    }

    public function title(): string
    {
        return 'List blog tags';
    }

    public function description(): string
    {
        return 'List tags used by entries of the blog, most used first, with the number of entries of each tag.';
    }

    public function properties(): array
    {
        return [
            'limit' => [
                'type'        => 'integer',
                'description' => 'Maximum number of tags, 1 to 500, default: 100.',
            ],
        ];
    }

    /**
     * Tags come from the official plugin tags.
     */
    public function available(): bool
    {
        return App::plugins()->moduleExists('tags');
    }

    public function call(array $args): array
    {
        $rs = App::meta()->getMetadata([
            'meta_type' => 'tag',
            'order'     => 'count DESC',
            'limit'     => $this->int($args, 'limit', 100, 1, 500),
        ]);

        $tags = [];
        while ($rs->fetch()) {
            $tags[] = [
                'tag'      => $rs->strField('meta_id'),
                'nb_posts' => $rs->intField('count'),
                'url'      => App::blog()->url() . App::url()->getURLFor('tag', rawurlencode($rs->strField('meta_id'))),
            ];
        }

        return ['tags' => $tags];
    }
}
