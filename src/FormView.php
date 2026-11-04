<?php
declare(strict_types=1);
namespace WorkflowBuilder;
final class FormView
{
    public static function show(array $fields, array $values = [], bool $readonly = false, ?int $ticket = null, array $context = []): void
    {
        $context = $context ?: Fields::context($values);
        echo '<div class="wb-fields">';
        foreach ($fields as $field) {
            $id = 'wb-' . bin2hex(random_bytes(6));
            $value = array_key_exists($field['id'], $values) ? $values[$field['id']] : ($field['default'] ?? null);
            $visible = Condition::evaluate($field['condition'], $context);
            $context['self.' . $field['id']] = $visible ? $value : null;
            if ($readonly && !$visible) { continue; }
            $name = 'values[' . $field['id'] . ']';
            echo '<div class="wb-field" data-wb-field="' . View::e(Store::json($field)) . '">';
            if ($field['type'] === 'section') {
                echo '<h3>' . View::e($field['label']) . '</h3><p>' . View::e($field['description']) . '</p></div>';
                continue;
            }
            echo '<label for="' . $id . '" class="form-label">' . View::e($field['label']) . ($field['required'] ? ' *' : '') . '</label>';
            if ($readonly) {
                echo '<div>' . self::value($field, $value, $ticket) . '</div></div>';
                continue;
            }
            $attrs = ' id="' . $id . '" name="' . View::e($name) . '" class="form-control" aria-describedby="' . $id . '-help"';
            $type = $field['type'];
            if ($type === 'textarea') {
                echo '<textarea' . $attrs . ' maxlength="' . $field['maxlength'] . '">' . View::e($value) . '</textarea>';
            } elseif (in_array($type, ['select', 'radio', 'multiselect', 'boolean'], true)) {
                if ($type === 'radio') {
                    echo '<div id="' . $id . '"><label><input type="radio" name="' . View::e($name) . '" value=""' . ($value === null ? ' checked' : '') . '> Aucun</label> ';
                    foreach ($field['choices'] as $choice) {
                        echo '<label><input type="radio" name="' . View::e($name) . '" value="' . View::e($choice) . '"' . ($value === $choice ? ' checked' : '') . '> ' . View::e($choice) . '</label> ';
                    }
                    echo '</div>';
                } else {
                    $multi = $type === 'multiselect';
                    echo '<select' . str_replace(View::e($name), View::e($name . ($multi ? '[]' : '')), $attrs) . ($multi ? ' multiple' : '') . '>';
                    if (!$multi) { echo '<option value="">Sélectionner…</option>'; }
                    $choices = $type === 'boolean' ? ['1' => 'Oui', '0' => 'Non'] : array_combine($field['choices'], $field['choices']);
                    foreach ($choices as $key => $label) {
                        $selected = $multi ? in_array((string) $key, (array) $value, true)
                            : ($value !== null && (string) $key === (is_bool($value) ? ($value ? '1' : '0') : (string) $value));
                        echo '<option value="' . View::e($key) . '"' . ($selected ? ' selected' : '') . '>' . View::e($label) . '</option>';
                    }
                    echo '</select>';
                }
            } elseif ($type === 'checkbox') {
                echo '<input type="hidden" name="' . View::e($name) . '" value="0">';
                echo '<input type="checkbox"' . $attrs . ' value="1"' . ($value ? ' checked' : '') . '>';
            } elseif (isset(Fields::REFERENCES[$type])) {
                echo '<div class="wb-reference" data-reference-type="' . $type . '"' . ($ticket ? ' data-ticket="' . $ticket . '"' : '') . '>';
                echo '<input type="hidden"' . $attrs . ' value="' . View::e($value) . '"><span data-reference-label>';
                echo $value !== null ? self::value($field, $value, $ticket) : 'Aucun élément sélectionné';
                echo '</span><input class="form-control" type="search" placeholder="Rechercher (2 caractères minimum)…" aria-label="Rechercher ' . View::e($field['label']) . '"><div data-reference-results></div><button type="button" class="btn btn-sm btn-outline-secondary" data-clear-reference>Effacer</button></div>';
                if ($type === 'document') { echo '<small>Fichier déjà joint au ticket par GLPI. Pour un nouveau ticket, ajouter les documents dans les étapes après lancement.</small>'; }
                if ($type === 'asset') { echo '<small>Matériel pris en charge : ordinateurs GLPI.</small>'; }
            } else {
                $htmlType = match ($type) { 'datetime' => 'datetime-local', 'date', 'email', 'url', 'number' => $type, default => 'text' };
                echo '<input type="' . $htmlType . '"' . $attrs . ' value="' . View::e($value) . '" maxlength="' . $field['maxlength'] . '"' . ($type === 'number' ? ' step="any"' : '');
                foreach (['min', 'max'] as $bound) {
                    if ($type === 'number' && isset($field[$bound])) { echo ' ' . $bound . '="' . View::e($field[$bound]) . '"'; }
                }
                echo '>';
            }
            echo '<small id="' . $id . '-help">' . View::e($field['description']) . '</small></div>';
        }
        echo '</div>';
    }
    public static function value(array $field, mixed $value, ?int $ticket = null): string
    {
        if ($value === null) { return '—'; }
        $type = $field['type'];
        if (!isset(Fields::REFERENCES[$type])) {
            return nl2br(View::e(Engine::printValue($value)));
        }
        $class = Fields::REFERENCES[$type];
        if ($type === 'entity' && (int) $value === 0) { return 'Entité racine'; }
        if ($type !== 'document') { return View::e(View::person($class, (int) $value)); }
        $doc = new \Document();
        if (!$doc->getFromDB((int) $value) || !$doc->canViewFile($ticket ? ['tickets_id' => $ticket] : [])) {
            return 'Document supprimé ou inaccessible';
        }
        global $CFG_GLPI;
        return '<a href="' . View::e($CFG_GLPI['root_doc'] . '/front/document.send.php?docid=' . (int) $value . ($ticket ? '&tickets_id=' . $ticket : '')) . '">' . View::e($doc->fields['name']) . '</a>';
    }
}
