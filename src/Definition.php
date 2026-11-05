<?php
declare(strict_types=1);
namespace WorkflowBuilder;

final class Definition
{
    public const MAX_BYTES = 16777216;
    public static function empty(): array
    {
        return ['schema' => 1, 'name' => 'Nouveau workflow', 'description' => '', 'category' => '',
            'icon' => 'ti ti-checklist', 'launch_groups' => [], 'execute_groups' => [], 'manage_groups' => [],
            'launch_users' => [], 'execute_users' => [], 'manage_users' => [],
            'ticket_title' => '{{workflow}}', 'ticket_description' => '{{summary}}',
            'allow_create' => true, 'allow_attach' => true, 'require_before_close' => true, 'notify_summary' => true,
            'fields' => [], 'steps' => [], 'rules' => [], 'pdf' => PdfTemplate::empty()];
    }

    public static function decode(string $json): array
    {
        if (strlen($json) > self::MAX_BYTES) {
            throw new ValidationException('Définition trop volumineuse (16 Mio maximum, images PDF comprises).');
        }
        try {
            $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ValidationException('Le fichier JSON est malformé.');
        }
        if (!is_array($data)) {
            throw new ValidationException('Définition JSON invalide.');
        }
        return self::validate($data);
    }

    public static function validate(array $data): array
    {
        $base = self::empty();
        if (array_diff(array_keys($data), array_keys($base)) || ($data['schema'] ?? null) !== 1) {
            throw new ValidationException('Format de définition inconnu (schema 1 attendu).');
        }
        $data += $base;
        foreach (['name' => 255, 'description' => 10000, 'category' => 255, 'icon' => 80, 'ticket_title' => 255, 'ticket_description' => 10000] as $key => $max) {
            $data[$key] = self::text($data[$key], $max, in_array($key, ['name', 'ticket_title'], true));
        }
        if (!preg_match('/^ti ti-[a-z0-9-]+$/D', $data['icon'])) {
            throw new ValidationException('Icône Tabler invalide.');
        }
        foreach (['allow_create', 'allow_attach', 'require_before_close', 'notify_summary'] as $key) {
            $data[$key] = self::boolean($data[$key]);
        }
        foreach (['launch_groups', 'execute_groups', 'manage_groups', 'launch_users', 'execute_users', 'manage_users'] as $key) {
            $data[$key] = self::ids($data[$key]);
        }
        if (!is_array($data['fields']) || !is_array($data['steps']) || !is_array($data['rules'])
            || !array_is_list($data['steps']) || count($data['steps']) > 100) {
            throw new ValidationException('Liste de champs, étapes ou règles invalide (100 étapes maximum).');
        }
        // Initial field conditions can only reference preceding fields.
        $vars = [];
        foreach ($data['fields'] as $field) {
            $normalized = Fields::definitions([$field], $vars)[0];
            $vars[] = 'form.' . $normalized['id'];
            $vars[] = 'self.' . $normalized['id'];
        }
        // Validate uniqueness with the whole form as well.
        $data['fields'] = Fields::definitions($data['fields'], $vars);
        // Recheck that forward references cannot slip through the whole-form pass.
        $seen = [];
        foreach ($data['fields'] as $field) {
            Condition::validate($field['condition'], $seen);
            $seen[] = 'form.' . $field['id'];
            $seen[] = 'self.' . $field['id'];
        }
        $vars = array_map(static fn($f) => 'form.' . $f['id'], $data['fields']);
        $stepIds = [];
        foreach ($data['steps'] as &$step) {
            if (!is_array($step) || array_diff(array_keys($step), ['id', 'title', 'description', 'instructions', 'required', 'users_id', 'groups_id', 'condition', 'depends_on', 'dependency_mode', 'knowbaseitems_id', 'fields', 'initial_state', 'notify_available', 'notify_taken', 'notify_done'])) {
                throw new ValidationException('Propriétés d’étape inconnues.');
            }
            self::identifier($step['id'] ?? null);
            if (in_array($step['id'], $stepIds, true)) {
                throw new ValidationException('Identifiant d’étape dupliqué.');
            }
            $stepIds[] = $step['id'];
            $step['title'] = self::text($step['title'] ?? '', 255, true);
            $step['description'] = self::text($step['description'] ?? '', 10000);
            // Legacy imports/versions retain this text so the editor can migrate it into a native KB article.
            $step['instructions'] = self::text($step['instructions'] ?? '', 10000);
            $step['required'] = self::boolean($step['required'] ?? true);
            foreach (['notify_available', 'notify_taken', 'notify_done'] as $option) {
                $step[$option] = self::boolean($step[$option] ?? true);
            }
            foreach (['users_id', 'groups_id'] as $key) {
                if (filter_var($step[$key] ?? 0, FILTER_VALIDATE_INT) === false || (int) ($step[$key] ?? 0) < 0) {
                    throw new ValidationException('Responsable invalide.');
                }
                $step[$key] = (int) ($step[$key] ?? 0);
            }
            $step['condition'] ??= null;
            Condition::validate($step['condition'], $vars);
            $step['depends_on'] ??= [];
            if (!is_array($step['depends_on']) || !array_is_list($step['depends_on'])) {
                throw new ValidationException('Dépendances invalides.');
            }
            foreach ($step['depends_on'] as $dep) {
                self::identifier($dep);
            }
            $step['depends_on'] = array_values(array_unique($step['depends_on']));
            $step['dependency_mode'] ??= 'start';
            if (!in_array($step['dependency_mode'], ['start', 'complete'], true)) {
                throw new ValidationException('Mode de dépendance invalide.');
            }
            $step['initial_state'] ??= 'available';
            if (!in_array($step['initial_state'], ['available', 'blocked'], true)) {
                throw new ValidationException('État initial invalide.');
            }
            $step['knowbaseitems_id'] = self::ids($step['knowbaseitems_id'] ?? []);
            if (!is_array($step['fields'] ?? [])) {
                throw new ValidationException('Champs d’étape invalides.');
            }
            $step['fields'] = Fields::definitions($step['fields'] ?? [], $vars);
        }
        unset($step);
        self::acyclic($data['steps'], $stepIds);
        if (!array_is_list($data['rules']) || count($data['rules']) > 100) {
            throw new ValidationException('100 règles maximum.');
        }
        foreach ($data['rules'] as $rule) {
            if (!is_array($rule) || array_diff(array_keys($rule), ['step', 'action', 'when'])
                || !in_array($rule['step'] ?? '', $stepIds, true)
                || !in_array($rule['action'] ?? '', ['show', 'enable', 'block'], true)
                || !isset($rule['when'])) {
                throw new ValidationException('Règle invalide.');
            }
            Condition::validate($rule['when'], $vars);
        }
        foreach (['ticket_title', 'ticket_description'] as $key) {
            preg_match_all('/{{\s*([^{}]+?)\s*}}/', $data[$key], $matches);
            $allowed = array_merge(array_column($data['fields'], 'id'), ['workflow', 'summary']);
            if (array_diff($matches[1], $allowed)) {
                throw new ValidationException('Variable de ticket inconnue.');
            }
        }
        $data['pdf'] = PdfTemplate::validate($data['pdf'], $data);
        if (strlen(Store::json($data)) > self::MAX_BYTES) {
            throw new ValidationException('Définition trop volumineuse (16 Mio maximum, images PDF comprises).');
        }
        return $data;
    }

    private static function acyclic(array $steps, array $ids): void
    {
        $graph = array_column($steps, 'depends_on', 'id');
        $marks = [];
        $visit = function (string $id) use (&$visit, &$marks, $graph, $ids): void {
            if (($marks[$id] ?? 0) === 1) {
                throw new ValidationException('Dépendance circulaire détectée.');
            }
            if (($marks[$id] ?? 0) === 2) {
                return;
            }
            $marks[$id] = 1;
            foreach ($graph[$id] as $dep) {
                if (!in_array($dep, $ids, true)) {
                    throw new ValidationException('Dépendance vers une étape inexistante.');
                }
                $visit($dep);
            }
            $marks[$id] = 2;
        };
        foreach ($ids as $id) {
            $visit($id);
        }
    }

    public static function identifier(mixed $value): void
    {
        if (!is_string($value) || !preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $value)) {
            throw new ValidationException('Identifiant invalide : lettres minuscules, chiffres et soulignement, 64 caractères maximum.');
        }
    }

    public static function text(mixed $value, int $max, bool $required = false): string
    {
        if (!is_string($value) || !preg_match('//u', $value) || str_contains($value, "\0") || strlen($value) > $max * 4
            || ($required && trim($value) === '')) {
            throw new ValidationException('Texte invalide ou manquant.');
        }
        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
        if ($length > $max) {
            throw new ValidationException('Texte trop long (maximum ' . $max . ').');
        }
        return $value;
    }

    public static function boolean(mixed $value): bool
    {
        if (!in_array($value, [true, false, 0, 1, '0', '1'], true)) {
            throw new ValidationException('Valeur booléenne invalide.');
        }
        return in_array($value, [true, 1, '1'], true);
    }

    public static function ids(mixed $values): array
    {
        if (!is_array($values) || !array_is_list($values) || count($values) > 200) {
            throw new ValidationException('Liste de références invalide.');
        }
        foreach ($values as &$value) {
            if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1) {
                throw new ValidationException('Référence GLPI invalide.');
            }
            $value = (int) $value;
        }
        unset($value);
        return array_values(array_unique($values));
    }
}
