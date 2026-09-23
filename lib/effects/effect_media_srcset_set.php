<?php

/**
 * Generischer Media-Manager-Effekt fuer virtuelle Bildtypen (is_<preset>__<width>).
 * Wird nicht direkt einem Media-Manager-Typ zugeordnet, sondern dynamisch ueber
 * den Extension Point MEDIA_MANAGER_FILTERSET aufgerufen (siehe SetFilterset).
 *
 * Reihenfolge ist entscheidend fuer korrekte srcset-Descriptoren:
 *  1. Vorverarbeitung ("chain"): Effekte fremder Media-Manager-Typen in voller
 *     Quellaufloesung (optional, z. B. Wasserzeichen, Graustufen)
 *  2. Ratio-Crop in voller Quellaufloesung (Focuspoint bzw. zentriert)
 *  3. Breitenbegrenztes Resize auf die Zielbreite (Hoehe folgt dem Ratio,
 *     nie vergroessern)
 * So ist die Ausgabebreite immer min(Zielbreite, croppbare Quellbreite) -
 * unabhaengig davon, ob die Quelle hoch- oder querformatig ist.
 */
class rex_effect_media_srcset_set extends rex_effect_abstract
{
    /** Schutz gegen zirkulaere Ketten (Typ A kettet B, B kettet wieder A) */
    private const MAX_CHAIN_DEPTH = 5;

    /** @var list<string> aktuell abgearbeitete Kettenglieder, ueber alle Instanzen hinweg */
    private static array $chainStack = [];

    public function execute()
    {
        $sourcePath = (string) $this->media->getSourcePath();
        $sourceFile = (string) $this->media->getMediaFilename();
        $mimeType = strtolower((string) rex_file::mimeType($sourcePath));
        $extension = strtolower((string) rex_file::extension($sourceFile !== '' ? $sourceFile : $sourcePath));

        // SVG immer unveraendert ausliefern (kein Rasterizing).
        if ($mimeType === 'image/svg+xml' || $extension === 'svg') {
            return;
        }

        if (!$this->isSupportedRasterImage($mimeType, $extension)) {
            return;
        }

        try {
            $this->media->asImage();
        } catch (Throwable) {
            return;
        }

        $ratio = trim((string) ($this->params['ratio'] ?? '16_9'));
        $mode = trim((string) ($this->params['mode'] ?? 'focuspoint'));
        $width = max(1, (int) ($this->params['width'] ?? 1200));
        $allowEnlarge = (string) ($this->params['allow_enlarge'] ?? 'not_enlarge');

        // Vorverarbeitung vor dem Zuschnitt, damit Wasserzeichen/Filter in voller
        // Quellaufloesung wirken und der Descriptor der Zielbreite gueltig bleibt.
        $this->applyChain((string) ($this->params['chain'] ?? ''));

        if ($mode === 'focuspoint' && $ratio !== 'original') {
            $this->cropToRatio($ratio);
        }

        // Breitenbegrenzt skalieren: nur width setzen, die Hoehe berechnet
        // rex_effect_resize proportional (style maximum).
        $resize = new rex_effect_resize();
        $resize->setMedia($this->media);
        $resize->setParams([
            'width' => $width,
            'height' => '',
            'style' => 'maximum',
            'allow_enlarge' => $allowEnlarge,
        ]);
        $resize->execute();
    }

    /**
     * Wendet die Effekte fremder Media-Manager-Typen auf das laufende Bild an
     * (Idee aus dem Addon media_chain, Konfiguration wie dort als kommagetrennte
     * Typliste). Anders als dort laeuft die Kette vollstaendig auf dem bereits
     * geladenen rex_managed_media: keine Zwischendateien im oeffentlichen
     * Medienordner, kein erneutes Encodieren je Kettenglied und damit weder
     * Qualitaetsverlust noch Aufraeumaufwand.
     *
     * Ausgelassen werden Typen, die es nicht gibt, sowie die Groessen-Effekte
     * resize/srcset/media_srcset_set - die Zielbreite bestimmt allein dieses Set,
     * sonst waere der srcset-Descriptor nicht mehr die tatsaechliche Dateibreite.
     */
    private function applyChain(string $chain): void
    {
        $types = array_values(array_filter(array_map('trim', explode(',', $chain)), static fn (string $t): bool => $t !== ''));
        if ($types === []) {
            return;
        }

        if (count(self::$chainStack) >= self::MAX_CHAIN_DEPTH) {
            return;
        }

        foreach ($types as $type) {
            // Selbstreferenz und Zyklen ueberspringen, statt die Anfrage in eine
            // Endlosschleife laufen zu lassen
            if (in_array($type, self::$chainStack, true)) {
                continue;
            }

            self::$chainStack[] = $type;
            try {
                $this->applyEffectsOfType($type);
            } catch (Throwable $e) {
                // Ein defektes Kettenglied darf die Bildauslieferung nicht verhindern;
                // der Fehler wird protokolliert, das Bild kommt ohne diesen Schritt
                rex_logger::logException($e);
            } finally {
                array_pop(self::$chainStack);
            }
        }
    }

    private function applyEffectsOfType(string $type): void
    {
        $manager = new rex_media_manager($this->media);
        $effects = $manager->effectsFromType($type);

        foreach ($effects as $effect) {
            $effectName = $effect['effect'];
            if ($effectName === '' || in_array($effectName, ['resize', 'srcset', 'media_srcset_set'], true)) {
                continue;
            }

            $class = 'rex_effect_' . $effectName;
            if (!class_exists($class) || !is_subclass_of($class, rex_effect_abstract::class)) {
                continue;
            }

            /** @var rex_effect_abstract $instance */
            $instance = new $class();
            $instance->setMedia($this->media);
            $instance->setParams($effect['params']);
            $instance->execute();
        }
    }

    private function cropToRatio(string $ratio): void
    {
        [$ratioW, $ratioH] = self::resolveRatio($ratio);

        if (class_exists('rex_effect_focuspoint_fit')) {
            // fr-Angaben: Crop in voller Quellaufloesung um den Fokuspunkt
            $focuspoint = new rex_effect_focuspoint_fit();
            $focuspoint->setMedia($this->media);
            $focuspoint->setParams([
                'width' => $ratioW . 'fr',
                'height' => $ratioH . 'fr',
                'zoom' => '100%',
                'meta' => 'med_focuspoint',
                'focus' => '50.0,50.0',
            ]);
            $focuspoint->execute();

            return;
        }

        // Fallback ohne focuspoint-Addon: zentrierter Crop auf das gewuenschte Ratio.
        $currentWidth = (int) $this->media->getWidth();
        $currentHeight = (int) $this->media->getHeight();
        if ($currentWidth < 1 || $currentHeight < 1) {
            return;
        }

        $targetWidth = $currentWidth;
        $targetHeight = (int) floor($targetWidth * $ratioH / $ratioW);

        if ($targetHeight > $currentHeight) {
            $targetHeight = $currentHeight;
            $targetWidth = (int) floor($targetHeight * $ratioW / $ratioH);
        }

        $targetWidth = max(1, min($targetWidth, $currentWidth));
        $targetHeight = max(1, min($targetHeight, $currentHeight));

        $crop = new rex_effect_crop();
        $crop->setMedia($this->media);
        $crop->setParams([
            'width' => $targetWidth,
            'height' => $targetHeight,
            'hpos' => 'center',
            'vpos' => 'middle',
            'offset_width' => 0,
            'offset_height' => 0,
        ]);
        $crop->execute();
    }

    public function getName()
    {
        return rex_i18n::msg('media_manager_effect_media_srcset_set');
    }

    /**
     * @return list<array{label: string, name: string, type: 'int'|'float'|'string'|'select'|'media', default?: mixed, notice?: string, prefix?: string, suffix?: string, attributes?: array<string, string>, options?: array<int, string>}>
     */
    public function getParams()
    {
        return [
            [
                'label' => rex_i18n::msg('media_srcset_effect_param_set'),
                'name' => 'preset',
                'type' => 'string',
                'default' => 'ratio_16_9',
            ],
            [
                'label' => rex_i18n::msg('media_srcset_effect_param_ratio'),
                'name' => 'ratio',
                'type' => 'string',
                'default' => '16_9',
            ],
            [
                'label' => rex_i18n::msg('media_srcset_effect_param_mode'),
                'name' => 'mode',
                'type' => 'select',
                'options' => ['focuspoint', 'resize'],
                'default' => 'focuspoint',
            ],
            [
                'label' => rex_i18n::msg('media_srcset_effect_param_target_width'),
                'name' => 'width',
                'type' => 'int',
                'default' => 1200,
            ],
            [
                'label' => rex_i18n::msg('media_srcset_effect_param_chain'),
                'name' => 'chain',
                'type' => 'string',
                'notice' => rex_i18n::msg('media_srcset_effect_param_chain_notice'),
            ],
            [
                'label' => rex_i18n::msg('media_srcset_effect_param_allow_enlarge'),
                'name' => 'allow_enlarge',
                'type' => 'select',
                'options' => ['enlarge', 'not_enlarge'],
                'default' => 'not_enlarge',
            ],
        ];
    }

    /**
     * "16_9" / "16:9" -> [16, 9]; "original" oder ungueltig -> [0, 0].
     *
     * @return array{int,int}
     */
    public static function resolveRatio(string $ratio): array
    {
        $normalized = str_replace(':', '_', trim($ratio));
        if (preg_match('/^(\d+)_+(\d+)$/', $normalized, $matches) !== 1) {
            return [0, 0];
        }

        return [max(1, (int) $matches[1]), max(1, (int) $matches[2])];
    }

    private function isSupportedRasterImage(string $mimeType, string $extension): bool
    {
        $supportedMimeTypes = [
            'image/jpeg',
            'image/jpg',
            'image/pjpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/avif',
            'image/vnd.wap.wbmp',
        ];
        if (in_array($mimeType, $supportedMimeTypes, true)) {
            return true;
        }

        $supportedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'wbmp'];
        return in_array($extension, $supportedExtensions, true);
    }
}
