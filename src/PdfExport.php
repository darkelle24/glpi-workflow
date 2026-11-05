<?php
declare(strict_types=1);
namespace WorkflowBuilder;

final class PdfExport
{
    /** Read access is rechecked on every download, including after ticket closure. */
    public static function instance(int $id): array
    {
        $data = Instances::data($id);
        if (!($data['definition']['pdf']['enabled'] ?? false) || $data['progress']['status'] !== 'completed') {
            throw new ValidationException('Le PDF sera disponible à la fin du workflow si son modèle l’active.');
        }
        return $data;
    }

    public static function variables(array $data): array
    {
        $d = $data['definition'];
        $i = $data['instance'];
        $out = ['workflow' => $d['name'], 'ticket' => (string) $i['tickets_id'], 'version' => (string) $data['version']['number'],
            'date_creation' => $i['date_creation'], 'date_completion' => $i['date_completion'] ?? ''];
        $form = $data['values'][''] ?? [];
        $context = Fields::context($form);
        $add = static function (array $fields, array $values, string $prefix, array $ctx, bool $applicable = true) use (&$out, $i): void {
            foreach ($fields as $field) {
                $value = $values[$field['id']] ?? null;
                $visible = $applicable && Condition::evaluate($field['condition'], $ctx);
                $ctx['self.' . $field['id']] = $visible ? $value : null;
                if ($field['type'] === 'section') { continue; }
                // Resolve GLPI references through the native access checks used in the UI.
                $out[$prefix . $field['id']] = !$visible || $value === null ? ''
                    : html_entity_decode(strip_tags(str_replace(['<br />', '<br>', '<br/>'], "\n",
                        FormView::value($field, $value, (int) $i['tickets_id']))), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        };
        $add($d['fields'], $form, 'form.', $context);
        foreach ($d['steps'] as $step) {
            $add($step['fields'], $data['values'][$step['id']] ?? [], 'steps.' . $step['id'] . '.', $context,
                ($data['states'][$step['id']] ?? 'na') !== 'na');
        }
        return $out;
    }

    public static function text(string $template, array $values): string
    {
        return preg_replace_callback('/{{\s*([^{}]+?)\s*}}/', static fn($m) => (string) ($values[$m[1]] ?? ''), $template);
    }

    public static function render(array $definition, array $variables, ?array $data = null): string
    {
        $definition = Definition::validate($definition);
        if (!class_exists(\TCPDF::class)) {
            throw new ValidationException('La bibliothèque TCPDF fournie par GLPI est indisponible. Vérifier l’installation de GLPI.');
        }
        $layout = $definition['pdf'];
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('Workflow Builder');
        $pdf->SetTitle($definition['name']);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->setCellPaddings(0, 0, 0, 0);
        if ($layout['mode'] === 'automatic') {
            PdfAutomatic::draw($pdf, $layout['automatic'], $variables);
        }
        foreach ($layout['mode'] === 'free' ? $layout['pages'] : [] as $page) {
            $landscape = $page['orientation'] === 'landscape';
            [$width, $height] = $landscape ? [297, 210] : [210, 297];
            $pdf->AddPage($landscape ? 'L' : 'P', 'A4');
            if ($page['background'] !== '') {
                $bytes = PdfTemplate::image($page['background']);
                $size = getimagesizefromstring($bytes);
                $ratio = min($width / $size[0], $height / $size[1]);
                $w = $size[0] * $ratio;
                $h = $size[1] * $ratio;
                $pdf->StartTransform();
                $pdf->Image('@' . $bytes, ($width - $w) / 2, ($height - $h) / 2, $w, $h, $size['mime'] === 'image/png' ? 'PNG' : 'JPEG');
                $pdf->StopTransform();
            }
            foreach ($page['boxes'] as $box) {
                $pdf->SetFont($box['font'], $box['bold'] ? 'B' : '', $box['font_size']);
                $pdf->SetTextColor(hexdec(substr($box['color'], 1, 2)), hexdec(substr($box['color'], 3, 2)), hexdec(substr($box['color'], 5, 2)));
                $pdf->SetXY($box['x'], $box['y']);
                // Plain text only. Fit text into the configured rectangle, without creating extra pages.
                $pdf->MultiCell($box['width'], $box['height'], self::text($box['text'], $variables), 0, $box['align'], false, 1,
                    $box['x'], $box['y'], true, 0, false, true, $box['height'], 'T', true);
            }
        }
        if ($layout['include_summary']) {
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetAutoPageBreak(true, 15);
            $pdf->AddPage('P', 'A4');
            $pdf->SetFont('dejavusans', '', 10);
            $pdf->SetTextColor(32, 43, 58);
            $pdf->SetXY(15, 15);
            $pdf->writeHTML($data ? TicketMessages::summary($data) : '<h1>Aperçu du récapitulatif</h1><p>Les réponses du lancement et des étapes, les états, les responsables, les dates et les commentaires seront ajoutés ici lors du téléchargement final.</p>', true, false, true, false, '');
        }
        if (!$pdf->getNumPages()) { throw new ValidationException('Le modèle PDF ne contient aucune page.'); }
        return $pdf->Output('', 'S');
    }
}