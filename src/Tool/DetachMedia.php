<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp\Tool;

use Dotclear\App;
use Dotclear\Plugin\ClaudetteMcp\McpToolException;

/**
 * @brief       ClaudetteMcp tool: unlink a media from an entry.
 * @ingroup     ClaudetteMcp
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class DetachMedia extends MediaTool
{
    public function name(): string
    {
        return 'detach_media';
    }

    public function title(): string
    {
        return 'Unlink a media from a blog entry';
    }

    public function description(): string
    {
        return 'Remove the link between a file of the media library and an entry of the blog. ' .
            'The file stays in the media library and the content of the entry is not changed. ' .
            'On a published entry the change is visible on the blog at once. ' .
            'An entry can be changed only by its author or by a content administrator.';
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

    public function available(): bool
    {
        return $this->canWrite() && $this->canMedia();
    }

    public function call(array $args): array
    {
        $post_id  = $this->int($args, 'post_id');
        $media_id = $this->int($args, 'media_id');
        $link     = $this->link($args);
        $rs       = $this->fetch($post_id);

        if (!$this->editable($rs)) {
            throw new McpToolException('You are not allowed to update this entry');
        }

        if (App::postMedia()->getPostMedia(['post_id' => $post_id, 'media_id' => $media_id, 'link_type' => $link])->isEmpty()) {
            throw new McpToolException('This media is not linked to this entry, see get_post');
        }

        App::postMedia()->removePostMedia($post_id, $media_id, $link);

        return [
            'id'    => $post_id,
            'media' => $this->media($post_id),
        ];
    }
}
