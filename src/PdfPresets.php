<?php
declare(strict_types=1);
namespace WorkflowBuilder;

/** Presets are entity-scoped starting points. Applying one copies its content into a workflow. */
final class PdfPresets
{
    public static function read(array $row): array
    {
        if (!\Session::haveAccessToEntity((int) $row['entities_id'])) { throw new ValidationException('Modèle PDF d’une entité inaccessible.'); }
        return PdfAutomatic::validate(Store::unpack($row['definition']), PdfTemplate::sources(Definition::empty()));
    }

    public static function catalog(): array
    {
        Access::need('template');
        $items = PdfAutomatic::defaults();
        foreach (Store::rows('pdfpresets', ['is_active' => 1], ['ORDER' => ['name', 'id']]) as $row) {
            try { $auto = self::read($row); } catch (ValidationException) { continue; }
            $items[] = ['id' => 'preset_' . $row['id'], 'name' => $row['name'], 'automatic' => $auto];
        }
        return $items;
    }

    public static function save(int $id, string $name, array $auto, int $revision, bool $active, int $entity): int
    {
        Access::need('admin', UPDATE);
        if (!\Session::haveAccessToEntity($entity)) { throw new ValidationException('Entité du modèle PDF inaccessible.'); }
        $name = Definition::text($name, 255, true);
        $auto = PdfAutomatic::validate($auto, PdfTemplate::sources(Definition::empty()));
        $json = Store::json($auto);
        if (strlen($json) > Definition::MAX_BYTES) { throw new ValidationException('Modèle PDF trop volumineux.'); }
        return Store::transaction(static function () use ($id, $name, $json, $revision, $active, $entity): int {
            if ($id) {
                $row = Store::lock('pdfpresets', $id); self::read($row);
                if ((int) $row['revision'] !== $revision) { throw new ValidationException('Ce modèle PDF a été modifié. Recharger avant de réessayer.'); }
                // An update cannot silently move a shared preset between entities.
                if ((int) $row['entities_id'] !== $entity) { throw new ValidationException('Créer une copie pour changer l’entité du modèle PDF.'); }
            }
            $values = ['name' => $name, 'definition' => $json, 'is_active' => (int) $active,
                'revision' => $revision + 1, 'date_mod' => Store::now()];
            if ($id) { Store::update('pdfpresets', $id, $values); return $id; }
            return Store::insert('pdfpresets', $values + ['entities_id' => $entity, 'users_id' => (int) \Session::getLoginUserID(), 'date_creation' => Store::now()]);
        });
    }
}