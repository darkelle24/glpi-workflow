<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
global $DB;
use WorkflowBuilder\{Access,Http,Schema,Store,View};
Http::start('Diagnostic Workflow Builder');
try {
    Access::need('admin', UPDATE);
    $config=Config::getConfigurationValues('plugin:workflowbuilder',['schema_version']);
    echo '<p>GLPI '.View::e(GLPI_VERSION).' · PHP '.View::e(PHP_VERSION).' · Schéma '.View::e($config['schema_version']??'absent').' / '.Schema::VERSION.'</p>';
    echo '<ul>';
    foreach(Store::TABLES as $table)echo '<li>'.View::e(Store::table($table)).' : '.($DB->tableExists(Store::table($table))?'présente':'ABSENTE').'</li>';
    echo '</ul><h2>Droits du profil courant</h2><ul>';
    foreach(Access::RIGHTS as $key=>$label)echo '<li>'.View::e($label).' : '.(int)($_SESSION['glpiactiveprofile'][$key]??0).'</li>';
    echo '</ul><p>Les détails techniques des erreurs se trouvent dans les journaux GLPI. Ne pas publier leurs contenus sans retirer les informations confidentielles.</p>';
} catch(Throwable $error){Http::error($error);}
Http::end();
