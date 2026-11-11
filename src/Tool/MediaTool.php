<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp\Tool;

use Dotclear\App;
use Dotclear\Helper\File\MediaFile;
use Dotclear\Plugin\ClaudetteMcp\McpToolException;
use Exception;

/**
 * @brief       ClaudetteMcp media tools helper.
 * @ingroup     ClaudetteMcp
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
abstract class MediaTool extends PostTool
{
    /**
     * Check if current user can use the media library.
     */
    protected function canMedia(): bool
    {
        return $this->can(App::auth()::PERMISSION_MEDIA, App::auth()::PERMISSION_MEDIA_ADMIN);
    }

    /**
     * Get a media current user can see.
     *
     * Private media of other users are found only by media administrators.
     *
     * @throws  McpToolException    If media is not found
     */
    protected function file(int $id): MediaFile
    {
        $file = null;
        if ($id > 0) {
            try {
                $file = App::media()->getFile($id);
            } catch (Exception) {
                $file = null;
            }
        }

        if (!$file instanceof MediaFile) {
            throw new McpToolException('Media not found, see list_media');
        }

        return $file;
    }

    /**
     * Describe a media.
     *
     * @return  array<string, mixed>
     */
    protected function describeFile(MediaFile $file): array
    {
        $vars = get_object_vars($file);

        return [
            'id'    => isset($vars['media_id'])    && is_numeric($vars['media_id']) ? (int) $vars['media_id'] : 0,
            'name'  => $file->basename,
            'path'  => $file->relname,
            'title' => isset($vars['media_title']) && is_string($vars['media_title']) ? $vars['media_title'] : '',
            'type'  => (string) $file->type,
            'size'  => $file->size,
            'date'  => isset($vars['media_dtstr']) && is_string($vars['media_dtstr']) ? $vars['media_dtstr'] : '',
            'url'   => $file->file_url,
        ];
    }

    /**
     * Get media link type argument.
     *
     * @param   array<string, mixed>    $args   The arguments
     *
     * @throws  McpToolException    If link type can not be used
     */
    protected function link(array $args): string
    {
        $link = $this->str($args, 'link', 'attachment');
        if (!in_array($link, $this->links(), true)) {
            throw new McpToolException('Unknown link type, use one of: ' . implode(', ', $this->links()));
        }

        return $link;
    }

    /**
     * Get arguments shared by tools linking a media to an entry.
     *
     * @return  array<string, array<string, mixed>>
     */
    protected function linkProperties(): array
    {
        return [
            'post_id' => [
                'type'        => 'integer',
                'description' => 'The entry ID (see list_posts).',
            ],
            'media_id' => [
                'type'        => 'integer',
                'description' => 'The media ID (see list_media).',
            ],
            'link' => [
                'type'        => 'string',
                'enum'        => $this->links(),
                'description' => 'The link type, default: attachment (a file to download listed with the entry).' .
                    (in_array('featured', $this->links(), true) ? ' "featured" is the featured media of the entry, an entry has only one.' : ''),
            ],
        ];
    }
}
