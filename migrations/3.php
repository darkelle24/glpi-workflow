<?php
declare(strict_types=1);
// Ownership is independent from the user/group assignment configured on an instance.
return static function (): void {
    global $DB;
    $table = \WorkflowBuilder\Store::table('stepinstances');
    if (!$DB->fieldExists($table, 'started_by')) {
        $DB->doQuery('ALTER TABLE ' . $table . ' ADD started_by INT UNSIGNED NULL DEFAULT NULL');
    }
    // Do not guess the operator of an old "in progress" step from its planned assignment.
};
