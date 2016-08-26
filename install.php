<?php
    $versions = [
        $this->getAddOn()->getPath('lib/effects/effect_srcset.php') => 0,
        $this->getPath('lib/effects/effect_srcset.php') => 0
    ];

    foreach($versions as $filename => &$version)
    {
        if(file_exists($filename))
        {
            $data = file_get_contents($filename);
            preg_match('/\@version([^0-9]+)?([0-9\.]+)/i', $data, $file_version);
            if(!empty($file_version[2]))
            {
                $file_version = explode('.', $file_version[2]);
                $i = 0;
                while(($c = array_pop($file_version)) !== null)
                {
                    $c = (int) $c;
                    $c = $c * pow(10,$i);
                    $version+= $c;
                    $i++;
                }
            }
        }
    }

    $filenames = array_keys($versions);
    $versions = array_values($versions);
    if($versions[1]) {
        if($versions[0] < $versions[1])
        {
            if($versions[0] > 0)
            {
                unlink($filenames[0]);
            }
            copy($filenames[1], $filenames[0]);
        }
    }
 ?>
