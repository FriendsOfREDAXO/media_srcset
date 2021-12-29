<?php
/**
 * @package redaxo\media-manager
 * @version 1.0
 */

class rex_effect_srcset extends rex_effect_resize
{
    public function getName()
    {
        return rex_i18n::msg('media_manager_effect_srcset');
    }

    public function getParams()
    {
        $params = [];

        foreach(parent::getParams() as $p)
        {
            if($p['name'] == 'width')
            {
                $p['label'] = rex_i18n::msg('media_manager_effect_param_width_label');
                $p['notice'] = rex_i18n::msg('media_manager_effect_param_width_notice');
                $p['default'] = 500;
            }

            if($p['name'] != 'height')
            {
                $params[] = $p;
            }
        }
        $params[] = [
            'label' => rex_i18n::msg('media_manager_effect_param_srcset_label'),
            'name' => 'srcset',
            'type' => 'string',
            'notice' => rex_i18n::msg('media_manager_effect_param_srcset_notice', '400 480w, 800 480w 2x, 700 768w') . '<br />' .
                        rex_i18n::msg('media_manager_effect_param_srcset_help') . '<br />' .
                        '<pre>
&lt;img src="index.php?rex_media_type=ImgTypeName&rex_media_file=ImageFileName" srcset="rex_media_type=ImgTypeName" /&gt;

&lt;!-- Outputs to
    &lt;img src="index.php?rex_media_type=ImgTypeName&rex_media_file=ImageFileName"
        srcset="index.php?rex_media_type=ImgTypeName__400&rex_media_file=ImageFileName 480w
                index.php?rex_media_type=ImgTypeName__700&rex_media_file=ImageFileName 768w
                index.php?rex_media_type=ImgTypeName__800&rex_media_file=ImageFileName 960w
        " /&gt;
//--&gt;

&lt;picture&gt;
  &lt;source media="(min-width: 56.25em)" srcset="rex_media_type=ImgTypeName"&gt;
  &lt;source srcset="rex_media_type=ImgTypeName"&gt;
  &lt;img src="index.php?rex_media_type=ImgTypeName&rex_media_file=ImageFileName" alt=""&gt;
&lt;/picture&gt;

&lt;!-- Outputs to
    &lt;picture&gt;
        &lt;source media="(min-width: 56.25em)"
            srcset="index.php?rex_media_type=ImgTypeName__400&rex_media_file=ImageFileName 480w
                index.php?rex_media_type=ImgTypeName__700&rex_media_file=ImageFileName 768w
                index.php?rex_media_type=ImgTypeName__800&rex_media_file=ImageFileName 960w
            "&gt;
        &lt;source
            srcset="index.php?rex_media_type=ImgTypeName__400&rex_media_file=ImageFileName 480w
                index.php?rex_media_type=ImgTypeName__700&rex_media_file=ImageFileName 768w
                index.php?rex_media_type=ImgTypeName__800&rex_media_file=ImageFileName 960w
            "&gt;
        &lt;img src="index.php?rex_media_type=ImgTypeName&rex_media_file=ImageFileName" alt=""&gt;
    &lt;/picture&gt;
//--&gt;</pre>'

        ];

        return $params;
    }
}
