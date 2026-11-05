<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
use WorkflowBuilder\Assignees;
session_start();
?><!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Affectation de test</title><link rel="stylesheet" href="../public/css/workflowbuilder.css">
<style>body{font-family:system-ui}.form-control{padding:.5rem;width:100%;box-sizing:border-box}.btn{padding:.5rem;margin:.2rem;cursor:pointer}</style>
<main class="wb" data-wb-root data-lookup="lookup-fixture.php"><form data-wb-runtime>
<?php
WorkflowBuilder\TicketOptions::show([]);
Assignees::show(['users'=>[], 'groups'=>[]]);
WorkflowBuilder\FormView::show(WorkflowBuilder\Fields::definitions([
    ['id'=>'tools','label'=>'Outils nécessaires','type'=>'multiselect','choices'=>['Ordinateur','Téléphone','Badge'],'default'=>['Ordinateur']],
    ['id'=>'phone','label'=>'Numéro de téléphone','type'=>'text','condition'=>['field'=>'self.tools','op'=>'contains','value'=>'Téléphone']]
], ['self.tools']));
?><button type="submit">Lancer</button></form>
<script src="../public/js/workflowbuilder.js"></script></main></html>
