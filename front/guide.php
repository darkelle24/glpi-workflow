<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
use WorkflowBuilder\{Guide,Http,ValidationException,View};
use Symfony\Component\HttpFoundation\Response;
global $CFG_GLPI;

try { Guide::requireAccess(); }
catch (ValidationException $error) { return new Response($error->getMessage(), 403, ['Content-Type'=>'text/plain; charset=UTF-8']); }

try {
    $format = Http::string($_GET, 'format', 'html');
    if ($format === 'md') {
        return new Response(Guide::source(), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="user-guide.md"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
    if ($format !== 'html') { return new Response('Format du guide inconnu.', 400, ['Content-Type'=>'text/plain; charset=UTF-8']); }
    header('Cache-Control: private, no-cache');
    Http::start('Guide utilisateur');
    echo '<div class="wb-guide-actions"><p>Le même fichier Markdown est utilisé ici et dans le dépôt du plugin.</p>';
    echo '<a class="btn btn-outline-primary" href="' . View::e(Http::url('guide.php', ['format'=>'md'])) . '">Télécharger le Markdown</a></div>';
    echo '<article class="wb-guide">' . Guide::render(Guide::source(), $CFG_GLPI['root_doc']) . '</article>';
    Http::end();
} catch (Throwable $error) {
    Http::error($error);
}
