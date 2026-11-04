<?php
declare(strict_types=1);
namespace WorkflowBuilder;
final class Access
{
    public const RIGHTS = [
        'plugin_workflowbuilder_template' => 'Voir, créer et modifier les modèles',
        'plugin_workflowbuilder_publish' => 'Publier et archiver les modèles',
        'plugin_workflowbuilder_view' => 'Voir les workflows des tickets accessibles',
        'plugin_workflowbuilder_launch' => 'Lancer un workflow',
        'plugin_workflowbuilder_execute' => 'Renseigner et démarrer les étapes',
        'plugin_workflowbuilder_validate' => 'Terminer les étapes',
        'plugin_workflowbuilder_reopen' => 'Réouvrir et débloquer les étapes',
        'plugin_workflowbuilder_admin' => 'Administrer les droits et remplacer les responsables',
    ];
    public static function reload(): void
    {
        global $DB;
        if (!isset($_SESSION['glpiactiveprofile']['id']) || !isset($DB) || !$DB->tableExists('glpi_profilerights')) {
            return;
        }
        foreach (array_keys(self::RIGHTS) as $name) {
            $_SESSION['glpiactiveprofile'][$name] = 0;
        }
        foreach ($DB->request(['FROM' => 'glpi_profilerights', 'WHERE' => ['profiles_id' => (int) $_SESSION['glpiactiveprofile']['id'], 'name' => array_keys(self::RIGHTS)]]) as $row) {
            $_SESSION['glpiactiveprofile'][$row['name']] = (int) $row['rights'];
        }
    }
    public static function has(string $right, int $bit = READ): bool
    {
        return \Session::haveRight('plugin_workflowbuilder_' . $right, $bit);
    }
    public static function need(string $right, int $bit = READ): void
    {
        if (!self::has($right, $bit)) {
            throw new ValidationException('Droit Workflow Builder insuffisant : ' . $right);
        }
    }
    public static function canBootstrapRights(): bool
    {
        global $DB;
        if (!\Session::haveRight('config', UPDATE)) { return false; }
        foreach ($DB->request(['FROM' => 'glpi_profilerights', 'WHERE' => ['name' => array_keys(self::RIGHTS), 'rights' => ['>', 0]], 'LIMIT' => 1]) as $unused) { return false; }
        return true;
    }
    public static function groups(): array
    {
        return array_map('intval', $_SESSION['glpigroups'] ?? []);
    }
    public static function restrictions(array $definition, string $scope): bool
    {
        $groups = $definition[$scope . '_groups'];
        $users = $definition[$scope . '_users'];
        return (!$groups && !$users) || in_array((int) \Session::getLoginUserID(), $users, true)
            || (bool) array_intersect(self::groups(), $groups);
    }
    public static function template(array $template, bool $manage = false): array
    {
        self::need('template');
        if (!\Session::haveAccessToEntity((int) $template['entities_id'], (bool) $template['is_recursive'])) {
            throw new ValidationException('Ce modèle appartient à une entité inaccessible.');
        }
        $definition = Definition::decode($template['draft']);
        if ($manage && !self::restrictions($definition, 'manage') && !self::has('admin', UPDATE)) {
            throw new ValidationException('Vous ne faites pas partie des gestionnaires de ce modèle.');
        }
        return $definition;
    }
    public static function applicable(array $template, int $entity): bool
    {
        return (int) $template['entities_id'] === $entity
            || ((bool) $template['is_recursive'] && in_array((int) $template['entities_id'], array_map('intval', getAncestorsOf('glpi_entities', $entity)), true));
    }
    public static function launch(array $template, array $definition, int $entity): void
    {
        self::need('launch', CREATE);
        if ($template['state'] !== 'active' || !$template['active_versions_id']
            || !\Session::haveAccessToEntity($entity) || !self::applicable($template, $entity)
            || !self::restrictions($definition, 'launch')) {
            throw new ValidationException('Ce modèle n’est pas disponible pour cet utilisateur et cette entité.');
        }
    }
    public static function ticket(int $id, bool $write = false): \Ticket
    {
        $ticket = new \Ticket();
        if (!$ticket->can($id, $write ? UPDATE : READ) || !empty($ticket->fields['is_deleted'])) {
            throw new ValidationException('Ticket introuvable ou accès refusé.');
        }
        return $ticket;
    }
    public static function instance(array $instance, bool $write = false): \Ticket
    {
        self::need('view');
        $ticket = self::ticket((int) $instance['tickets_id'], $write);
        if (!\Session::haveAccessToEntity((int) $ticket->fields['entities_id'])) {
            throw new ValidationException('Entité du ticket inaccessible.');
        }
        return $ticket;
    }
    public static function step(array $definition, array $row, string $action, array $team = []): void
    {
        $right = match ($action) {
            'complete' => 'validate',
            'reopen', 'unblock' => 'reopen',
            default => 'execute',
        };
        self::need($right, UPDATE);
        $operator = (int) ($row['started_by'] ?? 0);
        $me = (int) \Session::getLoginUserID();
        if ($action === 'release' && (!$operator || $operator !== $me)) {
            throw new ValidationException('Seule la personne ayant pris cette étape en charge peut la libérer.');
        }
        if ($operator && $operator !== $me && in_array($action, ['start', 'save', 'complete', 'block', 'release'], true)) {
            throw new ValidationException('Cette étape est déjà prise en charge par un autre technicien. Il doit la libérer, ou un administrateur doit la réaffecter.');
        }
        if (self::has('admin', UPDATE)) {
            return;
        }
        if (!self::restrictions($definition, 'execute')) {
            throw new ValidationException('Vous ne faites pas partie des exécutants de ce workflow.');
        }
        $assigned = (int) $row['users_id'] || (int) $row['groups_id'];
        if (!$assigned && (!empty($team['users']) || !empty($team['groups']))
            && !in_array($me, $team['users'] ?? [], true) && !array_intersect(self::groups(), $team['groups'] ?? [])) {
            throw new ValidationException('Cette étape est réservée aux responsables du workflow.');
        }
        if ($assigned && (int) $row['users_id'] !== (int) \Session::getLoginUserID()
            && !in_array((int) $row['groups_id'], self::groups(), true)) {
            throw new ValidationException('Cette étape est affectée à un autre responsable.');
        }
    }
    public static function definitionReferences(array $definition): void
    {
        foreach (['launch','execute','manage'] as $scope) {
            foreach (['users'=>'User','groups'=>'Group'] as $kind=>$class) {
                foreach ($definition[$scope . '_' . $kind] as $id) {
                    if (!(new $class())->can($id, READ)) { throw new ValidationException('Référence de permission GLPI inaccessible : ' . $id); }
                }
            }
        }
        foreach ($definition['steps'] as $step) {
            foreach (['users_id'=>'User','groups_id'=>'Group'] as $key=>$class) {
                if ($step[$key] && !(new $class())->can($step[$key], READ)) { throw new ValidationException('Responsable GLPI inaccessible. Confirmer votre identité si GLPI le demande.'); }
            }
        }
    }

    public static function references(array $fields, array $values, ?int $ticketId = null): void
    {
        foreach ($fields as $field) {
            $type = $field['type'];
            $id = $values[$field['id']] ?? null;
            if ($id === null || !isset(Fields::REFERENCES[$type])) {
                continue;
            }
            $class = Fields::REFERENCES[$type];
            $object = new $class();
            if ($type === 'document' && !$ticketId) {
                throw new ValidationException('Ajouter les pièces jointes dans une étape après création du ticket.');
            }
            $allowed = $type === 'document'
                ? $object->getFromDB($id) && $object->canViewFile($ticketId ? ['tickets_id' => $ticketId] : [])
                : ($type === 'entity' ? \Session::haveAccessToEntity((int) $id) : $object->can($id, READ));
            if (!$allowed || !empty($object->fields['is_deleted']) || ($type === 'user' && empty($object->fields['is_active']))) {
                throw new ValidationException($field['label'] . ' : référence GLPI inaccessible, supprimée ou inactive.');
            }
            if ($type === 'document' && $ticketId) {
                global $DB;
                $found = false;
                foreach ($DB->request(['FROM' => 'glpi_documents_items', 'WHERE' => ['documents_id' => $id, 'itemtype' => 'Ticket', 'items_id' => $ticketId], 'LIMIT' => 1]) as $unused) {
                    $found = true;
                }
                if (!$found) {
                    throw new ValidationException('Joindre d’abord ce document au ticket avec l’interface GLPI.');
                }
            }
        }
    }
}
