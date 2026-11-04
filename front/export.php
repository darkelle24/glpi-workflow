<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
use WorkflowBuilder\{Access, Http, Store, Templates, ValidationException};
use Symfony\Component\HttpFoundation\Response;
try {
    $id = Http::integer($_GET, 'id');
    $definition = Access::template(Store::one('templates', $id));
    $versionId = Http::integer($_GET, 'version');
    if ($versionId) {
        [$version, $definition] = Templates::version($versionId);
        if ((int) $version['templates_id'] !== $id) { throw new ValidationException('Version inaccessible.'); }
    }
    return new Response(Store::json($definition), 200, ['Content-Type' => 'application/json; charset=UTF-8',
        'Content-Disposition' => 'attachment; filename="workflow-' . $id . ($versionId ? '-v' . (int) $version['number'] : '-draft') . '.json"',
        'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
} catch (Throwable $error) {
    return new Response($error instanceof ValidationException ? $error->getMessage() : 'Export impossible.', 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
}
