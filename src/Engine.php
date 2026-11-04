<?php
declare(strict_types=1);
namespace WorkflowBuilder;

/** Pure state machine. Persistent services lock the instance before applying it. */
final class Engine
{
    public static function states(array $definition, array $form, array $stored = []): array
    {
        $context = Fields::context($form);
        $states = [];
        foreach ($definition['steps'] as $step) {
            $id = $step['id'];
            $visible = Condition::evaluate($step['condition'], $context);
            $enabled = true;
            $blocked = false;
            foreach ($definition['rules'] as $rule) {
                if ($rule['step'] !== $id) {
                    continue;
                }
                $match = Condition::evaluate($rule['when'], $context);
                match ($rule['action']) {
                    'show' => $visible = $visible && $match,
                    'enable' => $enabled = $enabled && $match,
                    'block' => $blocked = $blocked || $match,
                };
            }
            $states[$id] = !$visible ? 'na' : (!$enabled || $blocked ? 'blocked' : ($stored[$id] ?? $step['initial_state']));
        }
        // Evaluate dependencies on the final applicability of every step, including forward references.
        foreach ($definition['steps'] as $step) {
            $id = $step['id'];
            if (in_array($states[$id], ['na', 'blocked', 'done', 'progress'], true)) {
                continue;
            }
            $ready = self::dependenciesReady($step, $states);
            $states[$id] = $ready || $step['dependency_mode'] === 'complete' ? 'available' : 'waiting';
        }
        return $states;
    }

    public static function dependenciesReady(array $step, array $states): bool
    {
        foreach ($step['depends_on'] as $dep) {
            if (!in_array($states[$dep] ?? '', ['done', 'na'], true)) {
                return false;
            }
        }
        return true;
    }

    public static function transition(array $definition, array $form, array $stored, string $id, string $action, array $input): array
    {
        $states = self::states($definition, $form, $stored);
        $steps = array_column($definition['steps'], null, 'id');
        if (!isset($steps[$id]) || !isset($states[$id])) {
            throw new ValidationException('Étape introuvable.');
        }
        $step = $steps[$id];
        $old = $states[$id];
        if (!in_array($action, ['start', 'save', 'complete', 'reopen', 'block', 'unblock', 'comment', 'release'], true)) {
            throw new ValidationException('Action inconnue.');
        }
        if (in_array($old, ['na', 'waiting'], true)) {
            throw new ValidationException('Cette étape n’est pas disponible.');
        }
        if ($old === 'blocked' && !in_array($action, ['unblock', 'comment', 'release'], true)) {
            throw new ValidationException('Débloquer l’étape avant de continuer.');
        }
        if ($old === 'done' && !in_array($action, ['reopen', 'comment'], true)) {
            throw new ValidationException('L’étape est déjà terminée.');
        }
        if ($action === 'reopen') {
            if ($old !== 'done') {
                throw new ValidationException('Seule une étape terminée peut être réouverte.');
            }
            // No completed/started descendant may silently lose its prerequisite.
            $queue = [$id];
            $visited = [];
            while ($queue) {
                $parent = array_shift($queue);
                if (isset($visited[$parent])) {
                    continue;
                }
                $visited[$parent] = true;
                foreach ($steps as $child) {
                    if (in_array($parent, $child['depends_on'], true)) {
                        if (in_array($states[$child['id']], ['done', 'progress', 'blocked'], true)) {
                            throw new ValidationException('Réouvrir d’abord les étapes dépendantes déjà engagées.');
                        }
                        $queue[] = $child['id'];
                    }
                }
            }
        }
        if ($action === 'unblock' && $old !== 'blocked') {
            throw new ValidationException('Cette étape n’est pas bloquée.');
        }
        if ($action === 'release' && !in_array($old, ['progress', 'blocked'], true)) {
            throw new ValidationException('Seule une étape en cours ou bloquée peut être libérée.');
        }
        if ($action === 'complete' && !self::dependenciesReady($step, $states)) {
            throw new ValidationException('Terminer d’abord les étapes préalables.');
        }
        $values = in_array($action, ['save', 'complete'], true)
            ? Fields::values($step['fields'], $input, Fields::context($form), $action === 'complete') : null;
        $new = match ($action) {
            'start', 'save', 'reopen' => 'progress',
            'complete' => 'done',
            'block' => 'blocked',
            'unblock' => 'available',
            'release' => $old === 'blocked' ? 'blocked' : 'available',
            default => $old,
        };
        $stored[$id] = $new;
        $newStates = self::states($definition, $form, $stored);
        // A rule-driven block cannot be bypassed by the unblock action.
        if ($action === 'unblock' && $newStates[$id] === 'blocked') {
            throw new ValidationException('Une règle du modèle bloque cette étape.');
        }
        return ['old' => $old, 'state' => $newStates[$id], 'states' => $newStates, 'values' => $values,
            'progress' => self::progress($definition, $newStates)];
    }

    public static function progress(array $definition, array $states): array
    {
        $total = $done = $optional = $blocked = 0;
        foreach ($definition['steps'] as $step) {
            $state = $states[$step['id']];
            if ($state === 'na') {
                continue;
            }
            if ($state === 'blocked') {
                $blocked++;
            }
            if (!$step['required']) {
                $optional++;
                continue;
            }
            $total++;
            if ($state === 'done') {
                $done++;
            }
        }
        return ['total' => $total, 'done' => $done, 'remaining' => $total - $done, 'optional' => $optional,
            'blocked' => $blocked, 'percent' => $total ? (int) floor(100 * $done / $total) : 100,
            'status' => $done === $total ? 'completed' : ($blocked ? 'blocked' : 'running')];
    }

    public static function substitute(string $template, array $definition, array $form): string
    {
        $summary = [];
        foreach ($definition['fields'] as $field) {
            if ($field['type'] !== 'section' && ($form[$field['id']] ?? null) !== null) {
                $summary[] = $field['label'] . ' : ' . self::printValue($form[$field['id']]);
            }
        }
        $values = $form + ['workflow' => $definition['name'], 'summary' => implode("\n", $summary)];
        return preg_replace_callback('/{{\s*([^{}]+?)\s*}}/', static fn($m) => self::printValue($values[$m[1]] ?? ''), $template);
    }

    public static function printValue(mixed $value): string
    {
        return is_array($value) ? implode(', ', $value) : (is_bool($value) ? ($value ? 'Oui' : 'Non') : (string) $value);
    }
}
