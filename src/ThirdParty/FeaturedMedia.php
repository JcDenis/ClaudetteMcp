<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp\ThirdParty;

use ArrayObject;
use Dotclear\App;
use Dotclear\Plugin\ClaudetteMcp\McpMediaLink;

/**
 * @brief       ClaudetteMcp support of the plugin featuredMedia.
 * @ingroup     ClaudetteMcp
 *
 * The plugin featuredMedia (by Franck Paul) links one media to an entry
 * with the link type "featured" of the table post_media of Dotclear core.
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class FeaturedMedia
{
    public static function register(): void
    {
        App::behavior()->addBehavior('ClaudetteMcpAddMediaLink', function (ArrayObject $links): void {
            if (App::plugins()->moduleExists('featuredMedia')) {
                $links->append(new McpMediaLink('featured', 'the featured media of the entry', true));
            }
        });
    }
}
