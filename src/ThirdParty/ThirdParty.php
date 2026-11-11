<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp\ThirdParty;

/**
 * @brief       ClaudetteMcp support of third party plugins.
 * @ingroup     ClaudetteMcp
 *
 * ClaudetteMcp tools only use Dotclear core and its distributed plugins.
 * This directory holds what third party plugins could provide by themselves:
 * each class uses ClaudetteMcp behaviors only, like another plugin would do,
 * and does nothing if its plugin is not enabled.
 *
 * To move a support into its plugin, copy the behaviors and delete the class here.
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class ThirdParty
{
    public static function register(): void
    {
        FeaturedMedia::register();
    }
}
