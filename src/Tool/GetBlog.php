<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp\Tool;

use Dotclear\App;
use Dotclear\Plugin\ClaudetteMcp\McpTool;

/**
 * @brief       ClaudetteMcp tool: get blog information.
 * @ingroup     ClaudetteMcp
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class GetBlog extends McpTool
{
    public function name(): string
    {
        return 'get_blog';
    }

    public function title(): string
    {
        return 'Get blog information';
    }

    public function description(): string
    {
        return 'Get information about the blog (name, URL, language, timezone) and about what the user is allowed to do on it. ' .
            'Dates of entries are in the timezone of their author.';
    }

    public function call(array $args): array
    {
        return [
            'id'          => App::blog()->id(),
            'name'        => App::blog()->name(),
            'description' => App::blog()->desc(),
            'url'         => App::blog()->url(),
            'lang'        => (string) App::blog()->settings()->get('system')->getStr('lang'),
            'timezone'    => (string) App::blog()->settings()->get('system')->getStr('blog_timezone'),
            'user'        => [
                'id'            => App::auth()->userID(),
                'timezone'      => is_string($user_tz = App::auth()->getInfo('user_tz')) ? $user_tz : 'UTC',
                'lang'          => is_string($user_lang = App::auth()->getInfo('user_lang')) ? $user_lang : '',
                'content_admin' => $this->can(App::auth()::PERMISSION_CONTENT_ADMIN),
                'write'         => $this->can(App::auth()::PERMISSION_USAGE, App::auth()::PERMISSION_CONTENT_ADMIN),
                'publish'       => $this->can(App::auth()::PERMISSION_PUBLISH, App::auth()::PERMISSION_CONTENT_ADMIN),
                'media'         => $this->can(App::auth()::PERMISSION_MEDIA, App::auth()::PERMISSION_MEDIA_ADMIN),
            ],
        ];
    }
}
