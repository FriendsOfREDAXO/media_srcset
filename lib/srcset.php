<?php
/**
 * @package redaxo\media-manager\srcset
 * @version 1.0
 *
 * Class containing functions for inclusion via EXTENSION_POINTS - see boot.php for details
 */

class rex_media_srcset
{
    const IMG = 1;
    const PICTURE = 2;

    /**
     * Replaces all occurences of srcset="rex_media_type=[profile_name]" with the set up source sets
     * @param  rex_extension_point<string> $ep The extension point OUTPUT_FILTER
     * @return String                  The content with the replaced srcset attributes
     */
    public static function replaceSrcSets(rex_extension_point $ep)
    {
        // get the content
        $content = $ep->getSubject();

        // will store all the elements that may contain SRCSET attributes - IMG and PICTURE elements
        $elements = [];

        // get all IMG elements with srcset attribute
        preg_match_all('/<img([^>]+)?srcset="rex_media_type=([^"]+)"([^>]+)?\/?>/i', $content, $matches, PREG_SET_ORDER);
        if(!empty($matches[0]))
        {
            $elements = array_merge($elements, $matches);
        }

        // get all PICTURE elements with srcset attribute
        preg_match_all('/<picture([^>]+)?>(.*?)<\/picture>/is', $content, $matches, PREG_SET_ORDER);
        if(!empty($matches[0]))
        {
            $elements = array_merge($elements, $matches);
        }

        foreach($elements as $match)
        {
            // now we walk through all found elements

            $source = $match[0]; // that will be replaced with the valid srcset data

            if($imgsrc = static::getImgSrc($source))
            {
                // we have extracted the source filename from the element
                if($destination = static::replaceSrcSet($source, $imgsrc))
                {
                    // we have replaced the srcset attribute with a valid one
                    if($destination != $source)
                    {
                        // the new element does not look the same as the original element, so let's replace it in the content
                        $content = str_replace($source, $destination, $content);
                    }
                }
            }
        }

        // return processed content
        return $content;
    }

    /**
     * Tries to find a valid srcset media manager profile from a profilename like PROFILENAME__[imagesize]
     * and overwrites the default size of that profile with the requested imagesize
     * @param rex_extension_point<list<array{effect: string, params: array<string, mixed>}>> $ep The extension point MEDIA_MANAGER_FILTERSET
     * @return list<array{effect: string, params: array<string, mixed>}> A valid effects array for processing
     */
    public static function managerFilterset(rex_extension_point $ep)
    {
        $params = $ep->getParams(); // let's get the parameters
        $subject = $ep->getSubject(); // and the effects array
        $db_uscore = strpos($params['rex_media_type'],'__');

        if($db_uscore > 0)
        {
            // split it!
            $type = substr($params['rex_media_type'], 0, $db_uscore);
            $size = substr($params['rex_media_type'], $db_uscore + 2);
            $size = (int) preg_replace('/[^0-9]/', '', $size);

            // get the effects for the requested media manager profile
            $effects = static::getEffectsFromType($type);

            if ($size > 0 && !empty($effects)) {
                // make sure the size is contained within the source set
                $size = static::provideValidSize($size, $type);

                // size is given and the requested media manager profile exists
                // let's get the source sets of the profile
                $srcsets = static::getSrcSetByMediaType($type);

                foreach ($srcsets as $srcset => $srcset_params) {
                    if (isset($srcset_params[(string) $size])) {
                        // the size is included in list of provided sizes from the profile
                        foreach ($effects as $i => $effect) {
                            if (!empty($effect['params']['srcset']) && $effect['params']['srcset'] == $srcset) {
                                // only reset the default width of the profile with the requested one,
                                // if the profile's srcset parameter matches exactliy the one related to this size
                                $effects[$i]['params']['width'] = (int) $size;
                                $effects[$i]['params']['height'] = (int) 0;
                            }
                        }
                        unset($i, $effect);
                    }
                }
                unset($srcset, $srcset_params);

                // set the effects array
                $subject = $effects;

                unset($srcsets);
            }

            unset($effects, $size, $type);
            unset($db_uscore);
        }

        // return the effects array
        return $subject;
    }

    /**
     * Extracts a valid image file name from a given HTML content by finding a <img /> element with a src attribute
     * @param  String          $content The HTML content
     * @return String|NULL     A filename
     */
    protected static function getImgSrc($content)
    {
        $image = null;

        preg_match('/<img([^>]+)?src="([^"]+)"([^>]+)?\/?>/i', $content, $match);
        if(!empty($match[2]))
        {
            // an IMG element with a SRC attribute was found...

            // first try to get the filename from a rex_media_file parameter within the URI - that is, if no rewrite plugin is set up for example
            $regex = '/rex_media_file=([^\&]+)/i';
            if(preg_match($regex, $match[2], $_match))
            {
                $image = $_match[1];
            }
            else
            {
                // now we try to find the filename if any rewrite plugin is activated and
                // the URL matches the schema of these plugins
                // supported plugins: yRewrite, rewrite_url
                $regex = '/(\/|^)(images|imagetypes|media_file|media|mediatypes)\/[^\/]+\/(.*)$/';
                if(preg_match($regex, $match[2], $_match))
                {
                    $image = $_match[3];
                }
                else
                {
                    // try to receive a plain image file name
                    $regex = '/\/?([^\/\?\&]+)\.(svg|png|jpe?g)/i';
                    if(preg_match($regex, $match[2]))
                    {
                        $image = preg_replace($regex, "$1.$2", $match[2]);
                    }
                }
            }
            unset($regex);
        }
        unset($match);

        return $image;
    }

    /**
     * Replaces any srcset="rex_media_type=[profilename]" attribute with a valid srcset list
     * @param  String $element The element (e.g. IMG or PICTURE)
     * @param  String $imgsrc  The filename to put in
     * @return String          The element with teh correct srcset attributes
     */
    protected static function replaceSrcSet($element, $imgsrc)
    {
        preg_match_all('/srcset="rex_media_type=([^"]+)"/i', $element, $srcsets);
        if(!empty($srcsets[0]))
        {
            // the srcset attribute was found
            foreach($srcsets[1] as $i => $srcset)
            {
                // TODO: this should not happen in the first place, we night need to check the regexp someday
                // prevents "Trying to access array offset on value of type null"
                if (!isset($srcsets[0][$i])) {
                    continue;
                }

                $source = $srcsets[0][$i]; // this will be replaced with...
                $destination = ''; // ...an empty string if no valid srcset could be determined

                // get the list of provided srcset items
                if($srcsets = static::getSrcSetByMediaType($srcset))
                {
                    $srcsets = static::flattenSrcSetArray($srcsets);

                    // join them to a string
                    $srcset = join(', ', $srcsets);

                    // and replace '{rex_media_file}' with the correct image filename
                    $srcset = str_replace(['{rex_media_file}','%7Brex_media_file%7D'], $imgsrc, $srcset);

                    // set the replacing string
                    $destination = 'srcset="' . rex_escape($srcset) . '"';
                }

                // finally replace the source srcset attribute with the new one
                $element = str_replace($source, $destination, $element);
            }
        }

        return $element;
    }

    /**
     * If a size is requested that is not contained in the sizes list of the srcset effect
     * this will return the next largest size.
     * @param  mixed $size     The requested size
     * @param  string $type    The set requested profile name
     * @return int             The valid size
     */
    protected static function provideValidSize($size, $type): int
    {
        $size = (int) $size;

        // get all sizes of the
        $srcsets = static::getSrcSetByMediaType($type);
        $srcsets_flattened = static::flattenSrcSetArray($srcsets);

        if(!isset($srcsets_flattened[(string) $size]))
        {
            // the requested size is not available so let's get the next highest size...
            $sizes = array_keys($srcsets_flattened);
            foreach($sizes as $i => $fsize)
            {
                $fsize = (int) $fsize;
                if($fsize > $size)
                {
                    $size = $fsize;
                    break;
                }
            }
            unset($sizes, $fsize);
        }
        unset($srcsets, $srcsets_flattened);

        return $size;
    }

    /**
     * Get's the effects parameters of a given profile name. Uses
     * rex_managed_media and rex_media_manager.
     * @param  String $type The name of the profile
     * @return list<array{effect: string, params: array<string, mixed>}> The effects array.
     */
    protected static function getEffectsFromType($type)
    {
        // create a fake mediaobject and media manager to read the profile's effects
        $media = new rex_managed_media('');
        $manager = new rex_media_manager($media);

        return $manager->effectsFromType($type);
    }

    /**
     * Creates an image width and and viewport width parameter from a given string
     * @param  string $string The string containing the data
     * @return array{image_width: int, viewport_width: int}|null The data, or null if it could not be parsed
     */
    public static function getSingleSet($string)
    {
        $set = null;

        $segments = explode(' ', trim($string));

        $image_width = 0;
        $viewport_width = 0;
        $viewport_ratio = 1;
        while($seg = array_pop($segments))
        {
            if(preg_match('/[0-9]+x$/', $seg))
            {
                // looks like a Nx parameter setting the viewport ratio (e.g. 2x for a retina display)
                $viewport_ratio = (float) preg_replace('/[^0-9]/', '', $seg);
            }
            elseif(preg_match('/[0-9]+w$/', $seg))
            {
                // looks like a viewport parameter, e.g. '800w'
                $viewport_width = (int) preg_replace('/[^0-9]/', '', $seg);
            }
            elseif(!preg_match('/[^0-9]/', $seg))
            {
                // only a number so this is probably the requested image width
                $image_width = (int) preg_replace('/[^0-9]/', '', $seg);
            }
        }
        unset($seg);

        if(empty($image_width) && !empty($viewport_width))
        {
            // if no image width is given but a viewport width, the image width is the same as the viewport's width
            $image_width = $viewport_width;
        }
        elseif(empty($viewport_width) && !empty($image_width))
        {
            // if no viewport width is given but a image width, the viewport width is the same as the image's width
            $viewport_width = $image_width;
        }

        if($image_width>0 && $viewport_width>0)
        {
            // both, image width and viewport width are set up

            // let's multiply the viewport_width by the viewport_ratio
            $viewport_width = (int) round($viewport_ratio * $viewport_width);

            // set up the return array
            $set = [
                'image_width' => $image_width,
                'viewport_width' => $viewport_width
            ];
        }

        unset($image_width, $viewport_width, $viewport_ratio);

        return $set;
    }

    /**
     * Generates the image url depending on installed plugins
     * @param  string $type     The profile name
     * @param  string $filename The filename
     * @return string           The correct URL
     */
    public static function generateMediaImageUrl($type, $filename)
    {
        $filename = ltrim($filename, '/');

        if(rex_addon::exists('rewrite_url') && rex_addon::get('rewrite_url')->isAvailable())
        {
            // if the rewrite_url plugin is installed, we create the url for this addon
            $url = "/media_file/$type/$filename";
        }
        else
        {
            // request the raw, unescaped URL - callers are responsible for HTML-escaping
            // it at their own point of output (rex_media_manager::getUrl() otherwise
            // returns it pre-escaped with "&amp;", which would be double-escaped downstream)
            $url = rex_media_manager::getUrl($type, $filename, null, false);
        }

        return $url;
    }

    /**
     * Get's all srcset elements for a srcset attribute item by a given profile name and
     * returns an array containing all the single srcsets (if a user sets up multiple srcsets within
     * a profile) which itself contain the single sizes and their file URLs.
     * @param  string $type Profile name
     * @return array<string, array<int, string>> The srcsets
     */
    protected static function getSrcSetByMediaType($type)
    {
        $srcset = [];

        if($effects = static::getEffectsFromType($type))
        {
            // effects found...
            while($effect = array_shift($effects))
            {
                if($effect['effect'] == 'srcset')
                {
                    // a srcset effect was found...
                    $single_srcset = [];

                    // let's parse it's srcset param...
                    $effect_srcset = $effect['params']['srcset'];

                    // each item is deived by a ,
                    $items = explode(',', $effect_srcset);

                    foreach($items as $item)
                    {
                        if($set = static::getSingleSet($item))
                        {
                            // the single item could be parsed,let's store it in the array
                            $single_srcset[(string) $set['image_width']] = static::generateMediaImageUrl($type . '__' . ((string) $set['image_width']), '{rex_media_file}') . ' ' . ((string) $set['viewport_width']) . 'w';
                        }
                        unset($set);
                    }
                    unset($items, $item);

                    if(!empty($single_srcset))
                    {
                        // sort by size
                        ksort($single_srcset);

                        // store in the main array
                        $srcset[$effect_srcset] = $single_srcset;
                    }

                    unset($single_srcset, $effect_srcset);
                }
            }
        }

        return $srcset;
    }

    /**
     * Flattens a srcset array so we have only a list of sizes and their file URLS
     * @param  array<string, array<int, string>> $srcset The array of srcsets (as provided by getSrcSetByMediaType())
     * @return array<int, string> The single sizes and their file URLs
     */
    protected static function flattenSrcSetArray(array $srcset)
    {
        $tmp = [];
        foreach($srcset as $query => $set)
        {
            $tmp = array_replace($tmp, $set);
        }
        $srcset = $tmp;

        unset($tmp, $query, $set);

        return $srcset;
    }

    /**
     * Checks whether the given filename is a SVG.
     * SVGs scale natively in the browser, so media manager processing
     * (resizing, cropping, srcset resolution variants) neither applies nor benefits them.
     * @param  string $fileName
     * @return bool
     */
    protected static function isSvg(string $fileName): bool
    {
        return 'svg' === strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    }

    /**
     * get a srcset string
     * @param string $fileName
     * @param string $mediaType
     * @return string
     */
    public static function getSrcSet(string $fileName, string $mediaType): string
    {
        if (self::isSvg($fileName)) {
            return '';
        }

        $srcsets = static::getSrcSetByMediaType($mediaType);
        $srcsetsFlattened = static::flattenSrcSetArray($srcsets);
        $srcset = implode(', ', $srcsetsFlattened);
        return str_replace(['{rex_media_file}', '%7Brex_media_file%7D'], $fileName, $srcset);
    }

    /**
     * Estimates a "sizes" attribute from the layout context (container width class,
     * column counts) instead of merely restating the srcset breakpoints - same
     * approach as the builder addon's ResponsiveImage::buildSizes().
     * @param array{containerWidth?: string, columns?: int, columnsTablet?: int, columnsMobile?: int, mediaFraction?: float} $layout
     * @return string
     */
    protected static function calculateLayoutSizes(array $layout): string
    {
        $containerWidth = (string) ($layout['containerWidth'] ?? 'uk-container');
        $columns = max(1, (int) ($layout['columns'] ?? 3));
        $columnsTablet = max(1, (int) ($layout['columnsTablet'] ?? 2));
        $columnsMobile = max(1, (int) ($layout['columnsMobile'] ?? 1));
        $mediaFraction = max(0.05, min(1.0, (float) ($layout['mediaFraction'] ?? 1.0)));

        $containerMaxPx = self::estimateContainerMaxPx($containerWidth);

        $desktopPx = (int) max(220, round(($containerMaxPx / $columns) * $mediaFraction));
        $tabletVw = (int) max(20, min(100, floor((100 / $columnsTablet) * $mediaFraction)));
        $mobileVw = (int) max(30, min(100, floor((100 / $columnsMobile) * $mediaFraction)));

        return sprintf(
            '(min-width: 1200px) %dpx, (min-width: 640px) %dvw, %dvw',
            $desktopPx,
            $tabletVw,
            $mobileVw
        );
    }

    /**
     * Rough container-max-width estimate from a UIkit-style container width keyword
     * (e.g. "uk-container", "uk-container-xsmall", "uk-container-expand").
     * @param  string $container
     * @return int
     */
    protected static function estimateContainerMaxPx(string $container): int
    {
        if (str_contains($container, 'xsmall')) {
            return 640;
        }
        if (str_contains($container, 'small')) {
            return 900;
        }
        if (str_contains($container, 'xlarge')) {
            return 1600;
        }
        if (str_contains($container, 'large')) {
            return 1400;
        }
        if (str_contains($container, 'expand') || '' === $container) {
            return 1920;
        }

        return 1200;
    }

    /**
     * helper to get an HTML-Tag
     * @param string $fileName
     * @param string $mediaType
     * @param array<string, string|int>|null $attributes
     * @param int $tagType
     * @param list<string>|null $additionalSources
     * @param array{containerWidth?: string, columns?: int, columnsTablet?: int, columnsMobile?: int, mediaFraction?: float}|null $layout
     * @return string
     */
    public static function getTag(string $fileName, string $mediaType, ?array $attributes = null, int $tagType = self::IMG, ?array $additionalSources = null, ?array $layout = null): string
    {
        $media = \rex_media::get($fileName);
        if (!$media) {
            throw new \InvalidArgumentException(sprintf('Media file "%s" not found.', $fileName));
        }

        $isSvg = self::isSvg($fileName);

        if ($isSvg) {
            // SVGs scale natively in the browser - serve the pool file directly instead of
            // routing through the media manager, which has nothing meaningful to do for them
            $srcset = '';
            $mediaSrc = \rex_url::media($fileName);
            $imageSize = false;
        } else {
            $srcset = self::getSrcSet($fileName, $mediaType);
            $mediaPath = \rex_path::addonCache('media_manager', $mediaType . '/' . $fileName);

            // generate managed media object/media cache if not available
            if (!file_exists($mediaPath)) {
                \rex_media_manager::create($mediaType, $fileName);
            }

            // raw, unescaped URL - rex_escape() is applied uniformly when the attribute string is built below
            $mediaSrc = \rex_media_manager::getUrl($mediaType, $fileName, null, false);
            $imageSize = getimagesize($mediaPath);
        }

        if (!$attributes) {
            $attributes = [];
        }

        $attributes['src'] = $mediaSrc;
        if ('' !== $srcset) {
            $attributes['srcset'] = $srcset;
        }
        if ($imageSize !== false) {
            $attributes['width'] = $imageSize[0];
            $attributes['height'] = $imageSize[1];
        }

        if (empty($attributes['alt'])) {
            $attributes['alt'] = $media->getValue('title');
        }

        // Add a sizes attribute describing the srcset breakpoints, if there is a srcset to describe
        if ('' !== $srcset && !isset($attributes['sizes'])) {
            if (null !== $layout) {
                // layout-derived sizes (container width + column counts), matching builder's approach
                $attributes['sizes'] = self::calculateLayoutSizes($layout);
            } else {
                $sizes = [];
                preg_match_all('/(\d+)w/', $srcset, $matches);
                foreach ($matches[1] as $size) {
                    $sizes[] = "(max-width: {$size}px) {$size}px";
                }

                $sizesString = implode(', ', $sizes);
                if (isset($attributes['width'])) {
                    $sizesString .= ('' !== $sizesString ? ', ' : '') . $attributes['width'] . 'px';
                }
                $attributes['sizes'] = $sizesString;
            }
        }

        if ($tagType === self::PICTURE) {
            unset($attributes['srcset']);
        }

        $attributes = array_filter($attributes, static function ($key) {
            return (bool) preg_match('/^[a-zA-Z_:][-a-zA-Z0-9_:.]*$/', $key);
        }, ARRAY_FILTER_USE_KEY);

        $attributesString = implode(' ', array_map(
            static function ($value, $key) {
                return $key . '="' . rex_escape($value) . '"';
            },
            $attributes,
            array_keys($attributes)
        ));

        switch ($tagType) {
            case self::IMG:
                return '<img ' . $attributesString . '/>';
            case self::PICTURE:
                $output = '<picture>';

                if($additionalSources)
                {
                    foreach ($additionalSources as $additionalSource)
                    {
                        $output .= $additionalSource;
                    }
                }

                if ('' !== $srcset) {
                    $output .= '<source srcset="' . rex_escape($srcset) . '" type="' . rex_escape($media->getType()) . '">';
                }
                $output .= '<img ' . $attributesString . '/>';
                $output .= '</picture>';

                return $output;
            default:
                throw new \InvalidArgumentException(sprintf('Invalid tag type "%d" given.', $tagType));
        }
    }
    /**
     * helper to get an img-Tag
     * @param string $fileName
     * @param string $mediaType
     * @param array<string, string|int>|null $attributes
     * @param array{containerWidth?: string, columns?: int, columnsTablet?: int, columnsMobile?: int, mediaFraction?: float}|null $layout
     * @return string
     */
    public static function getImgTag(string $fileName, string $mediaType, ?array $attributes = null, ?array $layout = null): string
    {
        return self::getTag($fileName, $mediaType, $attributes, self::IMG, null, $layout);
    }

    /**
     * helper to get an picture-Tag
     * @param string $fileName
     * @param string $mediaType
     * @param array<string, string|int>|null $attributes
     * @param array<string, string>|null $mediaQueries
     * @param array{containerWidth?: string, columns?: int, columnsTablet?: int, columnsMobile?: int, mediaFraction?: float}|null $layout
     * @return string
     */
    public static function getPictureTag(string $fileName, string $mediaType, ?array $attributes = null, ?array $mediaQueries = null, ?array $layout = null): string
    {
        $additionalSources = null;

        if($mediaQueries)
        {
            $additionalSources = [];

            foreach ($mediaQueries as $mediaQuery => $mediaQueryMediaType)
            {
                $mediaQuerySrcset = self::getSrcSet($fileName, $mediaQueryMediaType);
                if ('' === $mediaQuerySrcset) {
                    continue;
                }
                $additionalSources[] = '<source srcset="' . rex_escape($mediaQuerySrcset) . '" media="' . rex_escape($mediaQuery) . '">';
            }
        }

        return self::getTag($fileName, $mediaType, $attributes, self::PICTURE, $additionalSources, $layout);
    }
}
