<?php
declare(strict_types=1);
namespace WorkflowBuilder;

/** Stable URLs, independent of translated tab labels and badge counts. */
final class TicketTabs
{
    private const SLUGS = [
        'Ticket$main' => 'ticket', 'Ticket$4' => 'statistiques',
        'TicketValidation$1' => 'validations', 'KnowbaseItem_Item$1' => 'connaissances',
        'Item_Ticket$1' => 'elements', 'Impact$1' => 'impacts',
        'TicketCost$1' => 'couts', 'Itil_Project$1' => 'projets',
        'ProjectTask_Ticket$1' => 'taches-projet', 'Problem_Ticket$1' => 'problemes',
        'Change_Ticket$1' => 'changements', 'Ticket_Contract$1' => 'contrats',
        'Log$1' => 'historique', 'PluginWorkflowbuilderTicket$1' => 'workflow',
        'PluginWorkflowbuilderTicket$2' => 'mes-etapes', '-1' => 'tout',
    ];

    public static function pretty(): bool
    {
        return getenv('WORKFLOWBUILDER_TICKET_URLS') === '1';
    }

    public static function slug(string $key): string
    {
        // Hex encoding avoids collisions between third-party tab IDs.
        return self::SLUGS[$key] ?? 'onglet-' . bin2hex($key);
    }

    public static function url(int $ticket, string $key): string
    {
        global $CFG_GLPI;
        return self::pretty()
            ? $CFG_GLPI['root_doc'] . '/front/ticket/' . $ticket . '/' . self::slug($key)
            : self::nativeUrl($ticket, preg_replace('/\\$1$/D', '', $key));
    }

    public static function nativeUrl(int $ticket, string $key): string
    {
        return \Ticket::getFormURLWithID($ticket) . '&forcetab=' . rawurlencode($key);
    }

    public static function available(\Ticket $ticket): array
    {
        $tabs = $ticket->defineAllTabs();
        $all = !isset($tabs['no_all_tab']);
        unset($tabs['no_all_tab']);
        $tabs = array_filter($tabs, static fn($label) => $label !== null && $label !== '');
        if ($all && count($tabs) > 1) { $tabs['-1'] = 'All'; }
        $result = [];
        foreach ($tabs as $key => $label) {
            $key = (string) $key;
            $result[$key] = ['slug' => self::slug($key), 'url' => self::url((int) $ticket->getID(), $key)];
        }
        return $result;
    }
}