<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
global $DB;
use WorkflowBuilder\{Access, Fields, Http, Knowledge, View, ValidationException};
use Symfony\Component\HttpFoundation\JsonResponse;
try {
    if (!Access::has('template') && !Access::has('view')) { throw new ValidationException('Accès refusé.'); }
    $type = Http::string($_GET, 'type');
    $query = Http::string($_GET, 'q');
    if ($type === 'knowledge') { return new JsonResponse(Knowledge::search($_GET), 200, ['Cache-Control' => 'no-store']); }
    if (mb_strlen($query) < 2 || mb_strlen($query) > 100) { return new JsonResponse(['items' => [], 'next' => 0]); }
    $types = Fields::REFERENCES;
    if (!isset($types[$type])) { throw new ValidationException('Type inconnu.'); }
    $class = $types[$type];
    if ($type === 'user' && User::isUserReauthenticationNeeded()) { return new JsonResponse(['items' => [], 'next' => 0, 'message' => 'GLPI demande une confirmation d’identité. Utiliser le lien Confirmer l’identité GLPI, puis relancer la recherche.']); }
    $ticketId = Http::integer($_GET, 'ticket');
    if ($ticketId) { Access::ticket($ticketId); }
    if ($type === 'document' && !$ticketId) {
        return new JsonResponse(['items' => [], 'next' => 0, 'message' => 'Joindre les documents au ticket après sa création.']);
    }
    $where = ['id' => ['>', Http::integer($_GET, 'after')], 'name' => ['LIKE', '%' . $query . '%']];
    if ($type === 'user') {
        unset($where['name']);
        $where['OR'] = ['name' => ['LIKE', '%' . $query . '%'], 'firstname' => ['LIKE', '%' . $query . '%'], 'realname' => ['LIKE', '%' . $query . '%']];
        $where['is_active'] = 1;
    }
    $items = [];
    $last = 0;
    $scanned = 0;
    foreach ($DB->request(['SELECT' => ['id'], 'FROM' => $class::getTable(), 'WHERE' => $where, 'ORDER' => ['id'], 'LIMIT' => 500]) as $row) {
        $scanned++;
        $last = (int) $row['id'];
        $object = new $class();
        $allowed = $type === 'document'
            ? $object->getFromDB($last) && $object->canViewFile(['tickets_id' => $ticketId])
            : ($type === 'entity' ? Session::haveAccessToEntity($last) && $object->getFromDB($last) : $object->can($last, READ));
        if (!$allowed || !empty($object->fields['is_deleted'])) { continue; }
        if ($type === 'document') {
            $links = $DB->request(['FROM' => 'glpi_documents_items', 'WHERE' => ['documents_id' => $last, 'itemtype' => 'Ticket', 'items_id' => $ticketId], 'LIMIT' => 1]);
            if (!$links->count()) { continue; }
        }
        $label = $object->fields['completename'] ?? $object->fields['name'] ?? ('#' . $last);
        if ($type === 'user') {
            $label = trim(($object->fields['firstname'] ?? '') . ' ' . ($object->fields['realname'] ?? '')) ?: $label;
        }
        $items[] = ['id' => $last, 'label' => $label];
        if (count($items) >= 20) { break; }
    }
    return new JsonResponse(['items' => $items, 'next' => ($scanned >= 500 || count($items) >= 20) ? $last : 0], 200, ['Cache-Control' => 'no-store']);
} catch (Throwable $error) {
    return new JsonResponse(['error' => $error instanceof ValidationException ? $error->getMessage() : 'Recherche impossible.'], 403, ['Cache-Control' => 'no-store']);
}
