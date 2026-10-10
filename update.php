<?php

/** @var rex_addon $this */

/*
 * Bei einem Update fuehrt REDAXO update.php aus, nicht install.php.
 * install.php ist idempotent (legt den Basistyp media_srcset_set nur an, wenn er fehlt,
 * und setzt output_filter nur, wenn noch keine Entscheidung getroffen wurde) und wird daher
 * hier wiederverwendet – sonst fehlt nach einem Update von 2.x der Basistyp und alle
 * is_*-Adressen der Sets liefern 404.
 *
 * Direkt per require: Der Installer bindet update.php aus dem Temp-Ordner (.new.media_srcset) ein,
 * __DIR__ zeigt also auf die neue install.php; $this->includeFile() wuerde den Pfad an den
 * (alten) Addon-Ordner haengen. $this (rex_addon) bleibt im Scope.
 */
require __DIR__ . '/install.php';
