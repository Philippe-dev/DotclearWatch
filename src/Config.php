<?php
/**
 * @brief DotclearWatch, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\DotclearWatch;

use dcCore;
use Dotclear\Core\Backend\Notices;
use Dotclear\Core\Backend\Page;
use Dotclear\Core\Process;
use Dotclear\Helper\Html\Form\{
    Checkbox,
    Div,
    Input,
    Label,
    Li,
    Note,
    Para,
    Text,
    Textarea,
    Ul
};
use Dotclear\Helper\Html\Html;

class Config extends Process
{
    private static string $hidden_modules  = '';
    private static string $distant_api_url = '';

    public static function init(): bool
    {
        return self::status(My::checkContext(My::MANAGE));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        if (dcCore::app()->auth->user_prefs->get('interface')->get('colorsyntax')) {
            dcCore::app()->addBehavior('pluginsToolsHeadersV2', fn (bool $plugin): string => Page::jsLoadCodeMirror(dcCore::app()->auth->user_prefs->get('interface')->get('colorsyntax_theme')));
        }

        self::$hidden_modules  = (string) My::settings()->getGlobal('hidden_modules');
        self::$distant_api_url = (string) My::settings()->getGlobal('distant_api_url');

        if (empty($_POST)) {
            return true;
        }

        if (!empty($_POST['clear_cache'])) {
            Utils::clearCache();
            Notices::AddSuccessNotice(__('Cache directory sucessfully cleared.'));
        }

        self::$hidden_modules = '';
        foreach (explode(',', $_POST['hidden_modules']) as $hidden) {
            $hidden = trim($hidden);
            if (!empty($hidden)) {
                self::$hidden_modules .= trim($hidden) . ',';
            }
        }

        self::$distant_api_url = !empty($_POST['distant_api_url']) && is_string($_POST['distant_api_url']) ? $_POST['distant_api_url'] : Utils::DISTANT_API_URL;

        My::settings()->put('hidden_modules', self::$hidden_modules, 'string', 'Hidden modules from report', true, true);
        My::settings()->put('distant_api_url', self::$distant_api_url, 'string', 'Distant API report URL', true, true);
        Notices::AddSuccessNotice(__('Settings successfully updated.'));

        if (!empty($_POST['send_report'])) {
            Utils::sendReport(true);
            Notices::AddSuccessNotice(__('Report sent.'));
        }

        dcCore::app()->admin->url->redirect('admin.plugins', ['module' => My::id(), 'conf' => '1']);

        return true;
    }

    public static function render(): void
    {
        if (!self::status()) {
            return;
        }

        echo
        (new Div())->items([
            (new Text('p', __('Settings are globals. Reports are by blog.')))->class('message'),
            (new Ul())->items([
                (new Li())->text(sprintf(__('API: %s'), Utils::DISTANT_API_VERSION)),
                (new Li())->text(sprintf(__('UID: %s'), Utils::getClient())),
            ]),
            (new Para())->items([
                (new Label(__('Hidden modules:')))->for('hidden_modules'),
                (new Input('hidden_modules'))->class('maximal')->size(65)->maxlenght(255)->value(self::$hidden_modules),
            ]),
            (new Note())->class('form-note')->text(__('This is the comma separated list of plugins IDs and themes IDs to ignore in report.')),
            (new Para())->items([
                (new Label(__('Distant API URL:')))->for('distant_api_url'),
                (new Input('distant_api_url'))->class('maximal')->size(65)->maxlenght(255)->value(self::$distant_api_url),
            ]),
            (new Note())->class('form-note')->text(__('This is the URL of the API to send report. Leave empty to reset value.')),
            (new Para())->items([
                (new Checkbox('clear_cache', false))->value(1),
                (new Label(__('Clear reports cache directory'), Label::OUTSIDE_LABEL_AFTER))->for('clear_cache')->class('classic'),
            ]),
            (new Note())->class('form-note')->text(__('This deletes all blogs reports in cache.')),
            (new Para())->items([
                (new Checkbox('send_report', false))->value(1),
                (new Label(__('Send report now'), Label::OUTSIDE_LABEL_AFTER))->for('send_report')->class('classic'),
            ]),
            (new Note())->class('form-note')->text(__('This sent report for current blog even if report exists in cache.')),
        ])->render();

        $contents = Utils::getReport();
        if (!empty($contents)) {
            echo
            (new Para())->items([
                (new Label(__('Report that will be sent for this blog:')))->for('report_contents'),
                (new Textarea('report_contents', Html::escapeHTML($contents)))
                ->cols(165)
                ->rows(14)
                ->readonly(true)
                ->class('maximal'),
            ])->render() .
            (
                !dcCore::app()->auth->user_prefs->get('interface')->get('colorsyntax') ? '' :
                Page::jsRunCodeMirror(My::id() . 'editor', 'report_contents', 'json', dcCore::app()->auth->user_prefs->get('interface')->get('colorsyntax_theme'))
            );
        }
    }
}
