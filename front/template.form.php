<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
use WorkflowBuilder\{Access, Definition, Http, View, Store, Templates, ValidationException};
Http::start('Éditeur de workflow');
try {
    Access::need('template');
    $id = Http::integer($_POST + $_GET, 'id');
    $template = $id ? Store::one('templates', $id) : null;
    $definition = $template ? Access::template($template) : Definition::empty();
    $revision = (int) ($template['revision'] ?? 0);
    $entity = (int) ($template['entities_id'] ?? $_SESSION['glpiactive_entity'] ?? 0);
    $recursive = (bool) ($template['is_recursive'] ?? false);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            Http::post();
            $revision = Http::integer($_POST, 'revision');
            $definition = Definition::decode(Http::string($_POST, 'definition'));
            $entity = Http::integer($_POST, 'entities_id');
            $recursive = isset($_POST['is_recursive']);
            $id = Templates::save($id, $definition, $entity, $recursive, $revision);
            Http::redirect(Http::url('template.form.php', ['id' => $id, 'saved' => 1]));
        } catch (Throwable $error) {
            Http::error($error);
            if ($id) { echo '<p><a href="' . View::e(Http::url('template.form.php', ['id' => $id])) . '">Recharger le modèle enregistré</a></p>'; }
        }
    }
    if (isset($_GET['saved'])) { echo '<div class="alert alert-success">Brouillon enregistré. Publier pour rendre les modifications disponibles aux nouveaux lancements.</div>'; }
    $editable = Access::has('template', $id ? UPDATE : CREATE);
    if ($template) {
        try { Access::template($template, true); } catch (ValidationException) { $editable = false; }
    }
    echo '<form method="post" data-wb-editor data-definition="' . View::e(Store::json($definition)) . '" data-preview="' . View::e(Http::url('preview.php')) . '" data-readonly="' . ($editable ? '0' : '1') . '" action="' . View::e(Http::url('template.form.php', $id ? ['id' => $id] : [])) . '">';
    Http::hidden(['id' => $id, 'revision' => $revision]);
    echo '<input type="hidden" name="definition"><div class="wb-scope"><label>Entité<select name="entities_id" class="form-select">';
    $entities = array_map('intval', $_SESSION['glpiactiveentities'] ?? [$entity]);
    if (!in_array($entity, $entities, true)) { $entities[] = $entity; }
    foreach ($entities as $entityId) {
        $object = new Entity();
        $name = $object->getFromDB($entityId) ? ($object->fields['completename'] ?? $object->fields['name']) : '#' . $entityId;
        echo '<option value="' . $entityId . '"' . ($entityId === $entity ? ' selected' : '') . '>' . View::e($name) . '</option>';
    }
    echo '</select></label><label><input type="checkbox" name="is_recursive" value="1"' . ($recursive ? ' checked' : '') . '> Sous-entités</label></div><div data-wb-editor-ui></div><noscript>JavaScript est nécessaire pour utiliser l’éditeur visuel.</noscript>';
    if ($editable) { echo '<button type="submit" class="btn btn-primary mt-3">Enregistrer le brouillon</button>'; }
    echo '</form>';
    if ($id) {
        echo '<section class="card p-3 my-3"><h2>Publication et versions</h2><p>État : ' . View::badge($template['state']) . '. Enregistrer le brouillon avant de publier.</p><div class="wb-toolbar">';
        if (Access::has('publish', UPDATE) && $editable) {
            echo '<form method="post" action="' . View::e(Http::url('action.php')) . '">';
            Http::hidden(['operation' => 'publish', 'id' => $id, 'revision' => $revision]);
            echo '<button class="btn btn-success" data-confirm="Publier une nouvelle version immuable du brouillon enregistré ?">Publier une nouvelle version</button></form>';
            foreach (['draft' => 'Désactiver', 'active' => 'Réactiver la version publiée', 'archived' => 'Archiver'] as $state => $label) {
                echo '<form method="post" action="' . View::e(Http::url('action.php')) . '">';
                Http::hidden(['operation' => 'state', 'id' => $id, 'revision' => $revision, 'state' => $state]);
                echo '<button class="btn btn-outline-secondary" data-confirm="Appliquer ce changement d’état ? Les instances existantes sont conservées.">' . View::e($label) . '</button></form>';
            }
        }
        echo '</div><ul>';
        foreach (Store::rows('versions', ['templates_id' => $id], ['ORDER' => ['number DESC']]) as $version) {
            echo '<li>Version ' . (int) $version['number'] . ' — ' . View::e($version['date_creation']) . ' — <a href="' . View::e(Http::url('export.php', ['id' => $id, 'version' => $version['id']])) . '">Exporter la définition figée</a></li>';
        }
        echo '</ul></section>';
    }
} catch (Throwable $error) { Http::error($error); }
Http::end();
