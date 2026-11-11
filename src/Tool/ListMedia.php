<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp\Tool;

use Dotclear\App;
use Dotclear\Plugin\ClaudetteMcp\McpTool;
use Dotclear\Plugin\ClaudetteMcp\McpToolException;
use Exception;

/**
 * @brief       ClaudetteMcp tool: list media.
 * @ingroup     ClaudetteMcp
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class ListMedia extends McpTool
{
    public function name(): string
    {
        return 'list_media';
    }

    public function title(): string
    {
        return 'List media library';
    }

    public function description(): string
    {
        return 'List sub-directories and files of a directory of the blog media library, files by page. ' .
            'Private files of other users are listed only if the user is a media administrator.';
    }

    public function properties(): array
    {
        return [
            'dir' => [
                'type'        => 'string',
                'description' => 'Directory path relative to the media library root, default: the root.',
            ],
            'limit' => [
                'type'        => 'integer',
                'description' => 'Files per page, 1 to 100, default: 50.',
            ],
            'page' => [
                'type'        => 'integer',
                'description' => 'Page number, default: 1.',
            ],
        ];
    }

    /**
     * Media library requires its own permission.
     */
    public function available(): bool
    {
        return $this->can(App::auth()::PERMISSION_MEDIA, App::auth()::PERMISSION_MEDIA_ADMIN);
    }

    public function call(array $args): array
    {
        $dir   = $this->str($args, 'dir');
        $limit = $this->int($args, 'limit', 50, 1, 100);
        $page  = $this->int($args, 'page', 1, 1);

        try {
            App::media()->chdir($dir);
            App::media()->getDir();
        } catch (Exception) {
            throw new McpToolException('Directory not found');
        }

        $dirs = [];
        foreach (App::media()->getDirs() as $item) {
            if (!$item->parent) {
                $dirs[] = $item->relname;
            }
        }

        $files = [];
        $all = App::media()->getFiles();
        foreach (array_slice($all, ($page - 1) * $limit, $limit) as $item) {
            $vars    = get_object_vars($item);
            $files[] = [
                'id'    => isset($vars['media_id'])    && is_int($vars['media_id']) ? $vars['media_id'] : 0,
                'name'  => $item->basename,
                'path'  => $item->relname,
                'title' => isset($vars['media_title']) && is_string($vars['media_title']) ? $vars['media_title'] : '',
                'type'  => (string) $item->type,
                'size'  => $item->size,
                'date'  => isset($vars['media_dtstr']) && is_string($vars['media_dtstr']) ? $vars['media_dtstr'] : '',
                'url'   => $item->file_url,
            ];
        }

        return [
            'dir'   => $dir,
            'dirs'  => $dirs,
            'total' => count($all),
            'page'  => $page,
            'limit' => $limit,
            'files' => $files,
        ];
    }
}
