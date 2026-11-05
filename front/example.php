<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
use WorkflowBuilder\{Guide,Http,ValidationException};
use Symfony\Component\HttpFoundation\Response;
try {
    Guide::requireAccess();
    $name = Http::string($_GET, 'name');
    return new Response(Guide::example($name), 200, ['Content-Type'=>'application/json; charset=UTF-8',
        'Content-Disposition'=>'attachment; filename="' . $name . '.json"',
        'Cache-Control'=>'private, no-store', 'X-Content-Type-Options'=>'nosniff']);
} catch (Throwable $error) {
    return new Response($error instanceof ValidationException ? $error->getMessage() : 'Exemple indisponible.',
        $error instanceof ValidationException ? 403 : 500, ['Content-Type'=>'text/plain; charset=UTF-8', 'Cache-Control'=>'private, no-store']);
}