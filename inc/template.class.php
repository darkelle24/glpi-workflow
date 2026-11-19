<?php
declare(strict_types=1);

/** Read-only native item adapter: all model writes stay in the versioned editor. */
final class PluginWorkflowbuilderTemplate extends CommonDBTM
{
    public static function getTypeName($nb = 0) { return __('Modèle de workflow', 'workflowbuilder'); }
    public static function getIcon() { return 'ti ti-checklist'; }
    public static function getTable($classname = null) { return WorkflowBuilder\Store::table('templates'); }
    public static function canView(): bool { return WorkflowBuilder\Access::has('template'); }
    public function canViewItem(): bool
    {
        if (empty($this->fields['id']) || !isset($this->fields['draft'])) { return false; }
        try { WorkflowBuilder\Access::template($this->fields); return true; }
        catch (WorkflowBuilder\ValidationException) { return false; }
    }
    public static function getFormURL($full = true)
    {
        global $CFG_GLPI;
        return ($full ? $CFG_GLPI['root_doc'] : '') . '/plugins/workflowbuilder/front/template.form.php';
    }
    public static function getSearchURL($full = true)
    {
        global $CFG_GLPI;
        return ($full ? $CFG_GLPI['root_doc'] : '') . '/plugins/workflowbuilder/front/index.php';
    }
}
