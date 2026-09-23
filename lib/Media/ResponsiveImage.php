<?php

namespace FriendsOfRedaxo\MediaSrcset\Media;

use FriendsOfRedaxo\MediaSrcset\Config\MediaTypeRegistry;

/**
 * Baut src/srcset/sizes bzw. fertiges <img>/<picture>-Markup aus virtuellen
 * Media-Manager-Typen (Preset + Zielbreite), ohne dass fuer jede Breite ein
 * eigener Media-Manager-Typ angelegt werden muss.
 *
 * Ausgabe-Regeln:
 * - width/height werden immer gesetzt (Massen der src-Variante), damit der
 *   Browser den Platz vor dem Laden reserviert.
 * - alt: uebergeben > MediaPlace/Metainfo (AltText) > leer; als dekorativ
 *   markierte Bilder erhalten alt="" und role="presentation".
 * - loading="lazy" decoding="async"; withPriority() liefert eager +
 *   fetchpriority="high" fuer das LCP-Bild.
 */
final class ResponsiveImage
{
    private string $file;
    private string $desktopPreset = '';
    private string $mobilePreset = '';

    /** @var list<int> */
    private array $widths = [400, 800, 1200, 1600];

    /** @var list<array{media: string, preset: string, widths: list<int>|null, sizes: string}> */
    private array $sources = [];

    /** @var list<float> */
    private array $densities = [];

    private ?string $alt = null;
    private bool $decorative = false;
    private bool $priority = false;

    private string $containerWidth = 'uk-container';
    private int $columns = 3;
    private int $columnsTablet = 2;
    private int $columnsMobile = 1;
    private float $mediaFraction = 1.0;
    private int $mobileBreakpoint = 639;
    private int $tabletBreakpoint = 640;
    private int $desktopBreakpoint = 1200;
    private string $customSizes = '';
    private bool $capToSource = true;

    private function __construct(string $file)
    {
        $this->file = $file;
    }

    public static function forFile(string $file): self
    {
        return new self($file);
    }

    public function withDesktopPreset(string $preset): self
    {
        $this->desktopPreset = $preset;
        return $this;
    }

    /** Kurzform fuer eine <source> unterhalb des Mobile-Breakpoints (siehe withSource fuer freie Media Queries) */
    public function withMobilePreset(string $preset): self
    {
        $this->mobilePreset = $preset;
        return $this;
    }

    /**
     * Zusaetzliche <source> mit eigener Media Query (Art Direction). Reihenfolge
     * der Aufrufe = Reihenfolge im Markup; der Browser nimmt die erste passende.
     *
     * @param array{widths?: mixed, sizes?: mixed} $options eigene Breitenstufen bzw. sizes fuer diese Quelle
     */
    public function withSource(string $media, string $preset, array $options = []): self
    {
        $media = trim($media);
        $preset = trim($preset);
        if ($media === '' || $preset === '') {
            return $this;
        }
        $widths = null;
        if (isset($options['widths']) && is_array($options['widths'])) {
            /** @var list<int|string> $rawWidths */
            $rawWidths = $options['widths'];
            $widths = self::normalizeWidths($rawWidths);
            if ($widths === []) {
                $widths = null;
            }
        }
        $this->sources[] = ['media' => $media, 'preset' => $preset, 'widths' => $widths, 'sizes' => trim((string) ($options['sizes'] ?? ''))];
        return $this;
    }

    /**
     * @param list<int> $widths
     */
    public function withWidths(array $widths): self
    {
        $normalized = self::normalizeWidths($widths);
        if ($normalized !== []) {
            $this->widths = $normalized;
        }
        return $this;
    }

    /**
     * Feste Darstellungsbreite mit Dichte-Descriptoren (1x, 2x, 3x) statt
     * sizes - fuer Logos, Avatare, Icons. Basisbreite ist die kleinste Stufe
     * aus withWidths() (bzw. die Standardbreite des Presets).
     *
     * @param list<float|int> $densities
     */
    public function withDensities(array $densities): self
    {
        $list = [];
        foreach ($densities as $d) {
            $d = (float) $d;
            if ($d >= 1 && $d <= 4) {
                $list[] = $d;
            }
        }
        $list = array_values(array_unique($list));
        sort($list);
        $this->densities = $list;
        return $this;
    }

    /** Alt-Text setzen (Vorrang vor Medienpool). Leerer String = dekorativ. */
    public function withAlt(string $alt): self
    {
        $this->alt = $alt;
        return $this;
    }

    /** Bild ausdruecklich dekorativ ausgeben: alt="" role="presentation" */
    public function asDecorative(bool $decorative = true): self
    {
        $this->decorative = $decorative;
        return $this;
    }

    /** LCP-Bild: loading="eager" fetchpriority="high" */
    public function withPriority(bool $priority = true): self
    {
        $this->priority = $priority;
        return $this;
    }

    public function withContainerWidth(string $containerWidth): self
    {
        $this->containerWidth = $containerWidth;
        return $this;
    }

    public function withColumns(int $desktop, int $tablet, int $mobile): self
    {
        $this->columns = max(1, $desktop);
        $this->columnsTablet = max(1, $tablet);
        $this->columnsMobile = max(1, $mobile);

        return $this;
    }

    public function withMediaFraction(float $fraction): self
    {
        $this->mediaFraction = max(0.05, min(1.0, $fraction));
        return $this;
    }

    public function withMobileBreakpoint(int $breakpoint): self
    {
        $this->mobileBreakpoint = max(1, $breakpoint);
        return $this;
    }

    /**
     * Breakpoints fuer das automatische sizes-Attribut: ab $tablet greifen
     * die Tablet-Spalten, ab $desktop die Desktop-Spalten (Standard 640/1200).
     * Layouts mit eigenen Media Queries (z.B. 960px) geben hier ihre Werte an.
     */
    public function withBreakpoints(int $tablet, int $desktop): self
    {
        $this->tabletBreakpoint = max(1, $tablet);
        $this->desktopBreakpoint = max($this->tabletBreakpoint + 1, $desktop);
        return $this;
    }

    /**
     * Eigenes sizes-Attribut (ueberschreibt die automatische Berechnung),
     * z.B. "(min-width: 960px) 50vw, 100vw".
     */
    public function withSizes(string $sizes): self
    {
        $this->customSizes = trim($sizes);
        return $this;
    }

    /**
     * srcset auf die tatsaechlich erreichbare Quellbreite begrenzen
     * (Standard an). Ohne Begrenzung wuerden Descriptoren groesser als die
     * gelieferte Datei sein und der Browser die falsche Variante waehlen.
     */
    public function withCapToSource(bool $cap): self
    {
        $this->capToSource = $cap;
        return $this;
    }

    /**
     * @return array{src: string, srcset: string, sizes: string, width: int, height: int, alt: string, decorative: bool}
     */
    public function toImage(): array
    {
        $empty = ['src' => '', 'srcset' => '', 'sizes' => '', 'width' => 0, 'height' => 0, 'alt' => '', 'decorative' => false];
        if ($this->file === '') {
            return $empty;
        }

        $src = $this->resolveSrc($this->desktopPreset);
        $srcset = $this->densities !== [] ? $this->buildDensitySrcset($this->desktopPreset) : $this->buildSrcset($this->desktopPreset);
        $dims = $this->getDimensions($this->desktopPreset, $this->srcWidth($this->desktopPreset));
        $alt = $this->resolveAlt();

        return [
            'src' => $src,
            'srcset' => $srcset,
            'sizes' => $this->densities !== [] ? '' : $this->buildSizes(),
            'width' => $dims['width'],
            'height' => $dims['height'],
            'alt' => $alt['alt'],
            'decorative' => $alt['decorative'],
        ];
    }

    /**
     * @return array{sources: list<array{media: string, srcset: string, sizes: string}>, img: array{src: string, srcset: string, sizes: string, width: int, height: int, alt: string, decorative: bool}}
     */
    public function toPicture(): array
    {
        $img = $this->toImage();
        $sources = [];
        if ($this->file === '') {
            return ['sources' => [], 'img' => $img];
        }

        foreach ($this->sources as $source) {
            $srcset = $this->buildSrcset($source['preset'], $source['widths']);
            if ($srcset === '') {
                continue;
            }
            $sources[] = [
                'media' => $source['media'],
                'srcset' => $srcset,
                'sizes' => $source['sizes'] !== '' ? $source['sizes'] : $this->buildSizes(),
            ];
        }

        if ($this->mobilePreset !== '' && $this->mobilePreset !== $this->desktopPreset) {
            $mobileSrcset = $this->buildSrcset($this->mobilePreset);
            if ($mobileSrcset !== '') {
                $sources[] = [
                    'media' => '(max-width: ' . $this->mobileBreakpoint . 'px)',
                    'srcset' => $mobileSrcset,
                    'sizes' => $this->buildMobileSizes(),
                ];
            }
        }

        return ['sources' => $sources, 'img' => $img];
    }

    /**
     * @param array<string, scalar|null> $attributes uebergebene Attribute haben Vorrang (alt, width, height, loading, ...)
     */
    public function toImageTag(array $attributes = []): string
    {
        $img = $this->toImage();
        if ($img['src'] === '') {
            return '';
        }

        $attrs = ['src' => $img['src']];
        if ($img['srcset'] !== '') {
            $attrs['srcset'] = $img['srcset'];
        }
        if ($img['sizes'] !== '') {
            $attrs['sizes'] = $img['sizes'];
        }
        if ($img['width'] > 0 && $img['height'] > 0) {
            $attrs['width'] = $img['width'];
            $attrs['height'] = $img['height'];
        }
        $attrs['alt'] = $img['alt'];
        if ($img['decorative']) {
            $attrs['role'] = 'presentation';
        }
        $attrs['loading'] = $this->priority ? 'eager' : 'lazy';
        $attrs['decoding'] = 'async';
        if ($this->priority) {
            $attrs['fetchpriority'] = 'high';
        }

        foreach ($attributes as $key => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            $attrs[$key] = $value;
        }
        // Uebergebenes alt hebt die Dekorativ-Rolle auf; leeres alt ohne Medienpool-Text bleibt dekorativ
        if (array_key_exists('alt', $attributes) && trim((string) $attributes['alt']) !== '' && !$this->decorative) {
            unset($attrs['role']);
        }

        return self::renderTag('img', $attrs, true);
    }

    /**
     * @param array<string, scalar|null> $imgAttributes
     * @param array<string, scalar|null> $pictureAttributes
     */
    public function toPictureTag(array $imgAttributes = [], array $pictureAttributes = []): string
    {
        $picture = $this->toPicture();
        $imgTag = $this->toImageTag($imgAttributes);

        if ($imgTag === '') {
            return '';
        }

        if ($picture['sources'] === []) {
            return $imgTag;
        }

        $html = self::renderOpenTag('picture', $pictureAttributes);
        foreach ($picture['sources'] as $source) {
            $html .= self::renderTag('source', [
                'media' => $source['media'],
                'srcset' => $source['srcset'],
                'sizes' => $source['sizes'],
            ], true);
        }

        $html .= $imgTag;
        $html .= '</picture>';

        return $html;
    }

    /**
     * Pixelmasse der Variante mit der Breite $width (Descriptor-Breite):
     * Ratio-Presets rechnerisch, "original"/resize aus den Quellmassen.
     *
     * @return array{width: int, height: int}
     */
    public function getDimensions(string $preset = '', int $width = 0): array
    {
        $preset = $preset !== '' ? $preset : $this->desktopPreset;
        if ($width <= 0) {
            $width = $this->srcWidth($preset);
        }
        if ($width <= 0) {
            return ['width' => 0, 'height' => 0];
        }
        $config = MediaTypeRegistry::getPresets()[$preset] ?? null;
        $ratio = (string) ($config['ratio'] ?? 'original');
        $mode = (string) ($config['mode'] ?? 'focuspoint');
        if ($mode === 'focuspoint' && $ratio !== 'original' && class_exists('rex_effect_media_srcset_set')) {
            [$rw, $rh] = \rex_effect_media_srcset_set::resolveRatio($ratio);
            if ($rw > 0 && $rh > 0) {
                return ['width' => $width, 'height' => max(1, (int) round($width * $rh / $rw))];
            }
        }
        $media = \rex_media::get($this->file);
        $sw = $media ? (int) $media->getWidth() : 0;
        $sh = $media ? (int) $media->getHeight() : 0;
        if ($sw > 0 && $sh > 0) {
            return ['width' => $width, 'height' => max(1, (int) round($width * $sh / $sw))];
        }
        return ['width' => $width, 'height' => 0];
    }

    /**
     * Breiten, die fuer dieses Bild und Preset tatsaechlich erreichbar sind.
     *
     * @return list<int>
     */
    public function getEffectiveWidths(string $preset = ''): array
    {
        return array_map(static fn (array $e): int => $e[1], $this->getSrcsetEntries($preset));
    }

    /**
     * srcset-Eintraege als [Typ-Breite, Descriptor-Breite].
     *
     * Typ-Breite: auf die Preset-Breiten gerundet (der Media Manager liefert
     * nur diese Stufen). Descriptor-Breite: tatsaechliche Pixelbreite der
     * gelieferten Datei - bei der groessten Stufe ggf. die Quellbreite, weil
     * nicht vergroessert wird.
     *
     * @param list<int>|null $widths eigene Stufen (Standard: withWidths)
     * @return list<array{int,int}>
     */
    public function getSrcsetEntries(string $preset = '', ?array $widths = null): array
    {
        $preset = $preset !== '' ? $preset : $this->desktopPreset;
        $config = MediaTypeRegistry::getPresets()[$preset] ?? [];
        $widths = $widths !== null && $widths !== [] ? $widths : $this->widths;

        $snapped = [];
        foreach ($widths as $w) {
            $snapped[] = $config !== [] ? MediaTypeRegistry::normalizeWidth($config, (int) $w) : (int) $w;
        }
        $snapped = array_values(array_unique($snapped));
        sort($snapped, SORT_NUMERIC);

        $maxSource = ($this->capToSource && $this->file !== '') ? $this->getSourceMaxWidth($preset) : 0;

        $entries = [];
        foreach ($snapped as $w) {
            if ($maxSource > 0 && $w >= $maxSource) {
                break;
            }
            $entries[] = [$w, $w];
        }
        if ($maxSource > 0 && count($entries) < count($snapped)) {
            $typeWidth = $config !== [] ? MediaTypeRegistry::normalizeWidth($config, $maxSource) : $maxSource;
            $entries[] = [$typeWidth, $maxSource];
        }
        if ($entries === []) {
            $w = $snapped[0] ?? 1200;
            $entries[] = [$w, $w];
        }

        return $entries;
    }

    /**
     * Maximale Ausgabebreite, die die Quelle im Preset-Ratio hergibt.
     */
    public function getSourceMaxWidth(string $preset = ''): int
    {
        $media = \rex_media::get($this->file);
        if (!$media) {
            return 0;
        }
        $sw = (int) $media->getWidth();
        $sh = (int) $media->getHeight();
        if ($sw < 1 || $sh < 1) {
            return 0;
        }

        $preset = $preset !== '' ? $preset : $this->desktopPreset;
        $config = MediaTypeRegistry::getPresets()[$preset] ?? null;
        $ratio = (string) ($config['ratio'] ?? 'original');
        $mode = (string) ($config['mode'] ?? 'focuspoint');
        if ($mode !== 'focuspoint' || $ratio === 'original' || !class_exists('rex_effect_media_srcset_set')) {
            return $sw;
        }

        [$rw, $rh] = \rex_effect_media_srcset_set::resolveRatio($ratio);
        if ($rw < 1 || $rh < 1) {
            return $sw;
        }

        return (int) min($sw, floor($sh * $rw / $rh));
    }

    /** @return array{alt: string, decorative: bool} */
    private function resolveAlt(): array
    {
        if ($this->decorative) {
            return ['alt' => '', 'decorative' => true];
        }
        if ($this->alt !== null) {
            return ['alt' => trim($this->alt), 'decorative' => trim($this->alt) === ''];
        }
        $resolved = AltText::resolve(\rex_media::get($this->file), \rex_clang::getCurrentId());
        return ['alt' => $resolved['alt'], 'decorative' => $resolved['decorative'] || $resolved['alt'] === ''];
    }

    /** Descriptor-Breite der src-Variante (bevorzugt 1200, sonst die groesste Stufe; bei Dichten die Basisbreite) */
    private function srcWidth(string $preset): int
    {
        $entries = $this->densities !== [] ? $this->getDensityEntries($preset) : $this->getSrcsetEntries($preset);
        if ($entries === []) {
            return 0;
        }
        if ($this->densities !== []) {
            return (int) $entries[0][1];
        }
        foreach ($entries as $entry) {
            if ($entry[0] === 1200) {
                return (int) $entry[1];
            }
        }
        return (int) end($entries)[1];
    }

    private function resolveSrc(string $preset): string
    {
        if ($this->file === '') {
            return '';
        }

        if (!\rex_addon::get('media_manager')->isAvailable() || $preset === '') {
            return \rex_url::media($this->file);
        }

        $entries = $this->densities !== [] ? $this->getDensityEntries($preset) : $this->getSrcsetEntries($preset);
        if ($entries === []) {
            return \rex_url::media($this->file);
        }
        $lastEntry = $entries[count($entries) - 1];
        $typeWidth = $this->densities !== [] ? (int) $entries[0][0] : (int) $lastEntry[0];
        if ($this->densities === []) {
            foreach ($entries as $entry) {
                if ($entry[0] === 1200) {
                    $typeWidth = 1200;
                    break;
                }
            }
        }

        return \rex_media_manager::getUrl(MediaTypeRegistry::buildVirtualType($preset, $typeWidth), $this->file);
    }

    /** @param list<int>|null $widths */
    private function buildSrcset(string $preset, ?array $widths = null): string
    {
        if ($this->file === '' || $preset === '' || !\rex_addon::get('media_manager')->isAvailable()) {
            return '';
        }

        $srcset = [];
        foreach ($this->getSrcsetEntries($preset, $widths) as [$typeWidth, $descriptorWidth]) {
            $srcset[] = \rex_media_manager::getUrl(MediaTypeRegistry::buildVirtualType($preset, $typeWidth), $this->file) . ' ' . $descriptorWidth . 'w';
        }

        return implode(', ', $srcset);
    }

    /**
     * Eintraege fuer Dichte-Descriptoren als [Typ-Breite, Descriptor-Breite, Dichte].
     * @return list<array{int,int,float}>
     */
    private function getDensityEntries(string $preset): array
    {
        $config = MediaTypeRegistry::getPresets()[$preset] ?? [];
        $base = (int) ($this->widths[0] ?? ($config['default_width'] ?? 400));
        $maxSource = ($this->capToSource && $this->file !== '') ? $this->getSourceMaxWidth($preset) : 0;
        $entries = [];
        $seen = [];
        foreach ($this->densities !== [] ? $this->densities : [1.0] as $density) {
            $target = (int) round($base * $density);
            $typeWidth = $config !== [] ? MediaTypeRegistry::normalizeWidth($config, $target) : $target;
            $descriptor = $maxSource > 0 ? min($maxSource, $typeWidth) : $typeWidth;
            if (isset($seen[$typeWidth])) {
                continue;
            }
            $seen[$typeWidth] = true;
            $entries[] = [$typeWidth, $descriptor, $density];
            if ($maxSource > 0 && $typeWidth >= $maxSource) {
                break;
            }
        }
        return $entries;
    }

    private function buildDensitySrcset(string $preset): string
    {
        if ($this->file === '' || $preset === '' || !\rex_addon::get('media_manager')->isAvailable()) {
            return '';
        }
        $entries = $this->getDensityEntries($preset);
        if (count($entries) < 2) {
            return '';
        }
        $base = (int) $entries[0][1];
        $srcset = [];
        foreach ($entries as [$typeWidth, $descriptor]) {
            // tatsaechliche Dichte aus gelieferter Breite (bei Kappung an der Quelle kleiner als gewuenscht)
            $density = round($descriptor / max(1, $base), 2);
            $srcset[] = \rex_media_manager::getUrl(MediaTypeRegistry::buildVirtualType($preset, $typeWidth), $this->file) . ' ' . rtrim(rtrim(number_format($density, 2, '.', ''), '0'), '.') . 'x';
        }
        return implode(', ', $srcset);
    }

    private function buildSizes(): string
    {
        if ($this->customSizes !== '') {
            return $this->customSizes;
        }

        $containerMaxPx = $this->estimateContainerMaxPx($this->containerWidth);

        $desktopPx = (int) max(220, round(($containerMaxPx / max(1, $this->columns)) * $this->mediaFraction));
        $tabletVw = (int) max(20, min(100, floor((100 / max(1, $this->columnsTablet)) * $this->mediaFraction)));
        $mobileVw = (int) max(30, min(100, floor((100 / max(1, $this->columnsMobile)) * $this->mediaFraction)));

        return sprintf(
            '(min-width: %dpx) %dpx, (min-width: %dpx) %dvw, %dvw',
            $this->desktopBreakpoint,
            $desktopPx,
            $this->tabletBreakpoint,
            $tabletVw,
            $mobileVw
        );
    }

    private function buildMobileSizes(): string
    {
        $mobileVw = (int) max(30, min(100, floor((100 / max(1, $this->columnsMobile)) * $this->mediaFraction)));

        return $mobileVw . 'vw';
    }

    private function estimateContainerMaxPx(string $container): int
    {
        if (str_contains($container, 'xsmall')) {
            return 640;
        }
        if (str_contains($container, 'small')) {
            return 900;
        }
        if (str_contains($container, 'xlarge')) {
            return 1600;
        }
        if (str_contains($container, 'large')) {
            return 1400;
        }
        if (str_contains($container, 'expand') || $container === '') {
            return 1920;
        }

        return 1200;
    }

    /**
     * @param list<int|string> $widths
     * @return list<int>
     */
    private static function normalizeWidths(array $widths): array
    {
        $normalized = [];
        foreach ($widths as $width) {
            $w = (int) $width;
            if ($w > 0) {
                $normalized[] = $w;
            }
        }
        $normalized = array_values(array_unique($normalized));
        sort($normalized, SORT_NUMERIC);
        return $normalized;
    }

    /**
     * @param array<string, scalar|null> $attributes
     */
    private static function renderOpenTag(string $tag, array $attributes = []): string
    {
        return '<' . $tag . self::renderAttributes($attributes) . '>';
    }

    /**
     * @param array<string, scalar|null> $attributes
     */
    private static function renderTag(string $tag, array $attributes = [], bool $selfClosing = false): string
    {
        $html = '<' . $tag . self::renderAttributes($attributes);

        if ($selfClosing) {
            return $html . '>';
        }

        return $html . '></' . $tag . '>';
    }

    /**
     * @param array<string, scalar|null> $attributes
     */
    private static function renderAttributes(array $attributes): string
    {
        $parts = [];

        foreach ($attributes as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }

            if ($value === true) {
                $parts[] = $name;
                continue;
            }

            $parts[] = $name . '="' . htmlspecialchars((string) $value, ENT_QUOTES) . '"';
        }

        return $parts === [] ? '' : ' ' . implode(' ', $parts);
    }
}
