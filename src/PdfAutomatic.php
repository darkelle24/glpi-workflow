<?php
declare(strict_types=1);
namespace WorkflowBuilder;

/** Content-driven PDF layout, shared by workflow definitions and reusable admin presets. */
final class PdfAutomatic
{
    public static function empty(): array
    {
        return ['title' => '{{workflow}}', 'description' => '', 'footer' => 'Document interne', 'logo' => '',
            'font' => 'dejavusans', 'font_size' => 10, 'accent' => '#ed8745', 'band' => '#fbd0ad',
            'paper' => '#fffaf0', 'text_color' => '#493c30', 'numbered' => true, 'groups' => []];
    }

    public static function validate(mixed $auto, array $sources, bool $checkSources = true): array
    {
        if (!is_array($auto) || array_diff(array_keys($auto), array_keys(self::empty()))) {
            throw new ValidationException('Composition automatique PDF invalide.');
        }
        $auto += self::empty();
        foreach (['title' => 255, 'description' => 1000, 'footer' => 255] as $key => $max) {
            $auto[$key] = self::template($auto[$key], $max, $sources, $checkSources);
        }
        if (!is_string($auto['logo'])) { throw new ValidationException('Logo PDF invalide.'); }
        if ($auto['logo'] !== '') { PdfTemplate::image($auto['logo']); }
        if (!in_array($auto['font'], PdfTemplate::FONTS, true)
            || (!is_int($auto['font_size']) && !is_float($auto['font_size']))
            || !is_finite((float) $auto['font_size']) || $auto['font_size'] < 8 || $auto['font_size'] > 14) {
            throw new ValidationException('Police automatique invalide (8 à 14 points).');
        }
        foreach (['accent', 'band', 'paper', 'text_color'] as $key) {
            if (!is_string($auto[$key]) || !preg_match('/^#[a-fA-F0-9]{6}$/D', $auto[$key])) {
                throw new ValidationException('Couleur de composition PDF invalide.');
            }
        }
        $auto['numbered'] = Definition::boolean($auto['numbered']);
        if (!is_array($auto['groups']) || !array_is_list($auto['groups']) || count($auto['groups']) > 30) {
            throw new ValidationException('30 groupes PDF maximum.');
        }
        $count = 0;
        foreach ($auto['groups'] as &$group) {
            if (!is_array($group) || array_diff(array_keys($group), ['title', 'description', 'items'])) {
                throw new ValidationException('Groupe PDF invalide.');
            }
            $group += ['title' => 'Groupe', 'description' => '', 'items' => []];
            $group['title'] = Definition::text($group['title'], 120, true);
            $group['description'] = Definition::text($group['description'], 1000);
            if (!is_array($group['items']) || !array_is_list($group['items']) || ($count += count($group['items'])) > 200) {
                throw new ValidationException('200 informations maximum dans la composition PDF.');
            }
            foreach ($group['items'] as &$item) {
                if (!is_array($item) || array_diff(array_keys($item), ['label', 'source', 'text', 'width'])) {
                    throw new ValidationException('Information PDF invalide.');
                }
                $item += ['label' => 'Information', 'source' => '', 'text' => '', 'width' => 6];
                $item['label'] = Definition::text($item['label'], 255, true);
                $item['source'] = Definition::text($item['source'], 160);
                if ($item['source'] !== '' && (!preg_match('/^[a-z][a-z0-9_.]*$/D', $item['source'])
                    || ($checkSources && !array_key_exists($item['source'], $sources)))) {
                    throw new ValidationException('Information PDF liée à un champ inconnu : ' . $item['label']);
                }
                $item['text'] = self::template($item['text'], 10000, $sources, $checkSources);
                if (!in_array($item['width'], [4, 6, 12], true)) {
                    throw new ValidationException('Largeur PDF invalide : tiers, moitié ou ligne entière attendus.');
                }
            }
            unset($item);
        }
        unset($group);
        return $auto;
    }

    private static function template(mixed $text, int $max, array $sources, bool $check): string
    {
        $text = Definition::text($text, $max);
        preg_match_all('/{{\s*([^{}]+?)\s*}}/', $text, $matches);
        if ($check && array_diff($matches[1], array_keys($sources))) {
            throw new ValidationException('Variable de composition PDF inconnue.');
        }
        return $text;
    }

    public static function defaults(): array
    {
        $employee = self::empty();
        $employee['title'] = 'Fiche de connexion — Nouveau salarié';
        $employee['description'] = 'Informations d’accès aux outils et services numériques';
        $employee['groups'] = [];
        foreach ([
            ['Identité du salarié', '', [['Nom et prénom', 4], ['Service', 4], ['Code délégué', 4]]],
            ['Connexion de session', 'Identifiant utilisé pour ouvrir la session sur le poste informatique.', [['Nom d’utilisateur', 12]]],
            ['Mail et téléphonie', 'Coordonnées professionnelles attribuées au salarié.', [['Adresse mail professionnelle', 12], ['Numéro interne', 4], ['Numéro de portable', 4], ['Numéro fixe', 4]]],
            ['Identifiants UNI-T et MAGIS', '', [['Nom d’utilisateur UNI-T / MAGIS', 12]]],
            ['Identifiants CAF partenaire', '', [['Nom d’utilisateur CAF', 12]]],
            ['Référents / contacts', '', [['Secrétaire', 6], ['Comptable', 6], ['Référent bancaire / Cellule de gestion', 6], ['Référent immobilier', 6]]],
        ] as [$title, $description, $items]) {
            $employee['groups'][] = ['title' => $title, 'description' => $description,
                'items' => array_map(static fn($i) => ['label' => $i[0], 'width' => $i[1], 'source' => '', 'text' => ''], $items)];
        }
        $neutral = self::empty();
        $neutral['accent'] = '#206bc4'; $neutral['band'] = '#e6effb'; $neutral['paper'] = '#ffffff'; $neutral['text_color'] = '#202b3a';
        $neutral['groups'] = [['title' => 'Informations générales', 'description' => '', 'items' => []]];
        return [
            ['id' => 'builtin_employee', 'name' => 'Fiche de connexion salarié', 'automatic' => $employee],
            ['id' => 'builtin_neutral', 'name' => 'Document sobre', 'automatic' => $neutral],
        ];
    }

    /** Wrap plain Unicode text using the actual PDF font metrics, including very long tokens. */
    private static function lines(\TCPDF $pdf, string $text, float $width): array
    {
        $lines = [];
        foreach (preg_split('/\R/u', $text) as $paragraph) {
            $line = '';
            foreach (preg_split('/[\t ]+/u', $paragraph, -1, PREG_SPLIT_NO_EMPTY) as $word) {
                if ($line !== '' && $pdf->GetStringWidth($line . ' ' . $word) > $width) { $lines[] = $line; $line = ''; }
                if ($pdf->GetStringWidth($word) > $width) {
                    foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) as $char) {
                        if ($line !== '' && $pdf->GetStringWidth($line . $char) > $width) { $lines[] = $line; $line = ''; }
                        $line .= $char;
                    }
                } else { $line .= ($line === '' ? '' : ' ') . $word; }
            }
            $lines[] = $line;
        }
        return $lines ?: [''];
    }

    private static function color(\TCPDF $pdf, string $hex, bool $fill = false): void
    {
        $rgb = [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
        if ($fill) { $pdf->SetFillColor(...$rgb); } else { $pdf->SetTextColor(...$rgb); }
    }

    public static function draw(\TCPDF $pdf, array $auto, array $variables): void
    {
        $font = $auto['font']; $size = $auto['font_size'];
        $pdf->SetMargins(13, 13, 13); $pdf->SetAutoPageBreak(false, 0); $pdf->setCellHeightRatio(1.35);
        $pdf->setCellPaddings(0, 0, 0, 0);
        $lineHeight = $size * 25.4 / 72 * 1.35;
        $labelHeight = 8 * 25.4 / 72 * 1.35;
        $y = 13.0;
        $write = static function (string $text, float $x, float $top, float $w, float $h) use ($pdf): void {
            $pdf->SetXY($x, $top);
            $pdf->MultiCell($w, $h, $text, 0, 'L', false, 1, $x, $top, true, 0, false, false);
        };
        $footer = static function () use ($pdf, $auto, $variables, $font, $write): void {
            $pdf->SetFont($font, '', 8); self::color($pdf, $auto['text_color']);
            $write(PdfExport::text($auto['footer'], $variables), 13, 283, 160, 10);
            $write('Page ' . $pdf->getPage(), 178, 283, 20, 10);
        };
        $newPage = static function (bool $first = false) use ($pdf, $auto, $variables, $font, &$y, $footer, $write): void {
            if (!$first) { $footer(); }
            $pdf->AddPage('P', 'A4');
            self::color($pdf, $auto['paper'], true); $pdf->Rect(0, 0, 210, 297, 'F');
            self::color($pdf, $auto['accent'], true); $pdf->Rect(0, 0, 3, 297, 'F');
            $hasLogo = $auto['logo'] !== '';
            $w = $hasLogo ? 139.0 : 176.0;
            $pdf->SetFont($font, 'B', $first ? 19 : 14);
            $title = self::lines($pdf, PdfExport::text($auto['title'], $variables), $w);
            $titleH = count($title) * ($first ? 9.1 : 6.7);
            $pdf->SetFont($font, '', 9);
            $description = $first && $auto['description'] !== '' ? self::lines($pdf, PdfExport::text($auto['description'], $variables), $w) : [];
            $descH = count($description) * 4.4;
            $headerH = max($hasLogo ? 28 : 20, $titleH + $descH + 11);
            self::color($pdf, $auto['band'], true); $pdf->RoundedRect(13, 13, 184, $headerH, 4, '1111', 'F');
            self::color($pdf, $auto['text_color']); $pdf->SetFont($font, 'B', $first ? 19 : 14);
            $write(implode("\n", $title), 17, 17, $w, $titleH);
            if ($description) { $pdf->SetFont($font, '', 9); $write(implode("\n", $description), 17, 18 + $titleH, $w, $descH); }
            if ($hasLogo) {
                $bytes = PdfTemplate::image($auto['logo']); $s = getimagesizefromstring($bytes);
                $scale = min(30 / $s[0], ($headerH - 8) / $s[1]); $lw = $s[0] * $scale; $lh = $s[1] * $scale;
                $pdf->StartTransform(); $pdf->Image('@' . $bytes, 162 + (30 - $lw) / 2, 17, $lw, $lh, $s['mime'] === 'image/png' ? 'PNG' : 'JPEG'); $pdf->StopTransform();
            }
            $y = 13 + $headerH + 7;
        };
        $newPage(true);
        foreach ($auto['groups'] as $number => $group) {
            $pdf->SetFont($font, 'B', 11);
            $heading = ($auto['numbered'] ? ($number + 1) . '. ' : '') . $group['title'];
            $headingLines = self::lines($pdf, $heading, 176);
            $pdf->SetFont($font, '', 9);
            $desc = $group['description'] !== '' ? self::lines($pdf, $group['description'], 180) : [];
            $headingH = max(9, count($headingLines) * 5.3 + 3) + count($desc) * 4.4 + ($desc ? 3 : 0);
            $band = static function (bool $continued = false) use ($pdf, $auto, $font, $headingLines, $desc, $write, &$y): void {
                $h = max(9, count($headingLines) * 5.3 + 3);
                self::color($pdf, $auto['band'], true); $pdf->RoundedRect(13, $y, 184, $h, 3, '1111', 'F');
                self::color($pdf, $auto['text_color']); $pdf->SetFont($font, 'B', 11);
                $write(implode("\n", $headingLines) . ($continued ? ' (suite)' : ''), 17, $y + 1.5, 176, $h - 2);
                $y += $h + 1;
                if ($desc && !$continued) { $pdf->SetFont($font, '', 9); $write(implode("\n", $desc), 15, $y, 180, count($desc) * 4.4); $y += count($desc) * 4.4 + 2; }
            };
            $rows = []; $row = []; $units = 0;
            foreach ($group['items'] as $item) {
                if ($units + $item['width'] > 12) { $rows[] = $row; $row = []; $units = 0; }
                $row[] = $item; $units += $item['width'];
            }
            if ($row) { $rows[] = $row; }
            // Reserve the real first label height and at least one answer line with the group heading.
            $firstLabel = $labelHeight;
            $pdf->SetFont($font, 'B', 8);
            foreach ($rows[0] ?? [] as $item) { $firstLabel = max($firstLabel, count(self::lines($pdf, $item['label'], 187 * $item['width'] / 12 - 7)) * $labelHeight); }
            if ($y + $headingH + $firstLabel + $lineHeight + 12 > 277) { $newPage(); }
            $band();
            foreach ($rows as $row) {
                $cells = []; $labelH = $labelHeight; $maxLines = 1;
                foreach ($row as $item) {
                    $width = 187 * $item['width'] / 12 - 3;
                    $pdf->SetFont($font, 'B', 8); $labels = self::lines($pdf, $item['label'], $width - 4);
                    $labelH = max($labelH, count($labels) * $labelHeight);
                    $text = $item['source'] !== '' ? (string) ($variables[$item['source']] ?? '') : PdfExport::text($item['text'], $variables);
                    $pdf->SetFont($font, '', $size); $values = self::lines($pdf, $text === '' ? '—' : $text, $width - 4);
                    $maxLines = max($maxLines, count($values)); $cells[] = compact('width', 'labels', 'values');
                }
                $offset = 0;
                while ($offset < $maxLines) {
                    $minimum = $labelH + $lineHeight + 6;
                    if ($y + $minimum > 277) { $newPage(); $band(true); }
                    $capacity = max(1, (int) floor((277 - $y - $labelH - 6) / $lineHeight));
                    $count = min($capacity, $maxLines - $offset);
                    $valueH = $count * $lineHeight + 3;
                    $x = 13.0;
                    foreach ($cells as $cell) {
                        self::color($pdf, $auto['text_color']); $pdf->SetFont($font, 'B', 8);
                        $write(implode("\n", $cell['labels']), $x + 1, $y, $cell['width'] - 2, $labelH);
                        $pdf->SetDrawColor(211, 203, 191); $pdf->SetFillColor(255, 255, 255);
                        $pdf->RoundedRect($x, $y + $labelH + 1, $cell['width'], $valueH, 1.5, '1111', 'DF');
                        $pdf->SetFont($font, '', $size);
                        $write(implode("\n", array_slice($cell['values'], $offset, $count)), $x + 2, $y + $labelH + 3, $cell['width'] - 4, $valueH - 3);
                        $x += $cell['width'] + 3;
                    }
                    $y += $labelH + $valueH + 3; $offset += $count;
                }
            }
            $y += 2;
        }
        $footer();
    }
}