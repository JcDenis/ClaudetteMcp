<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp\Tool;

use Dotclear\App;
use Dotclear\Plugin\ClaudetteMcp\McpToolException;

/**
 * @brief       ClaudetteMcp tool: link a media to an entry.
 * @ingroup     ClaudetteMcp
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class AttachMedia extends MediaTool
{
    public function name(): string
    {
        return 'attach_media';
    }

    public function title(): string
    {
        return 'Link a media to a blog entry';
    }

    public function description(): string
    {
        return 'Link a file of the media library to an entry of the blog, without changing the content of the entry. ' .
            'On a published entry the change is visible on the blog at once. ' .
            'An entry can be changed only by its author or by a content administrator. ' .
            'Media linked to an entry are given by get_post.';
    }

    public function properties(): array
    {
        return $this->linkProperties();
    }

    public function required(): array
    {
        return ['post_id', 'media_id'];
    }

    public function readOnly(): bool
    {
        return false;
    }

    /**
     * Linking a media requires to write entries and to use the media library.
     */
    public function available(): bool
    {
        return $this->canWrite() && $this->canMedia();
    }

    public function call(array $args): array
    {
        $post_id = $this->int($args, 'post_id');
        $link    = $this->link($args);
        $rs      = $this->fetch($post_id);

        if (!$this->editable($rs)) {
            throw new McpToolException('You are not allowed to update this entry');
        }

        $media_id = $this->describeFile($this->file($this->int($args, 'media_id')))['id'];
        if (!is_int($media_id) || $media_id < 1) {
            throw new McpToolException('Media not found, see list_media');
        }

        // An entry has only one featured media
        if ($link === 'featured') {
            $old = App::postMedia()->getPostMedia(['post_id' => $post_id, 'link_type' => $link]);
            while ($old->fetch()) {
                if ($old->intField('media_id') !== $media_id) {
                    App::postMedia()->removePostMedia($post_id, $old->intField('media_id'), $link);
                }
            }
        }

        App::postMedia()->addPostMedia($post_id, $media_id, $link);

        return [
            'id'    => $post_id,
            'media' => $this->media($post_id),
        ];
    }
}
