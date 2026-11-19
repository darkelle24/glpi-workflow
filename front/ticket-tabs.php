<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
use WorkflowBuilder\{Http, TicketTabs};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    throw new \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException(['GET']);
}
try {
    $id = Http::integer($_GET, 'id');
    $slug = isset($_GET['tab']) ? Http::string($_GET, 'tab') : null;
} catch (\WorkflowBuilder\ValidationException $error) {
    throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Paramètres de ticket invalides.', $error);
}
if ($id < 1) { throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException(); }
$ticket = new Ticket();
$ticket->check($id, READ);
$tabs = TicketTabs::available($ticket);
header('Cache-Control: private, no-store');
if (isset($_GET['tab'])) {
    foreach ($tabs as $key => $tab) {
        if ($tab['slug'] === $slug) {
            // Keep the native controller as the target of GLPI forms and AJAX calls.
            // The browser restores the short URL after the native page has loaded.
            Http::redirect(TicketTabs::nativeUrl($id, (string) $key));
        }
    }
    throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
}
header('Content-Type: application/json; charset=UTF-8');
echo json_encode(['tabs' => $tabs], JSON_THROW_ON_ERROR);