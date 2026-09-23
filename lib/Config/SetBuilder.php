<?php

namespace FriendsOfRedaxo\MediaSrcset\Config;

use rex;
use rex_sql;

/**
 * Set-Builder: leitet Breitenstufen aus Layout-Angaben ab, analysiert die
 * Nutzung der Presets im Code (Module, Templates) und die Breiten der
 * Originalbilder im Medienpool.
 */
final class SetBuilder
{
    /** Container-Breiten (Kennung => maximale Innenbreite in CSS-Pixeln), identisch zu ResponsiveImage */
    public const CONTAINERS = [
        'uk-container-xsmall' => 640,
        'uk-container-small' => 900,
        'uk-container' => 1200,
        'uk-container-large' => 1400,
        'uk-container-xlarge' => 1600,
        'expand' => 1920,
    ];

    public const PHONE_WIDTH = 414;
    public const ROUND_TO = 100;
    public const MIN_STEP_FACTOR = 1.2;

    /**
     * Breitenstufen vorschlagen.
     *
     * @param array{container?: string, columns?: int, columns_tablet?: int, columns_mobile?: int, fraction?: int, retina?: bool, bp_tablet?: int, bp_desktop?: int, source_max?: int} $o
     * @return array{widths: non-empty-list<int>, default_width: int, steps: list<array{label: string, css: int, x1: int, x2: int}>}
     */
    public static function suggest(array $o): array
    {
        $containerMax = self::CONTAINERS[(string) ($o['container'] ?? 'uk-container')] ?? 1200;
        $cols = max(1, min(6, (int) ($o['columns'] ?? 1)));
        $colsTablet = max(1, min(4, (int) ($o['columns_tablet'] ?? min($cols, 2))));
        $colsMobile = max(1, min(2, (int) ($o['columns_mobile'] ?? 1)));
        $fraction = max(5, min(100, (int) ($o['fraction'] ?? 100))) / 100;
        $retina = !empty($o['retina']);
        $bpTablet = max(320, min(1600, (int) ($o['bp_tablet'] ?? 640)));
        $bpDesktop = max($bpTablet + 1, min(2400, (int) ($o['bp_desktop'] ?? 1200)));
        $sourceMax = max(self::ROUND_TO, min(PresetStore::MAX_WIDTH, (int) ($o['source_max'] ?? 2400)));

        // CSS-Pixel-Breiten des Bildes in den drei Layoutbereichen (Rechnung wie ResponsiveImage::buildSizes)
        $desktop = (int) max(220, round(($containerMax / $cols) * $fraction));
        $tabletVw = max(20, min(100, floor((100 / $colsTablet) * $fraction)));
        $mobileVw = max(30, min(100, floor((100 / $colsMobile) * $fraction)));
        $steps = [];
        $candidates = [];
        foreach ([
            'phone' => (int) round(self::PHONE_WIDTH * $mobileVw / 100),
            'mobile' => (int) round(($bpTablet - 1) * $mobileVw / 100),
            'tablet' => (int) round(($bpDesktop - 1) * $tabletVw / 100),
            'desktop' => $desktop,
        ] as $label => $css) {
            $x1 = self::roundUp($css);
            $x2 = self::roundUp($css * 2);
            $steps[] = ['label' => $label, 'css' => $css, 'x1' => $x1, 'x2' => $x2];
            $candidates[] = $x1;
            if ($retina) {
                $candidates[] = $x2;
            }
        }

        $candidates = array_values(array_unique(array_filter($candidates, static fn (int $w) => $w >= PresetStore::MIN_WIDTH)));
        sort($candidates);
        // Stufen ueber der Quellbreite bringen nur Hochskalierungen
        $capped = array_values(array_filter($candidates, static fn (int $w) => $w <= $sourceMax));
        if ($capped !== [] && count($capped) < count($candidates)) {
            $capped[] = $sourceMax;
            $capped = array_values(array_unique($capped));
            sort($capped);
        }
        $candidates = $capped !== [] ? $capped : [$sourceMax];
        // zu dichte Stufen ausduennen (mindestens 20 % Abstand)
        $widths = [];
        foreach ($candidates as $w) {
            if ($widths === [] || $w >= (int) ceil(end($widths) * self::MIN_STEP_FACTOR)) {
                $widths[] = $w;
            }
        }
        if (count($widths) === 1 && $widths[0] > self::ROUND_TO * 4) {
            array_unshift($widths, self::roundUp($widths[0] / 2));
        }
        $default = min(self::roundUp($desktop), (int) end($widths));
        if (!in_array($default, $widths, true)) {
            $default = PresetStore::pickDefault($widths);
        }
        return ['widths' => $widths, 'default_width' => $default, 'steps' => $steps];
    }

    public static function roundUp(float $px): int
    {
        return (int) (ceil($px / self::ROUND_TO) * self::ROUND_TO);
    }

    /**
     * Nutzung der Presets im Code: Module (Ein-/Ausgabe) und Templates.
     * @return array<string, array{modules: list<string>, widths: list<int>}>
     */
    public static function scanUsage(): array
    {
        $usage = [];
        $add = static function (string $preset, string $source, array $widths = []) use (&$usage): void {
            $usage[$preset] ??= ['modules' => [], 'widths' => []];
            if ($source !== '' && !in_array($source, $usage[$preset]['modules'], true)) {
                $usage[$preset]['modules'][] = $source;
            }
            foreach ($widths as $w) {
                $w = (int) $w;
                if ($w > 0 && !in_array($w, $usage[$preset]['widths'], true)) {
                    $usage[$preset]['widths'][] = $w;
                }
            }
        };
        $scan = static function (string $code, string $source) use ($add): void {
            // is_<preset>__<width> als direkter Media-Manager-Typ
            if (preg_match_all('/\bis_([a-z][a-z0-9_]*?)__(\d{2,4})\b/', $code, $m, PREG_SET_ORDER)) {
                foreach ($m as $hit) {
                    $add($hit[1], $source, [(int) $hit[2]]);
                }
            }
            // ResponsiveImage: Presets und Breiten je Aufruf-Kette
            if (preg_match_all('/with(?:Desktop|Mobile)Preset\(\s*[\'"]([a-z][a-z0-9_]*)[\'"]\s*\)/', $code, $m, PREG_SET_ORDER)) {
                $widths = [];
                if (preg_match_all('/withWidths\(\s*\[([^\]]*)\]/', $code, $wm)) {
                    foreach ($wm[1] as $list) {
                        foreach (preg_split('/[\s,]+/', trim($list)) ?: [] as $w) {
                            if (is_numeric($w)) {
                                $widths[] = (int) $w;
                            }
                        }
                    }
                }
                foreach ($m as $hit) {
                    $add($hit[1], $source, $widths);
                }
            }
            // Preset-Namen als Auswahlwerte in Modul-Eingaben (z. B. 'ratio_4_3' => 'Querformat')
            if (preg_match_all('/[\'"](ratio_[a-z0-9_]+)[\'"]\s*=>/', $code, $m)) {
                foreach (array_unique($m[1]) as $preset) {
                    $add($preset, $source);
                }
            }
        };
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT id, name, input, output FROM ' . rex::getTable('module'));
        foreach ($sql as $row) {
            $scan((string) $row->getValue('input') . "\n" . (string) $row->getValue('output'), (string) $row->getValue('name'));
        }
        $sql->setQuery('SELECT id, name, content FROM ' . rex::getTable('template'));
        foreach ($sql as $row) {
            $scan((string) $row->getValue('content'), 'Template: ' . $row->getValue('name'));
        }
        foreach ($usage as &$u) {
            sort($u['widths']);
            sort($u['modules'], SORT_NATURAL | SORT_FLAG_CASE);
        }
        unset($u);
        ksort($usage, SORT_NATURAL | SORT_FLAG_CASE);
        return $usage;
    }

    /**
     * Breitenverteilung der Rasterbilder im Medienpool.
     * @return array{total: int, median: int, max: int, buckets: list<array{label: string, min: int, max: int, count: int}>, below: array<int, int>}
     */
    public static function mediaStats(): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT width FROM ' . rex::getTable('media') . ' WHERE filetype LIKE "image/%" AND filetype NOT LIKE "%svg%" AND width > 0 ORDER BY width');
        $widths = [];
        foreach ($sql as $row) {
            $widths[] = (int) $row->getValue('width');
        }
        $total = count($widths);
        $bounds = [0, 800, 1200, 1600, 2000, 2400, PHP_INT_MAX];
        $buckets = [];
        for ($i = 0; $i < count($bounds) - 1; $i++) {
            $min = $bounds[$i];
            $max = $bounds[$i + 1];
            $count = count(array_filter($widths, static fn (int $w) => $w >= $min && $w < $max));
            $buckets[] = ['label' => $max === PHP_INT_MAX ? '≥ ' . $min : $min . '–' . ($max - 1), 'min' => $min, 'max' => $max, 'count' => $count];
        }
        $below = [];
        foreach ([400, 800, 1200, 1600, 2000, 2400, 3000] as $limit) {
            $below[$limit] = count(array_filter($widths, static fn (int $w) => $w < $limit));
        }
        return [
            'total' => $total,
            'median' => $total > 0 ? $widths[(int) floor(($total - 1) / 2)] : 0,
            'max' => $total > 0 ? max($widths) : 0,
            'buckets' => $buckets,
            'below' => $below,
        ];
    }

    /**
     * Anteil (0-100) der Bilder, die schmaler als $width sind
     * @param array{total: int, below: array<int, int>, ...} $stats
     */
    public static function upscaleShare(array $stats, int $width): int
    {
        if ($stats['total'] === 0) {
            return 0;
        }
        $below = 0;
        foreach ($stats['below'] as $limit => $count) {
            if ($limit <= $width) {
                $below = $count;
            }
        }
        return (int) round($below / $stats['total'] * 100);
    }
}
