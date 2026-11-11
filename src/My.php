<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp;

use Dotclear\App;
use Dotclear\Module\MyPlugin;
use Dotclear\Plugin\ApiServer\My as ApiServerMy;

/**
 * @brief       ClaudetteMcp module definition.
 * @ingroup     ClaudetteMcp
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class My extends MyPlugin
{
    public static function checkCustomContext(int $context): ?bool
    {
        return match ($context) {
            // Users with API permission (or blog administrators) manage their own keys
            self::BACKEND, self::MENU, self::MANAGE => App::task()->checkContext('BACKEND')
                && App::blog()->isDefined()
                && App::auth()->check(App::auth()->makePermissions([
                    ApiServerMy::id(),
                ]), App::blog()->id()),

            default => null,
        };
    }
}
