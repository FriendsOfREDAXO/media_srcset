<?php

namespace FriendsOfRedaxo\MediaSrcset;

use FriendsOfRedaxo\MediaSrcset\Config\MediaTypeRegistry;
use rex_config;
use rex_extension_point;

class MediaNegotiatorBridge
{
    public static function isEnabled(): bool
    {
        return class_exists('FriendsOfRedaxo\\MediaNegotiator\\Helper')
            && \rex_addon::get('media_negotiator')->isAvailable();
    }

    /**
     * @param rex_extension_point<object> $ep
     */
    public static function adjustCachePath(rex_extension_point $ep): void
    {
        if (!self::isEnabled()) {
            return;
        }

        $type = (string) $ep->getParam('type');
        $parsed = MediaTypeRegistry::parseVirtualType($type);

        if ($parsed === null) {
            return;
        }

        /** @var object $mediaManager */
        $mediaManager = $ep->getSubject();
        if (!method_exists($mediaManager, 'getCachePath') || !method_exists($mediaManager, 'setCachePath')) {
            return;
        }

        // String-Literal statt ::class: media_negotiator ist optional, die Klasse
        // muss zur Compile-Zeit nicht existieren (isEnabled() hat sie oben geprueft).
        $helperClass = 'FriendsOfRedaxo\\MediaNegotiator\\Helper';
        if (!class_exists($helperClass)) {
            return;
        }
        $possibleFormat = (string) $helperClass::getRequestOutputFormat();

        $cacheKey = $possibleFormat;
        if ($possibleFormat === 'webp') {
            $cacheKey .= '-q' . $helperClass::getWebpQuality();
        } elseif ($possibleFormat === 'avif') {
            $cacheKey .= '-q' . $helperClass::getAvifQuality();
        }

        $cacheKey .= '-im' . ((bool) rex_config::get('media_negotiator', 'force_imagick', false) ? '1' : '0');
        $cacheKey .= '-davif' . ((bool) rex_config::get('media_negotiator', 'disable_avif', false) ? '1' : '0');
        $cacheKey .= '-pref' . $helperClass::getPreferredFormat();

        /** @var string|null $currentCachePath */
        $currentCachePath = $mediaManager->getCachePath();
        if (is_string($currentCachePath) && !str_contains($currentCachePath, $cacheKey . '-')) {
            $mediaManager->setCachePath($currentCachePath . $cacheKey . '-');
        }
    }
}
