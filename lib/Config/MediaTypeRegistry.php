<?php

namespace FriendsOfRedaxo\MediaSrcset\Config;

use rex_extension;
use rex_extension_point;

/**
 * Verwaltet Presets fuer virtuelle Media-Manager-Typen (Schema is_<preset>__<width>).
 * Erlaubt beliebige Zielbreiten pro Preset ohne dass fuer jede Breite ein eigener
 * Media-Manager-Typ in der Datenbank angelegt werden muss.
 */
class MediaTypeRegistry
{
    /**
     * Aufgeloeste Sets: registerPreset() fuellt immer alle Schluessel.
     * @var array<string, array{ratio: string, mode: string, widths: non-empty-list<int>, default_width: int, chain: string}>
     */
    private static array $runtimePresets = [];

    /**
     * @param array<string, mixed> $config Eingabe-Set; fehlende Schluessel werden aufgefuellt
     */
    public static function registerPreset(string $name, array $config): void
    {
        $name = trim($name);
        if ($name === '') {
            return;
        }

        $ratio = trim((string) ($config['ratio'] ?? ''));
        if ($ratio === '') {
            return;
        }

        $mode = trim((string) ($config['mode'] ?? 'focuspoint'));
        if (!in_array($mode, ['focuspoint', 'resize'], true)) {
            $mode = 'focuspoint';
        }

        $widthsRaw = $config['widths'] ?? [];
        $widths = [];
        if (is_array($widthsRaw)) {
            foreach ($widthsRaw as $width) {
                $widths[] = max(1, (int) $width);
            }
        }

        $widths = array_values(array_unique($widths));
        sort($widths);

        if ($widths === []) {
            $widths = [max(1, (int) ($config['default_width'] ?? 1200))];
        }

        $defaultWidth = max(1, (int) ($config['default_width'] ?? $widths[0]));
        if (!in_array($defaultWidth, $widths, true)) {
            $defaultWidth = self::normalizeWidth(['widths' => $widths], $defaultWidth);
        }

        self::$runtimePresets[$name] = [
            'ratio' => $ratio,
            'mode' => $mode,
            'widths' => $widths,
            'default_width' => $defaultWidth,
            'chain' => trim((string) ($config['chain'] ?? '')),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $presets
     */
    public static function registerPresets(array $presets): void
    {
        foreach ($presets as $name => $config) {
            self::registerPreset($name, $config);
        }
    }

    /**
     * @return array<string, array{ratio: string, mode: string, widths: non-empty-list<int>, default_width: int, chain: string}>
     */
    public static function getPresets(): array
    {
        $presets = self::$runtimePresets;

        /** @var array<string, array{ratio: string, mode: string, widths: non-empty-list<int>, default_width: int, chain: string}> $presets */
        $presets = rex_extension::registerPoint(new rex_extension_point(
            'MEDIA_SRCSET_PRESETS',
            $presets
        ));

        return $presets;
    }

    public static function buildVirtualType(string $preset, int $width): string
    {
        return 'is_' . $preset . '__' . $width;
    }

    /**
     * @return array{preset: string, width: int}|null
     */
    public static function parseVirtualType(string $mediaType): ?array
    {
        if (!str_starts_with($mediaType, 'is_')) {
            return null;
        }

        $raw = substr($mediaType, 3);
        if ($raw === '') {
            return null;
        }

        $parts = explode('__', $raw);
        $widthRaw = array_pop($parts);
        $preset = implode('__', $parts);
        if ($preset === '' || preg_match('/^[0-9]+$/', $widthRaw) !== 1) {
            return null;
        }

        return [
            'preset' => $preset,
            'width' => (int) $widthRaw,
        ];
    }

    /**
     * @param array{widths?: list<int>, ...} $presetConfig
     */
    public static function normalizeWidth(array $presetConfig, int $requestedWidth): int
    {
        $widths = $presetConfig['widths'] ?? [];
        if ($widths === []) {
            return max(1, $requestedWidth);
        }

        sort($widths);
        foreach ($widths as $width) {
            if ($requestedWidth <= $width) {
                return (int) $width;
            }
        }

        return (int) end($widths);
    }
}
