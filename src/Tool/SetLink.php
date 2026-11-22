<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp\Tool;

use Dotclear\Plugin\blogroll\Status\Link;
use Dotclear\Plugin\ClaudetteMcp\McpToolException;
use Exception;

/**
 * @brief       ClaudetteMcp tool: add or update a blogroll link or category.
 * @ingroup     ClaudetteMcp
 *
 * No deletion: an item is put offline instead.
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class SetLink extends BlogrollTool
{
    public function name(): string
    {
        return 'set_link';
    }

    public function title(): string
    {
        return 'Add or update a blogroll link';
    }

    public function description(): string
    {
        return 'Add a link or a link category to the blog blogroll, or update one (with its ID from list_links). ' .
            'On update, only given arguments are changed. Items can not be deleted: set status "offline" to hide one. ' .
            'Online items are visible on the blog at once.';
    }

    public function properties(): array
    {
        return [
            'id' => [
                'type'        => 'integer',
                'description' => 'The ID of the item to update (see list_links), omit to add a new item.',
            ],
            'kind' => [
                'type'        => 'string',
                'enum'        => ['link', 'category'],
                'description' => 'For a new item: "link" (default) or "category".',
            ],
            'title' => [
                'type'        => 'string',
                'description' => 'The link or category title, required for a new item.',
            ],
            'url' => [
                'type'        => 'string',
                'description' => 'The link URL, required for a new link.',
            ],
            'description' => [
                'type'        => 'string',
                'description' => 'The link description.',
            ],
            'lang' => [
                'type'        => 'string',
                'description' => 'The language code of the linked site (ex: fr).',
            ],
            'xfn' => [
                'type'        => 'string',
                'description' => 'XFN relationship values, space separated (ex: "friend met").',
            ],
            'status' => [
                'type'        => 'string',
                'enum'        => ['online', 'offline'],
                'description' => 'The item status, default for a new item: online.',
            ],
            'after_id' => [
                'type'        => 'integer',
                'description' => 'Move the item just after this item ID (see list_links), 0 to move it first. ' .
                    'A link belongs to the last category before it. A new item without it goes last.',
            ],
        ];
    }

    public function readOnly(): bool
    {
        return false;
    }

    public function call(array $args): array
    {
        $blogroll = $this->blogroll();
        $items    = $this->items();

        $id = $this->int($args, 'id');
        if ($id !== 0 && !isset($items[$id])) {
            throw new McpToolException('Unknown blogroll item, see list_links');
        }

        $after = $this->int($args, 'after_id');
        if ($after !== 0 && (!isset($items[$after]) || $after === $id)) {
            throw new McpToolException('Unknown after_id item, see list_links');
        }

        $current = $id !== 0 ? $items[$id] : [
            'kind'        => $this->str($args, 'kind', 'link') === 'category' ? 'category' : 'link',
            'title'       => '',
            'url'         => '',
            'description' => '',
            'lang'        => '',
            'xfn'         => '',
            'status'      => 'online',
        ];

        $value = fn (string $name): string => $this->has($args, $name) ? $this->str($args, $name) : (is_string($v = $current[$name] ?? '') ? $v : '');

        $status = $value('status') === 'offline' ? Link::OFFLINE : Link::ONLINE;
        $title  = $value('title');
        if ($title === '') {
            throw new McpToolException('A blogroll item needs a title');
        }

        try {
            if ($current['kind'] === 'category') {
                if ($id === 0) {
                    $id = $blogroll->addCategory($title, $status);
                } else {
                    $blogroll->updateCategory((string) $id, $title, $status);
                }
            } else {
                $url = $value('url');
                if ($url === '' || !preg_match('#^https?://#i', $url)) {
                    throw new McpToolException('A link needs an http or https URL');
                }
                if ($id === 0) {
                    $before = array_keys($items);
                    $blogroll->addLink($title, $url, $value('description'), $value('lang'), $value('xfn'), $status);
                    $after = array_keys($this->items());
                    $id    = array_values(array_diff($after, $before))[0] ?? 0;
                } else {
                    $blogroll->updateLink((string) $id, $title, $url, $value('description'), $value('lang'), $value('xfn'), $status);
                }
            }

            if ($id !== 0 && ($this->has($args, 'after_id') || !isset($items[$id]))) {
                $this->move($id, $this->has($args, 'after_id') ? $this->int($args, 'after_id') : -1);
            }
        } catch (McpToolException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new McpToolException($e->getMessage());
        }

        $items = $this->items();

        return $items[$id] ?? throw new McpToolException('Blogroll item not saved');
    }


    /**
     * Move an item after another one and renumber all positions.
     *
     * @param   int     $after  The item ID to move after, 0 for first, -1 for last
     */
    private function move(int $id, int $after): void
    {
        $order = array_values(array_filter(array_keys($this->items()), fn (int $item): bool => $item !== $id));

        if ($after === 0) {
            array_unshift($order, $id);
        } elseif ($after === -1) {
            $order[] = $id;
        } else {
            $pos = array_search($after, $order, true);
            if ($pos === false) {
                throw new McpToolException('Unknown after_id item, see list_links');
            }
            array_splice($order, $pos + 1, 0, [$id]);
        }

        foreach ($order as $position => $item) {
            $this->blogroll()->updateOrder((string) $item, (string) ($position + 1));
        }
    }
}
