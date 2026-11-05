<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
use WorkflowBuilder\{Access, Definition, Http, PdfExport, PdfTemplate, Store, ValidationException};
use Symfony\Component\HttpFoundation\Response;
try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        Http::post();
        Access::need('template');
        $id = Http::integer($_POST, 'id');
        if ($id) { Access::template(Store::one('templates', $id), true); }
        else { Access::need('template', CREATE); }
        $definition = Definition::decode(Http::string($_POST, 'definition'));
        $values = [];
        foreach (PdfTemplate::sources($definition) as $key => $label) { $values[$key] = '[' . $label . ']'; }
        $bytes = PdfExport::render($definition, $values);
        $filename = 'apercu-' . $definition['pdf']['filename'] . '.pdf';
    } else {
        $data = PdfExport::instance(Http::integer($_GET, 'id'));
        $bytes = PdfExport::render($data['definition'], PdfExport::variables($data), $data);
        $filename = $data['definition']['pdf']['filename'] . '-' . (int) $data['instance']['tickets_id'] . '.pdf';
    }
    return new Response($bytes, 200, ['Content-Type' => 'application/pdf',
        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
} catch (Throwable $error) {
    return new Response($error instanceof ValidationException ? $error->getMessage() : 'Génération du PDF impossible.',
        $error instanceof ValidationException ? 403 : 500,
        ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store, private']);
}