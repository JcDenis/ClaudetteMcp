<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp;

use ArrayObject;
use Dotclear\App;

/**
 * @brief       ClaudetteMcp media link types stack.
 * @ingroup     ClaudetteMcp
 *
 * Dotclear core only knows the link type "attachment",
 * other plugins add theirs with the behavior ClaudetteMcpAddMediaLink.
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class McpMediaLinks
{
    /**
     * @var     array<string, McpMediaLink>     $stack
     */
    private static array $stack = [];

    /**
     * @return  array<string, McpMediaLink>
     */
    public static function all(): array
    {
        if (self::$stack === []) {
            /**
             * @var     ArrayObject<int, mixed>     $links
             */
            $links = new ArrayObject([
                new McpMediaLink('attachment', 'a file to download listed with the entry'),
            ]);

            # --BEHAVIOR-- ClaudetteMcpAddMediaLink -- ArrayObject
            App::behavior()->callBehavior(My::id() . 'AddMediaLink', $links);

            foreach ($links as $link) {
                if ($link instanceof McpMediaLink && preg_match('/^[a-z][a-z0-9_]{0,31}$/', $link->type) === 1 && !isset(self::$stack[$link->type])) {
                    self::$stack[$link->type] = $link;
                }
            }
        }

        return self::$stack;
    }
}
