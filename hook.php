<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
function plugin_workflowbuilder_install(): bool
{
    WorkflowBuilder\Schema::install();
    return true;
}
function plugin_workflowbuilder_uninstall(): bool
{
    WorkflowBuilder\Schema::uninstall();
    return true;
}
