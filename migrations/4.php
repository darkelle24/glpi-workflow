<?php
declare(strict_types=1);
// Keep existing workflows unrestricted until a team is explicitly selected on a new launch.
return static function (): void {
    global $DB;
    $table = \WorkflowBuilder\Store::table('instances');
    foreach (['assigned_users', 'assigned_groups'] as $column) {
        if (!$DB->fieldExists($table, $column)) {
            $DB->doQuery('ALTER TABLE ' . $table . ' ADD ' . $column . ' LONGTEXT NULL DEFAULT NULL');
        }
    }
};
