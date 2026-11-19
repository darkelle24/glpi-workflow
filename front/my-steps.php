<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
use WorkflowBuilder\{Http, View};
Http::start('Mes étapes à traiter', 'PluginWorkflowbuilderMyStepsMenu');
try {
    View::mySteps(0, Http::string($_GET, 'q'), Http::string($_GET, 'state'), max(1, Http::integer($_GET, 'page', 1)), Http::string($_GET, 'sort', 'deadline_asc'));
} catch (Throwable $error) { Http::error($error); }
Http::end();
