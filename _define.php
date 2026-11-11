<?php

/**
 * @file
 * @brief       The plugin ClaudetteMcp definition
 * @ingroup     ClaudetteMcp
 *
 * @defgroup    ClaudetteMcp Plugin ClaudetteMcp.
 *
 * Connect an AI assistant to your blog through MCP.
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
declare(strict_types=1);

if (!isset($this) || !is_object($this) || !method_exists($this, 'registerModule') || !isset($this->id) || !is_string($this->id)) {
    return;
}

$this->registerModule(
    'Claudette MCP',
    'Connect an AI assistant to your blog through MCP',
    'Jean-Christian Paul Denis and Contributors',
    '0.2',
    [
        'requires' => [
            ['php', '8.3'],
            ['core', '2.40'],
            ['ApiServer', '1.0'],
        ],
        'permissions' => 'My',
        'type'        => 'plugin',
        'support'     => 'https://github.com/JcDenis/' . $this->id . '/issues',
        'details'     => 'https://github.com/JcDenis/' . $this->id . '/src/branch/master/README.md',
        'repository'  => 'https://raw.githubusercontent.com/JcDenis/' . $this->id . '/master/dcstore.xml',
        'date'        => '2026-11-11T15:49:50+00:00',
    ]
);
