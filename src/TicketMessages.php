<?php
declare(strict_types=1);
namespace WorkflowBuilder;

/** Native GLPI followups written in the same transaction as the corresponding transition. */
final class TicketMessages
{
    private static function button(string $url): string
    {
        return '<p><a href="' . View::e($url) . '" style="display:inline-block;padding:10px 16px;background-color:#206bc4;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold">Ouvrir le workflow du ticket</a></p>';
    }
    private static function progress(array $progress): string
    {
        return '<p><strong>Avancement : ' . (int) $progress['percent'] . ' %</strong> — ' . (int) $progress['done'] . ' / '
            . (int) $progress['total'] . ' étapes obligatoires terminées</p>';
    }
    public static function values(array $fields, array $values, int $ticket = 0, array $context = []): string
    {
        $rows = '';
        foreach ($fields as $field) {
            $value = $values[$field['id']] ?? null;
            $visible = Condition::evaluate($field['condition'], $context);
            $context['self.' . $field['id']] = $visible ? $value : null;
            if (!$visible || $field['type'] === 'section' || $value === null || $value === '' || $value === []) { continue; }
            $rows .= '<tr><th scope="row" style="text-align:left;padding:8px;border:1px solid #d8dee7">'
                . View::e($field['label']) . '</th><td style="padding:8px;border:1px solid #d8dee7">'
                . FormView::value($field, $value, $ticket ?: null) . '</td></tr>';
        }
        return $rows ? '<table style="border-collapse:collapse;width:100%"><tbody>' . $rows . '</tbody></table>' : '<p>Aucune donnée renseignée.</p>';
    }
    public static function opening(array $definition, array $form, array $progress, array $team, int $version, string $key): string
    {
        $description = trim($definition['ticket_description']) === '{{summary}}'
            ? $definition['description'] : Engine::substitute($definition['ticket_description'], $definition, $form);
        return '<div style="border:1px solid #d8dee7;border-left:5px solid #206bc4;padding:16px;border-radius:8px">'
            . '<p><strong>WORKFLOW</strong> · Version ' . $version . '</p><h2>' . View::e($definition['name']) . '</h2>'
            . '<p>Ce ticket est piloté par un workflow. Les étapes, procédures et validations sont accessibles depuis le bouton ci-dessous.</p>'
            . '<p><strong>Lancé par :</strong> ' . View::e(View::participant((int) \Session::getLoginUserID())) . '</p>'
            . '<p><strong>Responsables du workflow :</strong> ' . View::e(Assignees::names($team)) . '</p>'
            . self::progress($progress) . ($description !== '' ? '<h3>Demande</h3><p>' . nl2br(View::e($description)) . '</p>' : '')
            . '<h3>Informations du lancement</h3>' . self::values($definition['fields'], $form, 0, Fields::context($form))
            . self::button(Http::url('instance.php', ['request_key' => $key])) . '</div>';
    }
    public static function post(int $ticket, string $content): void
    {
        $input = ['itemtype' => 'Ticket', 'items_id' => $ticket, 'content' => $content,
            'users_id' => (int) \Session::getLoginUserID(), 'is_private' => 1, '_no_reopen' => 1];
        $followup = new \ITILFollowup();
        if (!$followup->can(-1, CREATE, $input)) {
            throw new ValidationException('Les messages automatiques nécessitent le droit GLPI d’ajouter un suivi à ce ticket.');
        }
        if (!$followup->add($input)) {
            throw new ValidationException('Le suivi automatique GLPI n’a pas pu être enregistré. L’action du workflow a été annulée.');
        }
    }
    public static function event(array $data, array $step, array $row, string $event, string $comment = ''): void
    {
        $option = 'notify_' . $event;
        if (!($step[$option] ?? true)) { return; }
        $labels = ['available' => 'Étape disponible', 'taken' => 'Étape prise en charge', 'done' => 'Étape terminée'];
        $id = (int) $data['instance']['id'];
        $ticket = (int) $data['instance']['tickets_id'];
        $url = Http::url('instance.php', ['id' => $id]) . '#wb-step-' . rawurlencode($step['id']);
        $content = '<div style="border-left:4px solid #206bc4;padding:12px"><p><strong>WORKFLOW · '
            . View::e($labels[$event]) . '</strong></p><h3>' . View::e($step['title']) . '</h3><p>'
            . View::e($data['definition']['name']) . ' · Version ' . (int) $data['version']['number'] . '</p>'
            . '<p><strong>Action effectuée par :</strong> ' . View::e(View::participant((int) \Session::getLoginUserID())) . '</p>'
            . '<p><strong>Pris en charge par :</strong> ' . (!empty($row['started_by']) ? View::e(View::participant((int) $row['started_by'])) : 'Non prise en charge') . '</p>';
        if ($event === 'available') {
            $team = Assignees::forInstance($data['instance']);
            $responsibles = ($row['users_id'] || $row['groups_id'])
                ? View::participant((int) $row['users_id']) . ' · ' . View::person('Group', (int) $row['groups_id'])
                : Assignees::names($team);
            $content .= '<p><strong>Responsables :</strong> ' . View::e($responsibles) . '</p>';
        }
        if ($event === 'done') {
            $content .= '<p><strong>Terminée par :</strong> ' . View::e(View::participant((int) $row['completed_by'])) . '</p>'
                . '<h4>Données enregistrées à la fin de l’étape</h4>'
                . self::values($step['fields'], $data['values'][$step['id']] ?? [], $ticket, Fields::context($data['values'][''] ?? []));
            if ($comment !== '') { $content .= '<p><strong>Commentaire :</strong> ' . nl2br(View::e($comment)) . '</p>'; }
        }
        $content .= self::progress($data['progress']) . self::button($url) . '</div>';
        self::post($ticket, $content);
    }
    public static function completed(array $data, ?string $previous): void
    {
        if (!($data['definition']['notify_summary'] ?? true) || $data['progress']['status'] !== 'completed' || $previous === 'completed') { return; }
        $definition = $data['definition'];
        $instance = $data['instance'];
        $ticket = (int) $instance['tickets_id'];
        $content = '<div style="border-left:4px solid #2b8a3e;padding:16px"><h2>Workflow terminé — récapitulatif</h2><h3>'
            . View::e($definition['name']) . '</h3><p>Version ' . (int) $data['version']['number']
            . ' · Lancé le ' . View::e($instance['date_creation']) . ' par ' . View::e(View::participant((int) $instance['users_id']))
            . ' · Terminé le ' . View::e($instance['date_completion'] ?? Store::now()) . '</p>'
            . '<p><strong>Responsables du workflow :</strong> ' . View::e(Assignees::names(Assignees::forInstance($instance))) . '</p>'
            . self::progress($data['progress']) . '<h3>Informations du lancement</h3>'
            . self::values($definition['fields'], $data['values'][''] ?? [], $ticket, Fields::context($data['values'][''] ?? []));
        if (isset($data['values']['_ticket']['options'])) { $content .= TicketOptions::summary($data['values']['_ticket']['options']); }
        $rows = array_column($data['steps'], null, 'step_key');
        foreach ($definition['steps'] as $step) {
            $row = $rows[$step['id']];
            $content .= '<h3>' . View::e($step['title']) . '</h3><p>' . View::e(View::STATES[$data['states'][$step['id']]])
                . ($step['required'] ? ' · Obligatoire' : ' · Facultative') . '</p>'
                . '<p>Pris en charge par : ' . View::e(View::participant((int) ($row['started_by'] ?? 0)))
                . ' · Début : ' . View::e($row['started_at'] ?? '—') . ' · Fin : ' . View::e($row['completed_at'] ?? '—')
                . ' · Validée par : ' . View::e(View::participant((int) ($row['completed_by'] ?? 0))) . '</p>';
            if ($data['states'][$step['id']] !== 'na') {
                $content .= self::values($step['fields'], $data['values'][$step['id']] ?? [], $ticket, Fields::context($data['values'][''] ?? []));
            }
        }
        $comments = Store::rows('history', ['instances_id' => (int) $instance['id']], ['ORDER' => ['id']]);
        foreach ($comments as $event) {
            if (trim($event['comment'] ?? '') === '') { continue; }
            $content .= '<p><strong>Commentaire — ' . View::e($event['step_key'] ?? 'Workflow') . ' · '
                . View::e(View::participant((int) $event['users_id'])) . ' · ' . View::e($event['date_creation']) . ' :</strong> '
                . nl2br(View::e($event['comment'])) . '</p>';
        }
        self::post($ticket, $content . self::button(Http::url('instance.php', ['id' => (int) $instance['id']])) . '</div>');
    }
    public static function activated(array $data, array $previous): void
    {
        $rows = array_column($data['steps'], null, 'step_key');
        foreach ($data['definition']['steps'] as $step) {
            if ($data['states'][$step['id']] === 'available' && ($previous[$step['id']] ?? null) !== 'available') {
                self::event($data, $step, $rows[$step['id']], 'available');
            }
        }
    }
}
