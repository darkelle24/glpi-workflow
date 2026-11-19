<?php
declare(strict_types=1);
final class PluginWorkflowbuilderTicket extends CommonGLPI
{
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        return $item instanceof Ticket && $item->getID() > 0 && WorkflowBuilder\Access::has('view')
            ? [1 => self::createTabEntry(__('Workflow', 'workflowbuilder'), 0, null, 'ti ti-checklist'),
                2 => self::createTabEntry(__('Mes étapes à traiter', 'workflowbuilder'), 0, null, 'ti ti-list-check')] : '';
    }
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (!$item instanceof Ticket) { return false; }
        if (!in_array((int) $tabnum, [1, 2], true)) { return false; }
        try {
            if ((int) $tabnum === 2) { WorkflowBuilder\View::mySteps((int) $item->getID()); }
            else { WorkflowBuilder\View::ticket((int) $item->getID()); }
        }
        catch (Throwable $error) { WorkflowBuilder\Http::error($error); }
        return true;
    }
}
