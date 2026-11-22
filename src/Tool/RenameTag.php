<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp\Tool;

use Dotclear\App;
use Dotclear\Database\MetaRecord;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Plugin\ClaudetteMcp\McpToolException;
use Exception;

/**
 * @brief       ClaudetteMcp tool: rename or merge a tag.
 * @ingroup     ClaudetteMcp
 *
 * One call changes every entry of the tag: reserved to content administrators,
 * as the core only renames the tag on the user own entries otherwise.
 * Only entries types open to tools are changed (posts, pages, types added by ClaudetteMcpAddPostType):
 * the core, like the admin page of the plugin tags, would rename the tag on entries of every type.
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class RenameTag extends PostTool
{
    public function name(): string
    {
        return 'rename_tag';
    }

    public function title(): string
    {
        return 'Rename a blog tag';
    }

    public function description(): string
    {
        return 'Rename a tag on all entries of the blog (' . implode(', ', $this->types()) . '). If the new tag already exists, both tags are merged. ' .
            'This changes many entries at once and can not be undone simply: use it only on explicit request of the user.';
    }

    public function properties(): array
    {
        return [
            'tag' => [
                'type'        => 'string',
                'description' => 'The current tag (see list_tags).',
            ],
            'new_tag' => [
                'type'        => 'string',
                'description' => 'The new tag, or an existing tag to merge into.',
            ],
        ];
    }

    public function required(): array
    {
        return ['tag', 'new_tag'];
    }

    public function readOnly(): bool
    {
        return false;
    }

    /**
     * Tags come from the official plugin tags.
     */
    public function available(): bool
    {
        return App::plugins()->moduleExists('tags') && $this->can(App::auth()::PERMISSION_CONTENT_ADMIN);
    }

    public function call(array $args): array
    {
        $tag = $this->str($args, 'tag');
        $new = App::meta()::sanitizeMetaID($this->str($args, 'new_tag'));

        if ($tag === '' || $new === '') {
            throw new McpToolException('Give the current tag and the new tag');
        }

        $merged = $new !== $tag && $this->count($new) > 0;

        try {
            $found = false;
            foreach ($this->types() as $type) {
                // Not "||": the tag must be renamed for every type
                $found = App::meta()->updateMeta($tag, $new, 'tag', $type) || $found;
            }
            if (!$found) {
                throw new McpToolException('Unknown tag, see list_tags');
            }
        } catch (McpToolException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new McpToolException($e->getMessage());
        }

        return [
            'tag'      => $new,
            'merged'   => $merged,
            'nb_posts' => $this->count($new),
            'url'      => App::blog()->url() . App::url()->getURLFor('tag', rawurlencode($new)),
        ];
    }

    /**
     * Count entries of the blog with a tag, whatever their status, in entries types open to tools.
     *
     * In frontend context, App::meta()->getMetadata() only counts published entries.
     */
    private function count(string $tag): int
    {
        $sql = new SelectStatement();
        $rs  = $sql
            ->column($sql->count($sql->unique('M.post_id')))
            ->from([
                $sql->as(App::db()->con()->prefix() . App::meta()::META_TABLE_NAME, 'M'),
                $sql->as(App::db()->con()->prefix() . App::blog()::POST_TABLE_NAME, 'P'),
            ])
            ->where('P.post_id = M.post_id')
            ->and('P.blog_id = ' . $sql->quote(App::blog()->id()))
            ->and('M.meta_type = ' . $sql->quote('tag'))
            ->and('M.meta_id = ' . $sql->quote($tag))
            ->and('P.post_type' . $sql->in($this->types()))
            ->select();

        return $rs instanceof MetaRecord && is_numeric($n = $rs->f(0)) ? (int) $n : 0;
    }
}
