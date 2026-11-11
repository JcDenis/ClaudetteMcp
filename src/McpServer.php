<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp;

use Dotclear\App;
use stdClass;
use Throwable;

/**
 * @brief       ClaudetteMcp MCP server.
 * @ingroup     ClaudetteMcp
 *
 * Minimal stateless Model Context Protocol server:
 * one JSON-RPC message per POST request, one JSON response, tools only.
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class McpServer
{
    /**
     * Supported protocol versions, latest first.
     *
     * @var     array<int, string>  VERSIONS
     */
    public const VERSIONS = [
        '2025-11-25',
        '2025-06-18',
        '2025-03-26',
    ];

    public const PARSE_ERROR      = -32700;
    public const INVALID_REQUEST  = -32600;
    public const METHOD_NOT_FOUND = -32601;
    public const INVALID_PARAMS   = -32602;

    /**
     * The HTTP status code.
     */
    private int $status = 200;

    /**
     * The response body.
     */
    private string $content = '';

    /**
     * @param   null|McpTools   $tools      The tools, null for an anonymous call (no valid access key)
     * @param   bool            $readonly   Limit to read only tools
     */
    public function __construct(
        private readonly ?McpTools $tools,
        private readonly bool $readonly = true,
    ) {
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Encode a JSON-RPC error.
     *
     * @param   int|string|null         $id         The request ID
     * @param   int                     $code       The error code
     * @param   string                  $message    The error message
     * @param   array<string, mixed>    $data       The additional data
     */
    public static function error(int|string|null $id, int $code, string $message, array $data = []): string
    {
        $error = [
            'code'    => $code,
            'message' => $message,
        ];
        if ($data !== []) {
            $error['data'] = $data;
        }

        return self::encode([
            'jsonrpc' => '2.0',
            'id'      => $id,
            'error'   => $error,
        ]);
    }

    /**
     * Handle a request.
     *
     * @param   string  $body       The request body
     * @param   string  $version    The protocol version from HTTP header
     */
    public function handle(string $body, string $version = ''): void
    {
        $message = json_decode($body, true);

        if (!is_array($message)) {
            $this->fail(400, null, self::PARSE_ERROR, 'Parse error');

            return;
        }

        // Batch is not supported
        if ($message === [] || array_is_list($message)) {
            $this->fail(400, null, self::INVALID_REQUEST, 'Invalid request');

            return;
        }

        $id     = isset($message['id'])     && (is_int($message['id']) || is_string($message['id'])) ? $message['id'] : null;
        $method = isset($message['method']) && is_string($message['method']) ? $message['method'] : '';
        $params = isset($message['params']) && is_array($message['params']) ? $message['params'] : [];

        // Notification or response from client: nothing to do
        if ($id === null || $method === '') {
            $this->status  = 202;
            $this->content = '';

            return;
        }

        if ($version !== '' && !in_array($version, self::VERSIONS, true)) {
            $this->fail(400, $id, self::INVALID_REQUEST, 'Unsupported protocol version', ['supported' => self::VERSIONS, 'requested' => $version]);

            return;
        }

        // Anonymous call: handshake only
        if (!$this->tools instanceof McpTools && !in_array($method, ['initialize', 'ping'], true)) {
            $this->fail(401, $id, self::INVALID_REQUEST, sprintf('Missing or invalid access key, send it in the %s request header', McpEndpoint::HEADER));

            return;
        }

        switch ($method) {
            case 'initialize':
                $this->succeed($id, $this->initialize($params));

                break;

            case 'ping':
                $this->succeed($id, new stdClass());

                break;

            case 'tools/list':
                $this->succeed($id, ['tools' => $this->listTools()]);

                break;

            case 'tools/call':
                $name = isset($params['name'])      && is_string($params['name']) ? $params['name'] : '';
                $args = isset($params['arguments']) && is_array($params['arguments']) ? $params['arguments'] : [];
                $tool = $this->tools?->get($name);

                if (!$tool instanceof McpTool || !$this->isAllowed($tool)) {
                    $this->fail(200, $id, self::INVALID_PARAMS, 'Unknown tool: ' . $name);

                    break;
                }

                $this->succeed($id, $this->callTool($tool, $args));

                break;

            default:
                $this->fail(200, $id, self::METHOD_NOT_FOUND, 'Method not found');
        }
    }

    /**
     * @param   array<int|string, mixed>    $params     The request parameters
     *
     * @return  array<string, mixed>
     */
    private function initialize(array $params): array
    {
        $requested = isset($params['protocolVersion']) && is_string($params['protocolVersion']) ? $params['protocolVersion'] : '';

        // Anonymous call: say nothing about the blog
        if (!$this->tools instanceof McpTools) {
            return [
                'protocolVersion' => in_array($requested, self::VERSIONS, true) ? $requested : self::VERSIONS[0],
                'capabilities'    => [
                    'tools' => new stdClass(),
                ],
                'serverInfo' => [
                    'name'    => My::id(),
                    'version' => '',
                ],
                'instructions' => sprintf('An access key is required to use this server, send it in the %s request header.', McpEndpoint::HEADER),
            ];
        }

        return [
            'protocolVersion' => in_array($requested, self::VERSIONS, true) ? $requested : self::VERSIONS[0],
            'capabilities'    => [
                'tools' => new stdClass(),
            ],
            'serverInfo' => [
                'name'    => My::id(),
                'title'   => My::name(),
                'version' => is_string($version = App::plugins()->getDefine(My::id())->get('version')) ? $version : '',
            ],
            'instructions' => sprintf(
                'Tools to work on the Dotclear blog "%s" (%s) with the permissions of its user "%s".',
                App::blog()->name(),
                App::blog()->url(),
                App::auth()->userID()
            ),
        ];
    }

    /**
     * @return  array<int, array<string, mixed>>
     */
    private function listTools(): array
    {
        $list = [];
        foreach ($this->tools?->all() ?? [] as $tool) {
            if (!$this->isAllowed($tool)) {
                continue;
            }

            $properties = $tool->properties();

            $list[] = [
                'name'        => $tool->name(),
                'title'       => $tool->title(),
                'description' => $tool->description(),
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => $properties === [] ? new stdClass() : $properties,
                    'required'   => $tool->required(),
                ],
                'annotations' => [
                    'title'           => $tool->title(),
                    'readOnlyHint'    => $tool->readOnly(),
                    'destructiveHint' => false,
                    'openWorldHint'   => false,
                ],
            ];
        }

        return $list;
    }

    /**
     * @param   array<int|string, mixed>    $args   The tool arguments
     *
     * @return  array<string, mixed>
     */
    private function callTool(McpTool $tool, array $args): array
    {
        try {
            $text    = self::encode($tool->call(array_filter($args, is_string(...), ARRAY_FILTER_USE_KEY)), true);
            $is_fail = false;
        } catch (McpToolException $exception) {
            $text    = $exception->getMessage();
            $is_fail = true;
        } catch (Throwable) {
            // Do not send internal errors details to the assistant
            $text    = 'Tool failed';
            $is_fail = true;
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $text,
                ],
            ],
            'isError' => $is_fail,
        ];
    }

    private function isAllowed(McpTool $tool): bool
    {
        return $tool->available() && ($tool->readOnly() || !$this->readonly);
    }

    private function succeed(int|string $id, mixed $result): void
    {
        $this->status  = 200;
        $this->content = self::encode([
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => $result,
        ]);
    }

    /**
     * @param   array<string, mixed>    $data   The additional data
     */
    private function fail(int $status, int|string|null $id, int $code, string $message, array $data = []): void
    {
        $this->status  = $status;
        $this->content = self::error($id, $code, $message, $data);
    }

    private static function encode(mixed $value, bool $pretty = false): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $json = json_encode($value, $flags);

        return $json === false ? '{}' : $json;
    }
}
