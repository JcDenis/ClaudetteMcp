<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp;

use ArrayObject;
use Dotclear\App;

/**
 * @brief       ClaudetteMcp access keys handler.
 * @ingroup     ClaudetteMcp
 *
 * Keys are stored in Dotclear core credential table:
 * * credential_type is the plugin ID
 * * credential_value is the SHA-256 hash of the key (the key itself is never stored)
 * * credential_data contains key label and options
 * * a key is linked to one user and one blog
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class McpKeys
{
    /**
     * Get credential type.
     */
    public static function type(): string
    {
        return My::id();
    }

    /**
     * Check key format.
     */
    public static function isKey(string $key): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $key) === 1;
    }

    /**
     * Get key hash.
     */
    public static function hash(string $key): string
    {
        return hash('sha256', $key);
    }

    /**
     * Create a key for current user on current blog.
     *
     * @param   string  $label      The key label
     * @param   bool    $readonly   The key is limited to read only tools
     * @param   int     $expire     The expiration timestamp, 0 for never
     *
     * @return  string  The key, it can not be retrieved later
     */
    public static function create(string $label, bool $readonly = true, int $expire = 0): string
    {
        $key = bin2hex(random_bytes(32));

        $cur = App::credential()->openCredentialCursor();
        $cur->setStrField('user_id', App::auth()->userID());
        $cur->setStrField('blog_id', App::blog()->id());
        $cur->setStrField('credential_type', self::type());
        $cur->setStrField('credential_value', self::hash($key));
        $cur->setField('credential_data', new ArrayObject([
            'label'    => $label,
            'readonly' => $readonly,
            'expire'   => $expire,
        ]));

        App::credential()->setCredential(App::auth()->userID(), $cur);

        return $key;
    }

    /**
     * Find a key on current blog.
     *
     * Expired keys are not returned.
     *
     * @param   string  $key    The key
     */
    public static function find(string $key): ?McpKey
    {
        if (!self::isKey($key)) {
            return null;
        }

        $keys = self::search(['credential_value' => self::hash($key)]);
        $key  = $keys[0] ?? null;

        return $key instanceof McpKey && !$key->isExpired() ? $key : null;
    }

    /**
     * Get keys of current user on current blog.
     *
     * @return  array<int, McpKey>
     */
    public static function getUserKeys(): array
    {
        return self::search(['user_id' => App::auth()->userID()]);
    }

    /**
     * Delete a key of current user on current blog.
     *
     * @param   string  $hash   The key hash
     */
    public static function delete(string $hash): void
    {
        if (self::isKey($hash)) {
            App::credential()->delCredentials(self::type(), $hash, App::auth()->userID(), false);
        }
    }

    /**
     * Search keys on current blog.
     *
     * @param   array<string, string>   $params     The search parameters
     *
     * @return  array<int, McpKey>
     */
    private static function search(array $params): array
    {
        // credential_type and blog_id are mandatory: without them core searches global webauthn credentials
        $params['credential_type'] = self::type();
        $params['blog_id']         = App::blog()->id();

        $rs   = App::credential()->getCredentials($params);
        $keys = [];
        while ($rs->fetch()) {
            $data = App::credential()->decryptData($rs->strField('credential_data'));

            $keys[] = new McpKey(
                hash:     $rs->strField('credential_value'),
                user:     $rs->strField('user_id'),
                label:    isset($data['label'])    && is_string($data['label']) ? $data['label'] : '',
                readonly: !isset($data['readonly']) || $data['readonly'] !== false,
                expire:   isset($data['expire'])   && is_int($data['expire']) ? $data['expire'] : 0,
                date:     $rs->strField('credential_dt'),
            );
        }

        return $keys;
    }
}
