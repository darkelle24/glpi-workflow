<?php
declare(strict_types=1);
namespace WorkflowBuilder;
/** Serialize plugin writes and compatible ticket closures until the request has completed. */
final class TicketMutex
{
    private static array $held = [];
    public static function acquire(int $ticket): void
    {
        global $DB;
        if ($ticket < 1 || isset(self::$held[$ticket])) { return; }
        $name = 'wb_' . substr(hash('sha256', (string) ($DB->dbdefault ?? '')), 0, 12) . '_ticket_' . $ticket;
        $result = $DB->doQuery("SELECT GET_LOCK('" . $name . "', 5) AS acquired");
        $row = $DB->fetchAssoc($result);
        if ((int) ($row['acquired'] ?? 0) !== 1) {
            throw new ValidationException('Ce ticket est en cours de modification. Réessayer dans quelques secondes.');
        }
        self::$held[$ticket] = true;
        register_shutdown_function(static function () use ($name): void {
            global $DB;
            try { $DB->doQuery("SELECT RELEASE_LOCK('" . $name . "')"); }
            catch (\Throwable) { /* Connection teardown also releases advisory locks. */ }
        });
    }
}
