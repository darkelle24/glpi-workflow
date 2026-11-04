<?php
declare(strict_types=1);
use Glpi\Plugin\Hooks;
require_once __DIR__ . '/bootstrap.php';

function plugin_init_workflowbuilder(): void
{
    global $PLUGIN_HOOKS;
    require_once __DIR__ . '/inc/menu.class.php';
    require_once __DIR__ . '/inc/ticket.class.php';
    require_once __DIR__ . '/inc/profile.class.php';
    Plugin::registerClass(PluginWorkflowbuilderTicket::class, ['addtabon' => Ticket::class]);
    Plugin::registerClass(PluginWorkflowbuilderProfile::class, ['addtabon' => Profile::class]);
    $PLUGIN_HOOKS[Hooks::MENU_TOADD]['workflowbuilder'] = ['helpdesk' => PluginWorkflowbuilderMenu::class];
    $PLUGIN_HOOKS[Hooks::ADD_CSS]['workflowbuilder'] = ['css/workflowbuilder.css'];
    $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['workflowbuilder'] = ['js/workflowbuilder.js'];
    $PLUGIN_HOOKS[Hooks::CHANGE_PROFILE]['workflowbuilder'] = [WorkflowBuilder\Access::class, 'reload'];
    $PLUGIN_HOOKS[Hooks::POST_PREPAREUPDATE]['workflowbuilder'] = [Ticket::class => [WorkflowBuilder\TicketGuard::class, 'beforeUpdate']];
    WorkflowBuilder\Access::reload();
}
function plugin_version_workflowbuilder(): array
{
    return ['name' => 'Workflow Builder', 'version' => '0.1.4',
        'author' => 'UDAF24', 'license' => 'GPLv3+', 'homepage' => 'https://github.com/darkelle24/glpi-workflow',
        'requirements' => ['glpi' => ['min' => '12.0.0', 'max' => '12.1.0'], 'php' => ['min' => '8.3']]];
}
function plugin_workflowbuilder_check_prerequisites(): bool
{
    if (version_compare(GLPI_VERSION, '12.0.0', '<') || version_compare(GLPI_VERSION, '12.1.0', '>=')) {
        echo 'Workflow Builder requiert GLPI 12.0.x.';
        return false;
    }
    return PHP_VERSION_ID >= 80300;
}
function plugin_workflowbuilder_check_config(bool $verbose = false): bool
{
    return true;
}
