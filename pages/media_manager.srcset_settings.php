<?php

$form = rex_config_form::factory("marco"); //Addonname/Pluginname -> Namespace in Rex_config db

$field = $form->addSelectField('output');
$field->setLabel("Ausgabe");
$select = $field->getSelect();
$select->setSize(1);
$select->addOption("srcset", 'srcset');
$select->addOption("data-srcset", 'data-srcset');


$fragment = new rex_fragment();
$fragment->setVar('class', 'primary', false);
$fragment->setVar('title', "Einstellungen", false);
$fragment->setVar('body', $form->get(), false);
echo $fragment->parse('core/page/section.php');
