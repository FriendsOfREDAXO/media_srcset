<?php

/** @var rex_addon $this */

/**
 * Legt den zentralen Media-Manager-Basistyp fuer die is_*-Ableitungen an.
 * Die tatsaechliche Effekt-Konfiguration pro Set/Breite laeuft dynamisch ueber
 * MEDIA_MANAGER_FILTERSET (siehe boot.php), nicht ueber diesen Typ direkt.
 *
 * Der klassische Weg (Effekt "srcset" auf einem eigenen Typ) braucht nichts
 * davon und funktioniert unabhaengig hiervon weiter.
 */

/*
 * Standard fuer die HTML-Platzhalterersetzung (OUTPUT_FILTER):
 *
 * - Neuinstallation: aus. Neue Projekte nutzen die PHP-API; der Regex-Lauf
 *   ueber jede Seitenausgabe waere nur unnoetige Last.
 * - Update einer bestehenden Installation: an. Dort koennen Templates den
 *   Platzhalter srcset="rex_media_type=..." verwenden, der sonst still
 *   aufhoeren wuerde zu funktionieren.
 *
 * Eine bereits getroffene Entscheidung wird nie ueberschrieben.
 */
if (null === $this->getConfig('output_filter')) {
    // Ein Update erkennt man daran, dass bereits ein Media-Manager-Typ den
    // klassischen srcset-Effekt verwendet: dann existiert das Addon in diesem
    // Projekt schon und Templates koennen den Platzhalter nutzen.
    $existing = rex_sql::factory();
    $existing->setQuery(
        'SELECT id FROM ' . rex::getTable('media_manager_type_effect') . ' WHERE effect = :effect LIMIT 1',
        ['effect' => 'srcset'],
    );
    $this->setConfig('output_filter', $existing->getRows() > 0);
}

if (rex_addon::get('media_manager')->isAvailable()) {
    rex_media_manager::addEffect(rex_effect_media_srcset_set::class);

    $sql = rex_sql::factory();
    $sql->setQuery('SELECT id FROM ' . rex::getTable('media_manager_type') . ' WHERE name = :name', ['name' => 'media_srcset_set']);

    if (!$sql->getRows()) {
        $sql->setTable(rex::getTable('media_manager_type'));
        $sql->setValue('name', 'media_srcset_set');
        $sql->setValue('description', 'media_srcset: zentraler Medientyp fuer is_*-Ableitungen (nicht direkt verwenden)');
        $sql->addGlobalCreateFields();
        $sql->insert();
        $typeId = $sql->getLastId();

        $sql->setTable(rex::getTable('media_manager_type_effect'));
        $sql->setValue('type_id', $typeId);
        $sql->setValue('effect', 'media_srcset_set');
        $sql->setValue('priority', 1);
        $sql->setValue('parameters', json_encode([
            'rex_effect_media_srcset_set' => [
                'preset' => 'ratio_16_9',
                'ratio' => '16_9',
                'mode' => 'focuspoint',
                'width' => 1200,
                'chain' => '',
                'allow_enlarge' => 'not_enlarge',
            ],
        ]));
        $sql->addGlobalCreateFields();
        $sql->insert();
    }

    rex_media_manager::deleteCache();
}

$this->setProperty('install', true);
