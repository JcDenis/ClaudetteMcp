<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp\Tool;

use Dotclear\App;
use Dotclear\Plugin\ClaudetteMcp\McpToolException;

/**
 * @brief       ClaudetteMcp tool: create an entry.
 * @ingroup     ClaudetteMcp
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class CreatePost extends PostTool
{
    public function name(): string
    {
        return 'create_post';
    }

    public function title(): string
    {
        return 'Create a blog entry';
    }

    public function description(): string
    {
        return 'Create a new entry on the blog. The entry is never published by this tool: ' .
            'it is created unpublished (or pending if the user is not allowed to publish) and stays invisible on the blog. ' .
            'Its URL is computed from its title and date.';
    }

    public function properties(): array
    {
        return $this->writeProperties() + [
            'format' => [
                'type'        => 'string',
                'enum'        => ['xhtml', 'wiki'],
                'description' => 'The format of content and excerpt, default: xhtml (HTML).',
            ],
        ];
    }

    public function required(): array
    {
        return ['title', 'content'];
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
        $title   = $this->str($args, 'title');
        $content = $this->str($args, 'content');
        $format  = $this->str($args, 'format', 'xhtml');

        if ($title === '' || $content === '') {
            throw new McpToolException('An entry needs a title and a content');
        }

        $this->prepareFormat($format);

        $cur = App::blog()->openPostCursor();
        $cur->setStrField('post_type', static::POST_TYPE);
        $cur->setStrField('user_id', App::auth()->userID());
        $cur->setStrField('post_title', $title);
        $cur->setStrField('post_content', $content);
        $cur->setStrField('post_excerpt', $this->str($args, 'excerpt'));
        $cur->setStrField('post_format', $format);
        $cur->setStrField('post_lang', $this->str($args, 'lang', is_string($lang = App::auth()->getInfo('user_lang')) ? $lang : ''));
        $cur->setStrField('post_dt', $this->has($args, 'date') ? $this->date($this->str($args, 'date')) : '');
        $cur->setIntField('post_status', App::status()->post()::UNPUBLISHED);
        $cur->setIntField('post_selected', (int) $this->bool($args, 'selected'));
        $cur->setIntField('post_open_comment', (int) $this->bool($args, 'open_comments', App::blog()->settings()->get('system')->getBool('allow_comments', false)));
        $cur->setIntField('post_open_tb', (int) $this->bool($args, 'open_trackbacks', App::blog()->settings()->get('system')->getBool('allow_trackbacks', false)));
        $this->setCategory($cur, $this->int($args, 'category_id'));

        $id = App::blog()->addPost($cur);

        if ($this->has($args, 'tags')) {
            $this->setTags($id, $this->strings($args, 'tags'));
        }

        return $this->written($id);
    }
}
