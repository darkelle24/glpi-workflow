<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
use WorkflowBuilder\{Guide,Http,ValidationException,View};
use Symfony\Component\HttpFoundation\Response;
global $CFG_GLPI;
try {
    Guide::requireAccess();
    $key = Http::string($_GET, 'guide', 'user');
    $page = Guide::page($key);
    $format = Http::string($_GET, 'format', 'html');
    if (!in_array($format, ['html', 'md'], true)) { return new Response('Format du guide inconnu.', 400, ['Content-Type'=>'text/plain; charset=UTF-8']); }
    $source = Guide::source($key);
    if ($format === 'md') {
        return new Response($source, 200, ['Content-Type'=>'text/markdown; charset=UTF-8',
            'Content-Disposition'=>'attachment; filename="' . $page['file'] . '"',
            'Cache-Control'=>'private, no-store', 'X-Content-Type-Options'=>'nosniff']);
    }
    header('Cache-Control: private, no-cache');
    Http::start($page['title']);
    echo '<nav class="wb-tabs" aria-label="Guides du plugin">';
    foreach (Guide::PAGES as $id => $entry) {
        echo '<a class="btn btn-outline-primary"' . ($id === $key ? ' aria-current="page"' : '') . ' href="' . View::e(Http::url('guide.php', ['guide'=>$id])) . '">' . View::e($entry['title']) . '</a>';
    }
    echo '</nav><div class="wb-guide-actions"><p>Ce guide et ses images sont livrés avec le plugin et consultables dans le dépôt.</p>';
    echo '<a class="btn btn-outline-primary" href="' . View::e(Http::url('guide.php', ['guide'=>$key,'format'=>'md'])) . '">Télécharger le Markdown</a></div>';
    echo '<article class="wb-guide">' . Guide::render($source, $CFG_GLPI['root_doc']) . '</article>';
    Http::end();
} catch (ValidationException $error) {
    return new Response($error->getMessage(), 403, ['Content-Type'=>'text/plain; charset=UTF-8', 'Cache-Control'=>'private, no-store']);
} catch (Throwable $error) { Http::error($error); }