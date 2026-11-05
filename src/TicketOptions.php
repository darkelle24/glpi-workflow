<?php
declare(strict_types=1);
namespace WorkflowBuilder;

/** Native ticket creation options. Existing tickets are never changed by this form. */
final class TicketOptions
{
    public static function normalize(array $input): array
    {
        $defaults = ['type' => 2, 'urgency' => 3, 'impact' => 3, 'time_to_resolve' => '',
            'requester_users' => [], 'requester_groups' => [], 'assign_users' => [], 'assign_groups' => [],
            'observer_users' => [], 'observer_groups' => []];
        if (array_diff(array_keys($input), array_keys($defaults))) { throw new ValidationException('Paramètres de ticket inconnus.'); }
        $input += $defaults;
        foreach (['type' => 2, 'urgency' => 5, 'impact' => 5] as $key => $max) {
            $input[$key] = Http::integer($input, $key);
            if ($input[$key] < 1 || $input[$key] > $max) { throw new ValidationException('Paramètre de ticket invalide : ' . $key); }
        }
        $date = Http::string($input, 'time_to_resolve');
        if ($date !== '') {
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $date);
            if (!$parsed || $parsed->format('Y-m-d\TH:i') !== $date) { throw new ValidationException('Date de fin du ticket invalide.'); }
        }
        foreach (['requester', 'assign', 'observer'] as $role) {
            foreach (['users', 'groups'] as $kind) { $input[$role . '_' . $kind] = Definition::ids($input[$role . '_' . $kind]); }
        }
        return $input;
    }
    public static function fromInput(array $input): array
    {
        $options = $input['ticket'] ?? [];
        if (!is_array($options)) { throw new ValidationException('Paramètres de ticket invalides.'); }
        return self::normalize($options);
    }
    public static function creation(array $input, \Ticket $ticket): array
    {
        $options = self::normalize($input);
        $result = array_intersect_key($options, array_flip(['type', 'urgency', 'impact']));
        if ($options['time_to_resolve'] !== '') { $result['time_to_resolve'] = str_replace('T', ' ', $options['time_to_resolve']) . ':00'; }
        if (($options['assign_users'] || $options['assign_groups']) && !$ticket->canAssign()) {
            throw new ValidationException('Le droit GLPI d’attribuer un ticket est nécessaire pour choisir ses personnes ou groupes attribués.');
        }
        foreach (['requester', 'assign', 'observer'] as $role) {
            $team = ['users' => $options[$role . '_users'], 'groups' => $options[$role . '_groups']];
            Assignees::check($team);
            if ($role === 'requester' && !$team['users']) { $team['users'] = [(int) \Session::getLoginUserID()]; }
            foreach (['users', 'groups'] as $kind) {
                if ($team[$kind]) { $result['_' . $kind . '_id_' . $role] = $team[$kind]; }
            }
        }
        return $result;
    }
    public static function summary(array $options): string
    {
        $html = '<h3>Paramètres du ticket au lancement</h3><p><strong>Type :</strong> ' . ($options['type'] === 1 ? 'Incident' : 'Demande')
            . ' · <strong>Sévérité (urgence) :</strong> ' . (int) $options['urgency'] . ' / 5 · <strong>Impact :</strong> ' . (int) $options['impact'] . ' / 5'
            . ' · <strong>Date de fin souhaitée :</strong> ' . View::e(str_replace('T', ' ', $options['time_to_resolve']) ?: 'Non renseignée') . '</p>';
        foreach (['requester' => 'Demandeurs', 'assign' => 'Attribués à', 'observer' => 'Observateurs (suivi)'] as $role => $label) {
            $team = ['users' => $options[$role . '_users'], 'groups' => $options[$role . '_groups']];
            $html .= '<p><strong>' . View::e($label) . ' :</strong> ' . View::e($team['users'] || $team['groups'] ? Assignees::names($team) : 'Selon les valeurs par défaut GLPI') . '</p>';
        }
        return $html;
    }
    public static function show(array $input): void
    {
        $options = self::normalize($input);
        echo '<fieldset class="card p-3 my-3 wb-assignees"><legend>Paramètres du ticket à créer</legend><p>Choisir une ou plusieurs personnes et groupes par rôle. Sans demandeur choisi, vous êtes le demandeur. Les responsables du workflow sont sélectionnés séparément.</p>';
        foreach (['type' => ['Type', [1 => 'Incident', 2 => 'Demande']],
            'urgency' => ['Sévérité (urgence)', [1 => 'Très basse', 2 => 'Basse', 3 => 'Moyenne', 4 => 'Haute', 5 => 'Très haute']],
            'impact' => ['Impact', [1 => 'Très bas', 2 => 'Bas', 3 => 'Moyen', 4 => 'Haut', 5 => 'Très haut']]] as $key => [$label, $choices]) {
            echo '<label class="wb-control">' . View::e($label) . '<select class="form-select" aria-label="' . View::e($label) . '" name="ticket[' . $key . ']">';
            foreach ($choices as $id => $name) { echo '<option value="' . $id . '"' . ($options[$key] === $id ? ' selected' : '') . '>' . View::e($name) . '</option>'; }
            echo '</select></label>';
        }
        echo '<label class="wb-control">Date de fin souhaitée (échéance de résolution)<input class="form-control" type="datetime-local" name="ticket[time_to_resolve]" value="' . View::e($options['time_to_resolve']) . '"></label><p>La priorité est calculée par GLPI à partir de l’urgence et de l’impact. Les règles métier GLPI restent appliquées.</p>';
        foreach (['requester' => 'Demandeurs', 'assign' => 'Attribués à', 'observer' => 'Observateurs (suivi)'] as $role => $label) {
            Assignees::show(['users' => $options[$role . '_users'], 'groups' => $options[$role . '_groups']], 'ticket[' . $role . '_%s][]', $label);
        }
        echo '</fieldset>';
    }
}