<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
class Ticket {
    public array $tabs = ['Ticket$main'=>'Ticket', 'Ticket$4'=>'Statistics', 'PluginWorkflowbuilderTicket$1'=>'Workflow'];
    public function getID(): int { return 11; }
    public function defineAllTabs(): array { return $this->tabs; }
    public static function getFormURLWithID($id): string { return '/glpi/front/ticket.form.php?id=' . $id; }
}
use WorkflowBuilder\{Http, TicketTabs};
$CFG_GLPI = ['root_doc'=>'/glpi'];
$old = getenv('WORKFLOWBUILDER_TICKET_URLS');
$count = 0;
function equal($expected, $actual): void {
    global $count;
    if ($expected !== $actual) { throw new RuntimeException(json_encode([$expected, $actual])); }
    $count++;
}
try {
    putenv('WORKFLOWBUILDER_TICKET_URLS=0');
    equal('/glpi/front/ticket.form.php?id=11&forcetab=Ticket%244', TicketTabs::url(11, 'Ticket$4'));
    equal('/glpi/front/ticket.form.php?id=11&forcetab=PluginWorkflowbuilderTicket%241#wb-instance-7-step-check', Http::ticketWorkflow(11, 7, 'check'));
    putenv('WORKFLOWBUILDER_TICKET_URLS=1');
    equal('/glpi/front/ticket/11/statistiques', TicketTabs::url(11, 'Ticket$4'));
    equal('/glpi/front/ticket/11/workflow#wb-instance-7-step-check', Http::ticketWorkflow(11, 7, 'check'));
    equal('/glpi/front/ticket/11/ticket', TicketTabs::url(11, 'Ticket$main'));
    $ticket = new Ticket();
    equal(['Ticket$main', 'Ticket$4', 'PluginWorkflowbuilderTicket$1', -1], array_keys(TicketTabs::available($ticket)));
    $ticket->tabs = ['Ticket$main'=>'Ticket', 'no_all_tab'=>true];
    equal(['Ticket$main'], array_keys(TicketTabs::available($ticket)));
    $ticket->tabs = ['Ticket$main'=>'Ticket', 'Ticket$4'=>null, 'Hidden$1'=>''];
    equal(['Ticket$main'], array_keys(TicketTabs::available($ticket)));
    $a = 'PluginA\\Ticket$1'; $b = 'PluginA_Ticket$1';
    equal(false, TicketTabs::slug($a) === TicketTabs::slug($b));
    equal($a, hex2bin(substr(TicketTabs::slug($a), 7)));
    equal(1, preg_match('/^[a-z0-9-]+$/D', TicketTabs::slug($a)));
    equal('statistiques', TicketTabs::slug('Ticket$4'));
    echo "$count ticket tab checks passed\n";
} finally {
    putenv($old === false ? 'WORKFLOWBUILDER_TICKET_URLS' : 'WORKFLOWBUILDER_TICKET_URLS=' . $old);
}