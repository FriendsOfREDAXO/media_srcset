media_manager / srcset
================

PlugIn für das media_manager Addon, das einen neuen Effekt namens SRCSET hinzufügt
(basierend auf dem resize-Effekt) und zusätzlich die Angabe eines SRCSET-Attributs
ermöglicht.

Installation
-------

* Release herunterladen und entpacken.
* Umbenennen in srcset
* In den Plugins-Ordner des MediaManager AddOns legen: /redaxo/src/addons/media_manager/plugins


Verwendung
-------
Im Feld SRCSET-Attribut die entsprechenden SRCSET-Angaben einfügen, allerdings statt eines Dateinamens die
gewünschte Breite einfügen:

z.B. "400 480w, 800 480w 2x, 700 768w"

Die einzelnen SRCSET-Attribute lassen sich dann innerhalb des Tenmplates über den Profilnamen einfügen:

    <img src="index.php?rex_media_type=ImgTypeName&rex_media_file=ImageFileName" srcset="rex_media_type=ImgTypeName" />

    <!-- Outputs to
        <img src="index.php?rex_media_type=ImgTypeName&rex_media_file=ImageFileName"
            srcset="index.php?rex_media_type=ImgTypeName__400&rex_media_file=ImageFileName 480w
                    index.php?rex_media_type=ImgTypeName__700&rex_media_file=ImageFileName 768w
                    index.php?rex_media_type=ImgTypeName__800&rex_media_file=ImageFileName 960w
            " />
    //-->

    <picture>
      <source media="(min-width: 56.25em)" srcset="rex_media_type=ImgTypeName">
      <source srcset="rex_media_type=ImgTypeName">
      <img src="index.php?rex_media_type=ImgTypeName&rex_media_file=ImageFileName" alt="">
    </picture>

    <!-- Outputs to
        <picture>
            <source media="(min-width: 56.25em)"
                srcset="index.php?rex_media_type=ImgTypeName__400&rex_media_file=ImageFileName 480w
                    index.php?rex_media_type=ImgTypeName__700&rex_media_file=ImageFileName 768w
                    index.php?rex_media_type=ImgTypeName__800&rex_media_file=ImageFileName 960w
                ">
            <source
                srcset="index.php?rex_media_type=ImgTypeName__400&rex_media_file=ImageFileName 480w
                    index.php?rex_media_type=ImgTypeName__700&rex_media_file=ImageFileName 768w
                    index.php?rex_media_type=ImgTypeName__800&rex_media_file=ImageFileName 960w
                ">
            <img src="index.php?rex_media_type=ImgTypeName&rex_media_file=ImageFileName" alt="">
        </picture>
    //-->


srcset.js
-------
Das SRCSET Attribut kann auch als data-srcset Attribut eingebunden werden. Dann lädt der Browser
zunächst das Standardbild (im SRC-Attribut). Wird das Script aus

    assets/addons/media_manager/plugins/srcset/srcset.js

eingebunden, wird beim Laden der Seite sowie nach einem Resize eine Routine ausgeführt, die
die Anzeigebreite jedes Elements checkt und ggf. eine neue Datei dazu lädt. So lässt sich
im SRCSET Attribute eine Breite nicht abhängig vom Viewport sondern von der tatsächlich
angezeigten Breite des Elements nutzen. Bitte beachten, dass das Bild dann als CSS-Eigenschaft

    width : 100%;
    height: auto;

erhalten muss.

    <script type="text/javascript" src="assets/addons/media_manager/plugins/srcset/srcset.js"></script>

    <img width="500" src="index.php?rex_media_type=ImgTypeName&rex_media_file=ImageFileName" data-srcset="rex_media_type=ImgTypeName" />
    <!-- Outputs to
        <img src="index.php?rex_media_type=ImgTypeName__700&rex_media_file=ImageFileName"
            data-srcset="index.php?rex_media_type=ImgTypeName__400&rex_media_file=ImageFileName 480w
                    index.php?rex_media_type=ImgTypeName__700&rex_media_file=ImageFileName 768w
                    index.php?rex_media_type=ImgTypeName__800&rex_media_file=ImageFileName 960w
            " />
    //-->

    <img width="200" ... />
    <!-- Outputs to
        <img src="index.php?rex_media_type=ImgTypeName__400&rex_media_file=ImageFileName" ... />
    //-->

    <img width="1200" ... />
    <!-- Outputs to
        <img src="index.php?rex_media_type=ImgTypeName__960&rex_media_file=ImageFileName" ... />
    //-->


---
Credits
-------
* [GitHub page](https://github.com/FriendsOfREDAXO/media_manager_srcset)