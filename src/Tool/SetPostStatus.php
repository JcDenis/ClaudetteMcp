<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp\Tool;

use DateTimeImmutable;
use DateTimeZone;
use Dotclear\App;
use Dotclear\Plugin\ClaudetteMcp\McpToolException;
use Exception;

/**
 * @brief       ClaudetteMcp tool: change the status of an entry.
 * @ingroup     ClaudetteMcp
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class SetPostStatus extends PostTool
{
    public function name(): string
    {
        return 'set_post_status';
    }

    public function title(): string
    {
        return 'Publish or unpublish a blog entry';
    }

    public function description(): string
    {
        return 'Change the status of an entry of the blog. "published" makes the entry visible to everyone on the blog at once. ' .
            '"scheduled" publishes it automatically at its date, which must be in the future (set it with update_post). ' .
            '"unpublished" and "pending" hide it from the blog. ' .
            'Only do this when the user explicitly asks for it. No ping is sent.';
    }

    public function properties(): array
    {
        return [
            'id' => [
                'type'        => 'integer',
                'description' => 'The entry ID (see list_posts).',
            ],
            'status' => [
                'type'        => 'string',
                'enum'        => array_keys($this->statuses()),
                'description' => 'The new status of the entry.',
            ],
        ];
    }

    public function required(): array
    {
        return ['id', 'status'];
    }

    public function readOnly(): bool
    {
        return false;
    }

    /**
     * Changing a status requires its own permission.
     */
    public function available(): bool
    {
        return $this->can(App::auth()::PERMISSION_PUBLISH, App::auth()::PERMISSION_CONTENT_ADMIN);
    }

    public function call(array $args): array
    {
        $id       = $this->int($args, 'id');
        $status   = $this->str($args, 'status');
        $statuses = $this->statuses();
        $rs       = $this->fetch($id);

        if (!isset($statuses[$status])) {
            throw new McpToolException('Unknown status, use one of: ' . implode(', ', array_keys($statuses)));
        }

        if (!$this->can(App::auth()::PERMISSION_CONTENT_ADMIN) && $rs->strField('user_id') !== App::auth()->userID()) {
            throw new McpToolException('You are not allowed to change the status of this entry');
        }

        if ($status === 'scheduled' && $rs->strField('post_dt') <= $this->now($rs->strField('post_tz'))) {
            throw new McpToolException('The date of the entry is not in the future, set it with update_post before scheduling');
        }

        if ($rs->intField('post_status') !== $statuses[$status]) {
            App::blog()->updPostStatus($id, $statuses[$status]);
        }

        return $this->written($id);
    }

    /**
     * Get current date in a timezone.
     */
    private function now(string $timezone): string
    {
        try {
            $tz = new DateTimeZone($timezone !== '' ? $timezone : 'UTC');
        } catch (Exception) {
            $tz = new DateTimeZone('UTC');
        }

        return (new DateTimeImmutable('now', $tz))->format('Y-m-d H:i:s');
    }
}
