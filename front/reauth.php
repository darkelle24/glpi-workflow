<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
use WorkflowBuilder\{Access,Http};
if (!Access::has('template') && !Access::has('view')) { Session::checkRight('plugin_workflowbuilder_view', READ); }
User::checkReAuthenticationOrRedirect();
Http::redirect(Http::url('index.php'));
