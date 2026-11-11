<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp;

use Dotclear\Plugin\ApiServer\ApiServerEndpoint;

/**
 * @brief       ClaudetteMcp API endpoint.
 * @ingroup     ClaudetteMcp
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class McpEndpoint extends ApiServerEndpoint
{
    public const ID = 'mcp';

    public const CACHE = false;

    /**
     * The request header used to send the access key.
     *
     * A dedicated header is used as some web servers do not give Authorization header to PHP.
     *
     * @var     string  HEADER
     */
    public const HEADER = 'X-ClaudetteMcp-Key';

    /**
     * The access key used for this call.
     */
    private McpKey $key;

    /**
     * Only POST method is supported (no SSE stream).
     */
    protected function checkEndpoint(): void
    {
        $method = isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';

        if ($method !== 'POST') {
            $this->sendRaw('', 405, 'application/json', ['Allow' => 'POST']);
        }
    }

    /**
     * User is authenticated by the access key from request header, or from URL if the key allows it.
     */
    protected function checkUser(): void
    {
        $name   = 'HTTP_' . strtoupper(str_replace('-', '_', self::HEADER));
        $header = isset($_SERVER[$name]) && is_string($_SERVER[$name]) ? trim($_SERVER[$name]) : '';

        // Key from header first, a key from URL is then ignored
        $key = McpKeys::find($header !== '' ? $header : $this->api->getArg(0));
        if ($header === '' && $key instanceof McpKey && $key->header) {
            $key = null;
        }

        // Do not say if key is wrong, expired, sent the wrong way or if user lost its permission
        if (!$key instanceof McpKey || !$this->setVerifiedUser($key->user)) {
            $this->sendRaw(McpServer::error(null, McpServer::INVALID_REQUEST, 'Not found'), 404);
        }

        $this->key = $key;
    }

    protected function callEndpoint(): void
    {
        $version = isset($_SERVER['HTTP_MCP_PROTOCOL_VERSION']) && is_string($_SERVER['HTTP_MCP_PROTOCOL_VERSION']) ? $_SERVER['HTTP_MCP_PROTOCOL_VERSION'] : '';

        $server = new McpServer(new McpTools(), $this->key->readonly);
        $server->handle($this->api->getBody(), $version);

        $this->sendRaw($server->getContent(), $server->getStatus());
    }
}
