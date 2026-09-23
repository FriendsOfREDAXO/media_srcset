---
name: media-srcset-development
description: Architektur, Konventionen und Stolperfallen beim Arbeiten am REDAXO-Addon media_srcset (responsive Bilder über srcset/sizes; klassischer Effekt-Weg und Sets).
---

# Skill: media_srcset entwickeln

## Wann nutzen

Immer dann, wenn Code in `lib/`, `pages/` oder `boot.php` dieses Addons geändert, erweitert oder auf Bugs geprüft wird.

## Zwei Wege in einem Addon

Seit 3.0.0 hat das Addon **zwei parallele Mechanismen**. Sie teilen sich den Extension Point `MEDIA_MANAGER_FILTERSET`, sind ansonsten aber unabhängig:

| | klassisch | Sets |
|---|---|---|
| Einstieg | `rex_media_srcset` (global) | `FriendsOfRedaxo\MediaSrcset\Media\ResponsiveImage` |
| Effekt | `rex_effect_srcset` (erbt `rex_effect_resize`) | `rex_effect_media_srcset_set` (erbt `rex_effect_abstract`) |
| Typ-Schema | `profil__breite` (echter Typ als Basis) | `is_<set>__<breite>` (ein DB-Typ `media_srcset_set` für alles) |
| Konfiguration | Effekt-Parameter am Media-Manager-Typ | Set-Array (Code) bzw. JSON in `rex_config` (Builder) |
| Dateien | `lib/srcset.php`, `lib/effects/effect_srcset.php` | `lib/Config/*`, `lib/Media/*`, `lib/SetFilterset.php`, `lib/effects/effect_media_srcset_set.php` |

**Regel:** Änderungen am einen Weg dürfen den anderen nicht beeinflussen. `parseVirtualType()` liefert für `hero__400` bewusst `null`, damit der klassische Handler greift – diese Trennung nicht aufweichen.

## Zweck des Addons

Das Addon fügt dem REDAXO Media Manager einen zusätzlichen Effekt `srcset` hinzu (Klasse `rex_effect_srcset`, erbt von `rex_effect_resize`) und stellt eine PHP-API (`rex_media_srcset`) bereit, mit der sich `<img>`/`<picture>`-Tags mit passendem `srcset`/`sizes`-Attribut erzeugen lassen, ohne für jede Bildbreite einen eigenen Media-Manager-Typ anlegen zu müssen.

Es gibt zwei parallele, unabhängige Nutzungswege:

1. **Programmatische API** (`getTag()` / `getImgTag()` / `getPictureTag()` / `getSrcSet()`) – der empfohlene Weg für Templates.
2. **Automatischer HTML-Ersatz** über den `OUTPUT_FILTER`-Extension-Point (`replaceSrcSets()` / `replaceSrcSet()`) – ersetzt `srcset="rex_media_type=PROFILNAME"`-Platzhalter in bereits gerendertem HTML per Regex. Älterer, fragilerer Mechanismus; bei Änderungen an der URL-Erzeugung (`generateMediaImageUrl()`, `getSrcSetByMediaType()`) immer beide Wege im Blick behalten.

## Kernkonzept: virtuelle Breiten-Profile

Ein Media-Manager-Profil (z. B. `hero`) bekommt den `srcset`-Effekt mit einem Konfigurationsstring wie:

```
400 480w, 800 480w 2x, 700 768w
```

Format: `<Bildbreite in px> <Viewport-Breite>w[ <Pixel-Ratio>x]`. Beim Rendern wird daraus für jede Bildbreite ein *virtuelles* Profil `hero__400`, `hero__700`, `hero__800` referenziert (`managerFilterset()` fängt `MEDIA_MANAGER_FILTERSET` ab, erkennt den `__WIDTH`-Suffix, lädt die Effekte des Basis-Profils und überschreibt darin nur `width`/`height`). Diese virtuellen Profile existieren nicht als eigene DB-Zeile – sie werden zur Laufzeit aus dem Basis-Profil abgeleitet.

`provideValidSize()` snappt eine angeforderte Breite auf die nächstgrößere im Profil konfigurierte Breite hoch, falls keine exakte Übereinstimmung existiert.

## SVG-Sonderbehandlung

Dateien mit Endung `.svg` (`isSvg()`) werden **nie** über den Media Manager geroutet: kein Cache-Aufbau, kein `getimagesize()`, keine `srcset`/`sizes`-Attribute. `src` zeigt direkt auf die Pool-Datei (`rex_url::media()`). Begründung: SVGs skalieren im Browser verlustfrei, Auflösungsvarianten bringen keinen Mehrwert und einige Media-Manager-Effekte verarbeiten SVG ohnehin nicht sinnvoll. Jede neue Ausgabestelle (neue Tag-Variante, neue Hilfsmethode) muss diesen Bypass mit berücksichtigen, sonst entstehen leere/kaputte Attribute für SVG-Aufrufe.

## Escaping-Disziplin (sicherheitsrelevant!)

- **Jeder** Attributwert, der in generiertes HTML geschrieben wird, muss durch `rex_escape($value)` laufen – auch scheinbar interne/serverseitige Werte wie Breiten/Höhen (kostet nichts, `rex_escape()` gibt Nicht-Strings unverändert zurück).
- Attributnamen werden gegen `/^[a-zA-Z_:][-a-zA-Z0-9_:.]*$/` gefiltert, bevor sie ausgegeben werden – verhindert Attribut-Injection über einen manipulierten `$attributes`-Array-Key.
- **Falle**: `rex_media_manager::getUrl($type, $file)` liefert standardmäßig (`$escape = true`) bereits HTML-vorverschlüsselte URLs (`&amp;` statt `&`). Wird das Ergebnis anschließend nochmal durch `rex_escape()` geschickt, entsteht `&amp;amp;` und die URL ist im Browser kaputt. Deshalb überall in diesem Addon **explizit** `getUrl($type, $file, null, false)` aufrufen (rohe URL) und erst am tatsächlichen Ausgabeort einmalig escapen. Das gilt für jede neue Stelle, die eine Media-Manager-URL erzeugt.
- Der `OUTPUT_FILTER`-Pfad (`replaceSrcSet()`) spleißt Strings direkt in bereits vorhandenes HTML – auch dort muss der zusammengesetzte `srcset`-Wert vor dem Einsetzen escaped werden, weil er (über `getSrcSetByMediaType()`) inzwischen unescaped URLs enthält.

## Sets: Descriptor-Garantie

Die wichtigste Invariante des Set-Wegs: **Ein `srcset`-Descriptor muss der tatsächlichen Pixelbreite der gelieferten Datei entsprechen.** Andernfalls wählt der Browser die falsche Variante. Daraus folgt:

- Die Effekt-Reihenfolge ist `chain` → Ratio-Zuschnitt → breitenbegrenztes Resize. Der Zuschnitt läuft in voller Quellauflösung, sonst erreichen Hochformat-Quellen die Zielbreite nicht.
- In der Vorverarbeitung (`applyChain()`) werden Größen-Effekte (`resize`, `srcset`, `media_srcset_set`) **übersprungen**. Ein Kettenglied, das die Breite ändert, würde die Garantie brechen.
- `ResponsiveImage::getSrcsetEntries()` rundet auf Set-Stufen und kappt an `getSourceMaxWidth()`. Wer hier etwas ändert, prüft es über *Sets & Builder › Vorschau* oder *Demo & Prüfung* – beide erzeugen jede Variante und messen sie nach.

Die Höhe darf dabei um 1 px vom rechnerischen Ratio abweichen (Rundung in Crop + Resize); nur die **Breite** ist garantiert.

## Vorverarbeitung (`chain`)

Verkettung von Media-Manager-Typen, bewusst ohne Zwischendateien umgesetzt: Die Effekte laufen auf dem bereits geladenen `rex_managed_media`, nicht über Zwischendateien im öffentlichen Medienordner. Kein erneutes Encodieren je Schritt, kein Aufräumen, kein Qualitätsverlust. Beim Erweitern beibehalten: Zyklenschutz über `self::$chainStack`, Tiefenbegrenzung, Prüfung auf `rex_effect_abstract` vor dem Instanziieren, und Fehler protokollieren statt die Bildauslieferung zu verhindern.

## Rückwärtskompatibilität

Die öffentliche API wird ausschließlich additiv erweitert: neue Parameter immer als `?array $x = null` (oder passender optionaler Typ) **ans Ende** der Signatur anhängen, niemals bestehende Parameter umsortieren oder deren Bedeutung ändern. Ein `null`-Default muss exakt das bisherige Verhalten reproduzieren (siehe `$layout`-Parameter für `sizes` als Beispiel: ohne ihn bleibt die alte Breakpoint-Wiederholung erhalten, mit ihm wird nach Container-/Spalten-Logik gerechnet – ein bereits gesetztes `$attributes['sizes']` hat aber immer Vorrang vor beidem).

## Typisierung / Statische Analyse

Der Code nutzt PHPStan-taugliche Array-Shape-Docblocks (`array<string, array<int, string>>` u. ä.), auch wo native PHP-Typen nur `array` zulassen. Wichtige Falle dabei: PHP castet rein numerische String-Array-Keys automatisch zu `int` – ein Docblock mit `array<string, ...>` für ein mit `(string) $numericValue` befülltes Array ist dann falsch und führt zu falschen `isset()`-Befunden in der statischen Analyse. Bei Unsicherheit: den tatsächlichen Schlüsseltyp mit einem kleinen Testskript verifizieren, nicht raten.

Vor jedem Commit die statische Analyse dieses Addons laufen lassen (Weg hängt vom jeweiligen REDAXOSetup/CI ab, z. B. über das `rexstan`-Addon oder direkt über die dort gebundene PHPStan-Binary) und auf 0 Findings halten, sofern nicht bewusst als Debt dokumentiert.

## Konfiguration im install.php

`output_filter` (HTML-Platzhalterersetzung) wird beim Installieren gesetzt: bei **Neuinstallation `false`**, bei **Update `true`** (erkannt daran, dass bereits ein Media-Manager-Typ den Effekt `srcset` nutzt). Eine vorhandene Entscheidung wird nie überschrieben.

**Falle:** `hasConfig()` taugt hier nicht als Guard – REDAXO stellt die Config einer zuvor installierten Version wieder her, bevor `install.php` läuft. Deshalb `null === $this->getConfig(...)` prüfen.

## Vor jeder Änderung prüfen

- Bleibt `getSrcSet()` bei leerem/fehlendem Profil ein leerer String statt eines Fehlers? (Aufrufer verlassen sich darauf.)
- Wirkt sich die Änderung auf **beide** Nutzungswege aus (programmatische API und `OUTPUT_FILTER`-Ersatz)?
- Wird an jeder Stelle, die eine neue HTML-Attribut- oder Tag-Ausgabe erzeugt, escaped – und nur einmal?
- Bleibt ein Aufruf ohne die neuen/optionalen Parameter bit-identisch zum bisherigen Verhalten?
- Betrifft die Änderung nur einen der beiden Wege – und bleibt der andere nachweislich unberührt?
- Bei Set-Änderungen: entspricht die erzeugte Dateibreite noch exakt dem Descriptor? (Vorschau/Demo-Seite prüft das serverseitig.)
- Lang-Keys in `de_de` **und** `en_gb` ergänzt? Beide Dateien müssen denselben Schlüsselsatz haben.
