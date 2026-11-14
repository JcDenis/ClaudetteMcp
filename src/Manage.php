<?php

declare(strict_types=1);

namespace Dotclear\Plugin\ClaudetteMcp;

use Dotclear\App;
use Dotclear\Helper\Date;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Hidden;
use Dotclear\Helper\Html\Form\Input;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Note;
use Dotclear\Helper\Html\Form\Option;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Select;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Form\Table;
use Dotclear\Helper\Html\Form\Tbody;
use Dotclear\Helper\Html\Form\Td;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Form\Th;
use Dotclear\Helper\Html\Form\Thead;
use Dotclear\Helper\Html\Form\Tr;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Process\TraitProcess;
use Dotclear\Plugin\ApiServer\My as ApiServerMy;
use Exception;

/**
 * @brief       ClaudetteMcp access keys management.
 * @ingroup     ClaudetteMcp
 *
 * Each user manages its own keys for the current blog.
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class Manage
{
    use TraitProcess;

    /**
     * Keys lifetimes in days.
     *
     * @var     array<int, int>     LIFETIMES
     */
    private const LIFETIMES = [0, 30, 90, 365];

    /**
     * The key created by this request.
     *
     * It is shown once on this page, it is never stored nor sent to another page.
     */
    private static string $new_key = '';

    /**
     * The key created by this request is accepted only from the request header.
     */
    private static bool $new_key_header = true;

    public static function init(): bool
    {
        return self::status(My::checkContext(My::MANAGE));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        try {
            // Create a key
            if (isset($_POST['create'])) {
                $label    = isset($_POST['key_label'])    && is_string($_POST['key_label']) ? trim($_POST['key_label']) : '';
                $lifetime = isset($_POST['key_lifetime']) && is_numeric($_POST['key_lifetime']) ? (int) $_POST['key_lifetime'] : 0;

                if ($label === '') {
                    throw new Exception(__('You must give a label to the key.'));
                }

                if (!in_array($lifetime, self::LIFETIMES, true)) {
                    $lifetime = 0;
                }

                self::$new_key = McpKeys::create(
                    mb_substr($label, 0, 64),
                    isset($_POST['key_readonly']),
                    $lifetime === 0 ? 0 : time() + $lifetime * 86400,
                    isset($_POST['key_header'])
                );
                self::$new_key_header = isset($_POST['key_header']);
            }

            // Revoke a key
            if (isset($_POST['revoke'])) {
                McpKeys::delete(isset($_POST['key_hash']) && is_string($_POST['key_hash']) ? $_POST['key_hash'] : '');

                App::backend()->notices()->addSuccessNotice(__('Key successfully revoked.'));
                My::redirect();
            }
        } catch (Exception $exception) {
            App::error()->add($exception->getMessage());
        }

        return true;
    }

    public static function render(): void
    {
        if (!self::status()) {
            return;
        }

        App::backend()->page()->openModule(My::name());

        echo
        App::backend()->page()->breadcrumb([
            Html::escapeHTML(App::blog()->name()) => '',
            My::name()                            => '',
        ]) .
        App::backend()->notices()->getNotices();

        if (!App::blog()->settings()->get(ApiServerMy::id())->getBool('active', false)) {
            echo (new Note())
                ->class('warning')
                ->text(__('API server is not active on this blog: keys can not be used until it is activated in blog settings.'))
                ->render();
        }

        if (self::$new_key !== '') {
            echo self::newKey()->render();
        }

        echo
        (new Text('h3', __('Your keys for this blog')))->render() .
        self::keysList()->render() .
        (new Text('h3', __('Create a key')))->render() .
        self::keyForm()->render();

        App::backend()->page()->helpBlock(My::id() . '-index');
        App::backend()->page()->closeModule();
    }

    /**
     * Get connector URL, with a key or not.
     */
    private static function connectorUrl(string $key = ''): string
    {
        return App::blog()->url() . App::url()->getURLFor(ApiServerMy::id(), McpEndpoint::ID . ($key === '' ? '' : '/' . $key));
    }

    private static function newKey(): Div
    {
        $items = [
            (new Text('p', __('Key successfully created. Copy these values now and add them as a connector in your AI assistant, the key will not be shown again.'))),
            (new Para())
                ->items([
                    (new Input('new_key_url'))
                        ->class('maximal')
                        ->readonly(true)
                        ->value(Html::escapeHTML(self::connectorUrl()))
                        ->label((new Label(__('Connector address:'), Label::OL_TF))),
                ]),
            (new Para())
                ->items([
                    (new Input('new_key_header'))
                        ->class('maximal')
                        ->readonly(true)
                        ->value(McpEndpoint::HEADER)
                        ->label((new Label(__('Request header name:'), Label::OL_TF))),
                ]),
            (new Para())
                ->items([
                    (new Input('new_key_value'))
                        ->class('maximal')
                        ->readonly(true)
                        ->value(self::$new_key)
                        ->label((new Label(__('Request header value (the key):'), Label::OL_TF))),
                ]),
        ];

        if (!self::$new_key_header) {
            $items[] = (new Para())
                ->items([
                    (new Input('new_key_full_url'))
                        ->class('maximal')
                        ->readonly(true)
                        ->value(Html::escapeHTML(self::connectorUrl(self::$new_key)))
                        ->label((new Label(__('Or, for an assistant which can not send request headers, this single address:'), Label::OL_TF))),
                ]);
        }

        $items[] = (new Text('p', __('The key is a secret: anyone who knows it can act on this blog with your permissions.')));

        return (new Div())
            ->class('success')
            ->items($items);
    }

    private static function keysList(): Table|Note
    {
        $keys = McpKeys::getUserKeys();
        if ($keys === []) {
            return (new Note())
                ->text(__('You have no key for this blog.'));
        }

        $user_tz = is_string($user_tz = App::auth()->getInfo('user_tz')) ? $user_tz : 'UTC';
        $rows    = [];
        foreach ($keys as $key) {
            $expire = __('Never');
            if ($key->expire > 0) {
                $expire = Date::str(__('%Y-%m-%d %H:%M'), $key->expire, $user_tz);
                if ($key->isExpired()) {
                    $expire .= ' (' . __('expired') . ')';
                }
            }

            $rows[] = (new Tr())
                ->class('line')
                ->cols([
                    (new Td())
                        ->class('maximal')
                        ->text(Html::escapeHTML($key->label)),
                    (new Td())
                        ->class('nowrap')
                        ->text($key->readonly ? __('Read only') : __('Read and write')),
                    (new Td())
                        ->class('nowrap')
                        ->text($key->header ? __('Header only') : __('Header or address')),
                    (new Td())
                        ->class('nowrap')
                        ->text(Html::escapeHTML(Date::dt2str(__('%Y-%m-%d %H:%M'), $key->date, $user_tz))),
                    (new Td())
                        ->class('nowrap')
                        ->text(Html::escapeHTML($expire)),
                    (new Td())
                        ->class('nowrap')
                        ->items([
                            (new Form('revoke_' . substr($key->hash, 0, 12)))
                                ->method('post')
                                ->action(My::manageUrl())
                                ->fields([
                                    (new Submit('revoke', __('Revoke')))
                                        ->class('delete'),
                                    (new Hidden('key_hash', $key->hash)),
                                    ... My::hiddenFields(),
                                ]),
                        ]),
                ]);
        }

        return (new Table('keys'))
            ->class('rch')
            ->thead((new Thead())
                ->rows([
                    (new Tr())
                        ->cols([
                            (new Th())->text(__('Label'))->scope('col'),
                            (new Th())->text(__('Access'))->scope('col'),
                            (new Th())->text(__('Key sent by'))->scope('col'),
                            (new Th())->text(__('Created'))->scope('col'),
                            (new Th())->text(__('Expires'))->scope('col'),
                            (new Th())->text(__('Action'))->scope('col'),
                        ]),
                ]))
            ->tbody((new Tbody())->rows($rows));
    }

    private static function keyForm(): Form
    {
        $lifetimes = [];
        foreach (self::LIFETIMES as $lifetime) {
            $lifetimes[] = new Option($lifetime === 0 ? __('Never') : sprintf(__('In %s days'), $lifetime), (string) $lifetime);
        }

        return (new Form('create_key'))
            ->method('post')
            ->action(My::manageUrl())
            ->fields([
                (new Para())
                    ->items([
                        (new Input('key_label'))
                            ->size(40)
                            ->maxlength(64)
                            ->required(true)
                            ->label((new Label(__('Label:'), Label::OL_TF))),
                    ]),
                (new Note())
                    ->class('form-note')
                    ->text(__('A name to recognize this key, for example the name of the assistant using it.')),
                (new Para())
                    ->items([
                        (new Checkbox('key_readonly', true))
                            ->value('1')
                            ->label((new Label(__('Read only'), Label::IL_FT))),
                    ]),
                (new Para())
                    ->items([
                        (new Checkbox('key_header', true))
                            ->value('1')
                            ->label((new Label(__('Accept the key only from the request header'), Label::IL_FT))),
                    ]),
                (new Note())
                    ->class('form-note')
                    ->text(__('Recommended. If unchecked, the key is also accepted in the connector address, where it can end in the web server logs.')),
                (new Para())
                    ->items([
                        (new Select('key_lifetime'))
                            ->items($lifetimes)
                            ->default('0')
                            ->label((new Label(__('Expires:'), Label::OL_TF))),
                    ]),
                (new Para())
                    ->class('form-buttons')
                    ->items([
                        (new Submit('create', __('Create a key'))),
                        ... My::hiddenFields(),
                    ]),
            ]);
    }
}
