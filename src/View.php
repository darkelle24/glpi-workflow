<?php
declare(strict_types=1);
namespace WorkflowBuilder;
final class View
{
    public const STATES = ['waiting' => 'En attente', 'available' => 'Disponible', 'progress' => 'En cours', 'done' => 'Terminée',
        'blocked' => 'Bloquée', 'na' => 'Non applicable', 'running' => 'En cours', 'completed' => 'Terminé',
        'draft' => 'Brouillon', 'active' => 'Actif', 'archived' => 'Archivé'];
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    public static function badge(string $state): string
    {
        return '<span class="wb-badge wb-state-' . self::e($state) . '">' . self::e(self::STATES[$state] ?? $state) . '</span>';
    }
    public static function person(string $class, int $id): string
    {
        if (!$id) { return '—'; }
        if ($class === 'User' && $id === (int) \Session::getLoginUserID()) { return (string) ($_SESSION['glpifriendlyname'] ?? $_SESSION['glpiname'] ?? ('#' . $id)); }
        $object = new $class();
        if (!$object->can($id, READ) || !empty($object->fields['is_deleted'])) {
            return 'Référence #' . $id . ' supprimée ou inaccessible';
        }
        return (string) ($object->fields['completename'] ?? $object->fields['name'] ?? ('#' . $id));
    }
    /** Ticket participant names use the same GLPI helper and anonymization as native ITIL tasks. */
    public static function participant(int $id): string
    {
        if (!$id) { return '—'; }
        return (string) \getUserName($id) ?: ('Utilisateur #' . $id . ' indisponible');
    }
    public static function ticket(int $id): void
    {
        Access::need('view');
        Access::ticket($id);
        echo '<section class="wb" data-wb-root data-lookup="' . self::e(Http::url('lookup.php')) . '">';
        if (Access::has('launch', CREATE)) {
            echo '<a class="btn btn-primary mb-3" href="' . self::e(Http::url('launch.php', ['tickets_id' => $id])) . '">Ajouter un workflow</a>';
        }
        $rows = Store::rows('instances', ['tickets_id' => $id], ['ORDER' => ['id DESC']]);
        if (!$rows) { echo '<p>Aucun workflow associé à ce ticket.</p>'; }
        foreach ($rows as $row) { self::instance((int) $row['id']); }
        echo '</section>';
    }
    public static function instance(int $id): void
    {
        $data = Instances::data($id);
        ['instance' => $instance, 'definition' => $definition, 'version' => $version, 'progress' => $p] = $data;
        $ticketId = (int) $instance['tickets_id'];
        echo '<article class="wb-instance card mb-4"><div class="card-body"><div class="wb-toolbar"><h2>' . self::e($definition['name']) . '</h2>' . self::badge($p['status']) . '</div><p>' . nl2br(self::e($definition['description'])) . '</p>';
        echo '<p>Version ' . (int) $version['number'] . ' · Lancé le ' . self::e($instance['date_creation']) . ' par ' . self::e(self::person('User', (int) $instance['users_id'])) . ' · Ticket <a href="' . self::e(\Ticket::getFormURLWithID($ticketId)) . '">#' . $ticketId . '</a></p>';
        echo '<p>' . $p['done'] . ' / ' . $p['total'] . ' étapes obligatoires terminées — ' . $p['percent'] . ' % · ' . $p['remaining'] . ' restantes · ' . $p['blocked'] . ' bloquées · ' . $p['optional'] . ' facultatives</p><progress max="100" value="' . $p['percent'] . '" aria-label="Progression">' . $p['percent'] . '%</progress>';
        echo '<details class="my-3"><summary>Informations du lancement</summary>';
        FormView::show($definition['fields'], $data['values'][''] ?? [], true, $ticketId, Fields::context($data['values'][''] ?? []));
        echo '</details><p class="text-muted">Ne saisir aucun mot de passe, secret ou jeton.</p>';
        $rows = array_column($data['steps'], null, 'step_key');
        foreach ($definition['steps'] as $step) {
            $key = $step['id'];
            $row = $rows[$key];
            $state = $data['states'][$key];
            $operator = (int) ($row['started_by'] ?? 0);
            echo '<details class="wb-step wb-step-' . self::e($state) . '"' . (in_array($state, ['available', 'progress', 'blocked'], true) ? ' open' : '') . '><summary>' . self::e($step['title']) . ' ' . self::badge($state) . ($step['required'] ? '' : ' · Facultative');
            if ($operator && in_array($state, ['progress', 'blocked', 'available'], true)) {
                echo '<span class="wb-worker">Pris en charge par ' . self::e(self::participant($operator))
                    . ' · depuis ' . self::e($row['started_at'] ?? '—') . '</span>';
            } elseif ($state === 'progress') {
                echo '<span class="wb-worker">En cours — prise en charge à confirmer</span>';
            }
            echo '</summary>';
            echo '<p>' . nl2br(self::e($step['description'])) . '</p><div class="wb-instructions">' . nl2br(self::e($step['instructions'])) . '</div><p>Utilisateur : ' . self::e(self::person('User', (int) $row['users_id'])) . ' · Groupe : ' . self::e(self::person('Group', (int) $row['groups_id'])) . '</p>';
            echo '<p>Prise en charge : ' . ($operator ? self::e(self::participant($operator)) : 'Non prise en charge') . '</p>';
            echo '<p>Début : ' . self::e($row['started_at'] ?? '—') . ' · Fin : ' . self::e($row['completed_at'] ?? '—') . ' · Validée par : ' . self::e(self::participant((int) ($row['completed_by'] ?? 0))) . '</p>';
            if ($step['depends_on']) { echo '<p>Dépendances : ' . self::e(implode(', ', $step['depends_on'])) . '</p>'; }
            foreach ($step['knowbaseitems_id'] as $articleId) {
                $article = new \KnowbaseItem();
                echo $article->can($articleId, READ)
                    ? '<p><a target="_blank" rel="noopener" href="' . self::e(\KnowbaseItem::getFormURLWithID($articleId)) . '">Voir la procédure : ' . self::e($article->fields['name']) . '</a></p>'
                    : '<p class="text-muted">Procédure supprimée ou inaccessible.</p>';
            }
            $actions = match ($state) {
                'done' => ['reopen' => 'Réouvrir', 'comment' => 'Commenter'],
                'blocked' => ['unblock' => 'Débloquer', 'comment' => 'Commenter'],
                'waiting', 'na' => [],
                default => ['start' => 'Je prends en charge', 'save' => 'Enregistrer', 'complete' => 'Terminer', 'block' => 'Signaler un blocage', 'comment' => 'Commenter'],
            };
            if ($state === 'progress' && $operator) { unset($actions['start']); }
            if ($operator === (int) \Session::getLoginUserID() && in_array($state, ['progress', 'blocked'], true)) {
                $actions['release'] = 'Libérer l’étape';
            }
            foreach ($actions as $action => $label) {
                try { Access::instance($instance, true); Access::step($definition, $row, $action); }
                catch (ValidationException) { unset($actions[$action]); }
            }
            if (!$actions) {
                FormView::show($step['fields'], $data['values'][$key] ?? [], true, $ticketId, Fields::context($data['values'][''] ?? []));
            } else {
                echo '<form method="post" action="' . self::e(Http::url('action.php')) . '" data-wb-runtime data-form-values="' . self::e(Store::json($data['values'][''] ?? [])) . '">';
                Http::hidden(['operation' => 'step', 'id' => $id, 'step' => $key, 'revision' => $instance['revision']]);
                $values = $data['values'][$key] ?? [];
                if (isset($_SESSION['wb_failed'][$id][$key])) {
                    $values = $_SESSION['wb_failed'][$id][$key];
                    unset($_SESSION['wb_failed'][$id][$key]);
                }
                FormView::show($step['fields'], $values, (!isset($actions['save']) && !isset($actions['complete'])) || in_array($state, ['done', 'blocked'], true), $ticketId, Fields::context($data['values'][''] ?? []));
                echo '<label class="form-label">Commentaire <textarea name="comment" class="form-control" maxlength="2000"></textarea></label><div class="wb-toolbar">';
                foreach ($actions as $action => $label) {
                    echo '<button class="btn ' . ($action === 'complete' ? 'btn-success' : 'btn-outline-primary') . '" name="action" value="' . $action . '"' . ($action === 'reopen' ? ' data-confirm="Réouvrir cette étape ?"' : '') . '>' . self::e($label) . '</button>';
                }
                echo '</div></form>';
            }
            if (Access::has('admin', UPDATE)) {
                echo '<details><summary>Réaffecter cette étape</summary><form method="post" action="' . self::e(Http::url('action.php')) . '">';
                Http::hidden(['operation' => 'step', 'action' => 'assign', 'id' => $id, 'step' => $key, 'revision' => $instance['revision']]);
                echo '<label>Utilisateur (ID, 0 = aucun)<input type="number" min="0" name="users_id" value="' . (int) $row['users_id'] . '" class="form-control"></label><label>Groupe (ID, 0 = aucun)<input type="number" min="0" name="groups_id" value="' . (int) $row['groups_id'] . '" class="form-control"></label><label>Motif<input name="comment" required maxlength="2000" class="form-control"></label><button class="btn btn-outline-secondary">Réaffecter</button></form></details>';
            }
            echo '</details>';
        }
        self::history($id);
        echo '</div></article>';
    }
    private static function history(int $id): void
    {
        echo '<details><summary>Historique</summary><div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Utilisateur</th><th>Étape</th><th>Action / état</th><th>Champs modifiés / commentaire</th></tr></thead><tbody>';
        foreach (Store::rows('history', ['instances_id' => $id], ['ORDER' => ['id DESC']]) as $event) {
            echo '<tr><td>' . self::e($event['date_creation']) . '</td><td>' . self::e(self::participant((int) $event['users_id'])) . '</td><td>' . self::e($event['step_key'] ?? 'Workflow') . '</td><td>' . self::e(['start' => 'Prise en charge', 'release' => 'Libération', 'save' => 'Enregistrement', 'complete' => 'Validation', 'reopen' => 'Réouverture', 'assign' => 'Réaffectation', 'block' => 'Blocage', 'unblock' => 'Déblocage', 'comment' => 'Commentaire', 'launch' => 'Lancement', 'dependency' => 'Dépendance', 'progress' => 'Progression'][$event['action']] ?? $event['action']) . ' : ' . self::e(self::STATES[$event['old_state']] ?? $event['old_state'] ?? '') . ' → ' . self::e(self::STATES[$event['new_state']] ?? $event['new_state'] ?? '') . '</td><td>' . self::e(implode(', ', Store::unpack($event['changed_fields']))) . '<br>' . nl2br(self::e($event['comment'])) . '</td></tr>';
        }
        echo '</tbody></table></div></details>';
    }
}
