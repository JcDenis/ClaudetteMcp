<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp;

/**
 * @brief       ClaudetteMcp access key descriptor.
 * @ingroup     ClaudetteMcp
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class McpKey
{
    /**
     * @param   string  $hash       The key hash
     * @param   string  $user       The key owner user ID
     * @param   string  $label      The key label
     * @param   bool    $readonly   The key is limited to read only tools
     * @param   int     $expire     The expiration timestamp, 0 for never
     * @param   bool    $header     The key is accepted only from the request header
     * @param   string  $date       The creation date
     */
    public function __construct(
        public readonly string $hash,
        public readonly string $user,
        public readonly string $label,
        public readonly bool $readonly,
        public readonly int $expire,
        public readonly bool $header,
        public readonly string $date,
    ) {
    }

    public function isExpired(): bool
    {
        return $this->expire > 0 && $this->expire < time();
    }
}
