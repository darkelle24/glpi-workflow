<?php
declare(strict_types=1);
namespace WorkflowBuilder;
final class Instances
{
    public static function launch(int $templateId, int $ticketId, int $entity, array $input, string $key, int $expectedVersion = 0, array $assignees = []): int
    {
        Access::need('view');
        Access::need('launch', CREATE);
        if (!preg_match('/^[a-f0-9]{64}$/D', $key)) {
            throw new ValidationException('Clé de lancement invalide.');
        }
        if ($ticketId) { TicketMutex::acquire($ticketId); }
        return Store::transaction(function () use ($templateId, $ticketId, $entity, $input, $key, $expectedVersion, $assignees) {
            $template = Store::lock('templates', $templateId);
            $existing = Store::rows('instances', ['request_key' => $key], ['LIMIT' => 1]);
            if ($existing) {
                Access::instance($existing[0]);
                if ((int) $existing[0]['users_id'] !== (int) \Session::getLoginUserID()
                    || (int) $existing[0]['templates_id'] !== $templateId
                    || ($ticketId && (int) $existing[0]['tickets_id'] !== $ticketId)) {
                    throw new ValidationException('Cette clé de lancement est déjà utilisée.');
                }
                return (int) $existing[0]['id'];
            }
            if (!$template['active_versions_id']) {
                throw new ValidationException('Le modèle n’est pas publié.');
            }
            if ($expectedVersion && (int) $template['active_versions_id'] !== $expectedVersion) {
                throw new ValidationException('Le modèle a été republié. Recharger le formulaire avant de lancer.');
            }
            [$version, $definition] = Templates::version((int) $template['active_versions_id']);
            if ($ticketId) {
                $ticket = Access::ticket($ticketId, true);
                $entity = (int) $ticket->fields['entities_id'];
                if (in_array((int) $ticket->fields['status'], [\CommonITILObject::SOLVED, \CommonITILObject::CLOSED], true)) {
                    throw new ValidationException('Réouvrir le ticket avant de lancer un workflow.');
                }
            }
            Access::launch($template, $definition, $entity);
            if (!($ticketId ? $definition['allow_attach'] : $definition['allow_create'])) {
                throw new ValidationException('Ce mode de lancement est désactivé.');
            }
            $assignees = Assignees::normalize($assignees);
            Assignees::check($assignees);
            $form = Fields::formValues($definition['fields'], $input);
            Access::references($definition['fields'], $form, $ticketId ?: null);
            $states = Engine::states($definition, $form);
            $progress = Engine::progress($definition, $states);
            $newTicket = !$ticketId;
            if (!$ticketId) {
                $title = trim(Engine::substitute($definition['ticket_title'], $definition, $form));
                Definition::text($title, 255, true);
                $description = TicketMessages::opening($definition, $form, $progress, $assignees, (int) $version['number'], $key);
                $ticketInput = ['name' => $title, 'content' => $description,
                    'entities_id' => $entity, '_users_id_requester' => (int) \Session::getLoginUserID(),
                    'status' => \CommonITILObject::INCOMING];
                $ticket = new \Ticket();
                if (!$ticket->can(0, CREATE, $ticketInput)) {
                    throw new ValidationException('Création de ticket interdite.');
                }
                $ticketId = (int) $ticket->add($ticketInput);
                if (!$ticketId) {
                    throw new ValidationException('GLPI n’a pas pu créer le ticket. Vérifier ses champs obligatoires et règles métier.');
                }
                $ticket = Access::ticket($ticketId);
                $entity = (int) $ticket->fields['entities_id'];
                Access::launch($template, $definition, $entity);
                if (in_array((int) $ticket->fields['status'], [\CommonITILObject::SOLVED, \CommonITILObject::CLOSED], true)) {
                    throw new ValidationException('Une règle GLPI a résolu le ticket pendant la création.');
                }
            } else {
                global $DB;
                $DB->doQuery('SELECT id FROM glpi_tickets WHERE id = ' . $ticketId . ' FOR UPDATE');
                $ticket = Access::ticket($ticketId, true);
                $entity = (int) $ticket->fields['entities_id'];
                Access::launch($template, $definition, $entity);
                if (in_array((int) $ticket->fields['status'], [\CommonITILObject::SOLVED, \CommonITILObject::CLOSED], true)) {
                    throw new ValidationException('Le ticket vient d’être résolu ou clôturé.');
                }
            }
            $now = Store::now();
            $instance = Store::insert('instances', ['templates_id' => $templateId, 'versions_id' => $version['id'],
                'tickets_id' => $ticketId, 'entities_id' => $entity, 'users_id' => (int) \Session::getLoginUserID(),
                'request_key' => $key, 'status' => $progress['status'], 'revision' => 1,
                'assigned_users' => Store::json($assignees['users']), 'assigned_groups' => Store::json($assignees['groups']),
                'date_creation' => $now, 'date_mod' => $now, 'date_completion' => $progress['status'] === 'completed' ? $now : null]);
            self::putValues($instance, '', $form);
            foreach ($definition['steps'] as $step) {
                Store::insert('stepinstances', ['instances_id' => $instance, 'step_key' => $step['id'],
                    'state' => $states[$step['id']], 'users_id' => $step['users_id'], 'groups_id' => $step['groups_id']]);
            }
            Store::audit($instance, null, 'launch', null, $progress['status']);
            if (!$newTicket) {
                TicketMessages::post($ticketId, TicketMessages::opening($definition, $form, $progress, $assignees, (int) $version['number'], $key));
            }
            TicketMessages::activated(self::data($instance), []);
            return $instance;
        });
    }
    public static function data(int $id): array
    {
        $instance = Store::one('instances', $id);
        Access::instance($instance);
        [$version, $definition] = Templates::version((int) $instance['versions_id']);
        $steps = Store::rows('stepinstances', ['instances_id' => $id]);
        $values = [];
        foreach (Store::rows('values', ['instances_id' => $id]) as $row) {
            $values[$row['step_key']][$row['field_key']] = json_decode($row['payload'], true, 32, JSON_THROW_ON_ERROR);
        }
        $states = Engine::states($definition, $values[''] ?? [], array_column($steps, 'state', 'step_key'));
        return compact('instance', 'version', 'definition', 'steps', 'values', 'states') + ['progress' => Engine::progress($definition, $states)];
    }
    public static function act(int $id, string $stepKey, string $action, array $input, int $revision, string $comment = '', int $user = 0, int $group = 0): void
    {
        $before = Store::one('instances', $id);
        Access::instance($before, true);
        TicketMutex::acquire((int) $before['tickets_id']);
        Store::transaction(function () use ($id, $stepKey, $action, $input, $revision, $comment, $user, $group) {
            $instance = Store::lock('instances', $id);
            $ticket = Access::instance($instance, true);
            if (in_array((int) $ticket->fields['status'], [\CommonITILObject::SOLVED, \CommonITILObject::CLOSED], true)) {
                throw new ValidationException('Réouvrir le ticket avant de modifier ses étapes.');
            }
            if ((int) $instance['revision'] !== $revision) {
                throw new ValidationException('Les données ont changé. Recharger le ticket avant de réessayer.');
            }
            $data = self::data($id);
            $rows = array_column($data['steps'], null, 'step_key');
            if (!isset($rows[$stepKey])) {
                throw new ValidationException('Étape inconnue.');
            }
            $row = $rows[$stepKey];
            $comment = Definition::text($comment, 2000);
            if (in_array($action, ['block', 'unblock', 'reopen', 'comment', 'assign'], true) && trim($comment) === '') {
                throw new ValidationException('Un commentaire est obligatoire pour cette action.');
            }
            if ($action === 'assign') {
                Access::need('admin', UPDATE);
                foreach (['User' => $user, 'Group' => $group] as $class => $reference) {
                    if ($reference < 0 || ($reference && !(new $class())->can($reference, READ))) {
                        throw new ValidationException('Responsable inaccessible.');
                    }
                }
                $assignment = ['users_id' => $user, 'groups_id' => $group];
                if ($row['state'] !== 'done') {
                    $assignment += ['started_by' => null, 'started_at' => null];
                    if ($row['state'] === 'progress') { $assignment['state'] = 'available'; }
                }
                Store::update('stepinstances', (int) $row['id'], $assignment);
                Store::audit($id, $stepKey, 'assign', $row['state'], $assignment['state'] ?? $row['state'], array_keys($assignment), $comment);
                Store::update('instances', $id, ['revision' => $revision + 1, 'date_mod' => Store::now()]);
                TicketMessages::activated(self::data($id), $data['states']);
                return;
            }
            Access::step($data['definition'], $row, $action, Assignees::forInstance($instance));
            $result = Engine::transition($data['definition'], $data['values'][''] ?? [],
                array_column($data['steps'], 'state', 'step_key'), $stepKey, $action, $input);
            $definitionStep = array_column($data['definition']['steps'], null, 'id')[$stepKey];
            if ($result['values'] !== null) {
                Access::references($definitionStep['fields'], $result['values'], (int) $instance['tickets_id']);
                self::putValues($id, $stepKey, $result['values']);
            }
            foreach ($data['steps'] as $item) {
                $next = $result['states'][$item['step_key']];
                if ($next !== $item['state'] || $item['step_key'] === $stepKey) {
                    $update = ['state' => $next];
                    if ($item['step_key'] === $stepKey) {
                        if (in_array($action, ['start', 'save', 'complete', 'reopen'], true)
                            && (empty($item['started_by']) || $action === 'reopen')) {
                            $update['started_by'] = (int) \Session::getLoginUserID();
                            $update['started_at'] = Store::now();
                        }
                        if ($action === 'release') {
                            $update['started_by'] = null;
                            $update['started_at'] = null;
                        }
                        if ($action === 'complete') {
                            $update['completed_at'] = Store::now();
                            $update['completed_by'] = (int) \Session::getLoginUserID();
                        } elseif ($action === 'reopen') {
                            $update['completed_at'] = null;
                            $update['completed_by'] = null;
                        }
                    }
                    Store::update('stepinstances', (int) $item['id'], $update);
                    if ($item['step_key'] !== $stepKey && $next !== $item['state']) {
                        Store::audit($id, $item['step_key'], 'dependency', $item['state'], $next);
                    }
                }
            }
            $status = $result['progress']['status'];
            Store::update('instances', $id, ['status' => $status, 'revision' => $revision + 1,
                'date_mod' => Store::now(), 'date_completion' => $status === 'completed' ? ($instance['date_completion'] ?? Store::now()) : null]);
            $changed = [];
            if (in_array($action, ['start', 'save', 'complete', 'reopen'], true)
                && (empty($row['started_by']) || $action === 'reopen')) { $changed[] = 'started_by'; }
            if ($action === 'release') { $changed[] = 'started_by'; }
            foreach ($result['values'] ?? [] as $key => $value) {
                if (($data['values'][$stepKey][$key] ?? null) !== $value) {
                    $changed[] = $key;
                }
            }
            Store::audit($id, $stepKey, $action, $result['old'], $result['state'], $changed, $comment);
            if ($instance['status'] !== $status) {
                Store::audit($id, null, 'progress', $instance['status'], $status);
            }
            $after = self::data($id);
            $updatedRow = array_column($after['steps'], null, 'step_key')[$stepKey];
            if (in_array($action, ['start', 'save', 'complete', 'reopen'], true)
                && (empty($row['started_by']) || $action === 'reopen')) {
                TicketMessages::event($after, $definitionStep, $updatedRow, 'taken');
            }
            if ($action === 'complete') { TicketMessages::event($after, $definitionStep, $updatedRow, 'done', $comment); }
            TicketMessages::activated($after, $data['states']);
        });
    }
    private static function putValues(int $id, string $step, array $values): void
    {
        global $DB;
        foreach ($values as $field => $value) {
            $where = ['instances_id' => $id, 'step_key' => $step, 'field_key' => $field];
            $DB->updateOrInsert(Store::table('values'), ['payload' => Store::json($value)], $where);
        }
    }
}
