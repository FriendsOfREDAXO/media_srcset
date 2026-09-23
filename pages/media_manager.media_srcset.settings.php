<?php

/**
 * Einstellungen: bisher nur der Schalter fuer die HTML-Platzhalterersetzung
 * (OUTPUT_FILTER). Der Schalter wirkt erst nach dem naechsten Seitenaufruf,
 * weil boot.php ihn beim Start auswertet.
 */

$addon = rex_addon::get('media_srcset');
$csrf = rex_csrf_token::factory('media_srcset_settings');

if (rex_post('func', 'string') === 'save') {
    if (!$csrf->isValid()) {
        echo rex_view::error(rex_i18n::msg('media_srcset_msg_csrf'));
    } else {
        $addon->setConfig('output_filter', (bool) rex_post('output_filter', 'int', 0));
        echo rex_view::success(rex_i18n::msg('media_srcset_settings_saved'));
    }
}

$outputFilter = (bool) $addon->getConfig('output_filter', true);

$content = '<p>' . rex_i18n::msg('media_srcset_settings_intro') . '</p>';
$content .= '<div class="checkbox"><label><input type="checkbox" name="output_filter" value="1"' . ($outputFilter ? ' checked' : '') . '> '
    . rex_i18n::msg('media_srcset_settings_output_filter') . '</label></div>';
$content .= '<p class="help-block">' . rex_i18n::msg('media_srcset_settings_output_filter_notice') . '</p>';

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('media_srcset_settings_title'), false);
$fragment->setVar('body', $content, false);
$body = $fragment->parse('core/page/section.php');

$fragment = new rex_fragment();
$fragment->setVar('elements', [
    ['field' => '<button class="btn btn-save" type="submit" name="func" value="save">' . rex_i18n::msg('media_srcset_settings_save') . '</button>'],
], false);
$buttons = $fragment->parse('core/form/submit.php');

echo '<form action="' . rex_url::currentBackendPage() . '" method="post">'
    . $csrf->getHiddenField()
    . $body
    . $buttons
    . '</form>';
