# media_srcset

Responsive image output for REDAXO: `srcset`, `sizes` and `<picture>`, without creating a media manager type for every image width.

The addon offers **two approaches** that do not exclude each other and can run side by side in the same project:

| | **`srcset` effect** (classic) | **Sets** |
|---|---|---|
| Configuration lives in | one real media manager type each | a set (code array or backend form) |
| Crop/ratio per use case | one media manager type per crop | one set per crop, no DB type needed |
| Width steps | virtual sub-types `hero__400` | virtual types `is_<set>__<width>` |
| PHP API | `rex_media_srcset::getImgTag()` | `ResponsiveImage::forFile()` |
| Alt text | media pool title as fallback | dedicated alt field, title deliberately **not** used |

**In short:** the classic approach automates the width steps below existing media manager types. Sets replace the types themselves with a configuration layer and need only a single technical type for the whole installation.

Existing projects need to change nothing: the classic approach is preserved unchanged, including `rex_media_srcset` and the HTML placeholder replacement.

Backend: **Media Manager › srcset & Sets** with *Overview*, *Sets & builder*, *Demo & check*, *Settings* and *Help*.

## Installation

- Download the release, unpack it, rename the folder to `media_srcset` and place it in `/redaxo/src/addons`.
- Install and activate it in the backend (requires `media_manager`).

Installation creates the media manager type `media_srcset_set`. It is purely technical and is **never used directly** — all `is_*` requests go through it.

---

# Approach 1: the `srcset` effect on a media manager type

A media manager type (e.g. `hero`) gets the **Image: SRCSET** effect with a configuration string:

```
400 480w, 800 480w 2x, 700 768w
```

Format per entry: `<image width in px> <viewport width>w [<pixel density>x]`.

- **400 480w** — produces a 400 px wide image intended for a 480 px slot.
- **800 480w 2x** — an 800 px file for the same slot on retina displays.
- **700 768w** — a 700 px file for a 768 px slot.

For each image width a virtual sub-profile `hero__400`, `hero__700`, `hero__800` is derived at request time. It inherits all other effects of the base profile and only overrides `width`/`height`. Nothing extra has to be created in the backend.

A requested width without an exact match is rounded up to the next configured width.

## Public API (`rex_media_srcset`)

```php
rex_media_srcset::getImgTag(string $fileName, string $mediaType, ?array $attributes = null, ?array $layout = null): string
rex_media_srcset::getPictureTag(string $fileName, string $mediaType, ?array $attributes = null, ?array $mediaQueries = null, ?array $layout = null): string
rex_media_srcset::getSrcSet(string $fileName, string $mediaType): string
rex_media_srcset::getTag(string $fileName, string $mediaType, ?array $attributes = null, int $tagType = rex_media_srcset::IMG, ?array $additionalSources = null, ?array $layout = null): string
```

Simple image:

```php
echo rex_media_srcset::getImgTag('team.jpg', 'hero');
```

With custom attributes (`alt` and `sizes` always take precedence over the automatic values):

```php
echo rex_media_srcset::getImgTag('team.jpg', 'hero', [
    'class'   => 'hero-image',
    'loading' => 'lazy',
    'alt'     => 'The team at work',
]);
```

Art direction with `<picture>` — one media manager type per media query, each allowed a different crop:

```php
echo rex_media_srcset::getPictureTag('team.jpg', 'hero_desktop', ['class' => 'hero-image'], [
    '(max-width: 719px)' => 'hero_mobile_portrait',
]);
```

Layout-based `sizes` instead of merely repeating the breakpoints:

```php
echo rex_media_srcset::getImgTag('team.jpg', 'hero', null, [
    'containerWidth' => 'uk-container',  // estimate of the container max width
    'columns'        => 3,               // columns from 1200 px up
    'columnsTablet'  => 2,               // columns between 640 and 1200 px
    'columnsMobile'  => 1,               // columns below 640 px
    'mediaFraction'  => 1.0,             // share of the column (0.05–1.0)
]);
// sizes="(min-width: 1200px) 400px, (min-width: 640px) 50vw, 100vw"
```

`.svg` files are detected and served straight from the media pool — no media manager, no `srcset`/`sizes`. SVGs scale losslessly in the browser anyway.

`getSrcSet()` deliberately returns an **unescaped** raw value; apply `rex_escape()` yourself when embedding it in your own markup.

## Automatic HTML replacement (OUTPUT_FILTER)

Alternatively `srcset="rex_media_type=ProfileName"` can sit directly in the template; the addon replaces the placeholder while rendering:

```html
<img src="index.php?rex_media_type=hero&rex_media_file=image.jpg" srcset="rex_media_type=hero" />
```

This also works for `<picture>` with several `<source srcset="rex_media_type=…">`.

> **Legacy.** This path scans **every** page output with a regular expression and offers less control than the PHP API (no `alt` fallback, no `sizes`, no SVG special case). For new code use `getImgTag()` / `getPictureTag()` or sets.

It can be switched off under **Media Manager › srcset & Sets › Settings**. Default:

- **Fresh installation:** off — new projects use the PHP API, so the regex pass would be pointless load.
- **Update of an existing installation:** on — templates there may use the placeholder, which would otherwise silently stop working.

A choice once made is never overwritten by later updates.

## srcset.js — resolution by actual element width

If the attribute is emitted as `data-srcset` and

```html
<script src="assets/addons/media_srcset/srcset.js"></script>
```

is included, a script checks each element's actual rendered width on load and after every resize, and loads a better matching file when needed. Selection then follows the rendered element width rather than just the viewport. Required:

```css
img[data-srcset] { width: 100%; height: auto; }
```

---

# Approach 2: sets

A **set** bundles aspect ratio, crop mode and the allowed width steps into one configuration unit — a PHP array in code, or an entry in the backend builder. An image is requested as `is_<set>__<width>`, e.g. `/media/is_ratio_4_3__800/image.jpg`.

Needing a new image shape for a single module therefore means: register a set (one line of code or a form) — not: create, configure and maintain a media manager type.

## How it works

1. **Sets** define ratio, mode and width steps, e.g. `ratio_4_3` with `400, 800, 1200, 1600, 2000`.
2. An image is requested as `is_<set>__<width>`. `MEDIA_MANAGER_FILTERSET` injects the `media_srcset_set` effect on the fly; only the base type `media_srcset_set` exists in the database.
3. The effect optionally applies the **pre-processing chain**, **then** crops to the aspect ratio (focus point, otherwise centred) and **then** scales width-bound. It never enlarges.
4. `ResponsiveImage` builds `src`, `srcset` and `sizes` from that.

Requested widths are **rounded up** to the next step: `is_ratio_4_3__900` delivers the 1200 step. This keeps the number of cache variants bounded.

## Defining sets

Two sources, both landing in the same registry:

| Source | Where | Who |
|---|---|---|
| **Code** | the addon's `boot.php` or a project addon via `MediaTypeRegistry::registerPreset()` or the extension point `MEDIA_SRCSET_PRESETS` | developers |
| **Builder** | backend › *Sets & builder*, stored in the addon configuration | editors and developers |

Built-in sets:

| Set | Ratio | Mode | Widths | Default |
|---|---|---|---|---|
| `ratio_16_9` | 16:9 | focuspoint | 400, 800, 1200, 1600, 2000 | 1200 |
| `ratio_21_9` | 21:9 | focuspoint | 400, 800, 1200, 1600, 2000 | 1200 |
| `ratio_4_3` | 4:3 | focuspoint | 400, 800, 1200, 1600, 2000 | 1200 |
| `ratio_1_1` | 1:1 | focuspoint | 400, 800, 1200, 1600 | 1200 |
| `ratio_original` | source | resize | 400 … 2400 | 1600 |

Code sets are read-only in the backend; builder sets must not reuse their names.

```php
use FriendsOfRedaxo\MediaSrcset\Config\MediaTypeRegistry;

MediaTypeRegistry::registerPreset('teaser_3_2', [
    'ratio' => '3_2',            // width_height, or 'original'
    'mode' => 'focuspoint',      // focuspoint | resize
    'widths' => [400, 800, 1200, 1600],
    'default_width' => 1200,
    'chain' => '',               // optional, see pre-processing
]);
```

## Usage in code

A single fixed width:

```php
echo '<img src="' . rex_media_manager::getUrl('is_ratio_16_9__1200', $file) . '" alt="…">';
```

Responsive output:

```php
use FriendsOfRedaxo\MediaSrcset\Media\ResponsiveImage;

echo ResponsiveImage::forFile($file)
    ->withDesktopPreset('ratio_4_3')      // set for srcset
    ->withMobilePreset('ratio_1_1')       // optional: different ratio below the mobile breakpoint
    ->withWidths([400, 800, 1200, 1600])  // desired steps (rounded to the set's steps)
    ->withContainerWidth('uk-container')  // uk-container(-xsmall|-small|-large|-xlarge) or 'expand'
    ->withColumns(3, 2, 1)                // columns desktop / tablet / mobile
    ->withMediaFraction(0.5)              // share of the column taken by the image
    ->withBreakpoints(960, 1200)          // your layout's breakpoints (default 640/1200)
    ->toImageTag(['alt' => $alt, 'class' => 'uk-width-1-1']);
```

Further output: `toImage()`, `toPicture()`, `toPictureTag()`, `getSrcsetEntries()`, `getEffectiveWidths()`, `getSourceMaxWidth()`, `getDimensions()`, `withCapToSource(false)`, `withSizes()`.

### Attributes of the `<img>`

`toImageTag()` sets automatically:

- **`width` and `height`** of the `src` variant, so the browser reserves the space before loading. Your own values take precedence.
- **`alt`** by the rule: passed in (`['alt' => …]` or `withAlt()`) > MediaPlace alt field (including language variant) > classic `med_alt` > empty. The media pool **title is not an alt text** and is never used. Without an alt text, or when marked decorative, `alt="" role="presentation"` is emitted.
- **`loading="lazy" decoding="async"`**; `withPriority()` yields `loading="eager" fetchpriority="high"` for the LCP image.

The alt rule is available to your own code as well: `AltText::resolve($media, $clangId)`.

### Fixed sizes with density descriptors

For logos, avatars or icons with a fixed display width, `withDensities()` replaces the `sizes` attribute with `1x/2x/3x`:

```php
echo ResponsiveImage::forFile($logo)
    ->withDesktopPreset('logo_1_1')
    ->withWidths([200])
    ->withDensities([1, 2, 3])
    ->toImageTag(['alt' => 'Company logo']);
```

If the source is reached first, the descriptor drops accordingly (e.g. `1.5x`) so it matches the delivered file.

## Art direction

1. **Crop per image (focus point):** every ratio crop is placed around the focus point set in the media pool — no code, per image, by the editorial team.
2. **Different aspect ratio per screen width:** `withSource()` adds any number of `<source>` elements with their own media query (call order = order in the markup).

```php
echo ResponsiveImage::forFile($file)
    ->withDesktopPreset('ratio_21_9')
    ->withSource('(max-width: 639px)', 'ratio_1_1', ['widths' => [400, 800], 'sizes' => '100vw'])
    ->withSource('(max-width: 1199px)', 'ratio_4_3')
    ->toPictureTag(['loading' => 'lazy']);
```

Both crops follow the same focus point. A completely different image per breakpoint is not supported; use two media fields for that.

## Descriptor guarantee

A `srcset` descriptor (`800w`) must match the file's pixel width, otherwise the browser picks wrongly. `ResponsiveImage` ensures this by:

- rounding the desired widths to the set's steps (only those files exist);
- capping at the source: steps above the reachable width are dropped, and the source width is appended as the largest variant with a correct descriptor;
- the order pre-processing → crop → scale, so portrait sources also reach the full target width.

---

## Pre-processing: reusing effects of other types

A set can reuse the effects of **existing media manager types** as building blocks — for example a watermark or a colour filter that is already maintained as a type:

```php
MediaTypeRegistry::registerPreset('teaser_bw', [
    'ratio' => '4_3',
    'mode' => 'focuspoint',
    'widths' => [400, 800, 1200],
    'chain' => 'watermark,make_greyscale',   // comma-separated media manager types
]);
```

The builder offers the *Pre-processing* field for this.

The chain runs **before** the crop, i.e. at full source resolution, and entirely in memory — no intermediate files and no re-encoding per step.

Rules:

- **Sizing effects are skipped** (`resize`, `srcset`, `media_srcset_set`). The target width is determined by the set alone, otherwise the `srcset` descriptor would no longer be the actual file width.
- Types that do not exist are skipped.
- Self-references and cycles are detected; nesting is limited to five levels.
- An error in one chain link is logged and skipped rather than blocking image delivery.

> The concept comes from the [media_chain](https://github.com/FriendsOfREDAXO/media_chain) addon and is applied here to the already loaded image object.

## Backend pages

**Media Manager › srcset & Sets**

1. **Overview** — all registered sets with ratio, mode, steps, default width, source and example type.
2. **Sets & builder** — create, edit, activate/deactivate and delete sets and clear their cache. Plus:
   - an **assistant** deriving width steps from container width, column counts, image share, retina and breakpoints, rounding to 100 px and thinning out steps that are too close together;
   - **usage & inventory** — which sets and widths modules and templates actually request, and how wide the original images in the media pool are, with hints about superfluous or missing steps;
   - **preview & test** — generated markup, all variants with actual pixel width and file size, a descriptor check and a simulation of the browser's choice.
3. **Demo & check** — the same check for freely chosen layout parameters, including a live display of which variant the browser just loaded.
4. **Settings** — the switch for the HTML placeholder replacement.
5. **Help** — this file.

## Which approach to choose?

- **Existing project with configured media manager types:** stay with the `srcset` effect. There is no pressure to migrate.
- **New project, or many similar image formats:** use sets. Fewer DB types, width steps appear on demand, `sizes` is computed from the layout and the alt text comes from the field meant for it.
- **Both at once** is explicitly supported: `hero__400` and `is_ratio_4_3__800` do not interfere.

## Interaction with other addons

- **focuspoint** — ratio crops follow the focus point set in the media pool (`med_focuspoint`); without the addon the crop is centred.
- **media_negotiator** — serves WebP/AVIF per the `Accept` header; the cache path is separated per format. Without it, JPG/PNG are served.
- **MediaPlace / metainfo_lang_fields** — sources for the sets' alt text.
- SVG and GIF are served unchanged.

## Cache and troubleshooting

- After changing sets or effects: Media Manager › clear cache, or clear the affected set's cache on *Sets & builder*.
- A type like `is_ratio_4_3__700` delivers the next step up (800); unknown sets fall back to the original.
- Very large originals take noticeable time on first request; afterwards everything comes from the cache.
- If generation aborts (blank image), memory (`memory_limit`) is usually missing or the original is damaged.

## Security

All generated attribute values are HTML-escaped (`rex_escape()`), including the `alt` fallback. Attribute names are validated against `/^[a-zA-Z_:][-a-zA-Z0-9_:.]*$/`, which prevents attribute injection through `$attributes` keys. `getSrcSet()` deliberately returns an unescaped raw value for your own use.

## Requirements

- REDAXO `^5.18.0`
- Addon `media_manager` `^2.5.6`
- PHP `>= 8.1` (tested up to PHP 8.4)

## API documentation

Full reference of both APIs: [API.en.md](API.en.md) · [Deutsch](API.md)

## Licence

MIT · [GitHub repository](https://github.com/FriendsOfREDAXO/media_srcset)
