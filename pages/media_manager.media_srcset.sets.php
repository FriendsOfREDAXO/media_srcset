<?php

/**
 * media_srcset - Sets & Builder.
 *
 * Verwaltung der Builder-Presets (rex_config), Assistent zur Ableitung von
 * Breitenstufen aus Layout-Angaben, Analyse der Nutzung im Code und der
 * Originalbreiten im Medienpool sowie eine interaktive Vorschau mit
 * Descriptor-Check und Simulation der Browser-Auswahl.
 */

use FriendsOfRedaxo\MediaSrcset\Config\MediaTypeRegistry;
use FriendsOfRedaxo\MediaSrcset\Config\PresetStore;
use FriendsOfRedaxo\MediaSrcset\Config\SetBuilder;
use FriendsOfRedaxo\MediaSrcset\Media\ResponsiveImage;

$e = static fn ($v): string => rex_escape((string) $v);
$t = static fn (string $key, string ...$args): string => rex_i18n::msg($key, ...$args);
$csrf = rex_csrf_token::factory('media_srcset_sets');
$func = rex_request('func', 'string', '');
$message = '';

// ---------------------------------------------------------------------
// Aktionen
// ---------------------------------------------------------------------
if ($func !== '' && !$csrf->isValid()) {
    $message = rex_view::error($t('media_srcset_msg_csrf'));
    $func = '';
}
if ($func === 'save') {
    $name = trim(rex_post('name', 'string', ''));
    $errors = PresetStore::save($name, rex_post('preset', 'array', []), rex_post('original_name', 'string', ''));
    if ($errors === []) {
        PresetStore::registerActive();
        $message = rex_view::success($t('media_srcset_msg_saved', $name));
    } else {
        $message = rex_view::error(implode('<br>', array_map($e, $errors)));
    }
} elseif ($func === 'delete') {
    $name = rex_request('name', 'string', '');
    if (PresetStore::get($name) !== null) {
        PresetStore::clearCache($name);
        PresetStore::delete($name);
        $message = rex_view::success($t('media_srcset_msg_deleted', $name));
    }
} elseif ($func === 'toggle') {
    $name = rex_request('name', 'string', '');
    $preset = PresetStore::get($name);
    if ($preset !== null) {
        PresetStore::setActive($name, !$preset['active']);
        $message = rex_view::success($t('media_srcset_msg_status', $name, $t(!$preset['active'] ? 'media_srcset_status_active' : 'media_srcset_status_inactive')));
    }
} elseif ($func === 'clear_cache') {
    $name = rex_request('name', 'string', '');
    $message = rex_view::success($t('media_srcset_msg_cache_cleared', (string) PresetStore::clearCache($name), $name));
}
echo $message;

$link = static fn (string $f, array $params = []): string => rex_url::currentBackendPage(array_merge(['func' => $f], $params) + $csrf->getUrlParams());
$registered = MediaTypeRegistry::getPresets();
$custom = PresetStore::all();
$usage = SetBuilder::scanUsage();
$stats = SetBuilder::mediaStats();

// ---------------------------------------------------------------------
// 1. Liste
// ---------------------------------------------------------------------
$rows = [];
foreach (PresetStore::BUILTIN as $name => $config) {
    $rows[] = ['name' => $name, 'config' => $config, 'source' => 'code', 'active' => true, 'note' => ''];
}
foreach ($custom as $name => $config) {
    $rows[] = ['name' => $name, 'config' => $config, 'source' => 'builder', 'active' => $config['active'], 'note' => $config['note']];
}
$list = '<p>' . $t('media_srcset_sets_intro') . '</p>';
$list .= '<div class="table-responsive"><table class="table table-striped table-hover"><thead><tr>'
    . '<th>' . $t('media_srcset_col_name') . '</th><th>' . $t('media_srcset_col_ratio') . '</th><th>' . $t('media_srcset_col_mode') . '</th><th>' . $t('media_srcset_col_widths') . '</th><th>' . $t('media_srcset_col_default') . '</th><th>' . $t('media_srcset_col_source') . '</th><th>' . $t('media_srcset_col_status') . '</th><th>' . $t('media_srcset_col_cache') . '</th><th class="rex-table-action">&nbsp;</th>'
    . '</tr></thead><tbody>';
foreach ($rows as $row) {
    $name = $row['name'];
    $cfg = $row['config'];
    $cache = PresetStore::cacheInfo($name);
    $inUse = isset($usage[$name]);
    $list .= '<tr' . ($row['active'] ? '' : ' class="text-muted"') . '>';
    $list .= '<td><code>' . $e($name) . '</code>' . ($row['note'] !== '' ? '<br><small class="text-muted">' . $e($row['note']) . '</small>' : '') . '</td>';
    $list .= '<td>' . $e(PresetStore::RATIOS[$cfg['ratio']] ?? str_replace('_', ':', $cfg['ratio'])) . '</td>';
    $list .= '<td>' . $e($cfg['mode']) . '</td>';
    $list .= '<td>' . $e(implode(', ', $cfg['widths'])) . '</td>';
    $list .= '<td>' . $e((string) $cfg['default_width']) . '</td>';
    $list .= '<td>' . ($row['source'] === 'code' ? '<span class="label label-default">' . $t('media_srcset_source_code') . '</span>' : '<span class="label label-primary">' . $t('media_srcset_source_builder') . '</span>') . '</td>';
    $list .= '<td>' . ($row['active'] ? '<span class="label label-success">' . $t('media_srcset_status_active') . '</span>' : '<span class="label label-warning">' . $t('media_srcset_status_inactive') . '</span>') . '</td>';
    $list .= '<td>' . ($cache['files'] > 0 ? $e($t('media_srcset_cache_files', (string) $cache['files'], rex_formatter::bytes($cache['bytes']))) : '<span class="text-muted">' . $t('media_srcset_cache_none') . '</span>') . '</td>';
    $list .= '<td class="rex-table-action" style="white-space:nowrap">';
    $list .= '<a class="btn btn-xs btn-default" href="' . rex_url::currentBackendPage(['preview_preset' => $name]) . '#is-preview"><i class="rex-icon fa-eye"></i> ' . $t('media_srcset_action_test') . '</a> ';
    if ($row['source'] === 'builder') {
        $list .= '<a class="btn btn-xs btn-default" href="' . rex_url::currentBackendPage(['edit' => $name]) . '#is-form"><i class="rex-icon rex-icon-edit"></i> ' . $t('media_srcset_action_edit') . '</a> ';
        $list .= '<a class="btn btn-xs btn-default" href="' . $link('toggle', ['name' => $name]) . '">' . $t($row['active'] ? 'media_srcset_action_deactivate' : 'media_srcset_action_activate') . '</a> ';
        if ($inUse) {
            $list .= '<span class="btn btn-xs btn-default disabled" title="' . $e($t('media_srcset_delete_blocked')) . '"><i class="rex-icon rex-icon-delete"></i></span> ';
        } else {
            $list .= '<a class="btn btn-xs btn-delete" href="' . $link('delete', ['name' => $name]) . '" data-confirm="' . $e($t('media_srcset_confirm_delete', $name)) . '"><i class="rex-icon rex-icon-delete"></i> ' . $t('media_srcset_action_delete') . '</a> ';
        }
    }
    if ($cache['files'] > 0) {
        $list .= '<a class="btn btn-xs btn-default" href="' . $link('clear_cache', ['name' => $name]) . '" data-confirm="' . $e($t('media_srcset_confirm_clear_cache')) . '"><i class="rex-icon fa-eraser"></i> ' . $t('media_srcset_action_clear_cache') . '</a>';
    }
    $list .= '</td></tr>';
}
$list .= '</tbody></table></div>';
$list .= '<a class="btn btn-primary" href="' . rex_url::currentBackendPage(['new' => 1]) . '#is-form"><i class="rex-icon rex-icon-add"></i> ' . $t('media_srcset_action_new') . '</a>';

$fragment = new rex_fragment();
$fragment->setVar('title', $t('media_srcset_sets_list_title'), false);
$fragment->setVar('body', $list, false);
echo $fragment->parse('core/page/section.php');

// ---------------------------------------------------------------------
// 2. Formular + Assistent
// ---------------------------------------------------------------------
$editName = rex_request('edit', 'string', '');
$editing = $editName !== '' ? PresetStore::get($editName) : null;
$posted = rex_post('preset', 'array', []);
$values = [
    'name' => $func === 'save' && $message !== '' && str_contains($message, 'alert-danger') ? rex_post('name', 'string', '') : ($editing !== null ? $editName : ''),
    'ratio' => (string) ($posted['ratio'] ?? ($editing['ratio'] ?? '16_9')),
    'ratio_custom' => (string) ($posted['ratio_custom'] ?? ''),
    'mode' => (string) ($posted['mode'] ?? ($editing['mode'] ?? 'focuspoint')),
    'widths' => (string) ($posted['widths'] ?? ($editing !== null ? implode(', ', $editing['widths']) : '400, 800, 1200, 1600')),
    'default_width' => (string) ($posted['default_width'] ?? ($editing['default_width'] ?? '1200')),
    'chain' => (string) ($posted['chain'] ?? ($editing['chain'] ?? '')),
    'note' => (string) ($posted['note'] ?? ($editing['note'] ?? '')),
    'active' => $posted !== [] ? !empty($posted['active']) : ($editing['active'] ?? true),
];
if (!isset(PresetStore::RATIOS[$values['ratio']])) {
    $values['ratio_custom'] = $values['ratio'];
    $values['ratio'] = 'custom';
}
$containers = [
    'uk-container-xsmall' => $t('media_srcset_container_xsmall'), 'uk-container-small' => $t('media_srcset_container_small'), 'uk-container' => $t('media_srcset_container_default'),
    'uk-container-large' => $t('media_srcset_container_large'), 'uk-container-xlarge' => $t('media_srcset_container_xlarge'), 'expand' => $t('media_srcset_container_expand'),
];
$suggest = SetBuilder::suggest(['container' => 'uk-container', 'columns' => 3, 'columns_tablet' => 2, 'columns_mobile' => 1, 'fraction' => 100, 'retina' => true, 'source_max' => min(2400, max(800, SetBuilder::roundUp($stats['max'] ?: 2400)))]);

$field = static function (string $label, string $input, string $notice = ''): string {
    return '<div class="form-group"><label class="control-label">' . $label . '</label>' . $input . ($notice !== '' ? '<p class="help-block">' . $notice . '</p>' : '') . '</div>';
};
$form = '<form method="post" action="' . rex_url::currentBackendPage() . '#is-form" id="is-form">' . $csrf->getHiddenField() . '<input type="hidden" name="func" value="save"><input type="hidden" name="original_name" value="' . $e($editName) . '">';
$form .= '<div class="row"><div class="col-md-6">';
$form .= $field($t('media_srcset_field_name'), '<input class="form-control" type="text" name="name" value="' . $e($values['name']) . '" pattern="[a-z][a-z0-9_]{1,60}" required placeholder="teaser_3col">', $t('media_srcset_field_name_notice'));
$ratioSelect = '<select class="form-control" name="preset[ratio]" id="is-f-ratio">';
foreach (PresetStore::RATIOS as $value => $label) {
    $ratioSelect .= '<option value="' . $e($value) . '"' . ($values['ratio'] === $value ? ' selected' : '') . '>' . $e($label) . '</option>';
}
$ratioSelect .= '<option value="custom"' . ($values['ratio'] === 'custom' ? ' selected' : '') . '>' . $t('media_srcset_ratio_custom') . '</option></select>';
$ratioSelect .= '<input class="form-control" type="text" name="preset[ratio_custom]" id="is-f-ratio-custom" value="' . $e($values['ratio_custom']) . '" placeholder="5_4" style="margin-top:6px' . ($values['ratio'] === 'custom' ? '' : ';display:none') . '">';
$form .= $field($t('media_srcset_field_ratio'), $ratioSelect, $t('media_srcset_field_ratio_custom_notice'));
$form .= $field($t('media_srcset_field_mode'), '<select class="form-control" name="preset[mode]" id="is-f-mode"><option value="focuspoint"' . ($values['mode'] === 'focuspoint' ? ' selected' : '') . '>' . $t('media_srcset_mode_focuspoint') . '</option><option value="resize"' . ($values['mode'] === 'resize' ? ' selected' : '') . '>' . $t('media_srcset_mode_resize') . '</option></select>');
$form .= $field($t('media_srcset_field_widths'), '<input class="form-control" type="text" name="preset[widths]" id="is-f-widths" value="' . $e($values['widths']) . '" required>', $t('media_srcset_field_widths_notice', (string) PresetStore::MIN_WIDTH, (string) PresetStore::MAX_WIDTH, (string) PresetStore::MAX_STEPS));
$form .= $field($t('media_srcset_field_default'), '<input class="form-control" type="number" name="preset[default_width]" id="is-f-default" value="' . $e($values['default_width']) . '" min="' . PresetStore::MIN_WIDTH . '" max="' . PresetStore::MAX_WIDTH . '">', $t('media_srcset_field_default_notice'));
$form .= $field($t('media_srcset_field_chain'), '<input class="form-control" type="text" name="preset[chain]" value="' . $e($values['chain']) . '" placeholder="watermark,make_greyscale">', $t('media_srcset_field_chain_notice'));
$form .= $field($t('media_srcset_field_note'), '<input class="form-control" type="text" name="preset[note]" value="' . $e($values['note']) . '" maxlength="200">', $t('media_srcset_field_note_notice'));
$form .= '<div class="checkbox"><label><input type="checkbox" name="preset[active]" value="1"' . ($values['active'] ? ' checked' : '') . '> ' . $t('media_srcset_field_active') . '</label></div>';
$form .= '<p style="margin-top:12px"><button class="btn btn-save" type="submit">' . $t('media_srcset_btn_save') . '</button> ' . ($editing !== null ? '<a class="btn btn-default" href="' . rex_url::currentBackendPage() . '">' . $t('media_srcset_btn_cancel') . '</a>' : '') . '</p>';
$form .= '</div><div class="col-md-6">';
// Assistent
$form .= '<div class="panel panel-default"><div class="panel-heading"><strong>' . $t('media_srcset_assistant_title') . '</strong></div><div class="panel-body" id="is-assistant">';
$form .= '<p class="help-block">' . $t('media_srcset_assistant_intro') . '</p>';
$containerSelect = '<select class="form-control" id="is-a-container">';
foreach ($containers as $value => $label) {
    $containerSelect .= '<option value="' . $e($value) . '" data-px="' . (int) SetBuilder::CONTAINERS[$value] . '"' . ($value === 'uk-container' ? ' selected' : '') . '>' . $e($label) . '</option>';
}
$containerSelect .= '</select>';
$form .= '<div class="row"><div class="col-sm-12">' . $field($t('media_srcset_assistant_container'), $containerSelect) . '</div>';
$form .= '<div class="col-sm-4">' . $field($t('media_srcset_assistant_cols_desktop'), '<input class="form-control" type="number" id="is-a-cd" min="1" max="6" value="3">') . '</div>';
$form .= '<div class="col-sm-4">' . $field($t('media_srcset_assistant_cols_tablet'), '<input class="form-control" type="number" id="is-a-ct" min="1" max="4" value="2">') . '</div>';
$form .= '<div class="col-sm-4">' . $field($t('media_srcset_assistant_cols_mobile'), '<input class="form-control" type="number" id="is-a-cm" min="1" max="2" value="1">') . '</div>';
$form .= '<div class="col-sm-6">' . $field($t('media_srcset_assistant_fraction'), '<input class="form-control" type="number" id="is-a-fraction" min="5" max="100" value="100">', $t('media_srcset_assistant_fraction_notice')) . '</div>';
$form .= '<div class="col-sm-6">' . $field($t('media_srcset_assistant_source_max'), '<input class="form-control" type="number" id="is-a-source" min="100" max="' . PresetStore::MAX_WIDTH . '" step="100" value="' . (int) ($suggest['steps'][3]['x2'] > 0 ? min(2400, max(800, SetBuilder::roundUp($stats['max'] ?: 2400))) : 2400) . '">', $t('media_srcset_assistant_source_max_notice')) . '</div>';
$form .= '<div class="col-sm-6">' . $field($t('media_srcset_assistant_bp_tablet'), '<input class="form-control" type="number" id="is-a-bpt" min="320" max="1600" value="640">') . '</div>';
$form .= '<div class="col-sm-6">' . $field($t('media_srcset_assistant_bp_desktop'), '<input class="form-control" type="number" id="is-a-bpd" min="480" max="2400" value="1200">') . '</div>';
$form .= '<div class="col-sm-12"><div class="checkbox"><label><input type="checkbox" id="is-a-retina" checked> ' . $t('media_srcset_assistant_retina') . '</label></div></div></div>';
$form .= '<h5>' . $t('media_srcset_assistant_steps') . '</h5><table class="table table-condensed" id="is-a-steps"><thead><tr><th></th><th>' . $t('media_srcset_assistant_css') . '</th><th>' . $t('media_srcset_assistant_1x') . '</th><th>' . $t('media_srcset_assistant_2x') . '</th></tr></thead><tbody>';
foreach (['phone', 'mobile', 'tablet', 'desktop'] as $key) {
    $form .= '<tr data-step="' . $key . '"><td>' . $t('media_srcset_step_' . $key) . '</td><td class="is-css">–</td><td class="is-x1">–</td><td class="is-x2">–</td></tr>';
}
$form .= '</tbody></table>';
$form .= '<p><strong>' . $t('media_srcset_assistant_result') . ':</strong> <code id="is-a-result">' . $e(implode(', ', $suggest['widths'])) . '</code> · ' . $t('media_srcset_col_default') . ' <code id="is-a-default">' . (int) $suggest['default_width'] . '</code></p>';
$form .= '<button class="btn btn-default" type="button" id="is-a-apply"><i class="rex-icon fa-magic"></i> ' . $t('media_srcset_assistant_apply') . '</button>';
$form .= '</div></div></div></div></form>';

$fragment = new rex_fragment();
$fragment->setVar('title', $editing !== null ? $t('media_srcset_form_title_edit', $editName) : $t('media_srcset_form_title_new'), false);
$fragment->setVar('body', $form, false);
echo $fragment->parse('core/page/section.php');

// ---------------------------------------------------------------------
// 3. Nutzung & Bestand
// ---------------------------------------------------------------------
$analysis = '<p>' . $t('media_srcset_analysis_intro') . '</p><div class="row"><div class="col-md-7">';
$analysis .= '<h4>' . $t('media_srcset_analysis_usage_title') . '</h4>';
if ($usage === []) {
    $analysis .= '<p class="text-muted">' . $t('media_srcset_analysis_usage_none') . '</p>';
} else {
    $analysis .= '<table class="table table-condensed table-striped"><thead><tr><th>' . $t('media_srcset_col_name') . '</th><th>' . $t('media_srcset_analysis_used_in') . '</th><th>' . $t('media_srcset_analysis_widths_requested') . '</th></tr></thead><tbody>';
    foreach ($usage as $preset => $u) {
        $analysis .= '<tr><td><code>' . $e($preset) . '</code>' . (isset($registered[$preset]) ? '' : ' <span class="label label-danger">' . $t('media_srcset_analysis_not_registered') . '</span>') . '</td><td>' . $e(implode(', ', $u['modules'])) . '</td><td>' . $e($u['widths'] !== [] ? implode(', ', $u['widths']) : '–') . '</td></tr>';
    }
    $analysis .= '</tbody></table>';
}
$analysis .= '</div><div class="col-md-5"><h4>' . $t('media_srcset_analysis_media_title') . '</h4>';
$analysis .= '<p>' . $e($t('media_srcset_analysis_media_total', (string) $stats['total'], (string) $stats['median'], (string) $stats['max'])) . '</p>';
$analysis .= '<table class="table table-condensed"><thead><tr><th>' . $t('media_srcset_analysis_bucket') . '</th><th class="text-right">' . $t('media_srcset_analysis_count') . '</th><th></th></tr></thead><tbody>';
foreach ($stats['buckets'] as $bucket) {
    $pct = $stats['total'] > 0 ? (int) round($bucket['count'] / $stats['total'] * 100) : 0;
    $analysis .= '<tr><td>' . $e($bucket['label']) . '</td><td class="text-right">' . $bucket['count'] . '</td><td style="width:40%"><div style="background:#4bbbd9;height:10px;border-radius:5px;width:' . $pct . '%"></div></td></tr>';
}
$analysis .= '</tbody></table></div></div>';
// Hinweise
$hints = [];
foreach ($usage as $preset => $u) {
    if (!isset($registered[$preset])) {
        $hints[] = ['danger', $t('media_srcset_analysis_hint_unregistered', $preset)];
        continue;
    }
    $widths = $registered[$preset]['widths'];
    foreach ($u['widths'] as $requested) {
        if (!in_array($requested, $widths, true)) {
            $hints[] = ['info', $t('media_srcset_analysis_hint_missing', $preset, (string) $requested, (string) MediaTypeRegistry::normalizeWidth($registered[$preset], $requested))];
        }
    }
    if ($u['widths'] !== []) {
        foreach ($widths as $w) {
            if (!in_array($w, $u['widths'], true)) {
                $hints[] = ['default', $t('media_srcset_analysis_hint_unused', $preset, (string) $w)];
            }
        }
    }
}
foreach ($registered as $preset => $config) {
    foreach ($config['widths'] as $w) {
        $share = SetBuilder::upscaleShare($stats, (int) $w);
        if ($share >= 80 && $stats['total'] > 0) {
            $hints[] = ['warning', $t('media_srcset_analysis_hint_upscale', $preset, (string) $share, (string) $w)];
        }
    }
}
$analysis .= '<h4>' . $t('media_srcset_analysis_hints') . '</h4>';
if ($hints === []) {
    $analysis .= '<p class="text-muted">' . $t('media_srcset_analysis_hint_ok') . '</p>';
} else {
    $analysis .= '<ul class="list-group">';
    foreach ($hints as [$level, $text]) {
        $analysis .= '<li class="list-group-item"><span class="label label-' . $level . '">&nbsp;</span> ' . $e($text) . '</li>';
    }
    $analysis .= '</ul>';
}
$fragment = new rex_fragment();
$fragment->setVar('title', $t('media_srcset_analysis_title'), false);
$fragment->setVar('body', $analysis, false);
$fragment->setVar('collapse', true);
$fragment->setVar('collapsed', $hints === []);
echo $fragment->parse('core/page/section.php');

// ---------------------------------------------------------------------
// 4. Vorschau & Test
// ---------------------------------------------------------------------
$previewPresets = $registered;
foreach ($custom as $name => $config) {
    if (!isset($previewPresets[$name])) {
        $previewPresets[$name] = $config; // auch inaktive Sets lassen sich testen
    }
}
ksort($previewPresets, SORT_NATURAL | SORT_FLAG_CASE);
$pFile = trim(rex_request('preview_media', 'string', ''));
$pPreset = rex_request('preview_preset', 'string', '');
if (!isset($previewPresets[$pPreset])) {
    $pPreset = (string) (array_key_first($previewPresets) ?? '');
}
$pContainer = rex_request('preview_container', 'string', 'uk-container');
if (!isset($containers[$pContainer])) {
    $pContainer = 'uk-container';
}
$pCols = max(1, min(6, rex_request('preview_columns', 'int', 3)));
$pColsT = max(1, min(4, rex_request('preview_columns_tablet', 'int', 2)));
$pColsM = max(1, min(2, rex_request('preview_columns_mobile', 'int', 1)));
$pFraction = max(5, min(100, rex_request('preview_fraction', 'int', 100)));
$pBpT = max(320, min(1600, rex_request('preview_bp_tablet', 'int', 640)));
$pBpD = max($pBpT + 1, min(2400, rex_request('preview_bp_desktop', 'int', 1200)));
$doPreview = rex_request('preview', 'int', 0) === 1 || rex_request('preview_preset', 'string', '') !== '';
$pMedia = $pFile !== '' ? rex_media::get($pFile) : null;
if ($pMedia === null) {
    $sql = rex_sql::factory();
    $sql->setQuery('SELECT filename FROM ' . rex::getTable('media') . ' WHERE filetype LIKE "image/%" AND filetype NOT LIKE "%svg%" AND width > 0 ORDER BY width DESC LIMIT 1');
    if ($sql->getRows()) {
        $pFile = (string) $sql->getValue('filename');
        $pMedia = rex_media::get($pFile);
    }
}
$pf = '<form method="get" action="' . rex_url::currentBackendPage() . '#is-preview" id="is-preview"><input type="hidden" name="page" value="' . $e(rex_be_controller::getCurrentPage()) . '"><input type="hidden" name="preview" value="1">';
$pf .= '<p>' . $t('media_srcset_preview_intro') . '</p><div class="row">';
$pf .= '<div class="col-md-5">' . $field($t('media_srcset_preview_media'), rex_var_media::getWidget(1, 'preview_media', $pFile)) . '</div>';
$presetSelect = '<select class="form-control" name="preview_preset">';
foreach ($previewPresets as $name => $config) {
    $presetSelect .= '<option value="' . $e($name) . '"' . ($name === $pPreset ? ' selected' : '') . '>' . $e($name) . ' (' . $e(PresetStore::RATIOS[$config['ratio']] ?? $config['ratio']) . ', ' . $e(implode('/', $config['widths'])) . ')</option>';
}
$presetSelect .= '</select>';
$pf .= '<div class="col-md-4">' . $field($t('media_srcset_preview_preset'), $presetSelect) . '</div>';
$pMobile = rex_request('preview_mobile', 'string', '');
if ($pMobile !== '' && !isset($previewPresets[$pMobile])) {
    $pMobile = '';
}
$mobileSelect = '<select class="form-control" name="preview_mobile"><option value="">' . $t('media_srcset_preview_mobile_none') . '</option>';
foreach ($previewPresets as $name => $config) {
    $mobileSelect .= '<option value="' . $e($name) . '"' . ($name === $pMobile ? ' selected' : '') . '>' . $e($name) . '</option>';
}
$mobileSelect .= '</select>';
$containerSelect = '<select class="form-control" name="preview_container">';
foreach ($containers as $value => $label) {
    $containerSelect .= '<option value="' . $e($value) . '"' . ($value === $pContainer ? ' selected' : '') . '>' . $e($label) . '</option>';
}
$containerSelect .= '</select>';
$pf .= '<div class="col-md-3">' . $field($t('media_srcset_assistant_container'), $containerSelect) . '</div></div><div class="row">';
$pf .= '<div class="col-md-4">' . $field($t('media_srcset_preview_mobile'), $mobileSelect, $t('media_srcset_preview_mobile_notice')) . '</div>';
$pf .= '<div class="col-md-2">' . $field($t('media_srcset_assistant_cols_desktop'), '<input class="form-control" type="number" name="preview_columns" min="1" max="6" value="' . $pCols . '">') . '</div>';
$pf .= '<div class="col-md-2">' . $field($t('media_srcset_assistant_cols_tablet'), '<input class="form-control" type="number" name="preview_columns_tablet" min="1" max="4" value="' . $pColsT . '">') . '</div>';
$pf .= '<div class="col-md-2">' . $field($t('media_srcset_assistant_cols_mobile'), '<input class="form-control" type="number" name="preview_columns_mobile" min="1" max="2" value="' . $pColsM . '">') . '</div>';
$pf .= '<div class="col-md-2">' . $field($t('media_srcset_assistant_fraction'), '<input class="form-control" type="number" name="preview_fraction" min="5" max="100" value="' . $pFraction . '">') . '</div>';
$pf .= '<div class="col-md-2">' . $field($t('media_srcset_assistant_bp_tablet'), '<input class="form-control" type="number" name="preview_bp_tablet" min="320" max="1600" value="' . $pBpT . '">') . '</div>';
$pf .= '<div class="col-md-2">' . $field($t('media_srcset_assistant_bp_desktop'), '<input class="form-control" type="number" name="preview_bp_desktop" min="480" max="2400" value="' . $pBpD . '">') . '</div>';
$pf .= '</div><button class="btn btn-primary" type="submit"><i class="rex-icon fa-eye"></i> ' . $t('media_srcset_preview_show') . '</button></form>';

$previewBody = $pf;
$srcsetJson = '[]';
$sizesJson = '""';
if ($doPreview && $pMedia !== null && $pPreset !== '') {
    // Inaktive Builder-Sets fuer diese Anfrage temporaer registrieren
    if (!isset($registered[$pPreset])) {
        MediaTypeRegistry::registerPreset($pPreset, $previewPresets[$pPreset]);
    }
    if ($pMobile !== '' && !isset($registered[$pMobile])) {
        MediaTypeRegistry::registerPreset($pMobile, $previewPresets[$pMobile]);
    }
    $image = ResponsiveImage::forFile($pFile)
        ->withDesktopPreset($pPreset)
        ->withWidths($previewPresets[$pPreset]['widths'])
        ->withContainerWidth($pContainer)
        ->withColumns($pCols, $pColsT, $pColsM)
        ->withMediaFraction($pFraction / 100)
        ->withBreakpoints($pBpT, $pBpD)
        ->withPriority();
    if ($pMobile !== '' && $pMobile !== $pPreset) {
        $image->withSource('(max-width: ' . ($pBpT - 1) . 'px)', $pMobile, ['widths' => $previewPresets[$pMobile]['widths']]);
    }
    $data = $image->toImage();
    $entries = $image->getSrcsetEntries();
    $tag = $pMobile !== '' && $pMobile !== $pPreset
        ? $image->toPictureTag(['id' => 'is-preview-img', 'style' => 'max-width:100%;height:auto;display:block'])
        : $image->toImageTag(['id' => 'is-preview-img', 'style' => 'max-width:100%;height:auto;display:block']);

    $previewBody .= '<hr><h4>' . $t('media_srcset_preview_markup') . '</h4><pre style="white-space:pre-wrap">' . $e($tag) . '</pre>';
    $previewBody .= '<p><span class="label label-default">width × height</span> ' . (int) $data['width'] . ' × ' . (int) $data['height'] . ' px &nbsp; <span class="label label-default">alt</span> ' . ($data['decorative'] ? '<em>' . $t('media_srcset_preview_alt_decorative') . '</em>' : $e($data['alt'])) . '</p>';
    $previewBody .= '<h4>' . $t('media_srcset_preview_variants') . '</h4><div class="table-responsive"><table class="table table-condensed table-striped"><thead><tr><th>' . $t('media_srcset_preview_type') . '</th><th>' . $t('media_srcset_preview_descriptor') . '</th><th>' . $t('media_srcset_preview_file') . '</th><th>' . $t('media_srcset_preview_size') . '</th><th>' . $t('media_srcset_preview_status') . '</th></tr></thead><tbody>';
    $totalBytes = 0;
    $srcsetList = [];
    foreach ($entries as [$typeWidth, $descriptorWidth]) {
        $type = MediaTypeRegistry::buildVirtualType($pPreset, (int) $typeWidth);
        $actualW = 0;
        $actualH = 0;
        $bytes = 0;
        $error = '';
        try {
            $manager = rex_media_manager::create($type, $pFile);
            $cacheFile = $manager->getCacheFilename();
            if (is_file($cacheFile)) {
                $dims = @getimagesize($cacheFile);
                if (is_array($dims)) {
                    $actualW = (int) $dims[0];
                    $actualH = (int) $dims[1];
                }
                $bytes = (int) filesize($cacheFile);
            }
            if ($actualW === 0) {
                $managed = $manager->getMedia();
                $actualW = (int) $managed->getWidth();
                $actualH = (int) $managed->getHeight();
            }
        } catch (Throwable $ex) {
            $error = $ex->getMessage();
        }
        $totalBytes += $bytes;
        $srcsetList[] = ['type' => $type, 'w' => (int) $descriptorWidth, 'bytes' => $bytes];
        $ok = $error === '' && $actualW === (int) $descriptorWidth;
        $status = $error !== '' ? '<span class="label label-danger">' . $t('media_srcset_preview_error') . '</span> ' . $e($error)
            : ($ok ? '<span class="label label-success">' . $t('media_srcset_preview_ok') . '</span>' : '<span class="label label-warning">' . $e($t('media_srcset_preview_deviation', (string) ($actualW - (int) $descriptorWidth))) . '</span>');
        $previewBody .= '<tr><td><code>' . $e($type) . '</code></td><td>' . (int) $descriptorWidth . 'w</td><td>' . ($actualW ? $actualW . ' × ' . $actualH . ' px' : '–') . '</td><td>' . ($bytes ? rex_formatter::bytes($bytes) : '–') . '</td><td>' . $status . '</td></tr>';
    }
    $previewBody .= '</tbody></table></div><p class="text-muted">' . $e($t('media_srcset_preview_total', rex_formatter::bytes($totalBytes))) . '</p>';
    $srcsetJson = json_encode($srcsetList, JSON_UNESCAPED_SLASHES) ?: '[]';
    $sizesJson = json_encode($data['sizes'], JSON_UNESCAPED_SLASHES) ?: '""';

    // Simulation
    $previewBody .= '<div class="row"><div class="col-md-7"><h4>' . $t('media_srcset_preview_sim_title') . '</h4><p class="help-block">' . $t('media_srcset_preview_sim_intro') . ' <code>sizes="' . $e($data['sizes']) . '"</code></p>';
    $previewBody .= '<table class="table table-condensed" id="is-sim"><thead><tr><th>' . $t('media_srcset_preview_sim_viewport') . '</th><th>DPR</th><th>' . $t('media_srcset_preview_sim_slot') . '</th><th>' . $t('media_srcset_preview_sim_needed') . '</th><th>' . $t('media_srcset_preview_sim_chosen') . '</th></tr></thead><tbody></tbody></table>';
    $previewBody .= '<div class="form-group"><label>' . $t('media_srcset_preview_sim_custom') . ': <span id="is-sim-custom-val">1024</span> px</label><input type="range" id="is-sim-custom" min="320" max="2560" step="10" value="1024" style="width:100%"></div>';
    $previewBody .= '<div id="is-sim-custom-out" class="well well-sm"></div></div>';
    $previewBody .= '<div class="col-md-5"><h4>' . $t('media_srcset_preview_live_title') . '</h4><p class="help-block">' . $t('media_srcset_preview_live_intro') . '</p>';
    $previewBody .= '<div style="border:1px solid #ddd;padding:8px;background:#fff">' . $tag . '</div>';
    $previewBody .= '<dl class="dl-horizontal" style="margin-top:10px"><dt>' . $t('media_srcset_preview_live_src') . '</dt><dd id="is-live-src">–</dd><dt>' . $t('media_srcset_preview_live_width') . '</dt><dd id="is-live-nw">–</dd><dt>' . $t('media_srcset_preview_live_container') . '</dt><dd id="is-live-cw">–</dd><dt>Status</dt><dd id="is-live-verdict">' . $t('media_srcset_preview_loading') . '</dd></dl></div></div>';
} elseif ($doPreview && $pMedia === null) {
    $previewBody .= rex_view::warning($t('media_srcset_preview_no_media'));
}

$fragment = new rex_fragment();
$fragment->setVar('title', $t('media_srcset_preview_title'), false);
$fragment->setVar('body', $previewBody, false);
echo $fragment->parse('core/page/section.php');

$jsI18n = json_encode([
    'optimal' => $t('media_srcset_preview_verdict_optimal'), 'big' => $t('media_srcset_preview_verdict_big'), 'small' => $t('media_srcset_preview_verdict_small'), 'loading' => $t('media_srcset_preview_loading'),
], JSON_UNESCAPED_UNICODE) ?: '{}';
?>
<script nonce="<?= rex_response::getNonce() ?>">
(function () {
    // ---------------- Assistent: Stufen aus dem Layout ableiten (Rechnung wie SetBuilder::suggest) ----------------
    var ROUND = <?= SetBuilder::ROUND_TO ?>, MIN_FACTOR = <?= SetBuilder::MIN_STEP_FACTOR ?>, PHONE = <?= SetBuilder::PHONE_WIDTH ?>, MIN_W = <?= PresetStore::MIN_WIDTH ?>;
    function $(id) { return document.getElementById(id); }
    function roundUp(px) { return Math.ceil(px / ROUND) * ROUND; }
    function num(id, min, max, def) { var v = parseInt($(id) && $(id).value, 10); if (isNaN(v)) { v = def; } return Math.max(min, Math.min(max, v)); }
    function suggest() {
        var sel = $('is-a-container'); if (!sel) { return null; }
        var containerMax = parseInt(sel.options[sel.selectedIndex].getAttribute('data-px'), 10) || 1200;
        var cd = num('is-a-cd', 1, 6, 1), ct = num('is-a-ct', 1, 4, Math.min(cd, 2)), cm = num('is-a-cm', 1, 2, 1);
        var fraction = num('is-a-fraction', 5, 100, 100) / 100, retina = $('is-a-retina').checked;
        var bpT = num('is-a-bpt', 320, 1600, 640), bpD = Math.max(bpT + 1, num('is-a-bpd', 480, 2400, 1200));
        var sourceMax = Math.max(ROUND, num('is-a-source', 100, <?= PresetStore::MAX_WIDTH ?>, 2400));
        var desktop = Math.max(220, Math.round(containerMax / cd * fraction));
        var tabletVw = Math.max(20, Math.min(100, Math.floor(100 / ct * fraction)));
        var mobileVw = Math.max(30, Math.min(100, Math.floor(100 / cm * fraction)));
        var steps = { phone: Math.round(PHONE * mobileVw / 100), mobile: Math.round((bpT - 1) * mobileVw / 100), tablet: Math.round((bpD - 1) * tabletVw / 100), desktop: desktop };
        var cands = [];
        Object.keys(steps).forEach(function (k) {
            var css = steps[k], x1 = roundUp(css), x2 = roundUp(css * 2);
            var row = document.querySelector('#is-a-steps tr[data-step="' + k + '"]');
            if (row) { row.querySelector('.is-css').textContent = css + ' px'; row.querySelector('.is-x1').textContent = x1; row.querySelector('.is-x2').textContent = x2; }
            cands.push(x1); if (retina) { cands.push(x2); }
        });
        cands = cands.filter(function (w, i, a) { return w >= MIN_W && a.indexOf(w) === i; }).sort(function (a, b) { return a - b; });
        var capped = cands.filter(function (w) { return w <= sourceMax; });
        if (capped.length && capped.length < cands.length) { capped.push(sourceMax); capped = capped.filter(function (w, i, a) { return a.indexOf(w) === i; }).sort(function (a, b) { return a - b; }); }
        cands = capped.length ? capped : [sourceMax];
        var widths = [];
        cands.forEach(function (w) { if (!widths.length || w >= Math.ceil(widths[widths.length - 1] * MIN_FACTOR)) { widths.push(w); } });
        if (widths.length === 1 && widths[0] > ROUND * 4) { widths.unshift(roundUp(widths[0] / 2)); }
        var def = Math.min(roundUp(desktop), widths[widths.length - 1]);
        if (widths.indexOf(def) === -1) { def = widths.reduce(function (best, w) { return Math.abs(w - 1200) < Math.abs(best - 1200) ? w : best; }, widths[0]); }
        $('is-a-result').textContent = widths.join(', ');
        $('is-a-default').textContent = def;
        return { widths: widths, def: def };
    }
    ['is-a-container', 'is-a-cd', 'is-a-ct', 'is-a-cm', 'is-a-fraction', 'is-a-retina', 'is-a-bpt', 'is-a-bpd', 'is-a-source'].forEach(function (id) {
        var el = $(id); if (el) { el.addEventListener('input', suggest); el.addEventListener('change', suggest); }
    });
    var apply = $('is-a-apply');
    if (apply) { apply.addEventListener('click', function () { var s = suggest(); if (s) { $('is-f-widths').value = s.widths.join(', '); $('is-f-default').value = s.def; $('is-f-widths').focus(); } }); }
    var ratio = $('is-f-ratio');
    if (ratio) {
        var sync = function () { $('is-f-ratio-custom').style.display = ratio.value === 'custom' ? '' : 'none'; if (ratio.value === 'original') { $('is-f-mode').value = 'resize'; } };
        ratio.addEventListener('change', sync); sync();
    }
    suggest();

    // ---------------- Vorschau: Simulation der Browser-Auswahl aus sizes + srcset ----------------
    var srcset = <?= $srcsetJson ?>, sizes = <?= $sizesJson ?>, i18n = <?= $jsI18n ?>;
    function slotWidth(viewport) {
        // sizes: "(min-width: 1200px) 380px, (min-width: 640px) 50vw, 100vw"
        var parts = sizes.split(',').map(function (s) { return s.trim(); });
        for (var i = 0; i < parts.length; i++) {
            var m = parts[i].match(/^\(min-width:\s*(\d+)px\)\s+(.+)$/);
            var value = m ? m[2] : parts[i];
            if (m && viewport < parseInt(m[1], 10)) { continue; }
            var v = value.match(/^([\d.]+)(px|vw)$/);
            if (!v) { return viewport; }
            return v[2] === 'vw' ? viewport * parseFloat(v[1]) / 100 : parseFloat(v[1]);
        }
        return viewport;
    }
    function choose(needed) {
        var sorted = srcset.slice().sort(function (a, b) { return a.w - b.w; });
        for (var i = 0; i < sorted.length; i++) { if (sorted[i].w >= needed) { return sorted[i]; } }
        return sorted[sorted.length - 1] || null;
    }
    function fmt(bytes) { return bytes ? (bytes > 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.round(bytes / 1024) + ' KB') : '–'; }
    function simRow(viewport, dpr) {
        var slot = Math.round(slotWidth(viewport)), needed = Math.round(slot * dpr), c = choose(needed);
        return '<td>' + viewport + ' px</td><td>' + dpr + '×</td><td>' + slot + ' px</td><td>' + needed + ' px</td><td>' + (c ? '<code>' + c.type + '</code> (' + c.w + 'w, ' + fmt(c.bytes) + ')' : '–') + '</td>';
    }
    var simTable = document.querySelector('#is-sim tbody');
    if (simTable && srcset.length) {
        var html = '';
        [[360, 2], [414, 3], [768, 2], [1024, 1], [1280, 1], [1440, 2], [1920, 1], [2560, 2]].forEach(function (p) { html += '<tr>' + simRow(p[0], p[1]) + '</tr>'; });
        simTable.innerHTML = html;
        var slider = $('is-sim-custom');
        var renderCustom = function () {
            var vp = parseInt(slider.value, 10); $('is-sim-custom-val').textContent = vp;
            $('is-sim-custom-out').innerHTML = '<table class="table table-condensed" style="margin:0"><tr>' + simRow(vp, 1) + '</tr><tr>' + simRow(vp, 2) + '</tr></table>';
        };
        slider.addEventListener('input', renderCustom); renderCustom();
    }

    // ---------------- Vorschau: was der aktuelle Browser geladen hat ----------------
    var img = $('is-preview-img');
    if (img) {
        var probe = null;
        function render() {
            var src = img.currentSrc || img.src, parts = src.split('/'), type = parts.length > 2 ? parts[parts.length - 2] : src;
            var nw = probe && probe.complete ? probe.naturalWidth : 0, cw = Math.round(img.getBoundingClientRect().width), dpr = window.devicePixelRatio || 1, need = Math.round(cw * dpr);
            $('is-live-src').innerHTML = '<code>' + type + '</code>';
            $('is-live-nw').textContent = nw ? nw + ' px' : '–';
            $('is-live-cw').textContent = cw + ' px × ' + dpr.toFixed(2) + ' = ' + need + ' px';
            var v = $('is-live-verdict');
            if (!nw) { v.textContent = i18n.loading; }
            else if (nw >= need && nw <= need * 1.6) { v.innerHTML = '<span class="label label-success">OK</span> ' + i18n.optimal; }
            else if (nw > need * 1.6) { v.innerHTML = '<span class="label label-info">+</span> ' + i18n.big; }
            else { v.innerHTML = '<span class="label label-warning">−</span> ' + i18n.small; }
        }
        function update() {
            var src = img.currentSrc || img.src; if (!src) { return; }
            if (!probe || probe.src !== src) { probe = new Image(); probe.onload = render; probe.src = src; return; }
            render();
        }
        img.addEventListener('load', update);
        window.addEventListener('resize', function () { window.setTimeout(update, 150); });
        if (img.complete) { update(); }
    }
})();
</script>
