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
            new Tool\UpdateCategory(),
            new Tool\UploadMedia(),
            new Tool\UpdateMedia(),
            new Tool\AttachMedia(),
            new Tool\DetachMedia(),
            new Tool\ListComments(),
            new Tool\SetCommentStatus(),
            new Tool\RenameTag(),
            new Tool\ListLinks(),
            new Tool\SetLink(),
            new Tool\GetCompatibilityAudit(),
        ]);

        # --BEHAVIOR-- ClaudetteMcpAddTool -- ArrayObject
        App::behavior()->callBehavior(My::id() . 'AddTool', $tools);

        foreach ($tools as $tool) {
            if ($tool instanceof McpTool && !isset($this->stack[$tool->name()])) {
                $this->stack[$tool->name()] = $tool;
            }
        }
    }

    /**
     * Get tools families, by ID, with their English name (translated in backend).
     *
     * The base family is always given to the assistant.
     *
     * @return  array<string, string>
     */
    public static function families(): array
    {
        return [
            McpTool::FAMILY_BASE           => 'Base',
            McpTool::FAMILY_POSTS          => 'Posts and pages',
            McpTool::FAMILY_CLASSIFICATION => 'Categories and tags',
            McpTool::FAMILY_MEDIA          => 'Media',
            McpTool::FAMILY_COMMENTS       => 'Comments',
            McpTool::FAMILY_EXTRAS         => 'Blog extras',
            McpTool::FAMILY_STATS          => 'Monitoring and statistics',
            McpTool::FAMILY_ADMIN          => 'Administration',
            McpTool::FAMILY_OTHER          => 'Others',
        ];
    }

    /**
     * Get the family of a tool, "other" for an unknown family.
     */
    public static function familyOf(McpTool $tool): string
    {
        return isset(self::families()[$tool->family()]) ? $tool->family() : McpTool::FAMILY_OTHER;
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
