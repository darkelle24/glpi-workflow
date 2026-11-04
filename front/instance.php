<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
use WorkflowBuilder\{Http, View};
Http::start('Exécution du workflow');
try { View::instance(Http::integer($_GET, 'id')); }
catch (Throwable $error) { Http::error($error); }
Http::end();
