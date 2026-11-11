<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp;

use Dotclear\App;

/**
 * @brief       ClaudetteMcp tool.
 * @ingroup     ClaudetteMcp
 *
 * A tool is a function the AI assistant can call.
 * Names and descriptions are read by the assistant, they are not translated.
 *
 * Tools run in frontend context where Dotclear does not limit contents
 * to user permissions: each tool MUST check permissions by itself.
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
abstract class McpTool
{
    /**
     * Get tool name (ex: list_posts).
     */
    abstract public function name(): string;

    /**
     * Get tool human readable title.
     */
    abstract public function title(): string;

    /**
     * Get tool description.
     */
    abstract public function description(): string;

    /**
     * Get tool arguments as JSON schema properties.
     *
     * @return  array<string, array<string, mixed>>
     */
    public function properties(): array
    {
        return [];
    }

    /**
     * Get required arguments names.
     *
     * @return  array<int, string>
     */
    public function required(): array
    {
        return [];
    }

    /**
     * Check if tool does not modify anything.
     */
    public function readOnly(): bool
    {
        return true;
    }

    /**
     * Check if tool can be used on current blog by current user.
     */
    public function available(): bool
    {
        return true;
    }

    /**
     * Call tool.
     *
     * @param   array<string, mixed>    $args   The arguments
     *
     * @throws  McpToolException   Message is sent to the assistant
     *
     * @return  array<string, mixed>    The result
     */
    abstract public function call(array $args): array;

    /**
     * Check a permission of current user on current blog.
     */
    protected function can(string ...$permissions): bool
    {
        return App::auth()->check(App::auth()->makePermissions(array_values($permissions)), App::blog()->id());
    }

    /**
     * @param   array<string, mixed>    $args   The arguments
     */
    protected function str(array $args, string $name, string $default = ''): string
    {
        return isset($args[$name]) && is_string($args[$name]) ? trim($args[$name]) : $default;
    }

    /**
     * @param   array<string, mixed>    $args   The arguments
     */
    protected function has(array $args, string $name): bool
    {
        return isset($args[$name]);
    }

    /**
     * @param   array<string, mixed>    $args   The arguments
     */
    protected function bool(array $args, string $name, bool $default = false): bool
    {
        return isset($args[$name]) && is_bool($args[$name]) ? $args[$name] : $default;
    }

    /**
     * @param   array<string, mixed>    $args   The arguments
     *
     * @return  array<int, string>
     */
    protected function strings(array $args, string $name): array
    {
        $values = [];
        if (isset($args[$name]) && is_array($args[$name])) {
            foreach ($args[$name] as $value) {
                if (is_string($value) && trim($value) !== '') {
                    $values[] = trim($value);
                }
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param   array<string, mixed>    $args   The arguments
     */
    protected function int(array $args, string $name, int $default = 0, int $min = 0, int $max = PHP_INT_MAX): int
    {
        $value = isset($args[$name]) && is_numeric($args[$name]) ? (int) $args[$name] : $default;

        return max($min, min($max, $value));
    }
}
