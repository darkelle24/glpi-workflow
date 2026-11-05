<?php
declare(strict_types=1);
namespace WorkflowBuilder;

/** Portable PDF layouts: no filesystem paths, remote resources or executable markup. */
final class PdfTemplate
{
    public const MAX_IMAGE_BYTES = 2097152;
    public const FONTS = ['dejavusans', 'dejavuserif', 'dejavusansmono'];

    public static function empty(): array
    {
        return ['enabled' => false, 'filename' => 'fiche-workflow', 'include_summary' => true, 'pages' => [], 'mode' => 'free', 'automatic' => PdfAutomatic::empty()];
    }

    public static function sources(array $definition): array
    {
        $sources = ['workflow' => 'Nom du workflow', 'ticket' => 'Numéro du ticket', 'version' => 'Version du workflow',
            'date_creation' => 'Date de lancement', 'date_completion' => 'Date de fin'];
        foreach ($definition['fields'] as $field) {
            if ($field['type'] !== 'section') { $sources['form.' . $field['id']] = $field['label'] . ' (lancement)'; }
        }
        foreach ($definition['steps'] as $step) {
            foreach ($step['fields'] as $field) {
                if ($field['type'] !== 'section') { $sources['steps.' . $step['id'] . '.' . $field['id']] = $step['title'] . ' / ' . $field['label']; }
            }
        }
        return $sources;
    }

    public static function image(string $uri): string
    {
        if (strlen($uri) > 2800000 || !preg_match('~^data:image/(png|jpeg);base64,([A-Za-z0-9+/=]+)$~D', $uri, $match)) {
            throw new ValidationException('Fond PDF invalide : image PNG ou JPEG intégrée attendue.');
        }
        $bytes = base64_decode($match[2], true);
        $info = $bytes === false ? false : @getimagesizefromstring($bytes);
        if (!$info || strlen($bytes) > self::MAX_IMAGE_BYTES || $info[0] > 6000 || $info[1] > 6000
            || $info[0] * $info[1] > 20000000 || ($info['mime'] ?? '') !== 'image/' . $match[1]) {
            throw new ValidationException('Image PDF invalide ou trop grande : 2 Mio, 6000 px par côté et 20 mégapixels maximum.');
        }
        if (function_exists('imagecreatefromstring')) {
            $image = @imagecreatefromstring($bytes);
            if ($image === false) { throw new ValidationException('Image PDF endommagée ou illisible.'); }
            unset($image);
        }
        return $bytes;
    }

    public static function validate(mixed $pdf, array $definition): array
    {
        if (!is_array($pdf) || array_diff(array_keys($pdf), array_keys(self::empty()))) {
            throw new ValidationException('Configuration PDF invalide.');
        }
        $pdf += self::empty();
        $pdf['enabled'] = Definition::boolean($pdf['enabled']);
        $pdf['include_summary'] = Definition::boolean($pdf['include_summary']);
        if (!is_string($pdf['filename']) || !preg_match('/^[a-zA-Z0-9_-]{1,80}$/D', $pdf['filename'])) {
            throw new ValidationException('Nom du PDF invalide : lettres, chiffres, tirets et soulignements uniquement (80 caractères).');
        }
        if (!is_array($pdf['pages']) || !array_is_list($pdf['pages']) || count($pdf['pages']) > 10) {
            throw new ValidationException('10 pages de modèle PDF maximum.');
        }
        $sources = self::sources($definition);
        if (!in_array($pdf['mode'], ['free', 'automatic'], true)) { throw new ValidationException('Mode PDF inconnu.'); }
        $pdf['automatic'] = PdfAutomatic::validate($pdf['automatic'], $sources, $pdf['mode'] === 'automatic');
        $count = 0;
        foreach ($pdf['pages'] as &$page) {
            if (!is_array($page) || array_diff(array_keys($page), ['orientation', 'background', 'boxes'])) {
                throw new ValidationException('Page PDF invalide.');
            }
            $page += ['orientation' => 'portrait', 'background' => '', 'boxes' => []];
            if (!in_array($page['orientation'], ['portrait', 'landscape'], true) || !is_string($page['background'])) {
                throw new ValidationException('Orientation ou fond PDF invalide.');
            }
            if ($page['background'] !== '') { self::image($page['background']); }
            if (!is_array($page['boxes']) || !array_is_list($page['boxes']) || ($count += count($page['boxes'])) > 200) {
                throw new ValidationException('200 zones PDF maximum par modèle.');
            }
            [$width, $height] = $page['orientation'] === 'portrait' ? [210, 297] : [297, 210];
            foreach ($page['boxes'] as &$box) {
                if (!is_array($box) || array_diff(array_keys($box), ['text', 'x', 'y', 'width', 'height', 'font', 'font_size', 'color', 'bold', 'align'])) {
                    throw new ValidationException('Zone PDF invalide.');
                }
                $box += ['text' => '', 'x' => 15, 'y' => 15, 'width' => 100, 'height' => 15,
                    'font' => 'dejavusans', 'font_size' => 12, 'color' => '#202b3a', 'bold' => false, 'align' => 'L'];
                foreach (['x' => [0, $width], 'y' => [0, $height], 'width' => [1, $width], 'height' => [1, $height], 'font_size' => [6, 72]] as $key => [$min, $max]) {
                    if ((!is_int($box[$key]) && !is_float($box[$key])) || !is_finite((float) $box[$key]) || $box[$key] < $min || $box[$key] > $max) {
                        throw new ValidationException('Position, dimensions ou taille de police PDF invalide.');
                    }
                }
                if ($box['x'] + $box['width'] > $width + 0.01 || $box['y'] + $box['height'] > $height + 0.01) {
                    throw new ValidationException('Une zone PDF dépasse de la page.');
                }
                if (!in_array($box['font'], self::FONTS, true) || !in_array($box['align'], ['L', 'C', 'R'], true)
                    || !is_string($box['color']) || !preg_match('/^#[a-fA-F0-9]{6}$/D', $box['color'])) {
                    throw new ValidationException('Police, couleur ou alignement PDF invalide.');
                }
                $box['bold'] = Definition::boolean($box['bold']);
                $box['text'] = Definition::text($box['text'], 10000);
                preg_match_all('/{{\s*([^{}]+?)\s*}}/', $box['text'], $matches);
                if ($pdf['mode'] === 'free' && array_diff($matches[1], array_keys($sources))) {
                    throw new ValidationException('Variable PDF inconnue : vérifier les champs et étapes associés aux zones.');
                }
            }
            unset($box);
        }
        unset($page);
        if ($pdf['enabled'] && $pdf['mode'] === 'free' && !$pdf['pages'] && !$pdf['include_summary']) {
            throw new ValidationException('Ajouter une page PDF ou activer le récapitulatif complet.');
        }
        return $pdf;
    }
}
