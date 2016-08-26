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

---
Credits
-------
* [GitHub page](https://github.com/FriendsOfREDAXO/media_manager_srcset)
