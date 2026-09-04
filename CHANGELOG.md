# Changelog

Alle wichtigen Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
und dieses Projekt folgt [Semantic Versioning](https://semver.org/lang/de/).

## [Unreleased]

## [2.3.0] - 2026-09-04

### 🔒 Security

- **Stored XSS behoben**: `getTag()` / `getImgTag()` / `getPictureTag()` haben Attributwerte – u. a. den Medienpool-Titel, der automatisch ins `alt`-Attribut übernommen wird – bisher ungefiltert in die generierte Markup-Ausgabe geschrieben. Ein Redakteur mit Schreibzugriff auf den Medienpool konnte darüber JavaScript im Titel eines Bildes hinterlegen, das anschließend bei jedem Frontend-Aufruf ausgeführt wurde. Alle Attributwerte werden jetzt über `rex_escape()` kodiert; Attributnamen werden zusätzlich gegen `/^[a-zA-Z_:][-a-zA-Z0-9_:.]*$/` validiert, was zusätzlich Attribut-Injection über `$attributes`-Keys verhindert.

### 🚀 New Features (Neue Funktionen)

#### Added (Hinzugefügt)

- **SVG-Unterstützung**: Dateien mit der Endung `.svg` werden automatisch erkannt und direkt aus dem Medienpool ausgeliefert (`rex_url::media()`), ohne den Media Manager zu durchlaufen. SVGs skalieren im Browser nativ – sie brauchen weder `srcset`-Auflösungsvarianten noch eine Media-Manager-Cache-Datei. Betrifft `getTag()`, `getImgTag()`, `getPictureTag()` und `getSrcSet()`.
- **Layout-basiertes `sizes`-Attribut**: Neuer optionaler `$layout`-Parameter für `getImgTag()`, `getPictureTag()` und `getTag()` berechnet das `sizes`-Attribut aus Container-Breite und Spaltenzahl (`containerWidth`, `columns`, `columnsTablet`, `columnsMobile`, `mediaFraction`) statt lediglich die srcset-Breakpoints als Media-Queries zu wiederholen. Ohne den Parameter bleibt das bisherige Verhalten unverändert (voll rückwärtskompatibel).

#### Changed (Geändert)

- Ein explizit in `$attributes['sizes']` gesetzter Wert hat weiterhin in jedem Fall Vorrang vor der automatischen Berechnung (egal ob mit oder ohne `$layout`).

### 🐛 Bug Fixes (Fehlerbehebungen)

- `getTag()` konnte einen `TypeError` werfen, wenn die angegebene Datei nicht im Medienpool existiert (`rex_media::get()` liefert dann `null`) – wirft jetzt stattdessen eine sprechende `InvalidArgumentException`.
- `getimagesize()` kann `false` liefern (z. B. bei nicht lesbaren Dateien); `width`/`height`/`sizes` werden dann korrekt weggelassen, statt kaputte Attribute zu erzeugen.
- `getTag()` hatte für einen ungültigen `$tagType` keinen Rückgabepfad, obwohl die Methode `: string` deklariert – hätte einen `TypeError` ausgelöst. Wirft jetzt eine `InvalidArgumentException`.
- Doppeltes Escaping von URLs behoben: `rex_media_manager::getUrl()` liefert standardmäßig bereits HTML-vorverschlüsselte URLs (mit `&amp;`); in Kombination mit dem neuen Escaping führte das zu `&amp;amp;` in `src`/`srcset`. Alle internen Aufrufe fordern jetzt explizit die rohe URL an, escapt wird nur noch einmal, zentral beim Aufbau der Attribute.
- `getImgSrc()`: doppelten, redundanten `preg_match()`-Aufruf zusammengeführt (verhinderte auch einen PHPStan-Fehlalarm).
- PHP-8.4-Deprecations behoben: implizit nullable Parameter (`array $x = null`) sind jetzt explizit `?array $x = null`.

### 🧹 Code Quality

- Statische Analyse (PHPStan/rexstan, Level 8) auf 0 Findings gebracht: fehlende Array-Shape-Typen ergänzt, zwei nie erreichbare `if`-Zweige entfernt, ein nie-falscher `empty()`-Check entfernt, ein defekter Docblock korrigiert.

## Ältere Versionen

Für dieses Projekt wurde erst ab 2.3.0 ein Changelog geführt. Frühere Versionen:

| Version | Datum |
| --- | --- |
| 2.2.0 | 2024-08-09 |
| 2.1.0 | 2022-05-02 |
| 2.0.5 | 2021-12-29 |
| 2.0.4 | 2021-03-14 |
| 2.0.3 | 2021-03-12 |
| 2.0.2 | 2021-01-21 |
| 2.0.1 | 2020-12-10 |
| 2.0 | 2019-10-16 |

Details zu diesen Versionen: [Commit-Historie](https://github.com/FriendsOfREDAXO/media_srcset/commits/main) und [Tags](https://github.com/FriendsOfREDAXO/media_srcset/tags).
