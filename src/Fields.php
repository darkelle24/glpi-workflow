<?php
declare(strict_types=1);
namespace WorkflowBuilder;

final class Fields
{
    public const TYPES = ['text', 'textarea', 'number', 'date', 'datetime', 'checkbox', 'select', 'radio', 'multiselect', 'boolean', 'email', 'url', 'user', 'group', 'entity', 'asset', 'document', 'section'];
    public const REFERENCES = ['user' => 'User', 'group' => 'Group', 'entity' => 'Entity', 'asset' => 'Computer', 'document' => 'Document'];

    public static function definitions(array $fields, array $variables): array
    {
        if (!array_is_list($fields) || count($fields) > 200) {
            throw new ValidationException('Au maximum 200 champs par formulaire.');
        }
        $seen = [];
        foreach ($fields as &$field) {
            if (!is_array($field) || array_diff(array_keys($field), ['id', 'label', 'description', 'type', 'required', 'default', 'choices', 'min', 'max', 'maxlength', 'condition'])) {
                throw new ValidationException('Propriétés de champ inconnues.');
            }
            Definition::identifier($field['id'] ?? null);
            $id = $field['id'];
            if (isset($seen[$id])) {
                throw new ValidationException('Identifiant de champ dupliqué : ' . $id);
            }
            $seen[$id] = true;
            $field['label'] = Definition::text($field['label'] ?? '', 255, true);
            $field['description'] = Definition::text($field['description'] ?? '', 4000);
            if (preg_match('/password|passwd|mot.?de.?passe|secret|token|jeton/i', $id . ' ' . $field['label'])) {
                throw new ValidationException('Les champs de mots de passe, secrets et jetons sont interdits.');
            }
            if (!in_array($field['type'] ?? '', self::TYPES, true)) {
                throw new ValidationException('Type de champ inconnu.');
            }
            $field['required'] = Definition::boolean($field['required'] ?? false);
            $field['condition'] ??= null;
            Condition::validate($field['condition'], $variables);
            $field['choices'] ??= [];
            if (!is_array($field['choices']) || !array_is_list($field['choices']) || count($field['choices']) > 100) {
                throw new ValidationException('Choix de champ invalides.');
            }
            foreach ($field['choices'] as &$choice) {
                $choice = Definition::text($choice, 255, true);
            }
            unset($choice);
            if (count(array_unique($field['choices'])) !== count($field['choices'])) {
                throw new ValidationException('Choix dupliqués.');
            }
            if (in_array($field['type'], ['select', 'radio', 'multiselect'], true) && !$field['choices']) {
                throw new ValidationException('Une liste doit contenir des choix.');
            }
            foreach (['min', 'max'] as $bound) {
                if (isset($field[$bound]) && (!is_numeric($field[$bound]) || !is_finite((float) $field[$bound]))) {
                    throw new ValidationException('Borne numérique invalide.');
                }
            }
            if (isset($field['min'], $field['max']) && (float) $field['min'] > (float) $field['max']) {
                throw new ValidationException('Le minimum dépasse le maximum.');
            }
            if (isset($field['maxlength']) && filter_var($field['maxlength'], FILTER_VALIDATE_INT) === false) { throw new ValidationException('Longueur maximale invalide.'); }
            $field['maxlength'] = (int) ($field['maxlength'] ?? ($field['type'] === 'textarea' ? 10000 : 255));
            if ($field['maxlength'] < 1 || $field['maxlength'] > 10000) {
                throw new ValidationException('Longueur maximale invalide (1 à 10000).');
            }
            if (array_key_exists('default', $field) && $field['default'] !== null && $field['default'] !== '') {
                $field['default'] = self::value($field, $field['default'], false);
                if (isset(self::REFERENCES[$field['type']])) {
                    throw new ValidationException('Choisir les références GLPI au lancement, sans valeur par défaut.');
                }
            }
            // Local visibility can reference only preceding fields: no cycles or hidden-value injection.
            $variables[] = 'self.' . $id;
        }
        unset($field);
        return $fields;
    }

    public static function values(array $fields, array $input, array $context, bool $complete): array
    {
        $allowed = array_column($fields, 'id');
        if (array_diff(array_keys($input), $allowed)) {
            throw new ValidationException('Champ de saisie inconnu.');
        }
        $output = [];
        foreach ($fields as $field) {
            if ($field['type'] === 'section') {
                continue;
            }
            $id = $field['id'];
            if (!Condition::evaluate($field['condition'], $context)) {
                $output[$id] = null;
                $context['self.' . $id] = null;

                continue;
            }
            $value = (array_key_exists($id, $input) ? $input[$id] : ($field['default'] ?? null));
            $output[$id] = self::value($field, $value, $complete);
            $context['self.' . $id] = $output[$id];
        }
        return $output;
    }

    public static function formValues(array $fields, array $input, bool $complete = true): array
    {
        $out = [];
        $context = [];
        if (array_diff(array_keys($input), array_column($fields, 'id'))) {
            throw new ValidationException('Champ de formulaire inconnu.');
        }
        foreach ($fields as $field) {
            if ($field['type'] === 'section') {
                continue;
            }
            $id = $field['id'];
            $out[$id] = Condition::evaluate($field['condition'], $context)
                ? self::value($field, (array_key_exists($id, $input) ? $input[$id] : ($field['default'] ?? null)), $complete) : null;
            $context['form.' . $id] = $out[$id];
            $context['self.' . $id] = $out[$id];
        }
        return $out;
    }

    public static function context(array $values): array
    {
        $out = [];
        foreach ($values as $key => $value) {
            $out['form.' . $key] = $value;
        }
        return $out;
    }

    private static function value(array $f, mixed $value, bool $complete): mixed
    {
        $type = $f['type'];
        if ($type === 'section') {
            return null;
        }
        if ($type === 'checkbox') {
            if (!in_array($value, [null, '', false, true, 0, 1, '0', '1'], true)) {
                throw new ValidationException($f['label'] . ' : case à cocher invalide.');
            }
            $value = in_array($value, [true, 1, '1'], true);
        }
        $empty = $value === null || $value === '' || $value === [];
        if ($complete && $f['required'] && ($empty || ($type === 'checkbox' && $value !== true))) {
            throw new ValidationException($f['label'] . ' : champ obligatoire.');
        }
        if ($empty) {
            return null;
        }
        if ($type === 'multiselect') {
            if (!is_array($value) || !array_is_list($value) || count($value) > 100 || count(array_filter($value, 'is_string')) !== count($value) || array_diff($value, $f['choices'])) {
                throw new ValidationException($f['label'] . ' : choix invalide.');
            }
            return array_values(array_unique($value));
        }
        if (!is_scalar($value)) {
            throw new ValidationException($f['label'] . ' : valeur invalide.');
        }
        $text = (string) $value;
        $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        if (!preg_match('//u', $text) || $length > $f['maxlength'] || str_contains($text, "\0")) {
            throw new ValidationException($f['label'] . ' : texte trop long ou invalide.');
        }
        $valid = match ($type) {
            'email' => filter_var($text, FILTER_VALIDATE_EMAIL) !== false,
            'url' => filter_var($text, FILTER_VALIDATE_URL) !== false && in_array(strtolower((string) parse_url($text, PHP_URL_SCHEME)), ['http', 'https'], true),
            'number' => is_numeric($value) && is_finite((float) $value)
                && (!isset($f['min']) || (float) $value >= (float) $f['min'])
                && (!isset($f['max']) || (float) $value <= (float) $f['max']),
            'date', 'datetime' => self::date($text, $type === 'date' ? 'Y-m-d' : 'Y-m-d\TH:i'),
            'select', 'radio' => in_array($text, $f['choices'], true),
            'boolean' => in_array($value, [true, false, 1, 0, '1', '0'], true),
            'user', 'group', 'entity', 'asset', 'document' => filter_var($value, FILTER_VALIDATE_INT) !== false && (int) $value >= ($type === 'entity' ? 0 : 1),
            default => true,
        };
        if (!$valid) {
            throw new ValidationException($f['label'] . ' : valeur non valide.');
        }
        return match (true) {
            $type === 'boolean', $type === 'checkbox' => in_array($value, [true, 1, '1'], true),
            $type === 'number' => (float) $value,
            isset(self::REFERENCES[$type]) => (int) $value,
            default => $text,
        };
    }

    private static function date(string $value, string $format): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
        return $date !== false && $date->format($format) === $value;
    }
}
