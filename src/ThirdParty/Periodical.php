<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp\ThirdParty;

use ArrayObject;
use Dotclear\App;

/**
 * @brief       ClaudetteMcp support of the plugin periodical.
 * @ingroup     ClaudetteMcp
 *
 * The plugin periodical (by Jean-Christian Denis) publishes pending posts one after the other at regular periods.
 * Periods are in its table "periodical", a post is linked to a period by a meta of type "periodical"
 * in the table meta of Dotclear core. No class of the plugin is used here.
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class Periodical
{
    public static function register(): void
    {
        App::behavior()->addBehavior('ClaudetteMcpAddTool', function (ArrayObject $tools): void {
            if (App::plugins()->moduleExists('periodical')) {
                $tools->append(new Periodical\ListPeriods());
                $tools->append(new Periodical\SetPostPeriod());
            }
        });
    }
}
