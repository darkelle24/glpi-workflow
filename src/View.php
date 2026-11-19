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
        echo '<a class="btn btn-outline-secondary mb-3" href="' . self::e(Http::url('guide.php')) . '">Guide utilisateur</a> ';
        if (Access::has('launch', CREATE)) {
            echo '<a class="btn btn-primary mb-3" href="' . self::e(Http::url('launch.php', ['tickets_id' => $id, 'from_ticket' => 1])) . '">Ajouter un workflow</a>';
        }
        $rows = Store::rows('instances', ['tickets_id' => $id], ['ORDER' => ['id DESC']]);
        if (!$rows) { echo '<p>Aucun workflow associé à ce ticket.</p>'; }
        foreach ($rows as $row) { self::instance((int) $row['id'], true); }
        echo '</section>';
    }
    public static function mySteps(int $ticketId = 0, string $query = '', string $state = '', int $page = 1, string $sort = 'deadline_asc'): void
    {
        $tickets = MySteps::tickets($ticketId, $query, $state, $sort);
        echo '<section class="wb wb-my-steps" aria-label="Mes étapes à traiter" data-wb-root data-lookup="' . self::e(Http::url('lookup.php')) . '">';
        if ($ticketId) {
            echo '<h2>Mes étapes à traiter pour le ticket #' . $ticketId . '</h2>';
            if (!$tickets) { echo '<p>Aucune étape à traiter pour ce ticket.</p></section>'; return; }
            $ticket = $tickets[0];
            echo '<p><strong>Échéance du ticket :</strong> ';
            self::ticketDeadline($ticket);
            echo '</p>';
            self::myProgress($ticket);
            echo '<p>Retrouvez ci-dessous vos étapes restantes, leurs détails et leurs actions. Les étapes en attente seront disponibles après leurs préalables.</p>';
            foreach ($ticket['workflows'] as $workflow) {
                self::renderInstance($workflow['data'], true, $workflow['steps'], true);
            }
            echo '</section>';
            return;
        }
        echo '<p>Un ticket par ligne, avec vos étapes terminées et celles qui vous restent à traiter.</p>';
        echo '<form method="get" action="' . self::e(Http::url('my-steps.php')) . '" class="wb-toolbar"><label>Ticket<input class="form-control" type="search" name="q" placeholder="N° ou titre du ticket" value="' . self::e($query) . '"></label><label>État d’une étape restante<select name="state" class="form-select"><option value="">Tous les états à traiter</option>';
        foreach (MySteps::FILTER_STATES as $option) {
            echo '<option value="' . $option . '"' . ($state === $option ? ' selected' : '') . '>' . self::e(self::STATES[$option]) . '</option>';
        }
        echo '</select></label><label>Trier par<select name="sort" class="form-select">';
        foreach (MySteps::SORTS as $value => $label) {
            echo '<option value="' . $value . '"' . ($sort === $value ? ' selected' : '') . '>' . self::e($label) . '</option>';
        }
        echo '</select></label><button class="btn btn-primary">Filtrer</button><a class="btn btn-outline-secondary" href="' . self::e(Http::url('my-steps.php')) . '">Réinitialiser</a></form>';
        $total = count($tickets);
        echo '<p role="status">' . $total . ' ticket(s) à traiter.</p>';
        if (!$tickets) { echo '<p class="text-muted">Aucun ticket à traiter pour les filtres sélectionnés.</p></section>'; return; }
        $pages = (int) ceil($total / 20);
        $page = min(max(1, $page), $pages);
        echo '<div class="table-responsive"><table class="table table-hover"><thead><tr><th>Ticket</th><th>Échéance du ticket</th><th>Workflows à traiter</th><th>Vos étapes traitées / total</th><th>Étapes restantes</th><th>Action</th></tr></thead><tbody>';
        foreach (array_slice($tickets, ($page - 1) * 20, 20) as $ticket) {
            $url = self::e(Http::ticketMySteps($ticket['ticket_id']));
            $names = [];
            foreach ($ticket['workflows'] as $id => $workflow) {
                $names[] = self::e($workflow['data']['definition']['name']) . ' · #' . $id;
            }
            echo '<tr><td><a href="' . $url . '">#' . $ticket['ticket_id'] . ' — ' . self::e($ticket['ticket_name']) . '</a></td><td>';
            self::ticketDeadline($ticket);
            echo '</td><td>' . implode('<br>', $names) . '</td><td>';
            self::myProgress($ticket);
            echo '</td><td><strong>' . $ticket['remaining'] . ' restante(s)</strong><br>' . $ticket['actionable'] . ' à traiter maintenant<br>' . $ticket['states']['waiting'] . ' en attente</td><td><a class="btn btn-sm btn-outline-primary" href="' . $url . '">Ouvrir mes étapes</a></td></tr>';
        }
        echo '</tbody></table></div>';
        if ($pages > 1) {
            echo '<nav class="wb-toolbar" aria-label="Pagination des tickets">';
            for ($number = 1; $number <= $pages; $number++) {
                echo '<a class="btn btn-sm ' . ($number === $page ? 'btn-primary' : 'btn-outline-secondary') . '"' . ($number === $page ? ' aria-current="page"' : '') . ' href="' . self::e(Http::url('my-steps.php', ['q' => $query, 'state' => $state, 'page' => $number, 'sort' => $sort])) . '">' . $number . '</a>';
            }
            echo '</nav>';
        }
        echo '</section>';
    }
    private static function ticketDeadline(array $ticket): void
    {
        if ($ticket['deadline'] === null) { echo '<span class="text-muted">Sans échéance</span>'; return; }
        echo '<span' . ($ticket['overdue'] ? ' class="text-danger fw-bold"' : '') . '>' . self::e(\Html::convDateTime($ticket['deadline'])) . '</span>';
        if ($ticket['overdue']) { echo ' <span class="wb-badge wb-state-blocked">En retard</span>'; }
    }
    private static function myProgress(array $ticket): void
    {
        echo '<p><strong>' . $ticket['done'] . ' / ' . $ticket['total'] . ' traitée(s) par vous</strong> · ' . $ticket['remaining'] . ' restante(s)</p><progress max="100" value="' . $ticket['percent'] . '" aria-label="Vos étapes traitées">' . $ticket['percent'] . ' %</progress>';
    }
    public static function instance(int $id, bool $inTicket = false): void
    {
        self::renderInstance(Instances::data($id), $inTicket);
    }
    private static function renderInstance(array $data, bool $inTicket, ?array $stepKeys = null, bool $mySteps = false): void
    {
        $id = (int) $data['instance']['id'];
        ['instance' => $instance, 'definition' => $definition, 'version' => $version, 'progress' => $p] = $data;
        $ticketId = (int) $instance['tickets_id'];
        $instanceAnchor = ($mySteps ? 'wb-my-instance-' : 'wb-instance-') . $id;
        echo '<article id="' . $instanceAnchor . '" class="wb-instance card mb-4"><div class="card-body"><div class="wb-toolbar"><h2>' . self::e($definition['name']) . '</h2>' . self::badge($p['status']) . '</div><p>' . nl2br(self::e($definition['description'])) . '</p>';
        echo '<p>Version ' . (int) $version['number'] . ' · Lancé le ' . self::e($instance['date_creation']) . ' par ' . self::e(self::person('User', (int) $instance['users_id'])) . ' · Ticket <a href="' . self::e(\Ticket::getFormURLWithID($ticketId)) . '">#' . $ticketId . '</a></p>';
        echo '<p><a class="btn btn-primary" data-wb-ticket-link href="' . self::e(\Ticket::getFormURLWithID($ticketId)) . '">Ouvrir le ticket #' . $ticketId . '</a></p>';
        echo '<p><a class="btn btn-outline-primary" href="' . self::e(Http::url('instance.php', ['id' => $id])) . '">Lien direct vers ce workflow</a> <a class="btn btn-outline-secondary" href="' . self::e(Http::ticketWorkflow($ticketId, $id)) . '">Ouvrir dans le ticket</a></p>';
        echo '<p>' . $p['done'] . ' / ' . $p['total'] . ' étapes obligatoires terminées — ' . $p['percent'] . ' % · ' . $p['remaining'] . ' restantes · ' . $p['blocked'] . ' bloquées · ' . $p['optional'] . ' facultatives</p><progress max="100" value="' . $p['percent'] . '" aria-label="Progression">' . $p['percent'] . '%</progress>';
        if ($definition['pdf']['enabled'] ?? false) {
            echo $p['status'] === 'completed'
                ? '<p><a class="btn btn-primary" href="' . self::e(Http::url('pdf.php', ['id' => $id])) . '">Télécharger le PDF</a></p>'
                : '<p class="text-muted">Le PDF sera disponible à la fin du workflow.</p>';
        }
        echo '<p><strong>Responsables du workflow :</strong> ' . self::e(Assignees::names(Assignees::forInstance($instance))) . '</p>';
        echo '<details class="my-3"><summary>Informations du lancement</summary>';
        FormView::show($definition['fields'], $data['values'][''] ?? [], true, $ticketId, Fields::context($data['values'][''] ?? []));
        if (isset($data['values']['_ticket']['options'])) { echo TicketOptions::summary($data['values']['_ticket']['options']); }
        echo '</details><p class="text-muted">Ne saisir aucun mot de passe, secret ou jeton.</p>';
        $rows = array_column($data['steps'], null, 'step_key');
        foreach ($definition['steps'] as $step) {
            $key = $step['id'];
            if ($stepKeys !== null && !in_array($key, $stepKeys, true)) { continue; }
            $row = $rows[$key];
            $state = $data['states'][$key];
            $operator = (int) ($row['started_by'] ?? 0);
            $stepAnchor = $inTicket ? $instanceAnchor . '-step-' . $key : 'wb-step-' . $key;
            echo '<details id="' . self::e($stepAnchor) . '" class="wb-step wb-step-' . self::e($state) . '"' . ($mySteps || in_array($state, ['available', 'progress', 'blocked'], true) ? ' open' : '') . '><summary>' . self::e($step['title']) . ' ' . self::badge($state) . ($step['required'] ? '' : ' · Facultative');
            if ($operator && in_array($state, ['progress', 'blocked', 'available'], true)) {
                echo '<span class="wb-worker">Pris en charge par ' . self::e(self::participant($operator))
                    . ' · depuis ' . self::e($row['started_at'] ?? '—') . '</span>';
            } elseif ($state === 'progress') {
                echo '<span class="wb-worker">En cours — prise en charge à confirmer</span>';
            }
            echo '</summary>';
            echo '<p>' . nl2br(self::e($step['description'])) . '</p><p>Utilisateur : ' . self::e(self::person('User', (int) $row['users_id'])) . ' · Groupe : ' . self::e(self::person('Group', (int) $row['groups_id'])) . '</p>';
            echo '<p>Prise en charge : ' . ($operator ? self::e(self::participant($operator)) : 'Non prise en charge') . '</p>';
            echo '<p>Début : ' . self::e($row['started_at'] ?? '—') . ' · Fin : ' . self::e($row['completed_at'] ?? '—') . ' · Validée par : ' . self::e(self::participant((int) ($row['completed_by'] ?? 0))) . '</p>';
            if ($step['depends_on']) { echo '<p>Dépendances : ' . self::e(implode(', ', $step['depends_on'])) . '</p>'; }
            if ($step['knowbaseitems_id']) { echo '<section class="wb-procedures"><h3>Comment réaliser cette étape ?</h3><div class="wb-toolbar">'; }
            foreach ($step['knowbaseitems_id'] as $articleId) {
                $article = new \KnowbaseItem();
                echo $article->can($articleId, READ)
                    ? '<a class="btn btn-outline-primary" target="_blank" rel="noopener" href="' . self::e(\KnowbaseItem::getFormURLWithID($articleId)) . '">Voir la procédure : ' . self::e($article->fields['name']) . '</a>'
                    : '<p class="text-muted">Procédure supprimée ou inaccessible.</p>';
            }
            if ($step['knowbaseitems_id']) { echo '</div></section>'; }
            $actions = match ($state) {
                'done' => ['reopen' => 'Réouvrir', 'comment' => 'Commenter'],
                'blocked' => ['unblock' => 'Débloquer', 'comment' => 'Commenter'],
                'waiting', 'na' => [],
                default => ['start' => 'Je prends en charge', 'save' => 'Enregistrer', 'complete' => 'Terminer', 'block' => 'Signaler un blocage', 'comment' => 'Commenter'],
            };
            if ($state === 'progress' && $operator) { unset($actions['start']); }
            if ($mySteps && !Engine::dependenciesReady($step, $data['states'])) { unset($actions['complete']); }
            if ($operator === (int) \Session::getLoginUserID() && in_array($state, ['progress', 'blocked'], true)) {
                $actions['release'] = 'Libérer l’étape';
            }
            foreach ($actions as $action => $label) {
                try { Access::instance($instance, true); Access::step($definition, $row, $action, Assignees::forInstance($instance)); }
                catch (ValidationException) { unset($actions[$action]); }
            }
            if (!$actions) {
                FormView::show($step['fields'], $data['values'][$key] ?? [], true, $ticketId, Fields::context($data['values'][''] ?? []));
            } else {
                echo '<form method="post" action="' . self::e(Http::url('action.php')) . '" data-wb-runtime data-form-values="' . self::e(Store::json($data['values'][''] ?? [])) . '">';
                Http::hidden(['operation' => 'step', 'id' => $id, 'step' => $key, 'revision' => $instance['revision'], 'from_ticket' => (int) $inTicket, 'from_my_steps' => (int) $mySteps]);
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
                Http::hidden(['operation' => 'step', 'action' => 'assign', 'id' => $id, 'step' => $key, 'revision' => $instance['revision'], 'from_ticket' => (int) $inTicket, 'from_my_steps' => (int) $mySteps]);
                echo '<label>Utilisateur (ID, 0 = aucun)<input type="number" min="0" name="users_id" value="' . (int) $row['users_id'] . '" class="form-control"></label><label>Groupe (ID, 0 = aucun)<input type="number" min="0" name="groups_id" value="' . (int) $row['groups_id'] . '" class="form-control"></label><label>Motif<input name="comment" required maxlength="2000" class="form-control"></label><button class="btn btn-outline-secondary">Réaffecter</button></form></details>';
            }
            echo '</details>';
        }
        self::history($id, $stepKeys);
        echo '</div></article>';
    }
    private static function history(int $id, ?array $stepKeys = null): void
    {
        echo '<details><summary>Historique</summary><div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Utilisateur</th><th>Étape</th><th>Action / état</th><th>Champs modifiés / commentaire</th></tr></thead><tbody>';
        foreach (Store::rows('history', ['instances_id' => $id], ['ORDER' => ['id DESC']]) as $event) {
            if ($stepKeys !== null && $event['step_key'] !== null && !in_array($event['step_key'], $stepKeys, true)) { continue; }
            echo '<tr><td>' . self::e($event['date_creation']) . '</td><td>' . self::e(self::participant((int) $event['users_id'])) . '</td><td>' . self::e($event['step_key'] ?? 'Workflow') . '</td><td>' . self::e(['start' => 'Prise en charge', 'release' => 'Libération', 'save' => 'Enregistrement', 'complete' => 'Validation', 'reopen' => 'Réouverture', 'assign' => 'Réaffectation', 'block' => 'Blocage', 'unblock' => 'Déblocage', 'comment' => 'Commentaire', 'launch' => 'Lancement', 'dependency' => 'Dépendance', 'progress' => 'Progression'][$event['action']] ?? $event['action']) . ' : ' . self::e(self::STATES[$event['old_state']] ?? $event['old_state'] ?? '') . ' → ' . self::e(self::STATES[$event['new_state']] ?? $event['new_state'] ?? '') . '</td><td>' . self::e(implode(', ', Store::unpack($event['changed_fields']))) . '<br>' . nl2br(self::e($event['comment'])) . '</td></tr>';
        }
        echo '</tbody></table></div></details>';
    }
}
