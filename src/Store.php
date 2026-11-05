<?php
declare(strict_types=1);
namespace WorkflowBuilder;
final class Store
{
    public const TABLES = ['templates', 'versions', 'fields', 'steps', 'rules', 'dependencies', 'instances', 'stepinstances', 'values', 'history', 'pdfpresets'];
    public static function table(string $name): string
    {
        if (!in_array($name, self::TABLES, true)) {
            throw new \LogicException('Unknown table');
        }
        return 'glpi_plugin_workflowbuilder_' . $name;
    }
    public static function rows(string $name, array $where = [], array $options = []): array
    {
        global $DB;
        return iterator_to_array($DB->request(['FROM' => self::table($name), 'WHERE' => $where] + $options), false);
    }
    public static function one(string $name, int $id): array
    {
        $rows = self::rows($name, ['id' => $id], ['LIMIT' => 1]);
        if (!$rows) {
            throw new ValidationException('Élément introuvable.');
        }
        return $rows[0];
    }
    public static function insert(string $name, array $data): int
    {
        global $DB;
        $DB->insert(self::table($name), $data);
        return (int) $DB->insertId();
    }
    public static function update(string $name, int $id, array $data): void
    {
        global $DB;
        $DB->update(self::table($name), $data, ['id' => $id]);
    }
    public static function transaction(callable $callback): mixed
    {
        global $DB;
        $DB->beginTransaction();
        try {
            $result = $callback();
            $DB->commit();
            return $result;
        } catch (\Throwable $error) {
            $DB->rollBack();
            throw $error;
        }
    }
    public static function lock(string $name, int $id): array
    {
        global $DB;
        $DB->doQuery('SELECT id FROM ' . self::table($name) . ' WHERE id = ' . $id . ' FOR UPDATE');
        return self::one($name, $id);
    }
    public static function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
    public static function unpack(string $json): array
    {
        $value = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($value)) {
            throw new \RuntimeException('Corrupt workflow data');
        }
        return $value;
    }
    public static function now(): string
    {
        return \Session::getCurrentTime() ?? date('Y-m-d H:i:s');
    }
    public static function audit(int $instance, ?string $step, string $action, ?string $old, ?string $new, array $keys = [], string $comment = ''): void
    {
        self::insert('history', ['instances_id' => $instance, 'step_key' => $step,
            'users_id' => (int) \Session::getLoginUserID(), 'action' => $action,
            'old_state' => $old, 'new_state' => $new, 'changed_fields' => self::json(array_values($keys)),
            'comment' => $comment, 'date_creation' => self::now()]);
    }
}
