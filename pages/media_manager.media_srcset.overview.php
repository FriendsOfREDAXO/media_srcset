<?php

use FriendsOfRedaxo\MediaSrcset\Config\MediaTypeRegistry;
use FriendsOfRedaxo\MediaSrcset\Config\PresetStore;

$presets = MediaTypeRegistry::getPresets();

$content = '';
$content .= '<p>' . rex_i18n::msg('media_srcset_intro') . '</p>';

$content .= '<table class="table table-striped">';
$content .= '<thead><tr>';
$content .= '<th>' . rex_i18n::msg('media_srcset_col_name') . '</th><th>' . rex_i18n::msg('media_srcset_col_ratio') . '</th><th>' . rex_i18n::msg('media_srcset_col_mode') . '</th><th>' . rex_i18n::msg('media_srcset_col_widths') . '</th><th>' . rex_i18n::msg('media_srcset_col_default') . '</th><th>' . rex_i18n::msg('media_srcset_col_source') . '</th><th>Media-Manager-Typ</th>';
$content .= '</tr></thead><tbody>';

foreach ($presets as $name => $config) {
    $widths = implode(', ', $config['widths']);
    $exampleWidth = $config['default_width'];
    $exampleType = MediaTypeRegistry::buildVirtualType((string) $name, (int) $exampleWidth);

    $content .= '<tr>';
    $content .= '<td><code>' . rex_escape((string) $name) . '</code></td>';
    $content .= '<td>' . rex_escape(PresetStore::RATIOS[$config['ratio']] ?? str_replace('_', ':', $config['ratio'])) . '</td>';
    $content .= '<td>' . rex_escape($config['mode']) . '</td>';
    $content .= '<td>' . rex_escape($widths) . '</td>';
    $content .= '<td>' . rex_escape((string) $exampleWidth) . 'px</td>';
    $content .= '<td>' . (PresetStore::isBuiltin((string) $name) ? '<span class="label label-default">' . rex_i18n::msg('media_srcset_source_code') . '</span>' : '<a class="label label-primary" href="' . rex_url::backendPage('media_manager/media_srcset/sets', ['edit' => (string) $name]) . '">' . rex_i18n::msg('media_srcset_source_builder') . '</a>') . '</td>';
    $content .= '<td><code>' . rex_escape($exampleType) . '</code></td>';
    $content .= '</tr>';
}

$content .= '</tbody></table>';

$content .= '<h3>Nutzung im Code</h3>';
$content .= '<pre>' . rex_escape(
    "use FriendsOfRedaxo\\MediaSrcset\\Media\\ResponsiveImage;\n\n" .
    "echo ResponsiveImage::forFile(\$mediaFile)\n" .
    "    ->withDesktopPreset('ratio_4_3')\n" .
    "    ->withWidths([400, 800, 1200, 1600])\n" .
    "    ->withColumns(3, 2, 1)\n" .
    "    ->toImageTag(['alt' => \$altText, 'class' => 'uk-width-1-1']);"
) . '</pre>';

$content .= '<h3>Eigene Presets registrieren</h3>';
$content .= '<pre>' . rex_escape(
    "use FriendsOfRedaxo\\MediaSrcset\\Config\\MediaTypeRegistry;\n\n" .
    "MediaTypeRegistry::registerPreset('ratio_16_9', [\n" .
    "    'ratio' => '16_9',\n" .
    "    'mode' => 'focuspoint',\n" .
    "    'widths' => [400, 800, 1200, 1600],\n" .
    "    'default_width' => 1200,\n" .
    "]);"
) . '</pre>';

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('media_srcset_title'), false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
