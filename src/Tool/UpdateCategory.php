<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp\Tool;

use Dotclear\App;
use Dotclear\Plugin\ClaudetteMcp\McpTool;
use Dotclear\Plugin\ClaudetteMcp\McpToolException;
use Exception;

/**
 * @brief       ClaudetteMcp tool: update a category.
 * @ingroup     ClaudetteMcp
 *
 * The category URL is kept: renaming a category never breaks its links.
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class UpdateCategory extends McpTool
{
    public function name(): string
    {
        return 'update_category';
    }

    public function title(): string
    {
        return 'Update a blog category';
    }

    public function description(): string
    {
        return 'Rename a category, change its description or move it under another category. ' .
            'Only given arguments are changed. The category URL is kept. Changes are visible on the blog at once.';
    }

    public function properties(): array
    {
        return [
            'id' => [
                'type'        => 'integer',
                'description' => 'The category ID (see list_categories).',
            ],
            'title' => [
                'type'        => 'string',
                'description' => 'The new category title.',
            ],
            'description' => [
                'type'        => 'string',
                'description' => 'The new category description, in HTML.',
            ],
            'parent_id' => [
                'type'        => 'integer',
                'description' => 'The new parent category ID (see list_categories), 0 for a top level category.',
            ],
        ];
    }

    public function required(): array
    {
        return ['id'];
    }

    public function readOnly(): bool
    {
        return false;
    }

    /**
     * Categories require their own permission.
     */
    public function available(): bool
    {
        return $this->can(App::auth()::PERMISSION_CATEGORIES);
    }

    public function call(array $args): array
    {
        $id = $this->int($args, 'id');
        $rs = App::blog()->getCategory($id);
        if ($id === 0 || $rs->isEmpty()) {
            throw new McpToolException('Unknown category, see list_categories');
        }

        try {
            if ($this->has($args, 'title') || $this->has($args, 'description')) {
                $title = $this->has($args, 'title') ? $this->str($args, 'title') : $rs->strField('cat_title');
                if ($title === '') {
                    throw new McpToolException('A category needs a title');
                }

                $cur = App::blog()->categories()->openCategoryCursor();
                $cur->setStrField('cat_title', $title);
                // An empty URL would be rebuilt from the title by the core
                $cur->setStrField('cat_url', $rs->strField('cat_url'));
                $cur->setStrField('cat_desc', $this->has($args, 'description') ? $this->str($args, 'description') : $rs->strField('cat_desc'));

                App::blog()->updCategory($id, $cur);
            }

            if ($this->has($args, 'parent_id')) {
                $parent  = $this->int($args, 'parent_id');
                $current = App::blog()->getCategoryParent($id);
                $current = $current->isEmpty() ? 0 : $current->intField('cat_id');

                if ($parent !== $current) {
                    if ($parent === $id || ($parent > 0 && App::blog()->getCategory($parent)->isEmpty())) {
                        throw new McpToolException('Unknown parent category, see list_categories');
                    }

                    App::blog()->setCategoryParent($id, $parent);
                }
            }
        } catch (McpToolException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new McpToolException($e->getMessage() === 'Cannot move tree' ?
                'A category can not be moved under itself or one of its sub-categories' :
                $e->getMessage());
        }

        $rs     = App::blog()->getCategory($id);
        $parent = App::blog()->getCategoryParent($id);

        return [
            'id'          => $rs->intField('cat_id'),
            'title'       => $rs->strField('cat_title'),
            'description' => $rs->strField('cat_desc'),
            'parent_id'   => $parent->isEmpty() ? 0 : $parent->intField('cat_id'),
            'url'         => App::blog()->url() . App::url()->getURLFor('category', $rs->strField('cat_url')),
        ];
    }
}
