<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use WorkflowBuilder\{PdfAutomatic,Store,View,Http};
session_start();
?><!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Administration PDF — démonstration</title><link rel="stylesheet" href="../public/css/workflowbuilder.css">
<style>body{font-family:system-ui;background:#f5f7fb;color:#253347;padding:1rem}input,textarea,select{font:inherit;padding:.5rem;border:1px solid #ccd4df;border-radius:.3rem;box-sizing:border-box;width:100%}input[type=checkbox]{width:auto}.btn{padding:.5rem .75rem;background:white;border:1px solid #9cabbc;border-radius:.3rem;cursor:pointer}.form-label{display:block}.wb-toolbar{display:flex}</style>
<main class="wb"><h1>Administration PDF — modèles de base</h1>
<form method="post" data-wb-pdf-preset data-automatic="<?=View::e(Store::json(PdfAutomatic::empty()))?>" data-pdf-presets="<?=View::e(Store::json(PdfAutomatic::defaults()))?>">
<?php Http::hidden(['id'=>0,'revision'=>0,'operation'=>'save']); ?>
<label>Nom dans la bibliothèque<input name="name" value="Mon modèle"></label>
<input type="hidden" name="automatic"><div data-preset-ui></div><button>Enregistrer le modèle de base</button><button type="button" data-preset-preview>Télécharger un aperçu PDF</button><p role="status" data-preset-status></p></form>
<script src="../public/js/workflowbuilder.js"></script></main></html>