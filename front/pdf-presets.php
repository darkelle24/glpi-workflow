<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
use WorkflowBuilder\{Access, Definition, Http, PdfAutomatic, PdfExport, PdfPresets, PdfTemplate, Store, View, ValidationException};
use Symfony\Component\HttpFoundation\Response;
try {
// Preview and save require the same administrative right and the plugin's CSRF token.
Access::need('admin', UPDATE);
$id = Http::integer($_POST + $_GET, 'id');
$row = $id ? Store::one('pdfpresets', $id) : null;
$auto = $row ? PdfPresets::read($row) : PdfAutomatic::empty();
$name = $row['name'] ?? 'Nouveau modèle PDF';
$revision = (int) ($row['revision'] ?? 0);
$active = (bool) ($row['is_active'] ?? true);
$entity = (int) ($row['entities_id'] ?? $_SESSION['glpiactive_entity'] ?? 0);
$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        Http::post();
        $json = Http::string($_POST, 'automatic');
        if (strlen($json) > Definition::MAX_BYTES) { throw new ValidationException('Modèle PDF trop volumineux.'); }
        $auto = PdfAutomatic::validate(Store::unpack($json), PdfTemplate::sources(Definition::empty()));
        $operation = Http::string($_POST, 'operation');
        if ($operation === 'preview') {
            $d = Definition::empty(); $d['pdf']['mode'] = 'automatic'; $d['pdf']['automatic'] = $auto; $d['pdf']['include_summary'] = false;
            $variables = array_map(static fn($label) => '[' . $label . ']', PdfTemplate::sources($d));
            return new Response(PdfExport::render($d, $variables), 200, ['Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="apercu-modele.pdf"', 'Cache-Control' => 'no-store, private']);
        }
        if ($operation !== 'save') { throw new ValidationException('Action de modèle PDF inconnue.'); }
        $name = Http::string($_POST, 'name'); $revision = Http::integer($_POST, 'revision'); $active = isset($_POST['is_active']);
        $id = PdfPresets::save($id, $name, $auto, $revision, $active, $entity);
        Http::redirect(Http::url('pdf-presets.php', ['id' => $id, 'saved' => 1]));
    } catch (Throwable $e) {
        if (($_POST['operation'] ?? '') === 'preview') {
            return new Response($e instanceof ValidationException ? $e->getMessage() : 'Aperçu impossible.', 422,
                ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store, private']);
        }
        $error = $e;
    }
}
Http::start('Administration PDF');
if ($error) { Http::error($error); }
echo '<nav class="wb-tabs" aria-label="Administration PDF"><a class="btn btn-primary" aria-current="page" href="' . View::e(Http::url('pdf-presets.php')) . '">Modèles de base</a></nav>';
echo '<p>Préparer les en-têtes, les groupes, les libellés, les textes et les couleurs proposés aux créateurs de workflows. Chaque utilisation crée une copie indépendante dans le workflow.</p>';
if (isset($_GET['saved'])) { echo '<p class="alert alert-success">Modèle de base enregistré.</p>'; }
echo '<details class="card p-3 mb-3" open><summary>Bibliothèque de modèles</summary><div class="wb-toolbar"><a class="btn btn-outline-primary" href="' . View::e(Http::url('pdf-presets.php')) . '">Nouveau modèle de base</a></div><ul>';
foreach (Store::rows('pdfpresets', [], ['ORDER' => ['name', 'id']]) as $preset) {
    try { PdfPresets::read($preset); } catch (ValidationException) { continue; }
    echo '<li><a href="' . View::e(Http::url('pdf-presets.php', ['id' => (int) $preset['id']])) . '">' . View::e($preset['name']) . '</a> · ' . ($preset['is_active'] ? 'Disponible' : 'Désactivé') . ' · Révision ' . (int) $preset['revision'] . '</li>';
}
echo '</ul></details><section class="card p-3"><h2>' . View::e($row ? 'Modifier le modèle de base' : 'Créer un modèle de base') . '</h2>';
echo '<form method="post" data-wb-pdf-preset data-automatic="' . View::e(Store::json($auto)) . '" data-pdf-presets="' . View::e(Store::json(PdfAutomatic::defaults())) . '">';
Http::hidden(['id' => $id, 'revision' => $revision, 'operation' => 'save']);
echo '<label class="wb-control">Nom dans la bibliothèque<input class="form-control" name="name" required maxlength="255" value="' . View::e($name) . '"></label>';
echo '<label class="wb-control"><input type="checkbox" class="form-check-input" name="is_active" value="1"' . ($active ? ' checked' : '') . '> Proposer ce modèle aux workflows</label>';
echo '<p class="text-muted">Entité : #' . $entity . '. Les réponses propres à chaque workflow seront associées après sélection de ce modèle.</p>';
echo '<input type="hidden" name="automatic"><div data-preset-ui></div><div class="wb-toolbar"><button class="btn btn-primary" type="submit">Enregistrer le modèle de base</button><button class="btn btn-outline-primary" type="button" data-preset-preview>Télécharger un aperçu PDF</button></div><p role="status" data-preset-status></p></form></section>';
Http::end();
} catch (Throwable $error) {
    return new Response($error instanceof ValidationException ? $error->getMessage() : 'Administration PDF indisponible.', $error instanceof ValidationException ? 403 : 500, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store, private']);
}
