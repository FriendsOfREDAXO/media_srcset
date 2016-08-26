<?php
    $filename = $this->getAddOn()->getPath('lib/effects/effect_srcset.php');

    if(file_exists($filename))
    {
        unlink($filename);
    }

    // clean up database
    $query = "DELETE FROM `" . rex::getTablePrefix() . "media_manager_type_effect` WHERE `effect` = 'srcset';";
    $sql = rex_sql::factory();
    $sql->setQuery($query);

    unset($query, $sql);
 ?>
