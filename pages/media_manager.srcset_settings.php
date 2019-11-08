<?php
$addon = rex_addon::get('media_srcset');
$form = rex_config_form::factory($addon->name);

$field = $form->addSelectField('srcset-output');
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
