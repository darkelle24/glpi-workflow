<?php
declare(strict_types=1);
final class PluginWorkflowbuilderMenu extends CommonGLPI
{
    public static function getTypeName($nb = 0) { return __('Workflows', 'workflowbuilder'); }
    public static function canView(): bool
    {
        return WorkflowBuilder\Access::has('template') || WorkflowBuilder\Access::has('view');
    }
    public static function getMenuContent()
    {
        if (!self::canView()) { return false; }
        return ['title' => self::getTypeName(), 'page' => '/plugins/workflowbuilder/front/index.php',
            'icon' => 'ti ti-checklist', 'links' => ['search' => '/plugins/workflowbuilder/front/index.php']];
    }
}
