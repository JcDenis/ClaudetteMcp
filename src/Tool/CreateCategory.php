<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp\Tool;

use Dotclear\App;
use Dotclear\Plugin\ClaudetteMcp\McpTool;
use Dotclear\Plugin\ClaudetteMcp\McpToolException;

/**
 * @brief       ClaudetteMcp tool: create a category.
 * @ingroup     ClaudetteMcp
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class CreateCategory extends McpTool
{
    public function name(): string
    {
        return 'create_category';
    }

    public function title(): string
    {
        return 'Create a blog category';
    }

    public function description(): string
    {
        return 'Create a new category on the blog. It is visible on the blog as soon as a published post uses it. ' .
            'Check with list_categories that the category does not already exist.';
    }

    public function properties(): array
    {
        return [
            'title' => [
                'type'        => 'string',
                'description' => 'The category title.',
            ],
            'parent_id' => [
                'type'        => 'integer',
                'description' => 'The parent category ID (see list_categories), default: 0 for a top level category.',
            ],
            'description' => [
                'type'        => 'string',
                'description' => 'The category description, in HTML.',
            ],
        ];
    }

    public function required(): array
    {
        return ['title'];
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
        $title  = $this->str($args, 'title');
        $parent = $this->int($args, 'parent_id');

        if ($title === '') {
            throw new McpToolException('A category needs a title');
        }

        if ($parent > 0 && App::blog()->getCategory($parent)->isEmpty()) {
            throw new McpToolException('Unknown parent category, see list_categories');
        }

        $cur = App::blog()->categories()->openCategoryCursor();
        $cur->setStrField('cat_title', $title);
        $cur->setStrField('cat_desc', $this->str($args, 'description'));

        $rs = App::blog()->getCategory(App::blog()->addCategory($cur, $parent));

        return [
            'id'    => $rs->intField('cat_id'),
            'title' => $rs->strField('cat_title'),
            'url'   => App::blog()->url() . App::url()->getURLFor('category', $rs->strField('cat_url')),
        ];
    }
}
