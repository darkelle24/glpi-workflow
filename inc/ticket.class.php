<?php
declare(strict_types=1);
final class PluginWorkflowbuilderTicket extends CommonGLPI
{
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        return $item instanceof Ticket && $item->getID() > 0 && WorkflowBuilder\Access::has('view')
            ? __('Workflow', 'workflowbuilder') : '';
    }
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (!$item instanceof Ticket) { return false; }
        try { WorkflowBuilder\View::ticket((int) $item->getID()); }
        catch (Throwable $error) { WorkflowBuilder\Http::error($error); }
        return true;
    }
}
