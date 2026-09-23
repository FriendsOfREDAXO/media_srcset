# media_srcset

Responsive Bildausgabe für REDAXO: `srcset`, `sizes` und `<picture>`, ohne für jede Bildbreite einen eigenen Media-Manager-Typ anzulegen.

Das Addon bietet dafür **zwei Wege**, die sich nicht ausschließen und im selben Projekt nebeneinander laufen können:

| | **Effekt `srcset`** (klassisch) | **Sets** |
|---|---|---|
| Konfiguration liegt in | je einem echten Media-Manager-Typ | einem Set (Code-Array oder Backend-Formular) |
| Zuschnitt/Ratio je Anwendungsfall | eigener Media-Manager-Typ pro Ausschnitt | ein Set pro Ausschnitt, kein DB-Typ nötig |
| Breitenstufen | virtuelle Untertypen `hero__400` | virtuelle Typen `is_<set>__<breite>` |
| PHP-API | `rex_media_srcset::getImgTag()` | `ResponsiveImage::forFile()` |
| Alt-Text | Medienpool-Titel als Fallback | eigenes Alt-Feld, Titel bewusst **nicht** |

**Kurz:** Der klassische Weg automatisiert die Breitenstufen unterhalb bestehender Media-Manager-Typen. Sets ersetzen die Typen selbst durch eine Konfigurationsebene und brauchen in der ganzen Installation nur einen einzigen technischen Typ.

Bestehende Projekte müssen nichts ändern: Der klassische Weg ist unverändert erhalten, inklusive `rex_media_srcset` und der HTML-Platzhalterersetzung.

Backend: **Media Manager › srcset & Sets** mit *Übersicht*, *Sets & Builder*, *Demo & Prüfung*, *Einstellungen* und *Hilfe*.

## Inhalt

- [Installation](#installation)
- [Weg 1: Effekt `srcset`](#weg-1-effekt-srcset-auf-einem-media-manager-typ)
  - [Öffentliche API](#öffentliche-api-rex_media_srcset)
  - [Automatischer HTML-Ersatz](#automatischer-html-ersatz-output_filter)
  - [srcset.js](#srcsetjs--auflösung-nach-tatsächlicher-elementbreite)
- [Weg 2: Sets](#weg-2-sets)
  - [Funktionsweise](#funktionsweise)
  - [Sets definieren](#sets-definieren)
  - [Verwendung im Code](#verwendung-im-code)
  - [Art Direction](#art-direction)
  - [Descriptor-Garantie](#descriptor-garantie)
- [Vorverarbeitung: Effekte anderer Typen einbinden](#vorverarbeitung-effekte-anderer-typen-einbinden)
- [Backend-Seiten](#backend-seiten)
- [Welchen Weg wählen?](#welchen-weg-wählen)
- [Zusammenspiel mit anderen Addons](#zusammenspiel-mit-anderen-addons)
- [Cache und Fehlersuche](#cache-und-fehlersuche)
- [Sicherheit](#sicherheit)
- [Anforderungen](#anforderungen)

## Installation

- Release herunterladen, entpacken, Ordner in `media_srcset` umbenennen und nach `/redaxo/src/addons` legen.
- Im Backend installieren und aktivieren (Abhängigkeit: `media_manager`).

Bei der Installation wird der Media-Manager-Typ `media_srcset_set` angelegt. Er ist rein technisch und wird **nie direkt verwendet** – alle `is_*`-Anfragen laufen darüber.

---

# Weg 1: Effekt `srcset` auf einem Media-Manager-Typ

Ein Media-Manager-Typ (z. B. `hero`) bekommt den Effekt **Bild: SRCSET** mit einem Konfigurationsstring:

```
400 480w, 800 480w 2x, 700 768w
```

Format je Eintrag: `<Bildbreite in px> <Viewport-Breite>w [<Pixeldichte>x]`.

- **400 480w** – erzeugt ein 400 px breites Bild, gedacht für einen 480 px breiten Slot.
- **800 480w 2x** – 800 px Datei für denselben Slot auf Retina-Displays.
- **700 768w** – 700 px Datei für einen 768 px breiten Slot.

Für jede Bildbreite entsteht zur Laufzeit ein virtuelles Unterprofil `hero__400`, `hero__700`, `hero__800`, das alle übrigen Effekte des Basisprofils übernimmt und nur `width`/`height` überschreibt. Im Backend muss dafür nichts zusätzlich angelegt werden.

Eine angefragte Breite ohne exakte Entsprechung wird auf die nächstgrößere konfigurierte Breite aufgerundet.

## Öffentliche API (`rex_media_srcset`)

```php
rex_media_srcset::getImgTag(string $fileName, string $mediaType, ?array $attributes = null, ?array $layout = null): string
rex_media_srcset::getPictureTag(string $fileName, string $mediaType, ?array $attributes = null, ?array $mediaQueries = null, ?array $layout = null): string
rex_media_srcset::getSrcSet(string $fileName, string $mediaType): string
rex_media_srcset::getTag(string $fileName, string $mediaType, ?array $attributes = null, int $tagType = rex_media_srcset::IMG, ?array $additionalSources = null, ?array $layout = null): string
```

Einfaches Bild:

```php
echo rex_media_srcset::getImgTag('teamfoto.jpg', 'hero');
```

Mit eigenen Attributen (`alt` und `sizes` haben immer Vorrang vor der automatischen Ermittlung):

```php
echo rex_media_srcset::getImgTag('teamfoto.jpg', 'hero', [
    'class'   => 'hero-image',
    'loading' => 'lazy',
    'alt'     => 'Das Team bei der Arbeit',
]);
```

Art Direction mit `<picture>` – je Media Query ein eigener Media-Manager-Typ, der einen anderen Zuschnitt haben darf:

```php
echo rex_media_srcset::getPictureTag('teamfoto.jpg', 'hero_desktop', ['class' => 'hero-image'], [
    '(max-width: 719px)' => 'hero_mobile_portrait',
]);
```

Layout-basiertes `sizes` statt bloßer Wiederholung der Breakpoints:

```php
echo rex_media_srcset::getImgTag('teamfoto.jpg', 'hero', null, [
    'containerWidth' => 'uk-container',  // Schätzung der Container-Maximalbreite
    'columns'        => 3,               // Spalten ab 1200 px
    'columnsTablet'  => 2,               // Spalten zwischen 640 und 1200 px
    'columnsMobile'  => 1,               // Spalten unter 640 px
    'mediaFraction'  => 1.0,             // Anteil der Spalte (0.05–1.0)
]);
// sizes="(min-width: 1200px) 400px, (min-width: 640px) 50vw, 100vw"
```

`.svg`-Dateien werden erkannt und direkt aus dem Medienpool ausgeliefert – ohne Media-Manager, ohne `srcset`/`sizes`. SVGs skalieren im Browser ohnehin verlustfrei.

`getSrcSet()` liefert bewusst einen **unescapten** Rohwert; beim direkten Einbau in eigenes Markup selbst `rex_escape()` anwenden.

## Automatischer HTML-Ersatz (OUTPUT_FILTER)

Alternativ kann `srcset="rex_media_type=ProfilName"` direkt im Template stehen; das Addon ersetzt den Platzhalter beim Rendern:

```html
<img src="index.php?rex_media_type=hero&rex_media_file=bild.jpg" srcset="rex_media_type=hero" />
```

Das funktioniert auch für `<picture>` mit mehreren `<source srcset="rex_media_type=…">`.

> **Altlast.** Dieser Weg durchsucht **jede** Seitenausgabe per regulärem Ausdruck und bietet weniger Kontrolle als die PHP-API (kein `alt`-Fallback, kein `sizes`, keine SVG-Sonderbehandlung). Für neuen Code `getImgTag()` / `getPictureTag()` oder Sets verwenden.

Abschaltbar unter **Media Manager › srcset & Sets › Einstellungen**. Standard:

- **Neuinstallation:** aus – neue Projekte nutzen die PHP-API, der Regex-Lauf wäre unnötige Last.
- **Update einer bestehenden Installation:** an – dort können Templates den Platzhalter verwenden, der sonst still aufhören würde zu funktionieren.

Eine einmal getroffene Entscheidung wird von späteren Updates nie überschrieben.

## srcset.js – Auflösung nach tatsächlicher Elementbreite

Wird das Attribut als `data-srcset` ausgegeben und

```html
<script src="assets/addons/media_srcset/srcset.js"></script>
```

eingebunden, prüft ein Skript beim Laden und nach jedem Resize die tatsächliche Anzeigebreite jedes Elements und lädt bei Bedarf eine passendere Datei nach. Die Auswahl orientiert sich damit an der gerenderten Elementbreite statt nur am Viewport. Dafür nötig:

```css
img[data-srcset] { width: 100%; height: auto; }
```

---

# Weg 2: Sets

Ein **Set** bündelt Seitenverhältnis, Zuschnittsmodus und die erlaubten Breitenstufen in einer Konfigurationseinheit – als PHP-Array im Code oder als Eintrag im Backend-Builder. Ein Bild wird als `is_<set>__<breite>` angefragt, z. B. `/media/is_ratio_4_3__800/bild.jpg`.

Ein neues Bildformat für ein einzelnes Modul heißt damit: ein Set registrieren (eine Codezeile oder ein Formular) – nicht: einen Media-Manager-Typ anlegen, konfigurieren und pflegen.

## Funktionsweise

1. **Sets** definieren Ratio, Modus und Breitenstufen, z. B. `ratio_4_3` mit `400, 800, 1200, 1600, 2000`.
2. Ein Bild wird als `is_<set>__<breite>` angefragt. `MEDIA_MANAGER_FILTERSET` setzt dafür dynamisch den Effekt `media_srcset_set` ein; in der Datenbank existiert nur der Basistyp `media_srcset_set`.
3. Der Effekt wendet optional die **Vorverarbeitung** an, schneidet **dann** auf das Seitenverhältnis (Fokuspunkt, sonst zentriert) und skaliert **danach** breitenbegrenzt. Vergrößert wird nie.
4. `ResponsiveImage` baut daraus `src`, `srcset` und `sizes`.

Angefragte Breiten werden auf die nächste Stufe **aufgerundet**: `is_ratio_4_3__900` liefert die 1200er-Stufe. So bleibt die Zahl der Cache-Varianten begrenzt.

## Sets definieren

Zwei Quellen, beide landen in derselben Registry:

| Quelle | Wo | Wer |
|---|---|---|
| **Code** | `boot.php` des Addons oder eines Projekt-Addons via `MediaTypeRegistry::registerPreset()` bzw. Extension Point `MEDIA_SRCSET_PRESETS` | Entwickler |
| **Builder** | Backend › *Sets & Builder*, gespeichert in der Addon-Konfiguration | Redaktion und Entwickler |

Grundausstattung:

| Set | Ratio | Modus | Breiten | Standard |
|---|---|---|---|---|
| `ratio_16_9` | 16:9 | focuspoint | 400, 800, 1200, 1600, 2000 | 1200 |
| `ratio_21_9` | 21:9 | focuspoint | 400, 800, 1200, 1600, 2000 | 1200 |
| `ratio_4_3` | 4:3 | focuspoint | 400, 800, 1200, 1600, 2000 | 1200 |
| `ratio_1_1` | 1:1 | focuspoint | 400, 800, 1200, 1600 | 1200 |
| `ratio_original` | Quelle | resize | 400 … 2400 | 1600 |

Code-Sets sind im Backend schreibgeschützt; Builder-Sets dürfen deren Namen nicht verwenden.

```php
use FriendsOfRedaxo\MediaSrcset\Config\MediaTypeRegistry;

MediaTypeRegistry::registerPreset('teaser_3_2', [
    'ratio' => '3_2',            // Breite_Höhe oder 'original'
    'mode' => 'focuspoint',      // focuspoint | resize
    'widths' => [400, 800, 1200, 1600],
    'default_width' => 1200,
    'chain' => '',               // optional, siehe Vorverarbeitung
]);
```

## Verwendung im Code

Eine feste Breite:

```php
echo '<img src="' . rex_media_manager::getUrl('is_ratio_16_9__1200', $file) . '" alt="…">';
```

Responsive Ausgabe:

```php
use FriendsOfRedaxo\MediaSrcset\Media\ResponsiveImage;

echo ResponsiveImage::forFile($file)
    ->withDesktopPreset('ratio_4_3')      // Set für srcset
    ->withMobilePreset('ratio_1_1')       // optional: eigenes Ratio unter dem Mobile-Breakpoint
    ->withWidths([400, 800, 1200, 1600])  // gewünschte Stufen (werden auf Set-Stufen gerundet)
    ->withContainerWidth('uk-container')  // uk-container(-xsmall|-small|-large|-xlarge) oder 'expand'
    ->withColumns(3, 2, 1)                // Spalten Desktop / Tablet / Mobil
    ->withMediaFraction(0.5)              // Anteil des Bildes an der Spalte
    ->withBreakpoints(960, 1200)          // Breakpoints des eigenen Layouts (Standard 640/1200)
    ->toImageTag(['alt' => $alt, 'class' => 'uk-width-1-1']);
```

Weitere Ausgaben: `toImage()`, `toPicture()`, `toPictureTag()`, `getSrcsetEntries()`, `getEffectiveWidths()`, `getSourceMaxWidth()`, `getDimensions()`, `withCapToSource(false)`, `withSizes()`.

### Attribute des `<img>`

`toImageTag()` setzt automatisch:

- **`width` und `height`** der `src`-Variante, damit der Browser den Platz vor dem Laden reserviert. Eigene Werte haben Vorrang.
- **`alt`** nach der Regel: übergeben (`['alt' => …]` oder `withAlt()`) > MediaPlace-Alt-Feld (inkl. Sprachvariante) > klassisches `med_alt` > leer. Der Medienpool-**Titel ist kein Alt-Text** und wird nie verwendet. Ohne Alt-Text oder bei Markierung als dekorativ wird `alt="" role="presentation"` ausgegeben.
- **`loading="lazy" decoding="async"`**; `withPriority()` liefert stattdessen `loading="eager" fetchpriority="high"` für das LCP-Bild.

Die Alt-Regel steht auch eigenem Code zur Verfügung: `AltText::resolve($media, $clangId)`.

### Feste Größen mit Dichte-Descriptoren

Für Logos, Avatare oder Icons mit fester Darstellungsbreite ersetzt `withDensities()` das `sizes`-Attribut durch `1x/2x/3x`:

```php
echo ResponsiveImage::forFile($logo)
    ->withDesktopPreset('logo_1_1')
    ->withWidths([200])
    ->withDensities([1, 2, 3])
    ->toImageTag(['alt' => 'Firmenlogo']);
```

Wird die Quelle vorher erreicht, sinkt der Descriptor entsprechend (z. B. `1.5x`), damit er der gelieferten Datei entspricht.

## Art Direction

1. **Bildausschnitt je Bild (Fokuspunkt):** Jeder Ratio-Zuschnitt wird um den im Medienpool gesetzten Fokuspunkt gelegt – ohne Code, pro Bild, durch die Redaktion.
2. **Anderes Seitenverhältnis je Bildschirmbreite:** `withSource()` fügt beliebig viele `<source>` mit eigener Media Query hinzu (Aufrufreihenfolge = Reihenfolge im Markup).

```php
echo ResponsiveImage::forFile($file)
    ->withDesktopPreset('ratio_21_9')
    ->withSource('(max-width: 639px)', 'ratio_1_1', ['widths' => [400, 800], 'sizes' => '100vw'])
    ->withSource('(max-width: 1199px)', 'ratio_4_3')
    ->toPictureTag(['loading' => 'lazy']);
```

Beide Zuschnitte folgen demselben Fokuspunkt. Nicht vorgesehen ist ein komplett anderes Bild je Breakpoint; dafür zwei Medienfelder anlegen.

## Descriptor-Garantie

Ein `srcset`-Descriptor (`800w`) muss der Pixelbreite der Datei entsprechen, sonst wählt der Browser falsch. `ResponsiveImage` sorgt dafür durch:

- Runden der gewünschten Breiten auf Set-Stufen (nur diese Dateien existieren).
- Kappen an der Quelle: Stufen oberhalb der erreichbaren Breite entfallen; als größte Variante wird die Quellbreite mit korrektem Descriptor angehängt.
- Die Reihenfolge Vorverarbeitung → Zuschnitt → Skalierung, damit auch Hochformat-Quellen die volle Zielbreite erreichen.

---

## Vorverarbeitung: Effekte anderer Typen einbinden

Ein Set kann die Effekte **bestehender Media-Manager-Typen** als Bausteine wiederverwenden – etwa ein Wasserzeichen oder einen Farbfilter, der bereits als Typ gepflegt wird:

```php
MediaTypeRegistry::registerPreset('teaser_sw', [
    'ratio' => '4_3',
    'mode' => 'focuspoint',
    'widths' => [400, 800, 1200],
    'chain' => 'watermark,make_greyscale',   // kommagetrennte Media-Manager-Typen
]);
```

Im Builder steht dafür das Feld *Vorverarbeitung* zur Verfügung.

Die Kette läuft **vor** dem Zuschnitt, also in voller Quellauflösung, und vollständig im Arbeitsspeicher – ohne Zwischendateien und ohne erneutes Encodieren je Schritt.

Regeln:

- **Größen-Effekte werden übersprungen** (`resize`, `srcset`, `media_srcset_set`). Die Zielbreite bestimmt allein das Set, sonst wäre der `srcset`-Descriptor nicht mehr die tatsächliche Dateibreite.
- Nicht vorhandene Typen werden übersprungen.
- Selbstreferenzen und Zyklen werden erkannt, die Verschachtelung ist auf fünf Ebenen begrenzt.
- Ein Fehler in einem Kettenglied wird protokolliert und übersprungen, statt die Bildauslieferung zu verhindern.

> Das Konzept stammt aus dem Addon [media_chain](https://github.com/FriendsOfREDAXO/media_chain) und wird hier auf dem bereits geladenen Bildobjekt ausgeführt.

## Backend-Seiten

**Media Manager › srcset & Sets**

1. **Übersicht** – alle registrierten Sets mit Ratio, Modus, Stufen, Standardbreite, Quelle und Beispiel-Typ.
2. **Sets & Builder** – Sets anlegen, bearbeiten, aktivieren/deaktivieren, löschen und deren Cache leeren. Dazu:
   - **Assistent**, der Breitenstufen aus Container-Breite, Spaltenzahl, Bildanteil, Retina und Breakpoints ableitet, auf 100 px rundet und zu dichte Stufen ausdünnt.
   - **Nutzung & Bestand** – welche Sets und Breiten Module und Templates tatsächlich anfordern und wie breit die Originalbilder im Medienpool sind, mit Hinweisen auf überflüssige oder fehlende Stufen.
   - **Vorschau & Test** – erzeugtes Markup, alle Varianten mit tatsächlicher Pixelbreite und Dateigröße, Descriptor-Check und eine Simulation der Browser-Auswahl.
3. **Demo & Prüfung** – dieselbe Prüfung für frei wählbare Layout-Parameter, inklusive Live-Anzeige, welche Variante der Browser gerade geladen hat.
4. **Einstellungen** – Schalter für die HTML-Platzhalterersetzung.
5. **Hilfe** – diese Datei.

## Welchen Weg wählen?

- **Bestandsprojekt mit eingerichteten Media-Manager-Typen:** beim Effekt `srcset` bleiben. Es besteht kein Migrationsdruck.
- **Neues Projekt oder viele ähnliche Bildformate:** Sets verwenden. Weniger DB-Typen, Breitenstufen entstehen bei Bedarf, `sizes` wird aus dem Layout berechnet und der Alt-Text kommt aus dem dafür vorgesehenen Feld.
- **Beides gleichzeitig** ist ausdrücklich vorgesehen: `hero__400` und `is_ratio_4_3__800` stören sich nicht.

## Zusammenspiel mit anderen Addons

- **focuspoint** – Ratio-Zuschnitte folgen dem im Medienpool gesetzten Fokuspunkt (`med_focuspoint`); ohne das Addon wird zentriert geschnitten.
- **media_negotiator** – liefert WebP/AVIF nach `Accept`-Header; der Cache-Pfad wird pro Format getrennt. Ohne das Addon werden JPG/PNG ausgeliefert.
- **MediaPlace / metainfo_lang_fields** – Quellen für den Alt-Text der Sets.
- SVG und GIF werden unverändert ausgeliefert.

## Cache und Fehlersuche

- Nach Änderungen an Sets oder Effekten: Media Manager › Cache löschen, oder auf *Sets & Builder* den Cache des betroffenen Sets leeren.
- Ein Typ wie `is_ratio_4_3__700` liefert die nächsthöhere Stufe (800); unbekannte Sets fallen auf das Original zurück.
- Sehr große Originale brauchen beim ersten Aufruf spürbar Rechenzeit; danach kommt alles aus dem Cache.
- Bricht die Erzeugung ab (leeres Bild), fehlt meist Speicher (`memory_limit`) oder das Original ist beschädigt.

## Sicherheit

Alle erzeugten Attributwerte werden HTML-escaped (`rex_escape()`), inklusive des `alt`-Fallbacks. Attributnamen werden gegen `/^[a-zA-Z_:][-a-zA-Z0-9_:.]*$/` validiert, was Attribut-Injection über `$attributes`-Keys verhindert. `getSrcSet()` liefert bewusst einen unescapten Rohwert für eigene Verwendungszwecke.

## Anforderungen

- REDAXO `^5.18.0`
- Addon `media_manager` `^2.5.6`
- PHP `>= 8.1` (getestet bis PHP 8.4)

## API-Dokumentation

Vollständige Referenz beider APIs: [API.md](API.md) · [English](API.en.md)

## Changelog

Siehe [CHANGELOG.md](CHANGELOG.md).

## Lizenz

MIT · [GitHub-Repository](https://github.com/FriendsOfREDAXO/media_srcset)
