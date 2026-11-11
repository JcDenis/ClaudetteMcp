<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp\Tool;

use Dotclear\App;
use Dotclear\Helper\File\Files;
use Dotclear\Plugin\ClaudetteMcp\McpToolException;
use Exception;

/**
 * @brief       ClaudetteMcp tool: upload a media.
 * @ingroup     ClaudetteMcp
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class UploadMedia extends MediaTool
{
    public function name(): string
    {
        return 'upload_media';
    }

    public function title(): string
    {
        return 'Upload a file to the media library';
    }

    public function description(): string
    {
        return 'Add a new file to the blog media library. The content of the file is given base64 encoded. ' .
            'An existing file is never replaced: choose another name if the name is already used. ' .
            'Some file types are refused by the blog. A file of the media library is reachable by anyone who knows its URL.';
    }

    public function properties(): array
    {
        return [
            'name' => [
                'type'        => 'string',
                'description' => 'The file name with its extension (ex: my-picture.png), without directory.',
            ],
            'content' => [
                'type'        => 'string',
                'description' => 'The file content, base64 encoded.',
            ],
            'dir' => [
                'type'        => 'string',
                'description' => 'Existing directory path relative to the media library root (see list_media), default: the root.',
            ],
        ];
    }

    public function required(): array
    {
        return ['name', 'content'];
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
        $name = $this->str($args, 'name');
        $dir  = $this->str($args, 'dir');
        $bits = base64_decode($this->str($args, 'content'), true);

        if ($name === '' || str_contains($name, '/') || str_contains($name, '\\')) {
            throw new McpToolException('A file needs a name without directory');
        }

        if ($bits === false || $bits === '') {
            throw new McpToolException('The file content is empty or is not base64 encoded');
        }

        if (strlen($bits) > App::config()->maxUploadSize()) {
            throw new McpToolException(sprintf('The file is too big, the limit is %d bytes', App::config()->maxUploadSize()));
        }

        try {
            App::media()->chdir($dir);
            App::media()->getDir();
        } catch (Exception) {
            throw new McpToolException('Directory not found, see list_media');
        }

        // Same name as the one the media library will use
        $name = Files::tidyFileName($name);
        if ($name === '' || file_exists(App::media()->getPwd() . '/' . $name)) {
            throw new McpToolException(sprintf('A file named "%s" already exists in this directory, choose another name', $name));
        }

        try {
            $id = (int) App::media()->uploadBits($name, $bits);
        } catch (Exception) {
            $id = 0;
        }

        if ($id < 1) {
            throw new McpToolException('The blog refused this file: its type is not allowed or the directory is not writable');
        }

        return $this->describeFile($this->file($id));
    }
}
