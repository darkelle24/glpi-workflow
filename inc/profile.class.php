<?php
declare(strict_types=1);
final class PluginWorkflowbuilderProfile extends CommonGLPI
{
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        return $item instanceof Profile && Session::haveRight('profile', READ)
            ? __('Workflow Builder', 'workflowbuilder') : '';
    }
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (!$item instanceof Profile) { return false; }
        try { WorkflowBuilder\ProfileView::show($item); }
        catch (Throwable $error) { WorkflowBuilder\Http::error($error); }
        return true;
    }
}
