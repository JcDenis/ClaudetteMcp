<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp;

use ArrayObject;
use Dotclear\App;
use Dotclear\Helper\Process\TraitProcess;

/**
 * @brief       ClaudetteMcp frontend class.
 * @ingroup     ClaudetteMcp
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class Frontend
{
    use TraitProcess;

    public static function init(): bool
    {
        return self::status(My::checkContext(My::FRONTEND));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        // Register MCP endpoint to API server
        App::behavior()->addBehavior('ApiServerAddEndpoint', function (ArrayObject $endpoints): void {
            $endpoints->append(McpEndpoint::class);
        });

        return true;
    }
}
