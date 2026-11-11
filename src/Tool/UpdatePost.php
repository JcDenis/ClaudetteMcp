<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp\Tool;

use Dotclear\App;
use Dotclear\Plugin\ClaudetteMcp\McpToolException;

/**
 * @brief       ClaudetteMcp tool: update an entry.
 * @ingroup     ClaudetteMcp
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class UpdatePost extends PostTool
{
    public function name(): string
    {
        return 'update_post';
    }

    public function title(): string
    {
        return 'Update a blog entry';
    }

    public function description(): string
    {
        return 'Update an existing entry of the blog. Only given arguments are changed. ' .
            'This tool never changes the status of the entry, but changes on a published entry are visible on the blog at once. ' .
            'An entry can be updated only by its author or by a content administrator. ' .
            'Read the entry with get_post before changing its content.';
    }

    public function properties(): array
    {
        return [
            'id' => [
                'type'        => 'integer',
                'description' => 'The entry ID (see list_posts).',
            ],
        ] + $this->writeProperties() + [
            'url' => [
                'type'        => 'string',
                'description' => 'The entry URL part (ex: My-entry). Changing it breaks existing links to the entry.',
            ],
        ];
    }

    public function required(): array
    {
        return ['id'];
    }

    public function readOnly(): bool
    {
        return false;
    }

    public function available(): bool
    {
        return $this->canWrite();
    }

    public function call(array $args): array
    {
        $id = $this->int($args, 'id');
        $rs = $this->fetch($id);

        if (!$this->can(App::auth()::PERMISSION_CONTENT_ADMIN) && $rs->strField('user_id') !== App::auth()->userID()) {
            throw new McpToolException('You are not allowed to update this entry');
        }

        $format = $rs->strField('post_format');
        $this->prepareFormat($format);

        $title   = $this->has($args, 'title') ? $this->str($args, 'title') : $rs->strField('post_title');
        $content = $this->has($args, 'content') ? $this->str($args, 'content') : $rs->strField('post_content');
        $url     = $this->has($args, 'url') ? $this->str($args, 'url') : $rs->strField('post_url');

        if ($title === '' || $content === '' || $url === '') {
            throw new McpToolException('An entry needs a title, a content and an URL');
        }

        // Dotclear needs these fields on each update, even if they do not change
        $cur = App::blog()->openPostCursor();
        $cur->setStrField('post_title', $title);
        $cur->setStrField('post_content', $content);
        $cur->setStrField('post_excerpt', $this->has($args, 'excerpt') ? $this->str($args, 'excerpt') : $rs->strField('post_excerpt'));
        $cur->setStrField('post_format', $format);
        $cur->setStrField('post_lang', $this->has($args, 'lang') ? $this->str($args, 'lang') : $rs->strField('post_lang'));
        $cur->setStrField('post_dt', $this->has($args, 'date') ? $this->date($this->str($args, 'date')) : $rs->strField('post_dt'));
        $cur->setStrField('post_url', $url);
        $cur->setStrField('post_password', $rs->strField('post_password'));

        if ($this->has($args, 'selected')) {
            $cur->setIntField('post_selected', (int) $this->bool($args, 'selected'));
        }

        if ($this->has($args, 'open_comments')) {
            $cur->setIntField('post_open_comment', (int) $this->bool($args, 'open_comments'));
        }

        if ($this->has($args, 'open_trackbacks')) {
            $cur->setIntField('post_open_tb', (int) $this->bool($args, 'open_trackbacks'));
        }

        if ($this->has($args, 'category_id')) {
            $this->setCategory($cur, $this->int($args, 'category_id'));
        }

        App::blog()->updPost($id, $cur);

        if ($this->has($args, 'tags')) {
            $this->setTags($id, $this->strings($args, 'tags'));
        }

        return $this->written($id);
    }
}
