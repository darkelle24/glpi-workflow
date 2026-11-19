<?php
declare(strict_types=1);
final class PluginWorkflowbuilderMyStepsMenu extends CommonGLPI
{
    public static function getTypeName($nb = 0) { return __('Mes étapes à traiter', 'workflowbuilder'); }
    public static function canView(): bool { return WorkflowBuilder\Access::has('view'); }
    public static function getMenuContent()
    {
        if (!self::canView()) { return false; }
        return ['title' => self::getTypeName(), 'page' => '/plugins/workflowbuilder/front/my-steps.php',
            'icon' => 'ti ti-list-check', 'links' => ['search' => '/plugins/workflowbuilder/front/my-steps.php']];
    }
}
