<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
use WorkflowBuilder\{Access, Definition, Engine, Fields, FormView, Http, Store, View, ValidationException};
use Symfony\Component\HttpFoundation\JsonResponse;
try {
    Http::post();
    Access::need('template');
    $definition = Definition::decode(Http::string($_POST, 'definition'));
    $input = Store::unpack(Http::string($_POST, 'input', '{}'));
    $values = Fields::formValues($definition['fields'], $input, false);
    $states = Engine::states($definition, $values);
    ob_start();
    FormView::show($definition['fields'], $values);
    $html = ob_get_clean();
    $steps = [];
    foreach ($definition['steps'] as $step) {
        $steps[] = ['title' => $step['title'], 'state' => $states[$step['id']], 'required' => $step['required']];
    }
    return new JsonResponse(['html' => $html, 'steps' => $steps, 'progress' => Engine::progress($definition, $states)], 200, ['Cache-Control' => 'no-store']);
} catch (Throwable $error) {
    return new JsonResponse(['error' => $error instanceof ValidationException ? $error->getMessage() : 'Prévisualisation impossible.'], 422);
}
