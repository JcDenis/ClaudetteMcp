<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp\Tool;

use Dotclear\App;
use Dotclear\Database\MetaRecord;
use Dotclear\Helper\Html\Html;
use Dotclear\Plugin\ClaudetteMcp\McpTool;
use Dotclear\Plugin\ClaudetteMcp\McpToolException;

/**
 * @brief       ClaudetteMcp entries tools helper.
 * @ingroup     ClaudetteMcp
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
abstract class PostTool extends McpTool
{
    protected const POST_TYPE = 'post';

    /**
     * Get entries statuses the tools know.
     *
     * @return  array<string, int>
     */
    protected function statuses(): array
    {
        return [
            'published'   => App::status()->post()::PUBLISHED,
            'unpublished' => App::status()->post()::UNPUBLISHED,
            'scheduled'   => App::status()->post()::SCHEDULED,
            'pending'     => App::status()->post()::PENDING,
        ];
    }

    /**
     * Get entries query parameters limited to what current user can see.
     *
     * Published entries are visible to everyone. Other entries are visible
     * to content administrators, or to their author.
     *
     * @param   string  $status     The status name or "all"
     *
     * @return  array<string, mixed>
     */
    protected function visibility(string $status): array
    {
        $statuses = $this->statuses();

        if ($status === 'all') {
            $values = array_values($statuses);
        } elseif (isset($statuses[$status])) {
            $values = [$statuses[$status]];
        } else {
            throw new McpToolException('Unknown status, use one of: all, ' . implode(', ', array_keys($statuses)));
        }

        // In frontend context, Dotclear adds published entries to the requested statuses: limit to them again
        $sql    = 'AND P.post_status IN (' . implode(',', $values) . ') ';
        $params = [
            'post_type'   => static::POST_TYPE,
            'post_status' => $values,
        ];

        // Password protected entries are limited below, not by the frontend default
        App::blog()->withoutPassword(false);

        if (!$this->can(App::auth()::PERMISSION_CONTENT_ADMIN)) {
            // Entries of other authors: no password protected ones, and published ones only
            if ($values === [$statuses['published']]) {
                $sql .= 'AND (P.post_password IS NULL OR P.user_id = ' . "'" . App::db()->con()->escapeStr(App::auth()->userID()) . "'" . ') ';
            } else {
                $params['user_id'] = App::auth()->userID();
            }
        }

        $params['sql'] = $sql;

        return $params;
    }

    /**
     * Describe current entry of a record.
     *
     * @return  array<string, mixed>
     */
    protected function describe(MetaRecord $rs, bool $with_content = false): array
    {
        $status = array_search($rs->intField('post_status'), $this->statuses(), true);
        $meta   = App::meta()->getMetaArray($rs->strField('post_meta'));
        $type   = $rs->strField('post_type');

        $post = [
            'id'       => $rs->intField('post_id'),
            'title'    => $rs->strField('post_title'),
            'url'      => App::blog()->url() . App::postTypes()->get($type)->publicUrl(Html::sanitizeURL($rs->strField('post_url')), false),
            'date'     => $rs->strField('post_dt'),
            'status'   => $status === false ? 'unknown' : $status,
            'author'   => $rs->strField('user_id'),
            'category' => $rs->intField('cat_id') === 0 ? null : [
                'id'    => $rs->intField('cat_id'),
                'title' => $rs->strField('cat_title'),
            ],
            'tags'   => array_values($meta['tag'] ?? []),
            'lang'   => $rs->strField('post_lang'),
            'format' => $rs->strField('post_format'),
        ];

        if ($with_content) {
            $post += [
                'created'        => $rs->strField('post_creadt'),
                'updated'        => $rs->strField('post_upddt'),
                'selected'       => $rs->boolField('post_selected'),
                'open_comments'  => $rs->boolField('post_open_comment'),
                'open_trackback' => $rs->boolField('post_open_tb'),
                'nb_comments'    => $rs->intField('nb_comment'),
                'nb_trackbacks'  => $rs->intField('nb_trackback'),
                'excerpt'        => $rs->strField('post_excerpt'),
                'content'        => $rs->strField('post_content'),
            ];
        }

        return $post;
    }
}
