<?php

namespace FriendsOfRedaxo\MediaSrcset;

use FriendsOfRedaxo\MediaSrcset\Config\MediaTypeRegistry;
use rex_extension_point;

class SetFilterset
{
    /**
     * @param rex_extension_point<list<array{effect: string, params: array<string, mixed>}>> $ep
     * @return list<array{effect: string, params: array<string, mixed>}>
     */
    public static function apply(rex_extension_point $ep): array
    {
        $subject = $ep->getSubject();
        $mediaType = (string) $ep->getParam('rex_media_type');

        $parsed = MediaTypeRegistry::parseVirtualType($mediaType);
        if ($parsed === null) {
            return $subject;
        }

        $presets = MediaTypeRegistry::getPresets();
        $preset = $parsed['preset'];
        if (!isset($presets[$preset])) {
            return $subject;
        }

        $config = $presets[$preset];
        $width = MediaTypeRegistry::normalizeWidth($config, $parsed['width']);

        return [[
            'effect' => 'media_srcset_set',
            'params' => [
                'preset' => $preset,
                'ratio' => $config['ratio'],
                'mode' => $config['mode'],
                'width' => $width,
                'chain' => $config['chain'],
                'allow_enlarge' => 'not_enlarge',
            ],
        ], ...self::getOptionalEffects()];
    }

    /**
     * @return list<array{effect: string, params: array<string, mixed>}>
     */
    private static function getOptionalEffects(): array
    {
        if (!MediaNegotiatorBridge::isEnabled() || !class_exists('rex_effect_negotiator')) {
            return [];
        }

        return [[
            'effect' => 'negotiator',
            'params' => [],
        ]];
    }
}
