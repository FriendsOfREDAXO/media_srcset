<?php

/**
 * media_srcset - Demo & Pruefung.
 *
 * Rendert ein Bild mit ResponsiveImage fuer frei waehlbare Layout-Parameter
 * und prueft serverseitig, ob die srcset-Descriptoren zu den tatsaechlich
 * erzeugten Dateien passen. Ein kleines Script zeigt zusaetzlich, welche
 * Variante der Browser bei der aktuellen Fensterbreite/DPR gewaehlt hat.
 */

use FriendsOfRedaxo\MediaSrcset\Config\MediaTypeRegistry;
use FriendsOfRedaxo\MediaSrcset\Media\ResponsiveImage;

$presets = MediaTypeRegistry::getPresets();
ksort($presets, SORT_NATURAL | SORT_FLAG_CASE);

$mediaFile = trim(rex_request('media_file', 'string', ''));
$preset = rex_request('preset', 'string', 'ratio_4_3');
if (!isset($presets[$preset])) {
    $preset = (string) (array_key_first($presets) ?? '');
}
$columns = max(1, min(6, rex_request('columns', 'int', 2)));
$columnsTablet = max(1, min(4, rex_request('columns_tablet', 'int', 2)));
$container = rex_request('container', 'string', 'uk-container');
$containers = [
    'uk-container-small' => 'Schmal (≈ 900 px)',
    'uk-container' => 'Standard (≈ 1200 px)',
    'uk-container-large' => 'Breit (≈ 1400 px)',
    'uk-container-xlarge' => 'Extra breit (≈ 1600 px)',
    'expand' => 'Volle Breite (≈ 1920 px)',
];
if (!isset($containers[$container])) {
    $container = 'uk-container';
}
$fraction = max(5, min(100, rex_request('fraction', 'int', 100)));
$bpTablet = max(320, min(1600, rex_request('bp_tablet', 'int', 640)));
$bpDesktop = max($bpTablet + 1, min(2400, rex_request('bp_desktop', 'int', 1200)));

$media = $mediaFile !== '' ? rex_media::get($mediaFile) : null;

// Beispielbild vorschlagen, wenn keins gewaehlt: groesstes Rasterbild im Pool
if ($media === null) {
    $sql = rex_sql::factory();
    $sql->setQuery('SELECT filename FROM ' . rex::getTable('media') . ' WHERE filetype LIKE "image/%" AND filetype NOT LIKE "%svg%" AND width > 0 ORDER BY width DESC LIMIT 1');
    if ($sql->getRows()) {
        $mediaFile = (string) $sql->getValue('filename');
        $media = rex_media::get($mediaFile);
    }
}

// ---------------------------------------------------------------------
// Formular
// ---------------------------------------------------------------------
$form = '<form method="get" action="' . rex_url::currentBackendPage() . '">';
$form .= '<input type="hidden" name="page" value="' . rex_escape(rex_be_controller::getCurrentPage()) . '">';
$form .= '<div class="row">';
$form .= '<div class="col-md-6"><div class="form-group"><label>Bild aus dem Medienpool</label>' . rex_var_media::getWidget(1, 'media_file', $mediaFile) . '</div></div>';
$form .= '<div class="col-md-3"><div class="form-group"><label for="is-preset">Preset</label><select id="is-preset" name="preset" class="form-control">';
foreach ($presets as $name => $config) {
    $form .= '<option value="' . rex_escape((string) $name) . '"' . ($name === $preset ? ' selected' : '') . '>' . rex_escape((string) $name) . ' (' . rex_escape($config['ratio']) . ')</option>';
}
$form .= '</select></div></div>';
$form .= '<div class="col-md-3"><div class="form-group"><label for="is-container">Container</label><select id="is-container" name="container" class="form-control">';
foreach ($containers as $value => $label) {
    $form .= '<option value="' . rex_escape($value) . '"' . ($value === $container ? ' selected' : '') . '>' . rex_escape($label) . '</option>';
}
$form .= '</select></div></div>';
$form .= '</div><div class="row">';
$form .= '<div class="col-md-2"><div class="form-group"><label for="is-columns">Spalten Desktop</label><input id="is-columns" type="number" min="1" max="6" name="columns" class="form-control" value="' . $columns . '"></div></div>';
$form .= '<div class="col-md-2"><div class="form-group"><label for="is-columns-tablet">Spalten Tablet</label><input id="is-columns-tablet" type="number" min="1" max="4" name="columns_tablet" class="form-control" value="' . $columnsTablet . '"></div></div>';
$form .= '<div class="col-md-2"><div class="form-group"><label for="is-fraction">Bildanteil der Spalte (%)</label><input id="is-fraction" type="number" min="5" max="100" name="fraction" class="form-control" value="' . $fraction . '"></div></div>';
$form .= '<div class="col-md-2"><div class="form-group"><label for="is-bp-tablet">Breakpoint Tablet (px)</label><input id="is-bp-tablet" type="number" min="320" max="1600" name="bp_tablet" class="form-control" value="' . $bpTablet . '"></div></div>';
$form .= '<div class="col-md-2"><div class="form-group"><label for="is-bp-desktop">Breakpoint Desktop (px)</label><input id="is-bp-desktop" type="number" min="480" max="2400" name="bp_desktop" class="form-control" value="' . $bpDesktop . '"></div></div>';
$form .= '<div class="col-md-2"><div class="form-group"><label>&nbsp;</label><button class="btn btn-primary btn-block" type="submit">Anzeigen</button></div></div>';
$form .= '</div></form>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Demo & Prüfung', false);
$fragment->setVar('body', '<p>Wählen Sie ein Bild und die Layout-Parameter, mit denen ein Modul das Bild ausgibt. Die Seite zeigt das erzeugte Markup, prüft die Größen der generierten Dateien gegen die srcset-Descriptoren und zeigt, welche Variante der Browser gerade lädt.</p>' . $form, false);
echo $fragment->parse('core/page/section.php');

if ($media === null) {
    echo rex_view::info('Kein Bild ausgewählt.');
    return;
}

// ---------------------------------------------------------------------
// Bild rendern
// ---------------------------------------------------------------------
$presetConfig = $presets[$preset];
$widths = $presetConfig['widths'];

$image = ResponsiveImage::forFile($mediaFile)
    ->withDesktopPreset($preset)
    ->withWidths($widths)
    ->withContainerWidth($container)
    ->withColumns($columns, $columnsTablet, 1)
    ->withMediaFraction($fraction / 100)
    ->withBreakpoints($bpTablet, $bpDesktop);

$data = $image->toImage();
$entries = $image->getSrcsetEntries();
$sourceMax = $image->getSourceMaxWidth();
$tag = $image->toImageTag(['alt' => rex_escape((string) ($media->getTitle() ?: $mediaFile)), 'id' => 'is-demo-img', 'style' => 'width:100%;height:auto;display:block']);

// Vorschaubreite: Desktop-Anteil laut sizes, damit die Vorschau die reale Spaltenbreite hat
$containerPx = ['uk-container-small' => 900, 'uk-container' => 1200, 'uk-container-large' => 1400, 'uk-container-xlarge' => 1600, 'expand' => 1920][$container];
$previewPx = (int) max(120, round($containerPx / $columns * $fraction / 100));

$preview = '<p><strong>Quelle:</strong> <code>' . rex_escape($mediaFile) . '</code> – ' . (int) $media->getWidth() . ' × ' . (int) $media->getHeight() . ' px'
    . ' · <strong>maximal erreichbare Breite im Preset-Ratio:</strong> ' . $sourceMax . ' px</p>';
$preview .= '<div class="row"><div class="col-md-7">';
$preview .= '<p class="text-muted">Vorschau in der berechneten Desktop-Spaltenbreite (' . $previewPx . ' px, ggf. durch die Backend-Spalte begrenzt). Fenster verkleinern, um andere Varianten zu sehen.</p>';
$preview .= '<div id="is-demo-box" style="max-width:' . $previewPx . 'px;border:1px solid #ddd;background:#f7f7f7">' . $tag . '</div>';
$preview .= '</div><div class="col-md-5">';
$preview .= '<h4>Browser-Auswahl (live)</h4>';
$preview .= '<table class="table table-condensed"><tbody>';
$preview .= '<tr><th>Geladene Variante</th><td id="is-live-src">–</td></tr>';
$preview .= '<tr><th>Dateibreite (naturalWidth)</th><td id="is-live-nw">–</td></tr>';
$preview .= '<tr><th>Dargestellte Breite</th><td id="is-live-cw">–</td></tr>';
$preview .= '<tr><th>Device Pixel Ratio</th><td id="is-live-dpr">–</td></tr>';
$preview .= '<tr><th>Bewertung</th><td id="is-live-verdict">–</td></tr>';
$preview .= '</tbody></table>';
$preview .= '</div></div>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Vorschau', false);
$fragment->setVar('body', $preview, false);
echo $fragment->parse('core/page/section.php');

// ---------------------------------------------------------------------
// Markup
// ---------------------------------------------------------------------
$markup = '<h4>Attribute</h4><table class="table table-condensed"><tbody>';
$markup .= '<tr><th style="width:120px">src</th><td><code>' . rex_escape($data['src']) . '</code></td></tr>';
$markup .= '<tr><th>sizes</th><td><code>' . rex_escape($data['sizes']) . '</code></td></tr>';
$markup .= '<tr><th>srcset</th><td><code style="white-space:pre-wrap">' . rex_escape(str_replace(', ', ",\n", $data['srcset'])) . '</code></td></tr>';
$markup .= '</tbody></table>';
$markup .= '<h4>Aufruf im Modul</h4><pre>' . rex_escape(
    "use FriendsOfRedaxo\\MediaSrcset\\Media\\ResponsiveImage;\n\n" .
    "echo ResponsiveImage::forFile('" . $mediaFile . "')\n" .
    "    ->withDesktopPreset('" . $preset . "')\n" .
    "    ->withWidths([" . implode(', ', $widths) . "])\n" .
    "    ->withContainerWidth('" . $container . "')\n" .
    "    ->withColumns(" . $columns . ", " . $columnsTablet . ", 1)\n" .
    ($fraction !== 100 ? "    ->withMediaFraction(" . ($fraction / 100) . ")\n" : '') .
    (($bpTablet !== 640 || $bpDesktop !== 1200) ? "    ->withBreakpoints(" . $bpTablet . ", " . $bpDesktop . ")\n" : '') .
    "    ->toImageTag(['alt' => \$alt, 'class' => 'uk-width-1-1']);"
) . '</pre>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Erzeugtes Markup', false);
$fragment->setVar('body', $markup, false);
echo $fragment->parse('core/page/section.php');

// ---------------------------------------------------------------------
// Descriptor-Check: Dateien erzeugen und messen
// ---------------------------------------------------------------------
$check = '<p>Für jeden srcset-Eintrag wird die Datei über den Media Manager erzeugt und ihre tatsächliche Pixelbreite mit dem Descriptor verglichen. Abweichungen führen dazu, dass der Browser zu kleine oder unnötig große Dateien lädt.</p>';
$check .= '<table class="table table-striped table-condensed"><thead><tr><th>Media-Typ</th><th>Descriptor</th><th>Erzeugte Datei</th><th>Dateigröße</th><th>Status</th></tr></thead><tbody>';
$allOk = true;
foreach ($entries as [$typeWidth, $descriptorWidth]) {
    $type = MediaTypeRegistry::buildVirtualType($preset, $typeWidth);
    $actualW = 0;
    $actualH = 0;
    $bytes = 0;
    $error = '';
    try {
        $manager = rex_media_manager::create($type, $mediaFile);
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
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }

    $ok = $error === '' && $actualW === $descriptorWidth;
    if (!$ok) {
        $allOk = false;
    }
    $status = $error !== ''
        ? '<span class="label label-danger">Fehler</span> ' . rex_escape($error)
        : ($ok ? '<span class="label label-success">passt</span>' : '<span class="label label-warning">Abweichung ' . ($actualW - $descriptorWidth) . ' px</span>');

    $check .= '<tr><td><code>' . rex_escape($type) . '</code></td><td>' . $descriptorWidth . 'w</td><td>' . ($actualW ? $actualW . ' × ' . $actualH . ' px' : '–') . '</td><td>' . ($bytes ? rex_formatter::bytes($bytes) : '–') . '</td><td>' . $status . '</td></tr>';
}
$check .= '</tbody></table>';
$check = ($allOk ? rex_view::success('Alle Descriptoren stimmen mit den erzeugten Dateien überein.') : rex_view::warning('Mindestens ein Descriptor weicht von der erzeugten Datei ab.')) . $check;

$fragment = new rex_fragment();
$fragment->setVar('title', 'Descriptor-Check', false);
$fragment->setVar('body', $check, false);
echo $fragment->parse('core/page/section.php');
?>
<script nonce="<?= rex_response::getNonce() ?>">
(function () {
    var img = document.getElementById('is-demo-img');
    if (!img) { return; }
    // naturalWidth ist bei srcset mit w-Descriptoren bereits durch die
    // berechnete Dichte geteilt - die echte Dateibreite liefert nur ein
    // separat geladenes Image-Objekt mit derselben URL.
    var probe = null;
    function update() {
        var src = img.currentSrc || img.src;
        if (!src) { return; }
        if (!probe || probe.src !== src) {
            probe = new Image();
            probe.onload = render;
            probe.src = src;
            return;
        }
        render();
    }
    function render() {
        var src = img.currentSrc || img.src;
        var parts = src.split('/');
        var type = parts.length > 2 ? parts[parts.length - 2] : src;
        var nw = probe && probe.complete ? probe.naturalWidth : 0;
        var cw = Math.round(img.getBoundingClientRect().width);
        var dpr = window.devicePixelRatio || 1;
        var need = Math.round(cw * dpr);
        document.getElementById('is-live-src').textContent = type;
        document.getElementById('is-live-nw').textContent = nw ? nw + ' px' : '–';
        document.getElementById('is-live-cw').textContent = cw + ' px (× ' + dpr.toFixed(2) + ' = ' + need + ' px benötigt)';
        document.getElementById('is-live-dpr').textContent = dpr.toFixed(2);
        var verdict = document.getElementById('is-live-verdict');
        if (!nw) {
            verdict.textContent = 'Bild lädt …';
        } else if (nw >= need && nw <= need * 1.6) {
            verdict.innerHTML = '<span class="label label-success">optimal</span> ausreichend scharf, keine unnötig große Datei';
        } else if (nw > need * 1.6) {
            verdict.innerHTML = '<span class="label label-info">größer als nötig</span> nächstkleinere Stufe existiert nicht oder sizes ist zu großzügig';
        } else {
            verdict.innerHTML = '<span class="label label-warning">zu klein</span> Datei kleiner als benötigt – Quelle zu klein oder sizes zu knapp';
        }
    }
    img.addEventListener('load', update);
    window.addEventListener('resize', function () { window.setTimeout(update, 150); });
    if (img.complete) { update(); }
})();
</script>
