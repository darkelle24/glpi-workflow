<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
use WorkflowBuilder\{Access, Http, View, Store, Instances, ValidationException};
Http::start('Workflows');
try {
    if (!Access::has('template') && !Access::has('view')) { throw new ValidationException('Accès refusé.'); }
    $query = Http::string($_GET, 'q');
    $state = Http::string($_GET, 'state');
    $page = max(1, Http::integer($_GET, 'page', 1));
    $filter = Http::integer($_GET, 'template');
    if (Access::has('template')) {
        echo '<h2>Modèles</h2><form method="get" class="wb-toolbar"><input class="form-control" name="q" placeholder="Nom ou catégorie…" value="' . View::e($query) . '"><select name="state" class="form-select"><option value="">Tous les états</option>';
        foreach (['draft', 'active', 'archived'] as $item) {
            echo '<option value="' . $item . '"' . ($state === $item ? ' selected' : '') . '>' . View::e(View::STATES[$item]) . '</option>';
        }
        echo '</select><button class="btn btn-outline-primary">Rechercher</button></form>';
        $where = [];
        if ($query !== '') { $where['OR'] = ['name' => ['LIKE', '%' . $query . '%'], 'category' => ['LIKE', '%' . $query . '%']]; }
        if (in_array($state, ['draft', 'active', 'archived'], true)) { $where['state'] = $state; }
        $visible = [];
        foreach (Store::rows('templates', $where, ['ORDER' => ['date_mod DESC', 'id DESC']]) as $row) {
            try { $definition = Access::template($row); }
            catch (ValidationException) { continue; }
            $visible[] = [$row, $definition];
        }
        echo '<div class="table-responsive"><table class="table table-hover"><thead><tr><th>Nom / description</th><th>Catégorie</th><th>Étapes / en cours visibles</th><th>État / version</th><th>Modification / auteur</th><th>Actions</th></tr></thead><tbody>';
        foreach (array_slice($visible, ($page - 1) * 20, 20) as [$row, $definition]) {
            $id = (int) $row['id'];
            $active = $row['active_versions_id'] ? Store::one('versions', (int) $row['active_versions_id']) : null;
            $running = 0;
            if (Access::has('view')) {
                foreach (Store::rows('instances', ['templates_id' => $id, 'status' => ['running', 'blocked']]) as $instance) {
                    try { Access::instance($instance); $running++; } catch (ValidationException) {}
                }
            }
            echo '<tr><td><a href="' . View::e(Http::url('template.form.php', ['id' => $id])) . '">' . View::e($row['name']) . '</a><br><small>' . View::e($row['description']) . '</small></td><td>' . View::e($row['category']) . '</td><td>' . count($definition['steps']) . ' / ' . $running . '</td><td>' . View::badge($row['state']) . ' / ' . ($active ? (int) $active['number'] : '—') . '</td><td>' . View::e($row['date_mod']) . '<br>' . View::e(View::person('User', (int) $row['users_id'])) . '</td><td><div class="wb-toolbar"><a class="btn btn-sm btn-outline-primary" href="' . View::e(Http::url('template.form.php', ['id' => $id])) . '">Ouvrir / prévisualiser</a><a class="btn btn-sm btn-outline-secondary" href="' . View::e(Http::url('export.php', ['id' => $id])) . '">Exporter</a><a class="btn btn-sm btn-outline-secondary" href="' . View::e(Http::url('index.php', ['template' => $id])) . '#uses">Utilisations</a></div>';
            if (Access::has('template', CREATE)) {
                echo '<form method="post" action="' . View::e(Http::url('action.php')) . '">';
                Http::hidden(['operation' => 'duplicate', 'id' => $id]);
                echo '<button class="btn btn-sm btn-outline-secondary">Dupliquer</button></form>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div><nav class="wb-toolbar" aria-label="Pagination">';
        for ($n = 1; $n <= max(1, (int) ceil(count($visible) / 20)); $n++) {
            echo '<a class="btn btn-sm ' . ($n === $page ? 'btn-primary' : 'btn-outline-secondary') . '" href="' . View::e(Http::url('index.php', ['q' => $query, 'state' => $state, 'page' => $n])) . '">' . $n . '</a>';
        }
        echo '</nav>';
        if (Access::has('template', CREATE)) {
            echo '<details class="card p-3 my-3"><summary>Importer un modèle ou un exemple</summary><form method="post" enctype="multipart/form-data" action="' . View::e(Http::url('action.php')) . '">';
            Http::hidden(['operation' => 'import']);
            echo '<label>Fichier JSON<input type="file" name="model" accept=".json,application/json" class="form-control"></label><label>Ou exemple<select name="example" class="form-select"><option value="">Aucun</option><option value="creation-utilisateur">Création utilisateur</option><option value="depart-utilisateur">Départ utilisateur</option><option value="preparation-ordinateur">Préparation ordinateur</option></select></label><p>Import dans l’entité active, en brouillon. Les responsables, restrictions et articles locaux doivent être sélectionnés à nouveau.</p><button class="btn btn-primary">Importer</button></form></details>';
        }
    }
    if (Access::has('view')) {
        echo '<h2 id="uses">Instances et tableau de bord</h2>';
        $items = [];
        $counts = ['running' => 0, 'completed' => 0, 'blocked' => 0];
        foreach (Store::rows('instances', $filter ? ['templates_id' => $filter] : [], ['ORDER' => ['id DESC']]) as $row) {
            try {
                $data = Instances::data((int) $row['id']);
                $items[] = $data;
                $counts[$data['progress']['status']]++;
            } catch (ValidationException) {}
        }
        echo '<div class="wb-toolbar"><span class="wb-stat">' . $counts['running'] . ' en cours</span><span class="wb-stat">' . $counts['blocked'] . ' bloqués</span><span class="wb-stat">' . $counts['completed'] . ' terminés</span></div><div class="table-responsive"><table class="table"><thead><tr><th>Workflow</th><th>Ticket</th><th>Progression</th><th>État</th><th>Lancement</th></tr></thead><tbody>';
        $instancePage = max(1, Http::integer($_GET, 'instances_page', 1));
        foreach (array_slice($items, ($instancePage - 1) * 20, 20) as $data) {
            $i = $data['instance'];
            echo '<tr><td><a href="' . View::e(Http::url('instance.php', ['id' => $i['id']])) . '">' . View::e($data['definition']['name']) . ' · v' . (int) $data['version']['number'] . '</a></td><td><a href="' . View::e(Ticket::getFormURLWithID($i['tickets_id'])) . '">#' . (int) $i['tickets_id'] . '</a></td><td>' . $data['progress']['percent'] . '%</td><td>' . View::badge($data['progress']['status']) . '</td><td>' . View::e($i['date_creation']) . '</td></tr>';
        }
        echo '</tbody></table></div><nav class="wb-toolbar">';
        for ($n = 1; $n <= max(1, (int) ceil(count($items) / 20)); $n++) {
            echo '<a class="btn btn-sm btn-outline-secondary" href="' . View::e(Http::url('index.php', ['template' => $filter, 'instances_page' => $n])) . '#uses">' . $n . '</a>';
        }
        echo '</nav>';
    }
} catch (Throwable $error) { Http::error($error); }
Http::end();
