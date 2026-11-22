<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp\Tool;

use Dotclear\App;
use Dotclear\Helper\Html\Html;
use Dotclear\Plugin\ClaudetteMcp\McpToolException;
use Exception;
use SimpleXMLElement;

/**
 * @brief       ClaudetteMcp tool: update the properties of a media.
 * @ingroup     ClaudetteMcp
 *
 * Only title, alternate text, description and privacy, as in the media admin page.
 * The file itself is never moved, renamed nor replaced.
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class UpdateMedia extends MediaTool
{
    public function name(): string
    {
        return 'update_media';
    }

    public function title(): string
    {
        return 'Update a media';
    }

    public function description(): string
    {
        return 'Update the title, alternate text, description or privacy of a file of the media library. ' .
            'Only given arguments are changed. The alternate text describes the image for people who can not see it. ' .
            'A media administrator can update every file, other users only their own files. ' .
            'Changes are visible at once where the media is used on the blog.';
    }

    public function properties(): array
    {
        return [
            'id' => [
                'type'        => 'integer',
                'description' => 'The media ID (see list_media).',
            ],
            'title' => [
                'type'        => 'string',
                'description' => 'The media title.',
            ],
            'alt' => [
                'type'        => 'string',
                'description' => 'The alternate text, a short plain text description of an image.',
            ],
            'description' => [
                'type'        => 'string',
                'description' => 'The media description (used as legend), plain text.',
            ],
            'private' => [
                'type'        => 'boolean',
                'description' => 'True to make the media private (only its owner and media administrators see it in the media library).',
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
        return $this->canMedia();
    }

    public function call(array $args): array
    {
        $file = $this->file($this->int($args, 'id'));

        // Same rule as the core: a media administrator or the owner of the file
        if (!$this->can(App::auth()::PERMISSION_MEDIA_ADMIN) && $file->media_user !== App::auth()->userID()) {
            throw new McpToolException('Only the owner of this media or a media administrator can update it');
        }

        // Same path: the core moves the file if the path changes
        $new           = clone $file;
        $new->basename = basename($file->relname);
        $new->dir      = dirname($file->relname) === '.' ? '' : dirname($file->relname);
        if ($this->has($args, 'title')) {
            $new->media_title = Html::escapeHTML($this->str($args, 'title'));
        }
        if ($this->has($args, 'private')) {
            $new->media_priv = $this->bool($args, 'private');
        }

        // Clone metadata, not to change the original file object
        $meta = $file->media_meta instanceof SimpleXMLElement ? simplexml_load_string((string) $file->media_meta->asXML()) : null;
        if (!$meta instanceof SimpleXMLElement) {
            $meta = simplexml_load_string('<meta></meta>');
        }
        if ($meta instanceof SimpleXMLElement) {
            foreach (['alt' => 'AltText', 'description' => 'Description'] as $arg => $tag) {
                if ($this->has($args, $arg)) {
                    $value = Html::escapeHTML($this->str($args, $arg));
                    unset($meta->{$tag});
                    $meta->addChild($tag, $value);
                }
            }
            $new->media_meta = $meta;
        }

        try {
            App::media()->updateFile($file, $new);
        } catch (Exception $e) {
            throw new McpToolException($e->getMessage());
        }

        return $this->describeFile($this->file($this->int($args, 'id')));
    }
}
