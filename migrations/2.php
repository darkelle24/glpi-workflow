<?php
declare(strict_types=1);
// Upgrade the development schema 1 without deleting templates, versions or instances.
return static function (): void {
    global $DB;
    foreach (['fields','rules'] as $table) {
        $DB->doQuery('ALTER TABLE ' . \WorkflowBuilder\Store::table($table) . ' MODIFY definition LONGTEXT NOT NULL');
    }
    $columns = [
        'templates'=>['date_creation'=>false,'date_mod'=>false],
        'versions'=>['date_creation'=>false],
        'instances'=>['date_creation'=>false,'date_mod'=>false,'date_completion'=>true],
        'stepinstances'=>['started_at'=>true,'completed_at'=>true],
        'history'=>['date_creation'=>false],
    ];
    foreach($columns as $table=>$fields) {
        $changes=[];
        foreach($fields as $field=>$nullable) {
            $changes[]='MODIFY '.$field.' TIMESTAMP '.($nullable?'NULL DEFAULT NULL':'NOT NULL');
        }
        $DB->doQuery('ALTER TABLE '.\WorkflowBuilder\Store::table($table).' '.implode(', ',$changes));
    }
};
