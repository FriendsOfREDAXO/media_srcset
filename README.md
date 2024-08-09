# media_srcset

Addon das einen neuen Effekt namens SRCSET hinzufügt (basierend auf dem resize-Effekt) und zusätzlich die Angabe eines SRCSET-Attributs ermöglicht.Rewrite URLs von YRewrite werden unterstützt.

## Installation

* Release herunterladen und entpacken.
* Ordner umbenennen in `media_srcset`.
* In den AddOns-Ordner legen: `/redaxo/src/addons`.

## Hintergrund und Funktionsweise

### Erklärung der `srcset`-Attribute für optimale Bilddarstellung

Wenn du Bilder auf deiner Website einfügst und sicherstellen möchtest, dass sie sowohl auf Desktop- als auch auf Mobilgeräten optimal angezeigt werden, ohne tausende neue MediaManager-Typen anzulegen, kannst du mit diesem Addon automatisiert die `srcset`- und `sizes`-Attribute in HTML verwenden. 

#### Beispiel für einen `srcset`-Eingabe-String im Addon:

```
470 470w, 940 470w 2x, 1410 470w 3x
```
Dieser String wird im MM-Typ angegeben. 

### Was bedeutet dieser `srcset`-String?

1. **470 470w**
   - **470**: Die Breite des Bildes in Pixeln (470px).
   - **470w**: Diese Größe ist für Bildschirme mit normaler (1x) Auflösung gedacht. Das Bild wird in 470px Breite angezeigt.

2. **940 470w 2x**
   - **940**: Die Breite des Bildes in Pixeln (940px), das für Bildschirme mit doppelter (2x) Auflösung gedacht ist.
   - **470w**: Das Bild wird im Layout 470px breit angezeigt, aber für hochauflösende (Retina) Displays verwendet.

3. **1410 470w 3x**
   - **1410**: Die Breite des Bildes in Pixeln (1410px), das für Bildschirme mit dreifacher (3x) Auflösung gedacht ist.
   - **470w**: Das Bild wird im Layout 470px breit angezeigt, aber für hochauflösende Displays verwendet.

### Welche Auswirkungen hat das?

1. **Desktop-Bildschirme:**
   - **Normale Displays (1x)**: Das Bild wird in seiner Basisgröße von 470px angezeigt.
   - **Retina Displays (2x)**: Der Browser verwendet das Bild mit 940px Breite, aber zeigt es auf dem Bildschirm in 470px Breite an. Dies sorgt für eine schärfere Darstellung auf hochauflösenden Displays.
   - **Displays mit 3x-Auflösung**: Der Browser verwendet das Bild mit 1410px Breite, aber zeigt es auf dem Bildschirm in 470px Breite an, um maximale Klarheit auf sehr hochauflösenden Displays zu gewährleisten.

2. **Mobile Geräte:**
   - Die gleiche Logik wie auf Desktops wird angewendet. Der Browser wählt das am besten passende Bild basierend auf der Bildschirmauflösung aus, um sicherzustellen, dass das Bild klar und scharf aussieht, egal wie groß oder klein der Bildschirm ist.

### Einfluss auf das `sizes`-Attribut

Das `sizes`-Attribut gibt an, wie groß das Bild in verschiedenen Layouts angezeigt wird. Hier ein einfaches Beispiel:

```html
<img src="/path/to/default.jpg" 
     srcset="/path/to/image-470.jpg 470w, 
             /path/to/image-940.jpg 940w 2x, 
             /path/to/image-1410.jpg 1410w 3x" 
     sizes="(max-width: 600px) 100vw, 470px" 
     alt="Beispielbild">
```

- **`(max-width: 600px) 100vw`**: Wenn der Bildschirm maximal 600px breit ist (z.B. auf Mobilgeräten), wird das Bild die volle Breite des Bildschirms einnehmen (100vw).
- **`470px`**: Für größere Bildschirme wird das Bild auf 470px Breite angezeigt.

Mit den richtigen `srcset`- und `sizes`-Attributen sorgt das Addon automatisch dafür, dass deine Bilder auf allen Geräten und Auflösungen scharf ausgespielt wird. Der `srcset`-String gibt dem Browser verschiedene Bildgrößen zur Auswahl, abhängig von der Bildschirmauflösung und Größe. Das `sizes`-Attribut hilft dem Browser zu entscheiden, welche Bildgröße am besten für die aktuelle Anzeige geeignet ist, basierend auf festen Pixelwerten.

## Verwendung

Im Feld SRCSET-Attribut die entsprechenden SRCSET-Angaben einfügen, allerdings statt eines Dateinamens die
gewünschte Breite einfügen:

z.B. `400 480w, 800 480w 2x, 700 768w`

Die einzelnen SRCSET-Attribute lassen sich dann innerhalb des Templates über den Profilnamen einfügen:

### Image-Tag

#### Eingabe:

```html
<img src="index.php?rex_media_type=ImgTypeName&rex_media_file=ImageFileName"
    srcset="rex_media_type=ImgTypeName" />
```

oder

```php
echo '<img src="'.rex_media_manager::getUrl('ImgTypeName', 'ImageFileName').'" srcset="rex_media_type=muh" />';
```

oder

```php
echo rex_media_srcset::getImgTag('ImageFileName', 'ImgTypeName');
```


#### Generierte Ausgabe:

```html
<img src="index.php?rex_media_type=ImgTypeName&rex_media_file=ImageFileName"
    srcset="index.php?rex_media_type=ImgTypeName__400&rex_media_file=ImageFileName 480w
            index.php?rex_media_type=ImgTypeName__700&rex_media_file=ImageFileName 768w
            index.php?rex_media_type=ImgTypeName__800&rex_media_file=ImageFileName 960w
    ">
```

### Picture-Tag

#### Eingabe:

```html
<picture>
    <source media="(min-width: 56.25em)" srcset="rex_media_type=ImgTypeName">
    <source srcset="rex_media_type=ImgTypeName">
    <img src="index.php?rex_media_type=ImgTypeName&rex_media_file=ImageFileName" alt="">
</picture>
```

oder

```php
echo rex_media_srcset::getPictureTag('ImageFileName', 'ImgTypeName', ['(min-width: 56.25em)' => 'ImgTypeName']);
```

#### Generierte Ausgabe:

```html
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
```

## srcset.js

Das SRCSET Attribut kann auch als data-srcset Attribut eingebunden werden. Dann lädt der Browser zunächst das Standardbild (im SRC-Attribut). Wird das Script aus

`assets/addons/media_srcset/srcset.js`

eingebunden, wird beim Laden der Seite sowie nach einem Resize eine Routine ausgeführt, die die Anzeigebreite jedes Elements checkt und ggf. eine neue Datei dazu lädt. So lässt sich im SRCSET Attribute eine Breite nicht abhängig vom Viewport sondern von der tatsächlich angezeigten Breite des Elements nutzen. Bitte beachten, dass das Bild dann als CSS-Eigenschaft

```css
width : 100%;
height: auto;
```

erhalten muss.

### Eingabe:

```html
<script type="text/javascript" src="assets/addons/media_srcset/srcset.js"></script>

<img width="500" src="index.php?rex_media_type=ImgTypeName&rex_media_file=ImageFileName"
    data-srcset="rex_media_type=ImgTypeName">
```

### Ausgabe:

```html
<img src="index.php?rex_media_type=ImgTypeName__700&rex_media_file=ImageFileName"
    data-srcset="index.php?rex_media_type=ImgTypeName__400&rex_media_file=ImageFileName 480w
                 index.php?rex_media_type=ImgTypeName__700&rex_media_file=ImageFileName 768w
                 index.php?rex_media_type=ImgTypeName__800&rex_media_file=ImageFileName 960w
    ">
```

Eingabe:

```html
<img width="200" … >
```

Ausgabe:

```html
<img src="index.php?rex_media_type=ImgTypeName__400&rex_media_file=ImageFileName" … >
```

Eingabe:

```html
<img width="1200" … >
```

Ausgabe:

```html
<img src="index.php?rex_media_type=ImgTypeName__960&rex_media_file=ImageFileName" … >
```

## Credits

* [GitHub page](https://github.com/FriendsOfREDAXO/media_manager_srcset)
