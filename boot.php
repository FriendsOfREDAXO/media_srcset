<?php

if (rex::isBackend()) {
    rex_media_manager::addEffect('rex_effect_srcset');
}

rex_extension::register('OUTPUT_FILTER', ['rex_media_srcset', 'replaceSrcSets']);
rex_extension::register('MEDIA_MANAGER_FILTERSET', ['rex_media_srcset', 'managerFilterset']);
