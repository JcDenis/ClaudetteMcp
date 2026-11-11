<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp\ThirdParty\Periodical;

/**
 * @brief       ClaudetteMcp tool: list periods of the plugin periodical.
 * @ingroup     ClaudetteMcp
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class ListPeriods extends PeriodTool
{
    public function name(): string
    {
        return 'list_periods';
    }

    public function title(): string
    {
        return 'List periods of periodical publication';
    }

    public function description(): string
    {
        return 'List periods of the blog (plugin periodical). A period publishes by itself the pending posts linked to it, ' .
            'one after the other: "number" posts each "interval", from the date "next" to the date "end". ' .
            'With an entry ID, it also gives the period this entry is linked to.';
    }

    public function properties(): array
    {
        return [
            'post_id' => [
                'type'        => 'integer',
                'description' => 'An entry ID (see list_posts) to get its period.',
            ],
        ];
    }

    public function call(array $args): array
    {
        $rs      = $this->periods();
        $periods = [];
        while ($rs->fetch()) {
            $periods[] = $this->describePeriod($rs);
        }

        $res = ['periods' => $periods];

        if ($this->int($args, 'post_id') > 0) {
            $post        = $this->fetch($this->int($args, 'post_id'));
            $res['post'] = [
                'id'        => $post->intField('post_id'),
                'period_id' => $this->periodOf($post->intField('post_id')),
            ];
        }

        return $res;
    }
}
