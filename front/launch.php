<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
use WorkflowBuilder\{Access, Http, View, Store, Templates, Instances, ValidationException};
Http::start('Lancer un workflow');
try {
    Access::need('launch', CREATE);
    Access::need('view');
    $ticketId = Http::integer($_POST + $_GET, 'tickets_id');
    $entity = Http::integer($_POST + $_GET, 'entities_id', (int) ($_SESSION['glpiactive_entity'] ?? 0));
    if ($ticketId) { $entity = (int) Access::ticket($ticketId, true)->fields['entities_id']; }
    $templateId = Http::integer($_POST + $_GET, 'template');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            Http::post();
            $instance = Instances::launch($templateId, $ticketId, $entity, Http::values($_POST), Http::string($_POST, 'request_key'), Http::integer($_POST, 'version'));
            Http::redirect(Http::url('instance.php', ['id' => $instance]));
        } catch (Throwable $error) { Http::error($error); }
    }
    if (!$templateId) {
        echo '<p>' . ($ticketId ? 'Ajouter un workflow au ticket #' . $ticketId : 'Créer un ticket avec un workflow dans l’entité active.') . '</p><div class="wb-catalog">';
        foreach (Store::rows('templates', ['state' => 'active'], ['ORDER' => ['name']]) as $template) {
            try {
                [$version, $definition] = Templates::version((int) $template['active_versions_id']);
                Access::launch($template, $definition, $entity);
                if (!($ticketId ? $definition['allow_attach'] : $definition['allow_create'])) { continue; }
            } catch (ValidationException) { continue; }
            echo '<a class="card p-3" href="' . View::e(Http::url('launch.php', ['template' => $template['id'], 'tickets_id' => $ticketId, 'entities_id' => $entity])) . '"><h2>' . View::e($definition['name']) . '</h2><p>' . View::e($definition['description']) . '</p><small>Version ' . (int) $version['number'] . ' · ' . count($definition['steps']) . ' étapes</small></a>';
        }
        echo '</div>';
    } else {
        $template = Store::one('templates', $templateId);
        [$version, $definition] = Templates::version((int) $template['active_versions_id']);
        Access::launch($template, $definition, $entity);
        echo '<h2>' . View::e($definition['name']) . ' · v' . (int) $version['number'] . '</h2><p>' . View::e($definition['description']) . '</p><p class="alert alert-info">Ne saisir aucun mot de passe, secret ni jeton dans les formulaires ou commentaires.</p>';
        echo '<form method="post" data-wb-runtime data-initial-form data-form-values="{}">';
        Http::hidden(['template' => $templateId, 'version' => $version['id'], 'tickets_id' => $ticketId, 'entities_id' => $entity,
            'request_key' => Http::string($_POST, 'request_key', bin2hex(random_bytes(32)))]);
        WorkflowBuilder\FormView::show($definition['fields'], Http::values($_POST), false, $ticketId ?: null);
        echo '<button class="btn btn-primary mt-3">Valider et ' . ($ticketId ? 'associer au ticket' : 'créer le ticket') . '</button></form>';
    }
} catch (Throwable $error) { Http::error($error); }
Http::end();
