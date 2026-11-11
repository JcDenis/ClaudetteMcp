<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp;

use Dotclear\Helper\Process\TraitProcess;
use Dotclear\Plugin\Uninstaller\Uninstaller;

/**
 * @brief       ClaudetteMcp uninstall class.
 * @ingroup     ClaudetteMcp
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class Uninstall
{
    use TraitProcess;

    public static function init(): bool
    {
        return self::status(My::checkContext(My::UNINSTALL));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        Uninstaller::instance()
            ->addUserAction('credentials', 'delete_all', My::id())
            ->addUserAction('plugins', 'delete', My::id())
            ->addUserAction('versions', 'delete', My::id())
            ->addDirectAction('credentials', 'delete_all', My::id())
            ->addDirectAction('plugins', 'delete', My::id())
            ->addDirectAction('versions', 'delete', My::id())
        ;

        return true;
    }
}
