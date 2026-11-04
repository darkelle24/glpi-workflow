<?php
declare(strict_types=1);
namespace WorkflowBuilder;

/** Instance-level team; explicit step assignments retain priority. */
final class Assignees
{
    public static function normalize(array $input): array
    {
        if (array_diff(array_keys($input), ['users', 'groups'])) {
            throw new ValidationException('Affectation du workflow invalide.');
        }
        return ['users' => Definition::ids($input['users'] ?? []), 'groups' => Definition::ids($input['groups'] ?? [])];
    }
    public static function fromInput(array $input): array
    {
        return self::normalize(['users' => $input['assigned_users'] ?? [], 'groups' => $input['assigned_groups'] ?? []]);
    }
    public static function check(array $team): void
    {
        foreach (['users' => 'User', 'groups' => 'Group'] as $key => $class) {
            foreach ($team[$key] as $id) {
                $item = new $class();
                if (!$item->can($id, READ) || !empty($item->fields['is_deleted'])
                    || ($key === 'users' && empty($item->fields['is_active']))) {
                    throw new ValidationException('Responsable de workflow inaccessible, supprimé ou inactif. Confirmer votre identité via le lien GLPI si une confirmation est demandée.');
                }
            }
        }
    }
    public static function forInstance(array $instance): array
    {
        return ['users' => empty($instance['assigned_users']) ? [] : Store::unpack($instance['assigned_users']),
            'groups' => empty($instance['assigned_groups']) ? [] : Store::unpack($instance['assigned_groups'])];
    }
    public static function names(array $team): string
    {
        $names = [];
        foreach ($team['users'] ?? [] as $id) { $names[] = View::participant((int) $id); }
        foreach ($team['groups'] ?? [] as $id) { $names[] = 'Groupe : ' . View::person('Group', (int) $id); }
        return $names ? implode(' · ', $names) : 'Selon les responsables des étapes et les permissions du modèle';
    }
    public static function show(array $team): void
    {
        $labels = ['users' => [], 'groups' => []];
        foreach (['users' => 'User', 'groups' => 'Group'] as $key => $class) {
            foreach ($team[$key] as $id) { $labels[$key][$id] = View::person($class, $id); }
        }
        echo '<fieldset class="wb-assignees card p-3 my-3" data-wb-assignees data-assignees="' . View::e(Store::json($team)) . '" data-assignee-labels="' . View::e(Store::json($labels)) . '"><legend>Affectation du workflow</legend>';
        echo '<p>Choisir des utilisateurs et/ou des groupes pour les étapes sans responsable explicite. Les permissions du modèle et les droits du ticket restent nécessaires.</p><div data-assignees-ui></div><div data-assignees-inputs>';
        foreach (['users', 'groups'] as $key) {
            foreach ($team[$key] as $id) { echo '<input type="hidden" name="assigned_' . $key . '[]" value="' . (int) $id . '">'; }
        }
        echo '</div><noscript>Activer JavaScript pour sélectionner les responsables.</noscript></fieldset>';
    }
}
