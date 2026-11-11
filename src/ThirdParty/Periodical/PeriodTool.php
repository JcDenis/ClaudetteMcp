<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp\ThirdParty\Periodical;

use Dotclear\App;
use Dotclear\Database\MetaRecord;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Plugin\ClaudetteMcp\Tool\PostTool;

/**
 * @brief       ClaudetteMcp periodical tools helper.
 * @ingroup     ClaudetteMcp
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
abstract class PeriodTool extends PostTool
{
    /**
     * The plugin ID, also its table name, its settings namespace and its meta type.
     */
    protected const PERIODICAL = 'periodical';

    /**
     * Like the plugin: it must be enabled on the blog, and the user must be able to write entries.
     */
    public function available(): bool
    {
        return App::blog()->settings()->get(self::PERIODICAL)->getBool('periodical_active', false) && $this->canWrite();
    }

    /**
     * Get periods of the blog.
     *
     * @param   int     $id     Limit to a period ID
     */
    protected function periods(int $id = 0): MetaRecord
    {
        $sql = new SelectStatement();
        $sql
            ->columns([
                'periodical_id',
                'periodical_title',
                'periodical_curdt',
                'periodical_enddt',
                'periodical_pub_int',
                'periodical_pub_nb',
            ])
            ->from(App::db()->con()->prefix() . self::PERIODICAL)
            ->where('blog_id = ' . $sql->quote(App::blog()->id()))
            ->and('periodical_type = ' . $sql->quote('post'))
            ->order('periodical_id ASC');

        if ($id > 0) {
            $sql->and('periodical_id = ' . $id);
        }

        return $sql->select() ?? MetaRecord::newFromArray([]);
    }

    /**
     * Get the period ID of an entry, 0 if none.
     */
    protected function periodOf(int $post_id): int
    {
        $sql = new SelectStatement();
        $rs  = $sql
            ->column('meta_id')
            ->from(App::db()->con()->prefix() . App::meta()::META_TABLE_NAME)
            ->where('meta_type = ' . $sql->quote(self::PERIODICAL))
            ->and('post_id = ' . $post_id)
            ->select();

        return $rs instanceof MetaRecord && !$rs->isEmpty() ? $rs->intField('meta_id') : 0;
    }

    /**
     * Describe current period of a record.
     *
     * @return  array<string, mixed>
     */
    protected function describePeriod(MetaRecord $rs): array
    {
        return [
            'id'       => $rs->intField('periodical_id'),
            'title'    => $rs->strField('periodical_title'),
            'next'     => $rs->strField('periodical_curdt'),
            'end'      => $rs->strField('periodical_enddt'),
            'interval' => $rs->strField('periodical_pub_int'),
            'number'   => $rs->intField('periodical_pub_nb'),
        ];
    }
}
