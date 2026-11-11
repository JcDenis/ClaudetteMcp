<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp;

use ArrayObject;
use Dotclear\App;

/**
 * @brief       ClaudetteMcp tools stack.
 * @ingroup     ClaudetteMcp
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class McpTools
{
    /**
     * @var     array<string, McpTool>  $stack
     */
    private array $stack = [];

    public function __construct()
    {
        /**
         * @var     ArrayObject<int, mixed>     $tools
         */
        $tools = new ArrayObject([
            new Tool\GetBlog(),
            new Tool\ListPosts(),
            new Tool\GetPost(),
            new Tool\ListCategories(),
            new Tool\ListTags(),
            new Tool\ListMedia(),
            new Tool\CreatePost(),
            new Tool\UpdatePost(),
            new Tool\SetPostStatus(),
            new Tool\CreateCategory(),
            new Tool\UploadMedia(),
            new Tool\AttachMedia(),
            new Tool\DetachMedia(),
            new Tool\ListComments(),
            new Tool\SetCommentStatus(),
        ]);

        # --BEHAVIOR-- ClaudetteMcpAddTool -- ArrayObject
        App::behavior()->callBehavior(My::id() . 'AddTool', $tools);

        foreach ($tools as $tool) {
            if ($tool instanceof McpTool && !isset($this->stack[$tool->name()])) {
                $this->stack[$tool->name()] = $tool;
            }
        }
    }

    public function get(string $name): ?McpTool
    {
        return $this->stack[$name] ?? null;
    }

    /**
     * @return  array<string, McpTool>
     */
    public function all(): array
    {
        return $this->stack;
    }
}
