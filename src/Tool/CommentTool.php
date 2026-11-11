<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp\Tool;

use Dotclear\App;
use Dotclear\Database\MetaRecord;
use Dotclear\Database\Statement\JoinStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Plugin\ClaudetteMcp\McpToolException;

/**
 * @brief       ClaudetteMcp comments tools helper.
 * @ingroup     ClaudetteMcp
 *
 * In frontend context Dotclear only gives published comments,
 * so comments are read here with the rule of the blog administration:
 * a content administrator sees all comments, other users see comments of their own entries.
 *
 * Email and IP addresses of visitors are never given to the assistant.
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
abstract class CommentTool extends PostTool
{
    /**
     * Get comments statuses the tools know.
     *
     * @return  array<string, int>
     */
    protected function commentStatuses(): array
    {
        return [
            'published'   => App::status()->comment()::PUBLISHED,
            'unpublished' => App::status()->comment()::UNPUBLISHED,
            'pending'     => App::status()->comment()::PENDING,
            'junk'        => App::status()->comment()::JUNK,
        ];
    }

    /**
     * Get a query on comments limited to what current user can see.
     */
    protected function query(): SelectStatement
    {
        $prefix = App::db()->con()->prefix();
        $sql    = new SelectStatement();
        $sql
            ->from($sql->as($prefix . App::blog()::COMMENT_TABLE_NAME, 'C'))
            ->join(
                (new JoinStatement())
                    ->inner()
                    ->from($sql->as($prefix . App::blog()::POST_TABLE_NAME, 'P'))
                    ->on('C.post_id = P.post_id')
                    ->statement()
            )
            ->where('P.blog_id = ' . $sql->quote(App::blog()->id()))
            ->and('P.post_type' . $sql->in($this->types()));

        if (!$this->can(App::auth()::PERMISSION_CONTENT_ADMIN)) {
            $sql->and('P.user_id = ' . $sql->quote(App::auth()->userID()));
        }

        return $sql;
    }

    /**
     * Get comments from a query.
     */
    protected function comments(SelectStatement $sql): MetaRecord
    {
        $sql->columns([
            'C.comment_id',
            'C.comment_dt',
            'C.comment_author',
            'C.comment_site',
            'C.comment_content',
            'C.comment_status',
            'C.comment_spam_filter',
            'C.comment_trackback',
            'P.post_id',
            'P.post_title',
            'P.post_type',
        ]);

        return $sql->select() ?? MetaRecord::newFromArray([]);
    }

    /**
     * Get a comment current user can see.
     *
     * @throws  McpToolException    If comment is not found
     */
    protected function comment(int $id): MetaRecord
    {
        $rs = $this->comments($this->query()->and('C.comment_id = ' . $id));
        if ($id < 1 || $rs->isEmpty()) {
            throw new McpToolException('Comment not found, see list_comments');
        }

        return $rs;
    }

    /**
     * Describe current comment of a record.
     *
     * @return  array<string, mixed>
     */
    protected function describeComment(MetaRecord $rs): array
    {
        $status = array_search($rs->intField('comment_status'), $this->commentStatuses(), true);

        return [
            'id'          => $rs->intField('comment_id'),
            'date'        => $rs->strField('comment_dt'),
            'status'      => $status === false ? 'unknown' : $status,
            'spam_filter' => $rs->strField('comment_spam_filter'),
            'trackback'   => $rs->boolField('comment_trackback'),
            'author'      => $rs->strField('comment_author'),
            'site'        => $rs->strField('comment_site'),
            'content'     => $rs->strField('comment_content'),
            'post'        => [
                'id'    => $rs->intField('post_id'),
                'type'  => $rs->strField('post_type'),
                'title' => $rs->strField('post_title'),
            ],
        ];
    }
}
