# API-Referenz

Alle öffentlichen Klassen und Methoden von `media_srcset`. Einführung und Beispiele: [README.md](README.md).

## Inhalt

- [Weg 1: `rex_media_srcset`](#weg-1-rex_media_srcset)
- [Weg 2: Sets](#weg-2-sets)
  - [`Media\ResponsiveImage`](#mediaresponsiveimage)
  - [`Media\AltText`](#mediaalttext)
  - [`Config\MediaTypeRegistry`](#configmediatyperegistry)
- [Effekte](#effekte)
- [Extension Points](#extension-points)
- [Konfiguration](#konfiguration)
- [Backend-interne Klassen](#backend-interne-klassen)

---

## Weg 1: `rex_media_srcset`

Statische Klasse, global (kein Namespace). Arbeitet mit echten Media-Manager-Typen, die den Effekt `srcset` konfiguriert haben.

### Konstanten

| Konstante | Wert | Bedeutung |
|---|---|---|
| `rex_media_srcset::IMG` | `1` | `$tagType` für `<img>` |
| `rex_media_srcset::PICTURE` | `2` | `$tagType` für `<picture>` |

### `getImgTag()`

```php
public static function getImgTag(
    string $fileName,
    string $mediaType,
    ?array $attributes = null,
    ?array $layout = null
): string
```

Vollständiges `<img>`-Tag mit `src`, `srcset`, `width`, `height`, `alt` und `sizes`.

- `$attributes` – zusätzliche/überschreibende HTML-Attribute. Ein hier gesetztes `alt` oder `sizes` hat immer Vorrang.
- `$layout` – optional; berechnet `sizes` aus dem Layout statt aus den Breakpoints. Schlüssel: `containerWidth`, `columns`, `columnsTablet`, `columnsMobile`, `mediaFraction`. Alle optional.

Bei `.svg` werden nur `src` (direkter Medienpool-Link) und `alt` gesetzt.

Wirft `InvalidArgumentException`, wenn die Datei nicht im Medienpool existiert.

### `getPictureTag()`

```php
public static function getPictureTag(
    string $fileName,
    string $mediaType,
    ?array $attributes = null,
    ?array $mediaQueries = null,
    ?array $layout = null
): string
```

`$mediaType` ist das Profil für den `<img>`-Fallback. `$mediaQueries` ist eine Zuordnung `CSS-Media-Query => Media-Manager-Typ`; je Eintrag entsteht ein `<source media="…" srcset="…">`. Da pro Media Query ein eigenes Profil angegeben wird, lässt sich damit auch Art Direction abbilden.

### `getSrcSet()`

```php
public static function getSrcSet(string $fileName, string $mediaType): string
```

Nur der rohe `srcset`-Wert. Leerer String bei `.svg` oder wenn das Profil keinen `srcset`-Effekt hat. **Nicht HTML-escaped** – beim Einbau in eigenes Markup selbst `rex_escape()` anwenden.

### `getTag()`

```php
public static function getTag(
    string $fileName,
    string $mediaType,
    ?array $attributes = null,
    int $tagType = rex_media_srcset::IMG,
    ?array $additionalSources = null,
    ?array $layout = null
): string
```

Basismethode von `getImgTag()`/`getPictureTag()`. `$additionalSources` sind fertige `<source>`-HTML-Fragmente, die **unescaped** ausgegeben werden und vom Aufrufer escaped sein müssen.

### Weitere öffentliche Methoden

| Methode | Zweck |
|---|---|
| `replaceSrcSets(rex_extension_point $ep): string` | OUTPUT_FILTER-Handler; ersetzt `srcset="rex_media_type=…"` im gerenderten HTML |
| `managerFilterset(rex_extension_point $ep)` | MEDIA_MANAGER_FILTERSET-Handler; löst `profil__breite` auf |
| `getSingleSet(string $string): ?array` | Parst einen einzelnen srcset-Eintrag zu `['image_width' => int, 'viewport_width' => int]` |
| `generateMediaImageUrl(string $type, string $filename): string` | Media-Manager-URL, roh (nicht escaped) |

---

## Weg 2: Sets

Namespace `FriendsOfRedaxo\MediaSrcset`.

## `Media\ResponsiveImage`

Fluent Builder, ein Objekt pro Bild. Alle `with*()`-Methoden geben `self` zurück.

```php
use FriendsOfRedaxo\MediaSrcset\Media\ResponsiveImage;
```

### Erzeugen

```php
public static function forFile(string $file): self
```

`$file` ist der Dateiname im Medienpool, keine `rex_media`-Instanz.

### Set / Quellen wählen

| Methode | Signatur | Zweck |
|---|---|---|
| `withDesktopPreset` | `(string $preset): self` | Set für `src`/`srcset`. Pflicht für jede Ausgabe. |
| `withMobilePreset` | `(string $preset): self` | Kurzform für eine `<source>` unterhalb des Mobile-Breakpoints. Nur bei `toPicture()`/`toPictureTag()` wirksam. |
| `withSource` | `(string $media, string $preset, array $options = []): self` | Beliebig viele `<source>` für Art Direction. `$options`: `{widths?: list<int>, sizes?: string}`. Aufrufreihenfolge = Reihenfolge im Markup. |

### Breiten / Dichten

| Methode | Signatur | Zweck |
|---|---|---|
| `withWidths` | `(array $widths): self` | Gewünschte Zielbreiten, werden auf die Stufen des Sets gerundet. Ungültige Eingabe wird ignoriert (Default `[400, 800, 1200, 1600]`). |
| `withDensities` | `(array $densities): self` | Ersetzt `sizes` durch `1x`/`2x`/`3x`. Basis ist die kleinste Stufe aus `withWidths()`. Werte außerhalb `1–4` werden verworfen. |
| `withCapToSource` | `(bool $cap): self` | Default `true`: Stufen oberhalb der erreichbaren Quellbreite werden durch die Quellbreite mit korrektem Descriptor ersetzt. |

### Alt-Text / Priorität

| Methode | Signatur | Zweck |
|---|---|---|
| `withAlt` | `(string $alt): self` | Alt-Text explizit setzen. Leerer String = dekorativ. |
| `asDecorative` | `(bool $decorative = true): self` | Erzwingt `alt="" role="presentation"`. |
| `withPriority` | `(bool $priority = true): self` | LCP-Bild: `loading="eager" fetchpriority="high"`. |

### `sizes`-Berechnung

| Methode | Signatur | Zweck |
|---|---|---|
| `withContainerWidth` | `(string $containerWidth): self` | `uk-container`, `-xsmall`, `-small`, `-large`, `-xlarge` oder `expand`. Reine Schätzwerte (640/900/1200/1400/1600/1920 px), framework-unabhängig. |
| `withColumns` | `(int $desktop, int $tablet, int $mobile): self` | Spaltenzahl je Bildschirmklasse. |
| `withMediaFraction` | `(float $fraction): self` | Anteil des Bildes an der Spalte (0.05–1.0). |
| `withBreakpoints` | `(int $tablet, int $desktop): self` | Breakpoints des eigenen Layouts (Default 640/1200). |
| `withMobileBreakpoint` | `(int $breakpoint): self` | Grenze für `withMobilePreset()` (Default 639). |
| `withSizes` | `(string $sizes): self` | `sizes` komplett selbst setzen; überschreibt die Berechnung. |

Formel ohne `withSizes()`:

```
(min-width: <desktop>px) <Container / Spalten × Anteil>px,
(min-width: <tablet>px) <100 / Tablet-Spalten × Anteil>vw,
<100 / Mobil-Spalten × Anteil>vw
```

### Ausgabe

| Methode | Rückgabe |
|---|---|
| `toImage()` | `array{src, srcset, sizes, width, height, alt, decorative}` |
| `toPicture()` | `array{sources: list<array{media, srcset, sizes}>, img: …}` |
| `toImageTag(array $attributes = [])` | fertiges `<img>`; übergebene Attribute haben Vorrang |
| `toPictureTag(array $imgAttributes = [], array $pictureAttributes = [])` | fertiges `<picture>`; ohne Quellen nur das `<img>` |

### Abfragen

| Methode | Rückgabe |
|---|---|
| `getDimensions(string $preset = '', int $width = 0)` | `array{width: int, height: int}` der Variante |
| `getEffectiveWidths(string $preset = '')` | `list<int>` der tatsächlich erreichbaren Breiten |
| `getSrcsetEntries(string $preset = '', ?array $widths = null)` | `list<array{int,int}>` – Paare aus Typ-Breite und Descriptor-Breite |
| `getSourceMaxWidth(string $preset = '')` | maximale Ausgabebreite, die die Quelle im Set-Ratio hergibt |

## `Media\AltText`

```php
public static function resolve(?rex_media $media, ?int $clangId = null): array
```

Liefert `['alt' => string, 'decorative' => bool]`.

Reihenfolge: MediaPlace-eigenes Alt-Feld (JSON in `med_json_data`, Text je Sprache plus `decorative`-Flag) → mehrsprachiges Metainfo-Feld `med_alt` (über `metainfo_lang_fields`, plus `med_alt_decorative`) → leer.

Der Medienpool-**Titel ist bewusst kein Fallback**: Ein Titel beschreibt das Bild nicht für Screenreader. Ohne Alt-Text wird das Bild dekorativ ausgegeben.

Ergebnisse werden je `Dateiname|clangId` im Prozess zwischengespeichert.

## `Config\MediaTypeRegistry`

```php
use FriendsOfRedaxo\MediaSrcset\Config\MediaTypeRegistry;
```

| Methode | Zweck |
|---|---|
| `registerPreset(string $name, array $config): void` | Set registrieren. Fehlende Schlüssel werden aufgefüllt, ungültige Sets (ohne `ratio`) ignoriert. |
| `registerPresets(array $presets): void` | Mehrere Sets auf einmal. |
| `getPresets(): array` | Alle registrierten Sets, nach dem Extension Point `MEDIA_SRCSET_PRESETS`. |
| `buildVirtualType(string $preset, int $width): string` | `is_<preset>__<width>` |
| `parseVirtualType(string $mediaType): ?array` | `['preset' => string, 'width' => int]` oder `null`, wenn es kein Set-Typ ist |
| `normalizeWidth(array $presetConfig, int $requestedWidth): int` | Rundet auf die nächste Stufe auf |

Set-Konfiguration:

| Schlüssel | Typ | Default | Bedeutung |
|---|---|---|---|
| `ratio` | `string` | – (Pflicht) | `Breite_Höhe` (z. B. `16_9`) oder `original` |
| `mode` | `string` | `focuspoint` | `focuspoint` (Ratio erzwingen) oder `resize` (Quellverhältnis behalten) |
| `widths` | `list<int>` | `[default_width]` | Erlaubte Breitenstufen |
| `default_width` | `int` | kleinste Stufe | Breite für `src` |
| `chain` | `string` | `''` | Kommagetrennte Media-Manager-Typen für die Vorverarbeitung |

---

## Effekte

### `rex_effect_srcset`

Erbt von `rex_effect_resize`. Wird einem echten Media-Manager-Typ zugeordnet. Parameter: `width` (Standardbreite) und `srcset` (Konfigurationsstring `400 480w, 800 480w 2x, …`).

### `rex_effect_media_srcset_set`

Der Effekt der Sets. Wird nicht manuell zugeordnet, sondern dynamisch über `MEDIA_MANAGER_FILTERSET` eingesetzt.

Parameter: `preset`, `ratio`, `mode`, `width`, `chain`, `allow_enlarge`.

Ablauf: Vorverarbeitung (`chain`) → Ratio-Zuschnitt in voller Quellauflösung → breitenbegrenztes Resize. SVG und nicht unterstützte Formate werden unverändert durchgereicht.

```php
public static function resolveRatio(string $ratio): array
```

`"16_9"` / `"16:9"` → `[16, 9]`; `original` oder ungültig → `[0, 0]`.

**Vorverarbeitung (`chain`):** Die Effekte der angegebenen Typen werden auf dem bereits geladenen `rex_managed_media` ausgeführt – keine Zwischendateien, kein erneutes Encodieren. Übersprungen werden: nicht existierende Typen, die Größen-Effekte `resize`/`srcset`/`media_srcset_set`, Selbstreferenzen und Zyklen (max. 5 Ebenen). Fehler werden protokolliert und übersprungen.

---

## Extension Points

### `MEDIA_SRCSET_PRESETS`

```php
rex_extension::register('MEDIA_SRCSET_PRESETS', function (rex_extension_point $ep) {
    $presets = $ep->getSubject();
    $presets['mein_set'] = ['ratio' => '3_2', 'mode' => 'focuspoint', 'widths' => [400, 800]];
    return $presets;
});
```

Subject ist das Array aller Sets, Schlüssel = Set-Name. Wird bei jedem `getPresets()` durchlaufen.

### Genutzte Kern-Extension-Points

| Point | Zweck |
|---|---|
| `MEDIA_MANAGER_FILTERSET` | löst `profil__breite` (Weg 1) und `is_<set>__<breite>` (Weg 2) auf |
| `MEDIA_MANAGER_INIT` | trennt den Cache-Pfad je Ausgabeformat, wenn `media_negotiator` aktiv ist |
| `OUTPUT_FILTER` | HTML-Platzhalterersetzung; nur registriert, wenn eingeschaltet |

---

## Konfiguration

| Schlüssel | Typ | Bedeutung |
|---|---|---|
| `output_filter` | `bool` | HTML-Platzhalterersetzung aktiv. Neuinstallation `false`, Update `true`. |
| `presets` | `string` (JSON) | Builder-Sets aus dem Backend |

```php
rex_addon::get('media_srcset')->getConfig('output_filter');
```

---

## Backend-interne Klassen

Für die Bildausgabe nicht relevant.

### `Config\PresetStore`

Verwaltet die Builder-Sets (JSON in `rex_config`).

| Methode | Zweck |
|---|---|
| `all()` / `get(string $name)` | Builder-Sets lesen |
| `save(string $name, array $input, string $originalName = '')` | validieren und speichern, liefert `list<string>` Fehler |
| `delete()` / `setActive()` | löschen / aktivieren |
| `registerBuiltin()` / `registerActive()` | beim Boot registrieren |
| `cacheInfo(string $preset)` / `clearCache(string $preset)` | Cache-Dateien eines Sets zählen / löschen |
| `normalizeChain(string $raw): string` | Vorverarbeitungs-Typen normalisieren |
| `isBuiltin(string $name): bool` | Code-Set (schreibgeschützt)? |

Konstanten: `BUILTIN`, `RATIOS`, `MIN_WIDTH` (50), `MAX_WIDTH` (4000), `MAX_STEPS` (8).

### `Config\SetBuilder`

| Methode | Zweck |
|---|---|
| `suggest(array $o)` | Breitenstufen aus Layout-Angaben ableiten |
| `scanUsage()` | Module und Templates nach verwendeten Sets durchsuchen |
| `mediaStats()` | Breitenverteilung der Rasterbilder im Medienpool |
| `upscaleShare(array $stats, int $width)` | Anteil der Bilder schmaler als `$width` |

### `SetFilterset` und `MediaNegotiatorBridge`

Die Handler der oben genannten Extension Points.
