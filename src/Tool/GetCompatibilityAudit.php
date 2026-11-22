<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp\Tool;

use Dotclear\App;
use Dotclear\Plugin\ClaudetteMcp\McpTool;
use Dotclear\Plugin\ClaudetteMcp\McpToolException;
use Dotclear\Plugin\DotclearWatch\Audit;

/**
 * @brief       ClaudetteMcp tool: read the compatibility audit of DotclearWatch.
 * @ingroup     ClaudetteMcp
 *
 * DotclearWatch (distributed from Dotclear 2.41) checks the installed modules
 * against the rules of the next Dotclear version. This tool only reads the last result:
 * the audit is run by a super admin from the DotclearWatch admin page.
 * The audit covers the whole installation: super admin only, as its admin page.
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class GetCompatibilityAudit extends McpTool
{
    /**
     * Maximum number of findings sent for a module.
     */
    private const MAX_FINDINGS = 200;

    public function name(): string
    {
        return 'get_compatibility_audit';
    }

    public function title(): string
    {
        return 'Get the compatibility audit';
    }

    public function description(): string
    {
        return 'Get the last compatibility audit of DotclearWatch: which installed plugins and themes (distributed ones excepted) ' .
            'will break ("alert") or use deprecated code ("notice") with the next Dotclear version, and if the PHP version is enough. ' .
            'Without argument: a summary by module. With a module: its findings (file, line, symbol, replacement, note). ' .
            'The audit is only read: if it is missing or "outdated" is true, ask the user to run it from the DotclearWatch admin page.';
    }

    public function properties(): array
    {
        return [
            'module' => [
                'type'        => 'string',
                'description' => 'The module key from the summary, "plugin:<id>" or "theme:<id>", to get its findings.',
            ],
        ];
    }

    public function available(): bool
    {
        return App::auth()->isSuperAdmin()
            && App::plugins()->moduleExists('DotclearWatch')
            // The audit exists since DotclearWatch 1.8
            && is_string($version = App::plugins()->moduleInfo('DotclearWatch', 'version'))
            && version_compare($version, '1.8', '>=');
    }

    public function call(array $args): array
    {
        $rules = Audit::getRules();
        if ($rules === null) {
            throw new McpToolException('No compatibility rules yet: DotclearWatch receives them in answer to its next report');
        }

        $result = Audit::getResult();
        $php    = Audit::checkPhp();
        $module = $this->str($args, 'module');

        $data = [
            'dotclear' => App::config()->dotclearVersion(),
            'next'     => $rules['dotclear']['next']    ?? '',
            'php'      => PHP_VERSION,
            'php_min'  => $rules['dotclear']['php_min'] ?? '',
            'php_ok'   => $php === null,
            'rules'    => $rules['date'] > 0 ? date('c', $rules['date']) : '',
        ];

        if ($result === null) {
            return array_merge($data, ['audit' => null]);
        }

        $data = array_merge($data, [
            'audit'    => $result['date'] > 0 ? date('c', $result['date']) : '',
            'outdated' => $result['outdated'],
            'alerts'   => $result['alerts'],
            'notices'  => $result['notices'],
        ]);

        if ($module !== '') {
            if (!isset($result['results'][$module])) {
                throw new McpToolException('Unknown module in the audit, use a key from the summary');
            }

            $findings = is_array($findings = $result['results'][$module]['findings'] ?? null) ? $findings : [];

            return array_merge($data, [
                'module'    => $this->summary($module, $result['results'][$module]),
                'findings'  => array_slice($findings, 0, self::MAX_FINDINGS),
                'truncated' => count($findings) > self::MAX_FINDINGS,
            ]);
        }

        $modules = [];
        foreach ($result['results'] as $key => $item) {
            $modules[] = $this->summary($key, $item);
        }

        return array_merge($data, ['modules' => $modules]);
    }

    /**
     * @param   array<mixed>    $item
     *
     * @return  array<string, mixed>
     */
    private function summary(string $key, array $item): array
    {
        return [
            'key'     => $key,
            'name'    => is_string($item['name'] ?? null) ? $item['name'] : '',
            'version' => is_string($item['version'] ?? null) ? $item['version'] : '',
            'enabled' => (bool) ($item['enabled'] ?? false),
            'level'   => is_string($item['level'] ?? null) && $item['level'] !== '' ? $item['level'] : 'ok',
            'alerts'  => is_numeric($item['alerts'] ?? null) ? (int) $item['alerts'] : 0,
            'notices' => is_numeric($item['notices'] ?? null) ? (int) $item['notices'] : 0,
        ];
    }
}
