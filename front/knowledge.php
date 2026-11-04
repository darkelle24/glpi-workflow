<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
use WorkflowBuilder\{Http, Knowledge, ValidationException};
use Symfony\Component\HttpFoundation\JsonResponse;
try {
    Http::post();
    $item = Knowledge::create($_POST);
    return new JsonResponse(['item' => $item], 201, ['Cache-Control' => 'no-store']);
} catch (Throwable $error) {
    return new JsonResponse(['error' => $error instanceof ValidationException ? $error->getMessage() : 'Création de fiche impossible.'],
        403, ['Cache-Control' => 'no-store']);
}
