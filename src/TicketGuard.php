<?php
declare(strict_types=1);
namespace WorkflowBuilder;
final class TicketGuard
{
    public static function beforeUpdate(\Ticket $ticket): void
    {
        if (!is_array($ticket->input) || !isset($ticket->input['status'])
            || !in_array((int) $ticket->input['status'], [\CommonITILObject::SOLVED, \CommonITILObject::CLOSED], true)) {
            return;
        }
        // Independent from the caller's plugin rights; applies to compatible UI, API and cron paths.
        try {
            TicketMutex::acquire((int) $ticket->getID());
            foreach (Store::rows('instances', ['tickets_id' => (int) $ticket->getID()]) as $instance) {
                [$version, $definition] = Templates::version((int) $instance['versions_id']);
                if (!$definition['require_before_close']) {
                    continue;
                }
                $form = [];
                foreach (Store::rows('values', ['instances_id' => $instance['id'], 'step_key' => '']) as $row) {
                    $form[$row['field_key']] = json_decode($row['payload'], true, 32, JSON_THROW_ON_ERROR);
                }
                $steps = Store::rows('stepinstances', ['instances_id' => $instance['id']]);
                $states = Engine::states($definition, $form, array_column($steps, 'state', 'step_key'));
                if (Engine::progress($definition, $states)['remaining'] > 0) {
                    $ticket->input = false;
                    \Session::addMessageAfterRedirect('Terminer les étapes obligatoires du workflow avant de résoudre ou clôturer ce ticket.', false, ERROR);
                    return;
                }
            }
        } catch (\Throwable $error) {
            $ticket->input = false;
            global $PHPLOGGER;
            $PHPLOGGER?->error('Workflow Builder: ticket closure verification failed', ['exception' => $error]);
            \Session::addMessageAfterRedirect('Vérification du workflow impossible. Contacter un administrateur.', false, ERROR);
        }
    }
}
