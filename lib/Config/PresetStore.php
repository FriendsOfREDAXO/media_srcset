<?php

namespace FriendsOfRedaxo\MediaSrcset\Config;

use rex_addon;
use rex_addon_interface;
use rex_config;
use rex_dir;
use rex_finder;
use rex_i18n;
use rex_logger;
use rex_path;
use Throwable;

/**
 * Persistente Presets aus dem Set-Builder (Backend). Gespeichert als JSON in
 * rex_config, beim Boot nach den Code-Presets registriert. Code-Presets
 * (BUILTIN) bleiben schreibgeschuetzt; Builder-Presets duerfen deren Namen
 * nicht verwenden.
 */
final class PresetStore
{
    public const CONFIG_KEY = 'presets';

    /** @var array<string, array{ratio: string, mode: string, widths: list<int>, default_width: int}> */
    public const BUILTIN = [
        'ratio_16_9' => ['ratio' => '16_9', 'mode' => 'focuspoint', 'widths' => [400, 800, 1200, 1600, 2000], 'default_width' => 1200],
        'ratio_21_9' => ['ratio' => '21_9', 'mode' => 'focuspoint', 'widths' => [400, 800, 1200, 1600, 2000], 'default_width' => 1200],
        'ratio_4_3' => ['ratio' => '4_3', 'mode' => 'focuspoint', 'widths' => [400, 800, 1200, 1600, 2000], 'default_width' => 1200],
        'ratio_1_1' => ['ratio' => '1_1', 'mode' => 'focuspoint', 'widths' => [400, 800, 1200, 1600], 'default_width' => 1200],
        'ratio_original' => ['ratio' => 'original', 'mode' => 'resize', 'widths' => [400, 800, 1200, 1600, 2000, 2400], 'default_width' => 1600],
    ];

    /** Gaengige Seitenverhaeltnisse fuer die Auswahl im Builder (Wert => Label) */
    public const RATIOS = [
        '16_9' => '16:9', '21_9' => '21:9', '4_3' => '4:3', '3_2' => '3:2', '1_1' => '1:1', '3_4' => '3:4', '2_3' => '2:3', '9_16' => '9:16', 'original' => 'original',
    ];

    public const MIN_WIDTH = 50;
    public const MAX_WIDTH = 4000;
    public const MAX_STEPS = 8;

    private static function addon(): rex_addon_interface
    {
        return rex_addon::get('media_srcset');
    }

    public static function isBuiltin(string $name): bool
    {
        return isset(self::BUILTIN[$name]);
    }

    /**
     * Alle Builder-Presets (auch inaktive), Name => Konfiguration.
     * @return array<string, array{ratio: string, mode: string, widths: list<int>, default_width: int, chain: string, active: bool, note: string, updated: string}>
     */
    public static function all(): array
    {
        $raw = self::addon()->getConfig(self::CONFIG_KEY);
        $data = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : null);
        if (!is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $name => $config) {
            if (!is_string($name) || !is_array($config)) {
                continue;
            }
            $normalized = self::normalize($config);
            if ($normalized === null) {
                continue;
            }
            /** @var array{ratio: string, mode: string, widths: list<int>, default_width: int, chain: string, active: bool, note: string, updated: string} $normalized */
            $out[$name] = $normalized;
        }
        ksort($out, SORT_NATURAL | SORT_FLAG_CASE);
        return $out;
    }

    /** @return array{ratio: string, mode: string, widths: list<int>, default_width: int, chain: string, active: bool, note: string, updated: string}|null */
    public static function get(string $name): ?array
    {
        return self::all()[$name] ?? null;
    }

    /**
     * Validiert und speichert ein Preset. Liefert eine Liste von Fehlern
     * (leer = gespeichert).
     * @param array<string, mixed> $input
     * @return list<string>
     */
    public static function save(string $name, array $input, string $originalName = ''): array
    {
        $errors = [];
        $name = trim($name);
        if (!preg_match('/^[a-z][a-z0-9_]{1,60}$/', $name)) {
            $errors[] = rex_i18n::msg('media_srcset_msg_invalid_name');
        } elseif (self::isBuiltin($name)) {
            $errors[] = rex_i18n::msg('media_srcset_msg_name_builtin');
        }

        $ratio = trim((string) ($input['ratio'] ?? ''));
        if ($ratio === 'custom') {
            $ratio = trim((string) ($input['ratio_custom'] ?? ''));
            $ratio = str_replace([':', '/', 'x', 'X', ' '], '_', $ratio);
        }
        if ($ratio !== 'original' && !preg_match('/^[1-9]\d{0,2}_[1-9]\d{0,2}$/', $ratio)) {
            $errors[] = rex_i18n::msg('media_srcset_msg_invalid_ratio');
        }

        $mode = (string) ($input['mode'] ?? 'focuspoint');
        if (!in_array($mode, ['focuspoint', 'resize'], true)) {
            $mode = 'focuspoint';
        }
        if ($ratio === 'original') {
            $mode = 'resize';
        }

        $widths = self::parseWidths((string) ($input['widths'] ?? ''));
        if ($widths === [] || count($widths) > self::MAX_STEPS) {
            $errors[] = rex_i18n::msg('media_srcset_msg_invalid_widths', (string) self::MIN_WIDTH, (string) self::MAX_WIDTH, (string) self::MAX_STEPS);
        }

        $default = (int) ($input['default_width'] ?? 0);
        if ($default <= 0 && $widths !== []) {
            $default = self::pickDefault($widths);
        }
        if ($widths !== [] && !in_array($default, $widths, true)) {
            $errors[] = rex_i18n::msg('media_srcset_msg_invalid_default');
        }

        if ($errors !== []) {
            return $errors;
        }

        $all = self::all();
        if ($originalName !== '' && $originalName !== $name) {
            unset($all[$originalName]);
        }
        $all[$name] = [
            'ratio' => $ratio,
            'mode' => $mode,
            'widths' => $widths,
            'default_width' => $default,
            'chain' => self::normalizeChain((string) ($input['chain'] ?? '')),
            'active' => !empty($input['active']),
            'note' => mb_substr(trim((string) ($input['note'] ?? '')), 0, 200),
            'updated' => date('Y-m-d H:i:s'),
        ];
        self::persist($all);
        return [];
    }

    public static function delete(string $name): void
    {
        $all = self::all();
        unset($all[$name]);
        self::persist($all);
    }

    public static function setActive(string $name, bool $active): void
    {
        $all = self::all();
        if (isset($all[$name])) {
            $all[$name]['active'] = $active;
            $all[$name]['updated'] = date('Y-m-d H:i:s');
            self::persist($all);
        }
    }

    /** Code-Presets registrieren (boot.php) */
    public static function registerBuiltin(): void
    {
        MediaTypeRegistry::registerPresets(self::BUILTIN);
    }

    /** Aktive Builder-Presets registrieren (boot.php); Fehler blockieren nie das Frontend */
    public static function registerActive(): void
    {
        try {
            foreach (self::all() as $name => $config) {
                if ($config['active'] && !self::isBuiltin($name)) {
                    MediaTypeRegistry::registerPreset($name, $config);
                }
            }
        } catch (Throwable $e) {
            rex_logger::logException($e);
        }
    }

    /** @return list<int> */
    public static function parseWidths(string $raw): array
    {
        $widths = [];
        foreach (preg_split('/[\s,;]+/', trim($raw)) ?: [] as $part) {
            if ($part === '' || !is_numeric($part)) {
                continue;
            }
            $w = (int) $part;
            if ($w >= self::MIN_WIDTH && $w <= self::MAX_WIDTH) {
                $widths[] = $w;
            }
        }
        $widths = array_values(array_unique($widths));
        sort($widths);
        return $widths;
    }

    /**
     * Standardbreite: die Stufe, die 1200 px am naechsten kommt
     * @param list<int> $widths
     */
    public static function pickDefault(array $widths): int
    {
        $best = (int) ($widths[0] ?? 1200);
        foreach ($widths as $w) {
            if (abs((int) $w - 1200) < abs($best - 1200)) {
                $best = (int) $w;
            }
        }
        return $best;
    }

    /**
     * Cache-Dateien eines Presets (alle virtuellen Typen is_<preset>__*).
     * @return array{files: int, bytes: int, dirs: list<string>}
     */
    public static function cacheInfo(string $preset): array
    {
        $base = rex_path::addonCache('media_manager');
        $files = 0;
        $bytes = 0;
        $dirs = [];
        if (!is_dir($base)) {
            return ['files' => 0, 'bytes' => 0, 'dirs' => []];
        }
        foreach (rex_finder::factory($base)->dirsOnly() as $dir) {
            // Typ-Ordner, ggf. mit Format-Praefix von media_negotiator (z. B. "avif-q60-…-is_<preset>__800")
            $dirName = basename((string) $dir);
            if (!preg_match('/(^|-)is_' . preg_quote($preset, '/') . '__\d+$/', $dirName)) {
                continue;
            }
            $dirs[] = (string) $dir;
            foreach (rex_finder::factory((string) $dir)->recursive()->filesOnly() as $file) {
                $files++;
                $bytes += (int) @filesize((string) $file);
            }
        }
        return ['files' => $files, 'bytes' => $bytes, 'dirs' => $dirs];
    }

    /** Cache eines Presets leeren, liefert die Anzahl geloeschter Dateien */
    public static function clearCache(string $preset): int
    {
        $info = self::cacheInfo($preset);
        foreach ($info['dirs'] as $dir) {
            rex_dir::delete($dir);
        }
        return $info['files'];
    }

    /**
     * @param array<string, mixed> $config
     * @return array{ratio: string, mode: string, widths: list<int>, default_width: int, chain: string, active: bool, note: string, updated: string}|null
     */
    private static function normalize(array $config): ?array
    {
        $ratio = trim((string) ($config['ratio'] ?? ''));
        if ($ratio !== 'original' && !preg_match('/^[1-9]\d{0,2}_[1-9]\d{0,2}$/', $ratio)) {
            return null;
        }
        $widths = [];
        foreach ((array) ($config['widths'] ?? []) as $w) {
            $w = (int) $w;
            if ($w >= self::MIN_WIDTH && $w <= self::MAX_WIDTH) {
                $widths[] = $w;
            }
        }
        $widths = array_values(array_unique($widths));
        sort($widths);
        if ($widths === []) {
            return null;
        }
        $default = (int) ($config['default_width'] ?? 0);
        if (!in_array($default, $widths, true)) {
            $default = self::pickDefault($widths);
        }
        $mode = (string) ($config['mode'] ?? 'focuspoint');
        return [
            'ratio' => $ratio,
            'mode' => in_array($mode, ['focuspoint', 'resize'], true) ? $mode : 'focuspoint',
            'widths' => $widths,
            'default_width' => $default,
            'chain' => self::normalizeChain((string) ($config['chain'] ?? '')),
            'active' => !empty($config['active']),
            'note' => (string) ($config['note'] ?? ''),
            'updated' => (string) ($config['updated'] ?? ''),
        ];
    }

    /**
     * Kommagetrennte Media-Manager-Typen fuer die Vorverarbeitung (siehe
     * rex_effect_media_srcset_set::applyChain()). Nur Zeichen, die ein
     * Media-Manager-Typname haben darf; Leereintraege und Duplikate entfallen.
     */
    public static function normalizeChain(string $raw): string
    {
        $types = [];
        foreach (explode(',', $raw) as $type) {
            $type = trim($type);
            if ($type === '' || !preg_match('/^[a-zA-Z0-9_\\-]{1,64}$/', $type)) {
                continue;
            }
            if (!in_array($type, $types, true)) {
                $types[] = $type;
            }
        }
        return implode(',', $types);
    }

    /** @param array<string, array<string, mixed>> $all */
    private static function persist(array $all): void
    {
        self::addon()->setConfig(self::CONFIG_KEY, json_encode($all, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
