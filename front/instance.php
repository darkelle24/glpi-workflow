<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
use WorkflowBuilder\{Access, Http, Store, View, ValidationException};
Http::start('Exécution du workflow');
try {
    $id = Http::integer($_GET, 'id');
    if (!$id) {
        $key = Http::string($_GET, 'request_key');
        if (!preg_match('/^[a-f0-9]{64}$/D', $key)) { throw new ValidationException('Lien de workflow invalide.'); }
        $rows = Store::rows('instances', ['request_key' => $key], ['LIMIT' => 1]);
        if (!$rows) { throw new ValidationException('Workflow introuvable.'); }
        Access::instance($rows[0]);
        $id = (int) $rows[0]['id'];
    }
    View::instance($id);
} catch (Throwable $error) { Http::error($error); }
Http::end();
