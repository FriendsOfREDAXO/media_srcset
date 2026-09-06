# media_srcset

Addon, das dem REDAXO Media Manager einen neuen Effekt `srcset` hinzufügt (basierend auf dem `resize`-Effekt) und eine PHP-API bereitstellt, mit der sich `<img>`- und `<picture>`-Tags inklusive `srcset`/`sizes`-Attribut erzeugen lassen – ohne für jede benötigte Bildbreite einen eigenen Media-Manager-Typ anlegen zu müssen. Rewrite-URLs von yRewrite werden unterstützt.

## Inhalt

- [Installation](#installation)
- [Hintergrund und Funktionsweise](#hintergrund-und-funktionsweise)
- [Verwendung im Media Manager](#verwendung-im-media-manager)
- [Öffentliche API](#öffentliche-api)
  - [`getImgTag()`](#getimgtag)
  - [`getPictureTag()`](#getpicturetag)
  - [`getSrcSet()`](#getsrcset)
  - [`getTag()`](#gettag)
- [Beispiele](#beispiele)
  - [Einfaches Bild](#einfaches-bild)
  - [Eigene Attribute](#eigene-attribute)
  - [Art Direction mit `<picture>`](#art-direction-mit-picture)
  - [Layout-basiertes `sizes`-Attribut](#layout-basiertes-sizes-attribut)
  - [Manuelles `sizes`-Attribut](#manuelles-sizes-attribut)
  - [SVG-Dateien](#svg-dateien)
- [Automatischer HTML-Ersatz (OUTPUT_FILTER)](#automatischer-html-ersatz-output_filter)
- [srcset.js – Auflösung nach tatsächlicher Elementbreite](#srcsetjs--auflösung-nach-tatsächlicher-elementbreite)
- [Sicherheit](#sicherheit)
- [Anforderungen](#anforderungen)
- [Changelog](#changelog)
- [Credits](#credits)

## Installation

* Release herunterladen und entpacken.
* Ordner umbenennen in `media_srcset`.
* In den AddOns-Ordner legen: `/redaxo/src/addons`.
* Im Backend installieren und aktivieren (Abhängigkeit: `media_manager`).

## Hintergrund und Funktionsweise

### Erklärung der `srcset`-Attribute für optimale Bilddarstellung

Wenn du Bilder auf deiner Website einfügst und sicherstellen möchtest, dass sie sowohl auf Desktop- als auch auf Mobilgeräten optimal angezeigt werden, ohne tausende neue MediaManager-Typen anzulegen, kannst du mit diesem Addon automatisiert die `srcset`- und `sizes`-Attribute in HTML verwenden.

#### Beispiel für einen `srcset`-Eingabe-String im Addon:

```
470 470w, 940 470w 2x, 1410 470w 3x
```

Dieser String wird im MM-Typ im Feld des `srcset`-Effekts angegeben.

### Was bedeutet dieser `srcset`-String?

1. **470 470w**
   - **470**: Die Breite des Bildes in Pixeln (470px), die tatsächlich erzeugt wird.
   - **470w**: Diese Größe ist für Bildschirme mit normaler (1x) Auflösung gedacht. Das Bild wird im Layout in 470px Breite angezeigt.

2. **940 470w 2x**
   - **940**: Die Breite des erzeugten Bildes in Pixeln (940px), gedacht für Bildschirme mit doppelter (2x) Auflösung.
   - **470w**: Das Bild wird im Layout weiterhin 470px breit angezeigt, aber für hochauflösende (Retina) Displays verwendet.

3. **1410 470w 3x**
   - **1410**: Die Breite des erzeugten Bildes in Pixeln (1410px), gedacht für Bildschirme mit dreifacher (3x) Auflösung.
   - **470w**: Das Bild wird im Layout weiterhin 470px breit angezeigt, aber für sehr hochauflösende Displays verwendet.

### Welche Auswirkungen hat das?

1. **Desktop-Bildschirme:**
   - **Normale Displays (1x)**: Das Bild wird in seiner Basisgröße von 470px angezeigt.
   - **Retina Displays (2x)**: Der Browser verwendet das Bild mit 940px Breite, zeigt es aber auf dem Bildschirm in 470px Breite an – sorgt für eine schärfere Darstellung.
   - **Displays mit 3x-Auflösung**: Der Browser verwendet das Bild mit 1410px Breite, zeigt es aber weiterhin in 470px Breite an.

2. **Mobile Geräte:**
   - Die gleiche Logik wie auf Desktops wird angewendet. Der Browser wählt das am besten passende Bild basierend auf Bildschirmauflösung und -größe aus.

### Einfluss auf das `sizes`-Attribut

Das `sizes`-Attribut gibt an, wie groß das Bild im Layout bei verschiedenen Viewport-Breiten tatsächlich angezeigt wird. Beispiel:

```html
<img src="/path/to/default.jpg"
     srcset="/path/to/image-470.jpg 470w,
             /path/to/image-940.jpg 940w 2x,
             /path/to/image-1410.jpg 1410w 3x"
     sizes="(max-width: 600px) 100vw, 470px"
     alt="Beispielbild">
```

- **`(max-width: 600px) 100vw`**: Bei maximal 600px Viewport-Breite (z. B. Mobilgeräte) nimmt das Bild die volle Bildschirmbreite ein (100vw).
- **`470px`**: Auf größeren Bildschirmen wird das Bild in fester 470px-Breite angezeigt.

Der `srcset`-String gibt dem Browser verschiedene Bildkandidaten zur Auswahl; das `sizes`-Attribut sagt dem Browser, wie breit der jeweilige Slot im Layout tatsächlich ist, damit er daraus den passenden Kandidaten auswählen kann. Ohne ein sinnvolles `sizes`-Attribut wählt der Browser tendenziell zu große Bilder.

## Verwendung im Media Manager

Im Effekt-Feld `srcset` die gewünschten Breiten-Angaben eintragen – statt eines Dateinamens wird nur die gewünschte Bildbreite verwendet:

```
400 480w, 800 480w 2x, 700 768w
```

Das Profil selbst (z. B. `hero`) bleibt ein ganz normaler Media-Manager-Typ. Für jede im String angegebene Bildbreite (`400`, `700`, `800`) erzeugt das Addon zur Laufzeit ein virtuelles Unterprofil `hero__400`, `hero__700`, `hero__800`, das die restlichen Effekte des Basisprofils übernimmt und nur `width`/`height` überschreibt – dafür muss nichts zusätzlich im Backend angelegt werden.

## Öffentliche API

Alle Methoden befinden sich in der statischen Klasse `rex_media_srcset`.

### `getImgTag()`

```php
rex_media_srcset::getImgTag(
    string $fileName,
    string $mediaType,
    ?array $attributes = null,
    ?array $layout = null
): string
```

Erzeugt ein vollständiges `<img>`-Tag mit `src`, `srcset`, `width`, `height`, `alt` (Fallback: Medienpool-Titel) und `sizes`.

- `$attributes`: zusätzliche/überschreibende HTML-Attribute, z. B. `['class' => 'hero-image', 'loading' => 'lazy']`. Ein hier gesetztes `alt` oder `sizes` hat immer Vorrang vor der automatischen Ermittlung.
- `$layout`: optional, siehe [Layout-basiertes `sizes`-Attribut](#layout-basiertes-sizes-attribut).

Bei `.svg`-Dateien wird ausschließlich `src` (direkter Medienpool-Link) und `alt` gesetzt – kein `srcset`/`sizes`, siehe [SVG-Dateien](#svg-dateien).

### `getPictureTag()`

```php
rex_media_srcset::getPictureTag(
    string $fileName,
    string $mediaType,
    ?array $attributes = null,
    ?array $mediaQueries = null,
    ?array $layout = null
): string
```

Erzeugt ein `<picture>`-Element. `$mediaType` ist das Profil für den `<img>`-Fallback. `$mediaQueries` ist eine Zuordnung `CSS-Media-Query => Media-Manager-Typ` – für jeden Eintrag wird ein eigenes `<source media="…" srcset="…">` erzeugt. Da pro Media-Query ein eigenes Profil angegeben wird, lässt sich damit auch **Art Direction** abbilden (unterschiedliche Bildausschnitte/Seitenverhältnisse je Breakpoint, nicht nur unterschiedliche Auflösungen desselben Ausschnitts) – siehe [Beispiel](#art-direction-mit-picture).

### `getSrcSet()`

```php
rex_media_srcset::getSrcSet(string $fileName, string $mediaType): string
```

Liefert nur den rohen `srcset`-Wert (z. B. für eigene, abweichende Tag-Strukturen). Liefert einen leeren String bei `.svg`-Dateien oder wenn das Profil keinen `srcset`-Effekt konfiguriert hat. **Der Rückgabewert ist nicht HTML-escaped** – beim direkten Einbau in eigenes Markup selbst `rex_escape()` anwenden.

### `getTag()`

```php
rex_media_srcset::getTag(
    string $fileName,
    string $mediaType,
    ?array $attributes = null,
    int $tagType = rex_media_srcset::IMG,
    ?array $additionalSources = null,
    ?array $layout = null
): string
```

Die von `getImgTag()`/`getPictureTag()` intern genutzte Basismethode. `$tagType` ist `rex_media_srcset::IMG` oder `rex_media_srcset::PICTURE`; `$additionalSources` sind bereits fertige `<source>`-HTML-Fragmente, die vor dem generierten Fallback-`<source>` eingefügt werden. Direkter Aufruf nur nötig, wenn `getImgTag()`/`getPictureTag()` nicht ausreichen.

## Beispiele

### Einfaches Bild

```php
echo rex_media_srcset::getImgTag('teamfoto.jpg', 'hero');
```

```html
<img src="index.php?rex_media_type=hero&amp;rex_media_file=teamfoto.jpg"
     srcset="index.php?rex_media_type=hero__400&amp;rex_media_file=teamfoto.jpg 480w,
             index.php?rex_media_type=hero__700&amp;rex_media_file=teamfoto.jpg 768w,
             index.php?rex_media_type=hero__800&amp;rex_media_file=teamfoto.jpg 960w"
     width="500" height="333" alt=""
     sizes="(max-width: 480px) 480px, (max-width: 768px) 768px, (max-width: 960px) 960px, 500px"/>
```

### Eigene Attribute

```php
echo rex_media_srcset::getImgTag('teamfoto.jpg', 'hero', [
    'class'   => 'hero-image',
    'loading' => 'lazy',
    'alt'     => 'Das Team bei der Arbeit',
]);
```

`alt` und beliebige weitere Attribute werden übernommen; ein hier gesetztes `alt` überschreibt den automatischen Fallback auf den Medienpool-Titel.

### Art Direction mit `<picture>`

```php
echo rex_media_srcset::getPictureTag('teamfoto.jpg', 'hero_desktop', [
    'class' => 'hero-image',
], [
    '(max-width: 719px)' => 'hero_mobile_portrait',
]);
```

```html
<picture>
    <source srcset="…teamfoto.jpg 480w, …teamfoto.jpg 768w, …teamfoto.jpg 960w" media="(max-width: 719px)">
    <source srcset="…teamfoto.jpg 480w, …teamfoto.jpg 768w, …teamfoto.jpg 960w" type="image/jpeg">
    <img class="hero-image" src="…teamfoto.jpg" width="…" height="…" alt="…"/>
</picture>
```

`hero_mobile_portrait` kann dabei ein komplett anderes Seitenverhältnis/Crop (z. B. via Focuspoint- oder Crop-Effekt) konfiguriert haben als `hero_desktop` – so lässt sich derselbe Quellfile responsiv mit unterschiedlichen Bildausschnitten je Viewport ausgeben.

### Layout-basiertes `sizes`-Attribut

Standardmäßig wiederholt das automatisch erzeugte `sizes`-Attribut lediglich die im Profil konfigurierten Breakpoints. Für ein layoutgetreueres `sizes` kann stattdessen aus Container-Breite und Spaltenzahl gerechnet werden:

```php
echo rex_media_srcset::getImgTag('teamfoto.jpg', 'hero', null, [
    'containerWidth'  => 'uk-container',        // grobe Schätzung der Container-Maximalbreite
    'columns'         => 3,                     // Spalten ab Desktop-Breakpoint (≥1200px)
    'columnsTablet'   => 2,                     // Spalten zwischen 640px und 1200px
    'columnsMobile'   => 1,                     // Spalten unter 640px
    'mediaFraction'   => 1.0,                   // Anteil der Spaltenbreite, den das Bild einnimmt (0.05–1.0)
]);
```

Erzeugt z. B. `sizes="(min-width: 1200px) 400px, (min-width: 640px) 50vw, 100vw"`. Alle Schlüssel sind optional (Default: `containerWidth = 'uk-container'`, `columns = 3`, `columnsTablet = 2`, `columnsMobile = 1`, `mediaFraction = 1.0`). Ohne `$layout`-Parameter bleibt das bisherige Verhalten unverändert – der Parameter ist rein additiv und ändert nichts an bestehenden Aufrufen.

### Manuelles `sizes`-Attribut

Ein explizit gesetztes `sizes` in `$attributes` hat immer Vorrang, unabhängig davon ob `$layout` übergeben wird:

```php
echo rex_media_srcset::getImgTag('teamfoto.jpg', 'hero', ['sizes' => '100vw']);
```

### SVG-Dateien

SVGs werden automatisch erkannt und ohne Media-Manager-Routing direkt aus dem Medienpool ausgeliefert – kein `srcset`, kein `sizes`, kein Media-Manager-Cache:

```php
echo rex_media_srcset::getImgTag('logo.svg', 'hero');
// <img src="/media/logo.svg" alt=""/>
```

Das gilt unabhängig davon, welcher `$mediaType` übergeben wird.

## Automatischer HTML-Ersatz (OUTPUT_FILTER)

Alternativ zur programmatischen API kann `srcset="rex_media_type=ProfilName"` direkt im Template-HTML stehen; das Addon ersetzt es beim Rendern automatisch:

#### Eingabe:

```html
<img src="index.php?rex_media_type=ImgTypeName&rex_media_file=ImageFileName"
    srcset="rex_media_type=ImgTypeName" />
```

#### Generierte Ausgabe:

```html
<img src="index.php?rex_media_type=ImgTypeName&rex_media_file=ImageFileName"
    srcset="index.php?rex_media_type=ImgTypeName__400&rex_media_file=ImageFileName 480w
            index.php?rex_media_type=ImgTypeName__700&rex_media_file=ImageFileName 768w
            index.php?rex_media_type=ImgTypeName__800&rex_media_file=ImageFileName 960w
    ">
```

Ebenso für `<picture>`-Elemente mit mehreren `<source srcset="rex_media_type=…">`-Platzhaltern. Dieser Weg ist praktisch für Bestandscode/Redakteursinhalte, bietet aber weniger Kontrolle (kein `alt`-Fallback, kein `sizes`, keine SVG-Sonderbehandlung) als die programmatische API – für neuen Code wird `getImgTag()`/`getPictureTag()` empfohlen.

## srcset.js – Auflösung nach tatsächlicher Elementbreite

Das `srcset`-Attribut kann auch als `data-srcset`-Attribut eingebunden werden. Dann lädt der Browser zunächst das Standardbild (`src`-Attribut). Wird zusätzlich

```html
<script type="text/javascript" src="assets/addons/media_srcset/srcset.js"></script>
```

eingebunden, prüft ein Skript beim Laden der Seite sowie nach jedem Resize die tatsächliche Anzeigebreite jedes Elements und lädt bei Bedarf eine passendere Datei nach. So orientiert sich die Bildauswahl an der tatsächlich gerenderten Elementbreite statt nur am Viewport. Das Bild braucht dafür zwingend:

```css
img[data-srcset] {
    width: 100%;
    height: auto;
}
```

### Beispiel

Eingabe:

```html
<img width="500" src="index.php?rex_media_type=ImgTypeName&rex_media_file=ImageFileName"
    data-srcset="rex_media_type=ImgTypeName">
```

Ausgabe (Erstladung, Elementbreite ≈700px):

```html
<img src="index.php?rex_media_type=ImgTypeName__700&rex_media_file=ImageFileName"
    data-srcset="index.php?rex_media_type=ImgTypeName__400&rex_media_file=ImageFileName 480w
                 index.php?rex_media_type=ImgTypeName__700&rex_media_file=ImageFileName 768w
                 index.php?rex_media_type=ImgTypeName__800&rex_media_file=ImageFileName 960w
    ">
```

Bei kleinerer gerenderter Breite (z. B. 200px) wird stattdessen `ImgTypeName__400` geladen, bei sehr großer Breite (z. B. 1200px) `ImgTypeName__960` (das größte verfügbare Profil).

## Sicherheit

Alle über `getTag()`/`getImgTag()`/`getPictureTag()` erzeugten Attributwerte werden HTML-escaped (`rex_escape()`), inklusive des automatischen `alt`-Fallbacks auf den Medienpool-Titel. Attributnamen aus `$attributes` werden gegen ein festes Muster (`/^[a-zA-Z_:][-a-zA-Z0-9_:.]*$/`) validiert. `getSrcSet()` liefert bewusst einen **unescapten** Rohwert für eigene Verwendungszwecke – wird er direkt in HTML eingebaut, muss selbst escaped werden.

## Anforderungen

- REDAXO `^5.4.0`
- Addon `media_manager` `^2.5.6`
- PHP `>=7.3` (getestet bis PHP 8.4)

## Changelog

Siehe [CHANGELOG.md](CHANGELOG.md).

## Credits

* [GitHub-Repository](https://github.com/FriendsOfREDAXO/media_srcset)
