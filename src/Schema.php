<?php
declare(strict_types=1);
namespace WorkflowBuilder;
final class Schema
{
    public const VERSION = 3;
    public static function install(): void
    {
        global $DB;
        $config = \Config::getConfigurationValues('plugin:workflowbuilder', ['schema_version']);
        $current = (int) ($config['schema_version'] ?? 0);
        if ($current > self::VERSION) { throw new \RuntimeException('A newer Workflow Builder schema is installed.'); }
        // MariaDB DDL commits implicitly; restartable statements, version recorded last.
        foreach (self::definitions() as $name => $columns) {
            $DB->doQuery('CREATE TABLE IF NOT EXISTS ' . Store::table($name) . ' (
                id int unsigned NOT NULL AUTO_INCREMENT, ' . $columns . ',
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }
        if ($current === 1) {
            $upgrade = require dirname(__DIR__) . '/migrations/2.php';
            $upgrade();
        }
        if ($current > 0 && $current < 3) {
            $upgrade = require dirname(__DIR__) . '/migrations/3.php';
            $upgrade();
        }
        \Config::setConfigurationValues('plugin:workflowbuilder', ['schema_version' => self::VERSION]);
        if (\Session::haveRight('config', UPDATE)) {
            $profile = (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);
            if ($profile && !self::hasAnyRights()) {
                \ProfileRight::updateProfileRights($profile, array_fill_keys(array_keys(Access::RIGHTS), (READ | CREATE | UPDATE)));
            }
        }
        Access::reload();
    }
    private static function hasAnyRights(): bool
    {
        global $DB;
        foreach ($DB->request(['FROM' => 'glpi_profilerights', 'WHERE' => ['name' => array_keys(Access::RIGHTS), 'rights' => ['>', 0]], 'LIMIT' => 1]) as $row) {
            return true;
        }
        return false;
    }
    public static function definitions(): array
    {
        return [
            'templates' => 'name varchar(255) NOT NULL, category varchar(255) NOT NULL DEFAULT \'\',
                description text NOT NULL, entities_id int unsigned NOT NULL DEFAULT 0,
                is_recursive tinyint NOT NULL DEFAULT 0, users_id int unsigned NOT NULL,
                state varchar(16) NOT NULL DEFAULT \'draft\', draft longtext NOT NULL,
                active_versions_id int unsigned DEFAULT NULL, revision int unsigned NOT NULL DEFAULT 1,
                date_creation timestamp NOT NULL, date_mod timestamp NOT NULL,
                KEY entity (entities_id), KEY state (state)',
            'versions' => 'templates_id int unsigned NOT NULL, number int unsigned NOT NULL,
                definition longtext NOT NULL, checksum char(64) NOT NULL,
                users_id int unsigned NOT NULL, date_creation timestamp NOT NULL,
                UNIQUE KEY template_version (templates_id,number)',
            'fields' => 'versions_id int unsigned NOT NULL, step_key varchar(64) NOT NULL DEFAULT \'\',
                field_key varchar(64) NOT NULL, position int unsigned NOT NULL, definition longtext NOT NULL,
                UNIQUE KEY field_scope (versions_id,step_key,field_key)',
            'steps' => 'versions_id int unsigned NOT NULL, step_key varchar(64) NOT NULL,
                position int unsigned NOT NULL, definition longtext NOT NULL,
                UNIQUE KEY version_step (versions_id,step_key)',
            'rules' => 'versions_id int unsigned NOT NULL, position int unsigned NOT NULL,
                definition longtext NOT NULL, KEY version (versions_id)',
            'dependencies' => 'versions_id int unsigned NOT NULL, step_key varchar(64) NOT NULL,
                requires_key varchar(64) NOT NULL, UNIQUE KEY dependency (versions_id,step_key,requires_key)',
            'instances' => 'templates_id int unsigned NOT NULL, versions_id int unsigned NOT NULL,
                tickets_id int unsigned NOT NULL, entities_id int unsigned NOT NULL,
                users_id int unsigned NOT NULL, request_key char(64) NOT NULL,
                status varchar(16) NOT NULL, revision int unsigned NOT NULL DEFAULT 1,
                date_creation timestamp NOT NULL, date_mod timestamp NOT NULL, date_completion timestamp NULL DEFAULT NULL,
                UNIQUE KEY request_key (request_key), KEY ticket (tickets_id),
                KEY template_status (templates_id,status)',
            'stepinstances' => 'instances_id int unsigned NOT NULL, step_key varchar(64) NOT NULL,
                state varchar(16) NOT NULL, users_id int unsigned NOT NULL DEFAULT 0,
                groups_id int unsigned NOT NULL DEFAULT 0, started_at timestamp NULL DEFAULT NULL,
                started_by int unsigned DEFAULT NULL,
                completed_at timestamp NULL DEFAULT NULL, completed_by int unsigned DEFAULT NULL,
                UNIQUE KEY instance_step (instances_id,step_key)',
            'values' => 'instances_id int unsigned NOT NULL, step_key varchar(64) NOT NULL DEFAULT \'\',
                field_key varchar(64) NOT NULL, payload longtext NOT NULL,
                UNIQUE KEY instance_value (instances_id,step_key,field_key)',
            'history' => 'instances_id int unsigned NOT NULL, step_key varchar(64) DEFAULT NULL,
                users_id int unsigned NOT NULL, action varchar(32) NOT NULL,
                old_state varchar(16) DEFAULT NULL, new_state varchar(16) DEFAULT NULL,
                changed_fields text NOT NULL, comment text NOT NULL, date_creation timestamp NOT NULL,
                KEY instance_date (instances_id,date_creation)',
        ];
    }
    public static function uninstall(): void
    {
        global $DB;
        foreach (array_reverse(Store::TABLES) as $name) {
            $DB->doQuery('DROP TABLE IF EXISTS ' . Store::table($name));
        }
        \ProfileRight::deleteProfileRights(array_keys(Access::RIGHTS));
        \Config::deleteConfigurationValues('plugin:workflowbuilder', ['schema_version']);
    }
}
