<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp\Tool;

use DateTimeImmutable;
use Dotclear\App;
use Dotclear\Database\Cursor;
use Dotclear\Database\MetaRecord;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Html\WikiToHtml;
use Dotclear\Plugin\ClaudetteMcp\McpMediaLink;
use Dotclear\Plugin\ClaudetteMcp\McpMediaLinks;
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
    /**
     * The permission of the official plugin "pages".
     *
     * Not taken from the plugin class as the plugin could be disabled.
     */
    protected const PERMISSION_PAGES = 'pages';

    /**
     * Get the entries types current user can use.
     *
     * Pages need the official plugin "pages" and its permission, like in the blog administration.
     *
     * @return  array<int, string>
     */
    protected function types(): array
    {
        $types = ['post'];
        if (App::postTypes()->exists('page') && $this->can(self::PERMISSION_PAGES, App::auth()::PERMISSION_CONTENT_ADMIN)) {
            $types[] = 'page';
        }

        return $types;
    }

    /**
     * Get entries type argument.
     *
     * @param   array<string, mixed>    $args   The arguments
     *
     * @throws  McpToolException    If type can not be used
     */
    protected function type(array $args): string
    {
        $type = $this->str($args, 'type', 'post');
        if (!in_array($type, $this->types(), true)) {
            throw new McpToolException('Unknown entry type, use one of: ' . implode(', ', $this->types()));
        }

        return $type;
    }

    /**
     * Get entries type argument definition, if there is a choice.
     *
     * @return  array<string, array<string, mixed>>
     */
    protected function typeProperties(string $description): array
    {
        return count($this->types()) < 2 ? [] : [
            'type' => [
                'type'        => 'string',
                'enum'        => $this->types(),
                'description' => $description,
            ],
        ];
    }

    /**
     * Get media link types current blog can use.
     *
     * @return  array<string, McpMediaLink>
     */
    protected function links(): array
    {
        return McpMediaLinks::all();
    }

    /**
     * Check if current user can update an entry.
     */
    protected function editable(MetaRecord $rs): bool
    {
        return $this->can(App::auth()::PERMISSION_CONTENT_ADMIN)
            || ($this->can(App::auth()::PERMISSION_USAGE) && $rs->strField('user_id') === App::auth()->userID());
    }

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
     * @param   string                      $status     The status name or "all"
     * @param   string|array<int, string>   $type       The entries type(s)
     *
     * @return  array<string, mixed>
     */
    protected function visibility(string $status, string|array $type = 'post'): array
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
            'post_type'   => $type,
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
     * Get an entry current user can see, whatever its type.
     *
     * @throws  McpToolException    If entry is not found
     */
    protected function fetch(int $id): MetaRecord
    {
        if ($id < 1) {
            throw new McpToolException('Missing entry id');
        }

        // Published entry
        $params            = $this->visibility('published', $this->types());
        $params['post_id'] = $id;
        $rs                = App::blog()->getPosts($params);

        // Or any status limited to what user can see
        if ($rs->isEmpty()) {
            $params            = $this->visibility('all', $this->types());
            $params['post_id'] = $id;
            $rs                = App::blog()->getPosts($params);
        }

        if ($rs->isEmpty()) {
            throw new McpToolException('Entry not found');
        }

        return $rs;
    }

    /**
     * Check if current user can write entries.
     */
    protected function canWrite(): bool
    {
        return $this->can(App::auth()::PERMISSION_USAGE, App::auth()::PERMISSION_CONTENT_ADMIN);
    }

    /**
     * Get entries arguments shared by writing tools.
     *
     * @return  array<string, array<string, mixed>>
     */
    protected function writeProperties(): array
    {
        return [
            'title' => [
                'type'        => 'string',
                'description' => 'The entry title.',
            ],
            'content' => [
                'type'        => 'string',
                'description' => 'The entry content, in the format of the entry (HTML for xhtml).',
            ],
            'excerpt' => [
                'type'        => 'string',
                'description' => 'The entry excerpt, in the format of the entry. Empty for no excerpt.',
            ],
            'category_id' => [
                'type'        => 'integer',
                'description' => 'The category ID (see list_categories), 0 for no category. Ignored for pages.',
            ],
            'tags' => [
                'type'        => 'array',
                'items'       => ['type' => 'string'],
                'description' => 'The complete list of tags of the entry (it replaces existing tags). Ignored for pages.',
            ],
            'date' => [
                'type'        => 'string',
                'description' => 'The entry date as "YYYY-MM-DD HH:MM", in the timezone of the user (see get_blog).',
            ],
            'lang' => [
                'type'        => 'string',
                'description' => 'The entry language code (ex: fr).',
            ],
            'selected' => [
                'type'        => 'boolean',
                'description' => 'Mark the entry as selected. For a page, it hides the page from the widget listing pages.',
            ],
            'open_comments' => [
                'type'        => 'boolean',
                'description' => 'Accept comments on the entry.',
            ],
            'open_trackbacks' => [
                'type'        => 'boolean',
                'description' => 'Accept trackbacks on the entry.',
            ],
            'notes' => [
                'type'        => 'string',
                'description' => 'Personal notes about the entry, in plain text. They are never displayed on the blog. Empty for no notes.',
            ],
        ] + (in_array('page', $this->types(), true) ? [
            'position' => [
                'type'        => 'integer',
                'description' => 'The position of a page among pages, lowest first. Ignored for posts.',
            ],
        ] : []);
    }

    /**
     * Prepare content formater.
     *
     * Formaters are registered by editors in backend only: HTML does not need one,
     * wiki is registered here like the official plugin dcLegacyEditor does.
     *
     * @throws  McpToolException    If format can not be used here
     */
    protected function prepareFormat(string $format): void
    {
        if ($format === 'xhtml') {
            return;
        }

        if ($format === 'wiki') {
            if (!App::filter()->wiki() instanceof WikiToHtml) {
                App::filter()->initWikiPost();
            }

            $wiki = App::filter()->wiki();
            if ($wiki instanceof WikiToHtml) {
                App::formater()->addEditorFormater('dcLegacyEditor', 'wiki', $wiki->transform(...));

                return;
            }
        }

        throw new McpToolException(sprintf('Entries in format "%s" can only be written from the blog administration', $format));
    }

    /**
     * Get a valid entry date.
     *
     * @throws  McpToolException    If date is not valid
     */
    protected function date(string $date): string
    {
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i'] as $format) {
            $dt = DateTimeImmutable::createFromFormat('!' . $format, $date);
            if ($dt instanceof DateTimeImmutable && $dt->format($format) === $date) {
                return $dt->format('Y-m-d H:i:00');
            }
        }

        throw new McpToolException('Invalid date, use "YYYY-MM-DD HH:MM"');
    }

    /**
     * Set a category to an entry cursor.
     *
     * @throws  McpToolException    If category does not exist
     */
    protected function setCategory(Cursor $cur, int $cat_id): void
    {
        if ($cur->strField('post_type') === 'page') {
            return;
        }

        if ($cat_id < 1) {
            $cur->setField('cat_id', null);

            return;
        }

        if (App::blog()->getCategory($cat_id)->isEmpty()) {
            throw new McpToolException('Unknown category, see list_categories');
        }

        $cur->setField('cat_id', $cat_id);
    }

    /**
     * Replace tags of an entry.
     *
     * @param   array<int, string>  $tags   The tags
     */
    protected function setTags(int $id, array $tags, string $type = 'post'): void
    {
        if ($type === 'page' || !App::plugins()->moduleExists('tags')) {
            return;
        }

        App::meta()->delPostMeta($id, 'tag');
        foreach (App::meta()->splitMetaValues(implode(',', $tags)) as $tag) {
            App::meta()->setPostMeta($id, 'tag', $tag);
        }
    }

    /**
     * Describe an entry after it was written.
     *
     * @return  array<string, mixed>
     */
    protected function written(int $id): array
    {
        $post = $this->describe($this->fetch($id));

        if (App::config()->adminUrl() !== '') {
            // Admin URLs of entries types are only known in backend
            $post['edit_url'] = App::config()->adminUrl() . (str_ends_with(App::config()->adminUrl(), '/') ? '' : '/') .
                'index.php?process=' . ($post['type'] === 'page' ? 'Plugin&p=pages&act=page' : 'Post') . '&id=' . $id;
        }

        return $post;
    }

    /**
     * Describe media linked to an entry.
     *
     * @return  array<int, array<string, mixed>>
     */
    protected function media(int $id): array
    {
        $rs = App::postMedia()->getPostMedia([
            'post_id'   => $id,
            'link_type' => array_keys($this->links()),
            'columns'   => ['PM.link_type'],
        ]);

        $media = [];
        while ($rs->fetch()) {
            $media[] = [
                'id'    => $rs->intField('media_id'),
                'link'  => $rs->strField('link_type'),
                'path'  => $rs->strField('media_file'),
                'title' => $rs->strField('media_title'),
            ];
        }

        return $media;
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
            'type'     => $type,
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
            'lang'     => $rs->strField('post_lang'),
            'format'   => $rs->strField('post_format'),
            'timezone' => $rs->strField('post_tz'),
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
                'media'          => $this->media($rs->intField('post_id')),
                'excerpt'        => $rs->strField('post_excerpt'),
                'content'        => $rs->strField('post_content'),
            ];

            // Notes are personal: only for those who can update the entry
            if ($this->editable($rs)) {
                $post['notes'] = $rs->strField('post_notes');
            }
        }

        if ($type === 'page') {
            unset($post['category'], $post['tags']);
            $post['position'] = $rs->intField('post_position');
        }

        return $post;
    }
}
