<?php
declare(strict_types=1);
namespace WorkflowBuilder;
final class ProfileView
{
    public static function show(\Profile $profile): void
    {
        global $DB;
        $profile->check($profile->getID(), READ);
        $current = [];
        foreach ($DB->request(['FROM' => 'glpi_profilerights', 'WHERE' => ['profiles_id' => $profile->getID(), 'name' => array_keys(Access::RIGHTS)]]) as $row) {
            $current[$row['name']] = (int) $row['rights'];
        }
        echo '<section class="wb"><h2>Workflow Builder — droits du profil</h2><p>Voir pour accéder aux modèles et instances ; Créer pour créer un modèle et lancer un workflow ; Modifier pour les autres actions.</p><form method="post" action="' . View::e(Http::url('action.php')) . '">';
        Http::hidden(['operation' => 'rights', 'id' => $profile->getID()]);
        echo '<table class="table"><thead><tr><th>Droit</th><th>Voir</th><th>Créer / lancer</th><th>Modifier / exécuter</th></tr></thead><tbody>';
        foreach (Access::RIGHTS as $name => $label) {
            echo '<tr><td>' . View::e(__($label, 'workflowbuilder')) . '</td>';
            foreach ([READ, CREATE, UPDATE] as $bit) {
                echo '<td><input type="checkbox" aria-label="' . View::e($label . ' ' . $bit) . '" name="rights[' . $name . '][]" value="' . $bit . '"' . (($current[$name] ?? 0) & $bit ? ' checked' : '') . '></td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
        if ((Access::has('admin', UPDATE) || Access::canBootstrapRights()) && $profile->can($profile->getID(), UPDATE)) {
            echo '<button class="btn btn-primary">Enregistrer les droits</button>';
        }
        echo '</form></section>';
    }
}
