<?php
declare(strict_types=1);
// Standalone UI fixture. This directory is not exposed by GLPI's plugin router.
require dirname(__DIR__) . '/bootstrap.php';
use WorkflowBuilder\{Definition, Store, View, Http};
session_start();
$CFG_GLPI = ['root_doc'=>''];
$definition = Definition::decode(file_get_contents(dirname(__DIR__).'/examples/preparation-ordinateur.json'));
?><!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Test éditeur Workflow Builder</title><link rel="stylesheet" href="../public/css/workflowbuilder.css">
<style>body{font-family:system-ui;background:#f5f7fb;color:#253347}input,textarea,select{font:inherit;padding:.5rem;border:1px solid #ccd4df;border-radius:.3rem;box-sizing:border-box;width:100%}input[type=checkbox]{width:auto}.btn{padding:.5rem .75rem;background:#fff;border:1px solid #9cabbc;border-radius:.3rem;cursor:pointer}.card{background:#fff;padding:1rem}.wb-pane{background:#fff;padding:1rem}.wb-toolbar{display:flex}.form-label{display:block}</style>
<main class="wb" data-wb-root data-lookup="lookup-fixture.php"><h1>Workflow Builder — test de l’éditeur</h1>
<form method="post" data-wb-editor data-preview="preview-fixture.php" data-definition="<?= View::e(Store::json($definition)) ?>" data-readonly="0">
<?php Http::hidden(); ?><input type="hidden" name="definition"><div data-wb-editor-ui></div><button type="submit">Enregistrer le brouillon</button></form>
<script src="../public/js/workflowbuilder.js"></script></main></html>
