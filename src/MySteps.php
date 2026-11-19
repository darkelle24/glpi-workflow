<?php
declare(strict_types=1);
namespace WorkflowBuilder;

/** Personal work queue; native ticket rights and runtime step permissions still apply. */
final class MySteps
{
    public const STATES = ['available', 'progress', 'blocked'];
    public const FILTER_STATES = ['available', 'progress', 'blocked', 'waiting'];
    public const SORTS = ['deadline_asc' => 'Échéance la plus proche', 'deadline_desc' => 'Échéance la plus lointaine',
        'recent' => 'Tickets les plus récents'];

    public static function rows(int $ticketId = 0, string $query = '', string $state = ''): array
    {
        self::validateState($state, self::STATES);
        $items = [];
        foreach (self::instances($ticketId, $query) as $data) {
            $instance = $data['instance'];
            $name = $data['ticket_name'];
            $team = Assignees::forInstance($instance);
            $rows = array_column($data['steps'], null, 'step_key');
            foreach ($data['definition']['steps'] as $step) {
                $key = $step['id'];
                $current = $data['states'][$key];
                if (($state !== '' && $state !== $current)
                    || !self::eligible($data['definition'], $rows[$key], $current, $team, Engine::dependenciesReady($step, $data['states']))) { continue; }
                $items[] = ['ticket_id' => (int) $instance['tickets_id'], 'ticket_name' => $name,
                    'instance_id' => (int) $instance['id'], 'workflow' => $data['definition']['name'],
                    'step_key' => $key, 'title' => $step['title'], 'state' => $current];
            }
        }
        return $items;
    }

    /** One entry per ticket; completed work is attributed to its actual validator. */
    public static function tickets(int $ticketId = 0, string $query = '', string $state = '', string $sort = 'deadline_asc'): array
    {
        self::validateState($state, self::FILTER_STATES);
        if (!array_key_exists($sort, self::SORTS)) { throw new ValidationException('Tri invalide.'); }
        $tickets = [];
        $now = Store::now();
        $me = (int) \Session::getLoginUserID();
        foreach (self::instances($ticketId, $query) as $data) {
            $instance = $data['instance'];
            $id = (int) $instance['tickets_id'];
            $tickets[$id] ??= ['ticket_id' => $id, 'ticket_name' => $data['ticket_name'],
                'deadline' => $data['ticket_deadline'], 'overdue' => $data['ticket_deadline'] !== null && $data['ticket_deadline'] < $now, 'done' => 0,
                'remaining' => 0, 'actionable' => 0, 'states' => array_fill_keys(self::FILTER_STATES, 0), 'workflows' => []];
            $team = Assignees::forInstance($instance);
            $rows = array_column($data['steps'], null, 'step_key');
            $keys = [];
            foreach ($data['definition']['steps'] as $step) {
                $key = $step['id'];
                $row = $rows[$key];
                $current = $data['states'][$key];
                if ($current === 'done') {
                    if ((int) ($row['completed_by'] ?? 0) === $me) { $tickets[$id]['done']++; }
                    continue;
                }
                $actions = $current === 'blocked' ? ['start', 'complete', 'unblock'] : ['start', 'complete'];
                if (!in_array($current, self::FILTER_STATES, true) || !self::assigned($row, $team)
                    || !self::allowed($data['definition'], $row, $team, $actions)) { continue; }
                $keys[] = $key;
                $tickets[$id]['remaining']++;
                $tickets[$id]['states'][$current]++;
                if (self::eligible($data['definition'], $row, $current, $team, Engine::dependenciesReady($step, $data['states']))) {
                    $tickets[$id]['actionable']++;
                }
            }
            if ($keys) { $tickets[$id]['workflows'][(int) $instance['id']] = ['data' => $data, 'steps' => $keys]; }
        }
        $result = [];
        foreach ($tickets as $ticket) {
            if (!$ticket['remaining'] || ($state !== '' && !$ticket['states'][$state])) { continue; }
            $ticket['total'] = $ticket['done'] + $ticket['remaining'];
            $ticket['percent'] = (int) floor(100 * $ticket['done'] / $ticket['total']);
            $result[] = $ticket;
        }
        usort($result, static function (array $a, array $b) use ($sort): int {
            if ($sort !== 'recent') {
                // Missing deadlines stay last in both directions; compare dates before slicing pages.
                $missing = (int) ($a['deadline'] === null) <=> (int) ($b['deadline'] === null);
                if ($missing) { return $missing; }
                $date = strcmp($a['deadline'] ?? '', $b['deadline'] ?? '');
                if ($date) { return $sort === 'deadline_desc' ? -$date : $date; }
            }
            return $b['ticket_id'] <=> $a['ticket_id'];
        });
        return $result;
    }

    private static function validateState(string $state, array $states): void
    {
        if ($state !== '' && !in_array($state, $states, true)) {
            throw new ValidationException('État de filtre invalide.');
        }
    }

    private static function instances(int $ticketId, string $query): \Generator
    {
        Access::need('view');
        if ($ticketId) { Access::ticket($ticketId); }
        $query = trim($query);
        $number = preg_match('/^#?([0-9]+)$/D', $query, $match) ? (int) $match[1] : null;
        $where = $ticketId ? ['tickets_id' => $ticketId] : [];
        if ($number !== null) {
            if ($ticketId && $ticketId !== $number) { return; }
            $where['tickets_id'] = $number;
        }
        foreach (Store::rows('instances', $where, ['ORDER' => ['id DESC']]) as $instance) {
            try {
                $ticket = Access::instance($instance, true);
                if (in_array((int) $ticket->fields['status'], [\CommonITILObject::SOLVED, \CommonITILObject::CLOSED], true)) { continue; }
                $name = (string) $ticket->fields['name'];
                if ($query !== '' && $number === null && !preg_match('/' . preg_quote($query, '/') . '/iu', $name)) { continue; }
                $data = Instances::data((int) $instance['id']);
            } catch (ValidationException) { continue; }
            yield $data + ['ticket_name' => $name, 'ticket_deadline' => self::deadline($ticket->fields['time_to_resolve'] ?? null)];
        }
    }

    /** Native GLPI ticket resolution deadline; never use the launch-time snapshot. */
    private static function deadline(mixed $value): ?string
    {
        if (!is_string($value) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/D', $value)) { return null; }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));
        return $date && $date->format('Y-m-d H:i:s') === $value ? $value : null;
    }

    private static function eligible(array $definition, array $row, string $state, array $team, bool $canComplete): bool
    {
        if (!in_array($state, self::STATES, true)) { return false; }
        if (!self::assigned($row, $team)) { return false; }
        $actions = $state === 'blocked' ? ['unblock'] : ($canComplete ? ['start', 'complete'] : ['start']);
        return self::allowed($definition, $row, $team, $actions);
    }

    private static function assigned(array $row, array $team): bool
    {
        $me = (int) \Session::getLoginUserID();
        $operator = (int) ($row['started_by'] ?? 0);
        if ($operator && $operator !== $me) { return false; }
        // Administrative overrides must not turn another person's assignment into "my" work.
        if ((int) $row['users_id'] || (int) $row['groups_id']) {
            if ((int) $row['users_id'] !== $me && !in_array((int) $row['groups_id'], Access::groups(), true)) { return false; }
        } elseif (($team['users'] || $team['groups']) && !in_array($me, $team['users'], true)
            && !array_intersect(Access::groups(), $team['groups'])) { return false; }
        return true;
    }

    private static function allowed(array $definition, array $row, array $team, array $actions): bool
    {
        foreach ($actions as $action) {
            try { Access::step($definition, $row, $action, $team); return true; }
            catch (ValidationException) {}
        }
        return false;
    }
}
