# Changelog

Alle wichtigen Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
und dieses Projekt folgt [Semantic Versioning](https://semver.org/lang/de/).

## [Unreleased]

## [3.0.0] - 2026-09-23

Major-Release mit einem zweiten, neuen Weg zur responsiven Bildausgabe: **Sets**. Statt für jeden Bildausschnitt einen eigenen Media-Manager-Typ zu pflegen, stecken Seitenverhältnis, Zuschnittsmodus und Breitenstufen in einer Konfigurationseinheit – als PHP-Array im Code oder als Formular im Backend.

**Vollständig abwärtskompatibel.** Der bisherige Weg bleibt unverändert: `rex_media_srcset`, der Effekt `srcset`, die virtuellen Unterprofile `profil__breite`, `assets/srcset.js` und die HTML-Platzhalterersetzung verhalten sich exakt wie in 2.x. Bestehende Projekte können auf 3.0.0 aktualisieren, ohne eine Zeile zu ändern; beide Wege laufen im selben Projekt nebeneinander.

Die Major-Nummer steht für den Funktionsumfang und die angehobenen Systemanforderungen, nicht für Breaking Changes an der bestehenden API.

### 🚀 New Features (Neue Funktionen)

#### Added (Hinzugefügt)

- **Sets**: Ein Bild wird als `is_<set>__<breite>` angefragt, z. B. `/media/is_ratio_4_3__800/bild.jpg`. In der Datenbank existiert dafür nur ein einziger technischer Basistyp (`media_srcset_set`) – ein neues Bildformat für ein Modul heißt damit: ein Set registrieren, nicht einen Media-Manager-Typ anlegen und pflegen. Angefragte Breiten werden auf die nächste Stufe des Sets aufgerundet, damit die Zahl der Cache-Varianten begrenzt bleibt.
- **Mitgelieferte Sets**: `ratio_16_9`, `ratio_21_9`, `ratio_4_3`, `ratio_1_1` und `ratio_original` stehen sofort bereit. Eigene Sets kommen über `MediaTypeRegistry::registerPreset()`, den Extension Point `MEDIA_SRCSET_PRESETS` oder den Backend-Builder dazu.
- **`ResponsiveImage`** (`FriendsOfRedaxo\MediaSrcset\Media\ResponsiveImage`): Fluent Builder für `src`/`srcset`/`sizes` und fertige `<img>`/`<picture>`-Tags – mit layoutbasiertem `sizes` (Container-Breite, Spaltenzahl, Bildanteil, eigene Breakpoints), Art Direction über beliebig viele `<source>`, Dichte-Descriptoren (`1x/2x/3x`) für feste Darstellungsbreiten sowie automatischen `width`/`height`-Attributen gegen Layout-Sprünge.
- **Descriptor-Garantie**: Breiten werden auf die Stufen des Sets gerundet und an der tatsächlich erreichbaren Quellbreite gekappt, damit jeder `srcset`-Descriptor der Pixelbreite der gelieferten Datei entspricht. Andernfalls lädt der Browser die falsche Variante.
- **Alt-Text aus dem dafür vorgesehenen Feld** (`Media\AltText`): MediaPlace-Alt-Feld (inkl. Sprachvariante) → mehrsprachiges Metainfo-Feld `med_alt` → leer. Der Medienpool-Titel wird bewusst **nicht** als Alt-Text verwendet, weil ein Titel das Bild nicht für Screenreader beschreibt; ohne Alt-Text bzw. bei Markierung als dekorativ wird `alt="" role="presentation"` ausgegeben. Gilt nur für Sets – der bisherige Weg behält seinen Titel-Fallback unverändert.
- **Backend unter Media Manager › srcset & Sets**: *Übersicht* (alle registrierten Sets), *Sets & Builder* (Sets anlegen und verwalten, Assistent zur Ableitung der Breitenstufen aus Layout-Angaben, Analyse der tatsächlichen Nutzung in Modulen und Templates gegen die Breiten im Medienpool, Vorschau mit serverseitigem Descriptor-Check und Simulation der Browser-Auswahl), *Demo & Prüfung*, *Einstellungen* und *Hilfe*.
- **Vorverarbeitung je Set (`chain`)**: Ein Set kann die Effekte bestehender Media-Manager-Typen als Bausteine wiederverwenden, z. B. `watermark,make_greyscale`. Die Kette läuft vor dem Zuschnitt in voller Quellauflösung und vollständig auf dem bereits geladenen `rex_managed_media` – ohne Zwischendateien und ohne erneutes Encodieren je Schritt, also ohne Qualitätsverlust. Größen-Effekte (`resize`, `srcset`, `media_srcset_set`) werden übersprungen, damit die Zielbreite allein beim Set liegt; Selbstreferenzen und Zyklen werden erkannt (max. 5 Ebenen), Fehler protokolliert und übersprungen, statt die Bildauslieferung zu verhindern.
- **Schalter für die HTML-Platzhalterersetzung** (Media Manager › srcset & Sets › Einstellungen). Der `OUTPUT_FILTER` durchsucht jede Seitenausgabe per regulärem Ausdruck; Projekte, die ausschließlich die PHP-API nutzen, können ihn abschalten und sparen diesen Lauf. Standard: bei **Neuinstallationen aus**, bei **Updates bestehender Installationen an**, damit vorhandene Templates nicht still aufhören zu funktionieren. Eine getroffene Entscheidung wird von späteren Updates nie überschrieben.
- **Extension Point `MEDIA_SRCSET_PRESETS`** zum Ergänzen und Ändern von Sets aus anderen Addons.
- **Englische Übersetzung** (`en_gb`) mit vollständiger Abdeckung, dazu `README.en.md` und `API.en.md`.

#### Changed (Geändert)

- Mindestanforderungen auf **REDAXO ^5.18.0** und **PHP >= 8.1** angehoben.
- Die Installation legt den Media-Manager-Typ `media_srcset_set` an. Er ist rein technisch und wird nie direkt verwendet – alle `is_*`-Anfragen laufen darüber.
- README vollständig überarbeitet: stellt beide Wege gegenüber und sagt, wann welcher passt.

### 🧹 Code Quality

- Statische Analyse (PHPStan/rexstan, Level 8) über das gesamte Addon auf **0 Findings** gebracht: präzisere Array-Shapes für aufgelöste Sets, entfernte tote `??`-Zweige und nie-falsche Typprüfungen, ergänzte Docblock-Typen sowie ein möglicher Zugriff auf `end()` eines leeren Arrays in `ResponsiveImage::resolveSrc()`.
- In der Vorverarbeitung wird vor dem Instanziieren geprüft, dass die Effektklasse tatsächlich von `rex_effect_abstract` erbt.

### Hinweise

- **focuspoint ist optional.** Mit installiertem Addon folgen Ratio-Zuschnitte dem im Medienpool gesetzten Fokuspunkt, ohne wird zentriert geschnitten. Die Ausgabemaße sind in beiden Fällen identisch, es unterscheidet sich nur, *wo* geschnitten wird.
- **media_negotiator** wird unterstützt: Der Cache-Pfad wird pro Ausgabeformat getrennt, sodass Sets als AVIF oder WebP ausgeliefert werden.

## [2.3.0] - 2026-09-04

Baut auf [2.2.1](#221---2026-09-04) auf (Sicherheitsfix, siehe dort).

### 🚀 New Features (Neue Funktionen)

#### Added (Hinzugefügt)

- **SVG-Unterstützung**: Dateien mit der Endung `.svg` werden automatisch erkannt und direkt aus dem Medienpool ausgeliefert (`rex_url::media()`), ohne den Media Manager zu durchlaufen. SVGs skalieren im Browser nativ – sie brauchen weder `srcset`-Auflösungsvarianten noch eine Media-Manager-Cache-Datei. Betrifft `getTag()`, `getImgTag()`, `getPictureTag()` und `getSrcSet()`.
- **Layout-basiertes `sizes`-Attribut**: Neuer optionaler `$layout`-Parameter für `getImgTag()`, `getPictureTag()` und `getTag()` berechnet das `sizes`-Attribut aus Container-Breite und Spaltenzahl (`containerWidth`, `columns`, `columnsTablet`, `columnsMobile`, `mediaFraction`) statt lediglich die srcset-Breakpoints als Media-Queries zu wiederholen. Ohne den Parameter bleibt das bisherige Verhalten unverändert (voll rückwärtskompatibel).

#### Changed (Geändert)

- Ein explizit in `$attributes['sizes']` gesetzter Wert hat weiterhin in jedem Fall Vorrang vor der automatischen Berechnung (egal ob mit oder ohne `$layout`).

### 🐛 Bug Fixes (Fehlerbehebungen)

- `getTag()` baute den Media-Manager-Cache-Pfad bisher selbst zusammen (`<mediaType>/<fileName>`), statt ihn über `rex_media_manager::create()` zu beziehen. Das ging schief, sobald ein Effekt den Cache-Pfad verlegt (z. B. media_negotiator mit `avif-q60-…-<type>/`-Unterordnern): `width`/`height` fehlten dauerhaft, und die Cache-Datei wurde bei jedem Request neu erzeugt statt nur beim ersten Mal. `getTag()` nutzt jetzt `rex_media_manager::create($mediaType, $fileName)->getMedia()->getWidth()/getHeight()`, was beide Probleme behebt, unabhängig davon, wohin ein Effekt den Cache-Pfad verlegt.

### 🧹 Code Quality

- Statische Analyse (PHPStan/rexstan, Level 8) auf 0 Findings gebracht: fehlende Array-Shape-Typen ergänzt, zwei nie erreichbare `if`-Zweige entfernt, ein nie-falscher `empty()`-Check entfernt, ein defekter Docblock korrigiert.
- `$additionalSources` in `getTag()`/`getPictureTag()` im Docblock als "wird unescaped ausgegeben, muss vom Aufrufer escaped sein" dokumentiert (einzige verbleibende Stelle, die rohes HTML durchreicht – über `getPictureTag()` sicher, aber `getTag()` ist public).

## [2.2.1] - 2026-09-04

### 🔒 Security

- **Stored XSS behoben**: `getTag()` / `getImgTag()` / `getPictureTag()` haben Attributwerte – u. a. den Medienpool-Titel, der automatisch ins `alt`-Attribut übernommen wird – bisher ungefiltert in die generierte Markup-Ausgabe geschrieben. Ein Redakteur mit Schreibzugriff auf den Medienpool (Editor-Rechte genügen, keine Admin-Rechte nötig) konnte darüber JavaScript im Titel eines Bildes hinterlegen, das anschließend bei jedem Frontend-Aufruf ausgeführt wurde. Alle Attributwerte werden jetzt über `rex_escape()` kodiert (auch in `replaceSrcSets()`s Regex-basiertem HTML-Splice-Pfad); Attributnamen werden zusätzlich gegen `/^[a-zA-Z_:][-a-zA-Z0-9_:.]*$/` validiert, was zusätzlich Attribut-Injection über `$attributes`-Keys verhindert.

### 🐛 Bug Fixes (Fehlerbehebungen)

- `getTag()` konnte einen `TypeError` werfen, wenn die angegebene Datei nicht im Medienpool existiert (`rex_media::get()` liefert dann `null`) – wirft jetzt stattdessen eine sprechende `InvalidArgumentException`.
- `getimagesize()` kann `false` liefern (z. B. bei nicht lesbaren Dateien); `width`/`height`/`sizes` werden dann korrekt weggelassen, statt kaputte Attribute zu erzeugen.
- `getTag()` hatte für einen ungültigen `$tagType` keinen Rückgabepfad, obwohl die Methode `: string` deklariert – hätte einen `TypeError` ausgelöst. Wirft jetzt eine `InvalidArgumentException`.
- Doppeltes Escaping von URLs behoben: `rex_media_manager::getUrl()` liefert standardmäßig bereits HTML-vorverschlüsselte URLs (mit `&amp;`); in Kombination mit dem neuen Escaping führte das zu `&amp;amp;` in `src`/`srcset`. Alle internen Aufrufe fordern jetzt explizit die rohe URL an, escapt wird nur noch einmal, zentral beim Aufbau der Attribute. Dafür Mindestversion auf REDAXO 5.10.0 angehoben (der 4. `getUrl()`-Parameter existiert erst ab dort).
- `getImgSrc()`: doppelten, redundanten `preg_match()`-Aufruf zusammengeführt (verhinderte auch einen PHPStan-Fehlalarm).
- PHP-8.4-Deprecations behoben: implizit nullable Parameter (`array $x = null`) sind jetzt explizit `?array $x = null`.

Reines Security-Release, losgelöst von einem größeren Feature-PR (Review-Feedback), damit niemand ein Feature-Update einspielen muss, nur um die Lücke zu schließen. SVG-Unterstützung, layout-basierte `sizes` und das begleitende Aufräumen/README folgen als 2.3.0 darauf.

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
