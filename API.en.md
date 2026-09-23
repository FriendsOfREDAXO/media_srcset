# API reference

All public classes and methods of `media_srcset`. Introduction and examples: [README.en.md](README.en.md).

## Contents

- [Approach 1: `rex_media_srcset`](#approach-1-rex_media_srcset)
- [Approach 2: sets](#approach-2-sets)
  - [`Media\ResponsiveImage`](#mediaresponsiveimage)
  - [`Media\AltText`](#mediaalttext)
  - [`Config\MediaTypeRegistry`](#configmediatyperegistry)
- [Effects](#effects)
- [Extension points](#extension-points)
- [Configuration](#configuration)
- [Backend-internal classes](#backend-internal-classes)

---

## Approach 1: `rex_media_srcset`

Static class, global (no namespace). Works with real media manager types that have the `srcset` effect configured.

### Constants

| Constant | Value | Meaning |
|---|---|---|
| `rex_media_srcset::IMG` | `1` | `$tagType` for `<img>` |
| `rex_media_srcset::PICTURE` | `2` | `$tagType` for `<picture>` |

### `getImgTag()`

```php
public static function getImgTag(
    string $fileName,
    string $mediaType,
    ?array $attributes = null,
    ?array $layout = null
): string
```

A complete `<img>` tag with `src`, `srcset`, `width`, `height`, `alt` and `sizes`.

- `$attributes` — additional/overriding HTML attributes. An `alt` or `sizes` set here always wins.
- `$layout` — optional; computes `sizes` from the layout instead of the breakpoints. Keys: `containerWidth`, `columns`, `columnsTablet`, `columnsMobile`, `mediaFraction`. All optional.

For `.svg` only `src` (direct media pool link) and `alt` are set.

Throws `InvalidArgumentException` if the file does not exist in the media pool.

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

`$mediaType` is the profile for the `<img>` fallback. `$mediaQueries` maps `CSS media query => media manager type`; each entry produces a `<source media="…" srcset="…">`. Because each media query names its own profile, this also covers art direction.

### `getSrcSet()`

```php
public static function getSrcSet(string $fileName, string $mediaType): string
```

Only the raw `srcset` value. Empty string for `.svg` or when the profile has no `srcset` effect. **Not HTML-escaped** — apply `rex_escape()` yourself when embedding it in your own markup.

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

The base method behind `getImgTag()`/`getPictureTag()`. `$additionalSources` are ready-made `<source>` HTML fragments; they are emitted **unescaped** and must be escaped by the caller.

### Other public methods

| Method | Purpose |
|---|---|
| `replaceSrcSets(rex_extension_point $ep): string` | OUTPUT_FILTER handler; replaces `srcset="rex_media_type=…"` in rendered HTML |
| `managerFilterset(rex_extension_point $ep)` | MEDIA_MANAGER_FILTERSET handler; resolves `profile__width` |
| `getSingleSet(string $string): ?array` | Parses a single srcset entry into `['image_width' => int, 'viewport_width' => int]` |
| `generateMediaImageUrl(string $type, string $filename): string` | Media manager URL, raw (not escaped) |

---

## Approach 2: sets

Namespace `FriendsOfRedaxo\MediaSrcset`.

## `Media\ResponsiveImage`

Fluent builder, one object per image. All `with*()` methods return `self`.

```php
use FriendsOfRedaxo\MediaSrcset\Media\ResponsiveImage;
```

### Creating

```php
public static function forFile(string $file): self
```

`$file` is the file name in the media pool, not a `rex_media` instance.

### Choosing sets / sources

| Method | Signature | Purpose |
|---|---|---|
| `withDesktopPreset` | `(string $preset): self` | Set for `src`/`srcset`. Required for any output. |
| `withMobilePreset` | `(string $preset): self` | Shorthand for one `<source>` below the mobile breakpoint. Only effective with `toPicture()`/`toPictureTag()`. |
| `withSource` | `(string $media, string $preset, array $options = []): self` | Any number of `<source>` elements for art direction. `$options`: `{widths?: list<int>, sizes?: string}`. Call order = order in the markup. |

### Widths / densities

| Method | Signature | Purpose |
|---|---|---|
| `withWidths` | `(array $widths): self` | Desired target widths, rounded to the set's steps. Invalid input is ignored (default `[400, 800, 1200, 1600]`). |
| `withDensities` | `(array $densities): self` | Replaces `sizes` with `1x`/`2x`/`3x`. The base is the smallest step from `withWidths()`. Values outside `1–4` are discarded. |
| `withCapToSource` | `(bool $cap): self` | Default `true`: steps above the reachable source width are replaced by the source width with a correct descriptor. |

### Alt text / priority

| Method | Signature | Purpose |
|---|---|---|
| `withAlt` | `(string $alt): self` | Sets the alt text explicitly. Empty string = decorative. |
| `asDecorative` | `(bool $decorative = true): self` | Forces `alt="" role="presentation"`. |
| `withPriority` | `(bool $priority = true): self` | LCP image: `loading="eager" fetchpriority="high"`. |

### `sizes` calculation

| Method | Signature | Purpose |
|---|---|---|
| `withContainerWidth` | `(string $containerWidth): self` | `uk-container`, `-xsmall`, `-small`, `-large`, `-xlarge` or `expand`. Pure estimates (640/900/1200/1400/1600/1920 px), framework-agnostic. |
| `withColumns` | `(int $desktop, int $tablet, int $mobile): self` | Column count per screen class. |
| `withMediaFraction` | `(float $fraction): self` | Share of the column taken by the image (0.05–1.0). |
| `withBreakpoints` | `(int $tablet, int $desktop): self` | Your layout's breakpoints (default 640/1200). |
| `withMobileBreakpoint` | `(int $breakpoint): self` | Boundary for `withMobilePreset()` (default 639). |
| `withSizes` | `(string $sizes): self` | Sets `sizes` yourself; overrides the calculation. |

Formula without `withSizes()`:

```
(min-width: <desktop>px) <container / columns × fraction>px,
(min-width: <tablet>px) <100 / tablet columns × fraction>vw,
<100 / mobile columns × fraction>vw
```

### Output

| Method | Returns |
|---|---|
| `toImage()` | `array{src, srcset, sizes, width, height, alt, decorative}` |
| `toPicture()` | `array{sources: list<array{media, srcset, sizes}>, img: …}` |
| `toImageTag(array $attributes = [])` | a finished `<img>`; passed attributes take precedence |
| `toPictureTag(array $imgAttributes = [], array $pictureAttributes = [])` | a finished `<picture>`; without sources just the `<img>` |

### Queries

| Method | Returns |
|---|---|
| `getDimensions(string $preset = '', int $width = 0)` | `array{width: int, height: int}` of the variant |
| `getEffectiveWidths(string $preset = '')` | `list<int>` of the actually reachable widths |
| `getSrcsetEntries(string $preset = '', ?array $widths = null)` | `list<array{int,int}>` — pairs of type width and descriptor width |
| `getSourceMaxWidth(string $preset = '')` | maximum output width the source allows in the set's ratio |

## `Media\AltText`

```php
public static function resolve(?rex_media $media, ?int $clangId = null): array
```

Returns `['alt' => string, 'decorative' => bool]`.

Order: MediaPlace's own alt field (JSON in `med_json_data`, text per language plus a `decorative` flag) → multilingual metainfo field `med_alt` (via `metainfo_lang_fields`, plus `med_alt_decorative`) → empty.

The media pool **title is deliberately not a fallback**: a title does not describe the image for screen readers. Without an alt text the image is emitted as decorative.

Results are cached per `filename|clangId` within the process.

## `Config\MediaTypeRegistry`

```php
use FriendsOfRedaxo\MediaSrcset\Config\MediaTypeRegistry;
```

| Method | Purpose |
|---|---|
| `registerPreset(string $name, array $config): void` | Registers a set. Missing keys are filled in; invalid sets (without `ratio`) are ignored. |
| `registerPresets(array $presets): void` | Several sets at once. |
| `getPresets(): array` | All registered sets, after the `MEDIA_SRCSET_PRESETS` extension point. |
| `buildVirtualType(string $preset, int $width): string` | `is_<preset>__<width>` |
| `parseVirtualType(string $mediaType): ?array` | `['preset' => string, 'width' => int]`, or `null` if it is not a set type |
| `normalizeWidth(array $presetConfig, int $requestedWidth): int` | Rounds up to the next step |

Set configuration:

| Key | Type | Default | Meaning |
|---|---|---|---|
| `ratio` | `string` | — (required) | `width_height` (e.g. `16_9`) or `original` |
| `mode` | `string` | `focuspoint` | `focuspoint` (enforce ratio) or `resize` (keep source ratio) |
| `widths` | `list<int>` | `[default_width]` | Allowed width steps |
| `default_width` | `int` | smallest step | Width used for `src` |
| `chain` | `string` | `''` | Comma-separated media manager types for pre-processing |

---

## Effects

### `rex_effect_srcset`

Extends `rex_effect_resize`. Assigned to a real media manager type. Parameters: `width` (default width) and `srcset` (configuration string `400 480w, 800 480w 2x, …`).

### `rex_effect_media_srcset_set`

The sets' effect. Not assigned manually but injected dynamically via `MEDIA_MANAGER_FILTERSET`.

Parameters: `preset`, `ratio`, `mode`, `width`, `chain`, `allow_enlarge`.

Sequence: pre-processing (`chain`) → ratio crop at full source resolution → width-bound resize. SVG and unsupported formats are passed through unchanged.

```php
public static function resolveRatio(string $ratio): array
```

`"16_9"` / `"16:9"` → `[16, 9]`; `original` or invalid → `[0, 0]`.

**Pre-processing (`chain`):** the effects of the named types are executed on the already loaded `rex_managed_media` — no intermediate files, no re-encoding. Skipped: non-existent types, the sizing effects `resize`/`srcset`/`media_srcset_set`, self-references and cycles (max. 5 levels). Errors are logged and skipped.

---

## Extension points

### `MEDIA_SRCSET_PRESETS`

```php
rex_extension::register('MEDIA_SRCSET_PRESETS', function (rex_extension_point $ep) {
    $presets = $ep->getSubject();
    $presets['my_set'] = ['ratio' => '3_2', 'mode' => 'focuspoint', 'widths' => [400, 800]];
    return $presets;
});
```

The subject is the array of all sets, keyed by set name. It runs on every `getPresets()`.

### Core extension points used

| Point | Purpose |
|---|---|
| `MEDIA_MANAGER_FILTERSET` | resolves `profile__width` (approach 1) and `is_<set>__<width>` (approach 2) |
| `MEDIA_MANAGER_INIT` | separates the cache path per output format when `media_negotiator` is active |
| `OUTPUT_FILTER` | HTML placeholder replacement; only registered when enabled |

---

## Configuration

| Key | Type | Meaning |
|---|---|---|
| `output_filter` | `bool` | HTML placeholder replacement active. Fresh install `false`, update `true`. |
| `presets` | `string` (JSON) | Builder sets from the backend |

```php
rex_addon::get('media_srcset')->getConfig('output_filter');
```

---

## Backend-internal classes

Not relevant for image output.

### `Config\PresetStore`

Manages the builder sets (JSON in `rex_config`).

| Method | Purpose |
|---|---|
| `all()` / `get(string $name)` | read builder sets |
| `save(string $name, array $input, string $originalName = '')` | validate and store, returns `list<string>` of errors |
| `delete()` / `setActive()` | delete / activate |
| `registerBuiltin()` / `registerActive()` | register at boot |
| `cacheInfo(string $preset)` / `clearCache(string $preset)` | count / delete a set's cache files |
| `normalizeChain(string $raw): string` | normalise pre-processing types |
| `isBuiltin(string $name): bool` | is it a code set (read-only)? |

Constants: `BUILTIN`, `RATIOS`, `MIN_WIDTH` (50), `MAX_WIDTH` (4000), `MAX_STEPS` (8).

### `Config\SetBuilder`

| Method | Purpose |
|---|---|
| `suggest(array $o)` | derive width steps from layout parameters |
| `scanUsage()` | scan modules and templates for used sets |
| `mediaStats()` | width distribution of raster images in the media pool |
| `upscaleShare(array $stats, int $width)` | share of images narrower than `$width` |

### `SetFilterset` and `MediaNegotiatorBridge`

The handlers for the extension points listed above.
