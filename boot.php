<?php

/**
 * media_srcset - responsive Bildausgabe fuer REDAXO.
 *
 * Zwei Wege, die sich nicht ausschliessen:
 *
 * 1. Effekt "srcset" auf einem bestehenden Media-Manager-Typ. Der Typ bleibt
 *    die Einheit der Konfiguration, die Breitenstufen darunter (hero__400,
 *    hero__800, ...) entstehen zur Laufzeit. Klasse rex_media_srcset.
 * 2. Sets: Seitenverhaeltnis, Modus und Breitenstufen stecken in einer
 *    Konfigurationseinheit statt in je einem Media-Manager-Typ. Ein Bild wird
 *    als is_<set>__<breite> angefragt; in der Datenbank existiert dafuer nur
 *    der Basistyp "media_srcset_set". Klasse
 *    FriendsOfRedaxo\MediaSrcset\Media\ResponsiveImage.
 */

use FriendsOfRedaxo\MediaSrcset\Config\PresetStore;
use FriendsOfRedaxo\MediaSrcset\MediaNegotiatorBridge;
use FriendsOfRedaxo\MediaSrcset\SetFilterset;

if (rex_addon::get('media_manager')->isAvailable()) {
    // Weg 1: klassischer srcset-Effekt auf echten Media-Manager-Typen
    rex_media_manager::addEffect('rex_effect_srcset');
    rex_extension::register('MEDIA_MANAGER_FILTERSET', ['rex_media_srcset', 'managerFilterset']);

    // Weg 2: Sets ueber virtuelle Typen is_<set>__<breite>
    rex_media_manager::addEffect(rex_effect_media_srcset_set::class);

    rex_extension::register('MEDIA_MANAGER_FILTERSET', static function (rex_extension_point $ep): array {
        return SetFilterset::apply($ep);
    }, rex_extension::EARLY);

    rex_extension::register('MEDIA_MANAGER_INIT', static function (rex_extension_point $ep): void {
        MediaNegotiatorBridge::adjustCachePath($ep);
    }, rex_extension::EARLY);
}

// Code-Sets (Grundausstattung) und aktive Builder-Sets aus der Konfiguration
// (Backend: Media Manager > srcset & Sets > Sets & Builder)
PresetStore::registerBuiltin();
PresetStore::registerActive();

// Platzhalterersetzung srcset="rex_media_type=Profil" im gerenderten HTML.
// Altlast aus frueheren Versionen: laeuft per Regex ueber jede Seitenausgabe.
// Projekte, die ausschliesslich die PHP-API nutzen, koennen sie abschalten
// (Backend: Media Manager > srcset & Sets > Einstellungen).
if (rex_addon::get('media_srcset')->getConfig('output_filter', true)) {
    rex_extension::register('OUTPUT_FILTER', ['rex_media_srcset', 'replaceSrcSets']);
}
