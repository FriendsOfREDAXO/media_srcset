<?php

namespace FriendsOfRedaxo\MediaSrcset\Media;

use rex_addon;
use rex_media;
use rex_sql;

/**
 * Alt-Text eines Medienpool-Bildes ermitteln.
 *
 * Reihenfolge: MediaPlace-eigenes Alt-Feld (JSON in med_json_data, Text je
 * Sprache plus "decorative"-Flag) -> mehrsprachiges Metainfo-Feld med_alt
 * (lang_text ueber metainfo_lang_fields, plus med_alt_decorative) -> leer.
 * Der Medienpool-Titel ist bewusst KEIN Fallback: ein Titel beschreibt das
 * Bild nicht fuer Screenreader. Ohne Alt-Text wird das Bild dekorativ
 * ausgegeben (alt="" role="presentation").
 */
final class AltText
{
    private const CLASSIC_DECORATIVE_FIELD = 'med_alt_decorative';

    /** @var array<string, array{alt: string, decorative: bool}> */
    private static array $cache = [];

    /** @var array<string, bool> */
    private static array $columnExists = [];

    /**
     * @return array{alt: string, decorative: bool} decorative = ausdruecklich als dekorativ markiert
     */
    public static function resolve(?rex_media $media, ?int $clangId = null): array
    {
        if ($media === null) {
            return ['alt' => '', 'decorative' => false];
        }
        $key = $media->getFileName() . '|' . ($clangId ?? 0);
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        return self::$cache[$key] = self::lookup($media, $clangId);
    }

    /** @return array{alt: string, decorative: bool} */
    private static function lookup(rex_media $media, ?int $clangId): array
    {
        // 1) MediaPlace: eigenes Alt-Feld (Widget "alt") im JSON
        if (rex_addon::get('mediaplace')->isAvailable() && class_exists('FriendsOfRedaxo\\Mediaplace\\AltTextStatus')) {
            $field = \FriendsOfRedaxo\Mediaplace\AltTextStatus::resolveOwnAltField();
            if ($field !== null) {
                $json = json_decode((string) $media->getValue('med_json_data'), true);
                $data = is_array($json) ? ($json[$field->getKey()] ?? null) : null;
                if (is_array($data)) {
                    if (!empty($data['decorative'])) {
                        return ['alt' => '', 'decorative' => true];
                    }
                    $texts = (array) ($data['text'] ?? []);
                    // bevorzugt die angefragte Sprache, sonst der erste gefuellte Text
                    if ($clangId !== null) {
                        foreach ([(string) $clangId, $clangId] as $k) {
                            if (isset($texts[$k]) && trim((string) $texts[$k]) !== '') {
                                return ['alt' => trim((string) $texts[$k]), 'decorative' => false];
                            }
                        }
                    }
                    foreach ($texts as $text) {
                        if (trim((string) $text) !== '') {
                            return ['alt' => trim((string) $text), 'decorative' => false];
                        }
                    }
                }
            }
        }

        // 2) Klassische Metainfo-Felder
        if (self::hasColumn(self::CLASSIC_DECORATIVE_FIELD) && (bool) $media->getValue(self::CLASSIC_DECORATIVE_FIELD)) {
            return ['alt' => '', 'decorative' => true];
        }
        if (self::hasColumn('med_alt') && rex_addon::get('metainfo_lang_fields')->isAvailable() && class_exists('FriendsOfRedaxo\\MetaInfoLangFields\\MetainfoLangHelper')) {
            $alt = trim(\FriendsOfRedaxo\MetaInfoLangFields\MetainfoLangHelper::getMediaValue($media, 'med_alt', $clangId));
            if ($alt !== '') {
                return ['alt' => $alt, 'decorative' => false];
            }
        }

        return ['alt' => '', 'decorative' => false];
    }

    private static function hasColumn(string $column): bool
    {
        if (!isset(self::$columnExists[$column])) {
            self::$columnExists[$column] = rex_sql::showColumns(\rex::getTable('media')) !== [] && in_array($column, array_column(rex_sql::showColumns(\rex::getTable('media')), 'name'), true);
        }
        return self::$columnExists[$column];
    }
}
