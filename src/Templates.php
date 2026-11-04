<?php
declare(strict_types=1);
namespace WorkflowBuilder;
final class Templates
{
    public static function save(int $id, array $definition, int $entity, bool $recursive, int $revision): int
    {
        $definition = Definition::validate($definition);
        Access::need('template', $id ? UPDATE : CREATE);
        if (!\Session::haveAccessToEntity($entity)) {
            throw new ValidationException('Entité inaccessible.');
        }
        return Store::transaction(function () use ($id, $definition, $entity, $recursive, $revision) {
            $now = Store::now();
            if ($id) {
                $template = Store::lock('templates', $id);
                Access::template($template, true);
                if ((int) $template['revision'] !== $revision) {
                    throw new ValidationException('Le modèle a été modifié par un autre utilisateur. Recharger avant de réessayer.');
                }
                if ($template['active_versions_id'] && ((int) $template['entities_id'] !== $entity || (bool) $template['is_recursive'] !== $recursive)) {
                    throw new ValidationException('Dupliquer ce modèle pour modifier sa portée après publication.');
                }
            }
            $data = ['name' => $definition['name'], 'description' => $definition['description'], 'category' => $definition['category'],
                'entities_id' => $entity, 'is_recursive' => (int) $recursive,
                'draft' => Store::json($definition), 'date_mod' => $now];
            if ($id) {
                Store::update('templates', $id, $data + ['revision' => $revision + 1]);
                return $id;
            }
            return Store::insert('templates', $data + ['users_id' => (int) \Session::getLoginUserID(),
                'state' => 'draft', 'revision' => 1, 'date_creation' => $now]);
        });
    }
    public static function publish(int $id, int $revision): void
    {
        Access::need('publish', UPDATE);
        Store::transaction(function () use ($id, $revision) {
            $template = Store::lock('templates', $id);
            $definition = Access::template($template, true);
            if ((int) $template['revision'] !== $revision) {
                throw new ValidationException('Version de brouillon obsolète. Recharger la page.');
            }
            if (!$definition['steps']) {
                throw new ValidationException('Ajouter au moins une étape avant publication.');
            }
            Access::definitionReferences($definition);
            foreach ($definition['steps'] as $step) {
                foreach ($step['knowbaseitems_id'] as $article) {
                    if (!(new \KnowbaseItem())->can($article, READ)) {
                        throw new ValidationException('Article de connaissance introuvable ou inaccessible : ' . $article);
                    }
                }
            }
            $versions = Store::rows('versions', ['templates_id' => $id], ['ORDER' => ['number DESC'], 'LIMIT' => 1]);
            $number = $versions ? (int) $versions[0]['number'] + 1 : 1;
            $json = Store::json($definition);
            $version = Store::insert('versions', ['templates_id' => $id, 'number' => $number,
                'definition' => $json, 'checksum' => hash('sha256', $json),
                'users_id' => (int) \Session::getLoginUserID(), 'date_creation' => Store::now()]);
            foreach ($definition['fields'] as $position => $field) {
                self::field($version, '', $position, $field);
            }
            foreach ($definition['steps'] as $position => $step) {
                Store::insert('steps', ['versions_id' => $version, 'step_key' => $step['id'],
                    'position' => $position, 'definition' => Store::json($step)]);
                foreach ($step['fields'] as $fieldPosition => $field) {
                    self::field($version, $step['id'], $fieldPosition, $field);
                }
                foreach ($step['depends_on'] as $dependency) {
                    Store::insert('dependencies', ['versions_id' => $version, 'step_key' => $step['id'], 'requires_key' => $dependency]);
                }
            }
            foreach ($definition['rules'] as $position => $rule) {
                Store::insert('rules', ['versions_id' => $version, 'position' => $position, 'definition' => Store::json($rule)]);
            }
            Store::update('templates', $id, ['active_versions_id' => $version, 'state' => 'active', 'revision' => $revision + 1, 'date_mod' => Store::now()]);
        });
    }
    private static function field(int $version, string $step, int $position, array $field): void
    {
        Store::insert('fields', ['versions_id' => $version, 'step_key' => $step, 'field_key' => $field['id'],
            'position' => $position, 'definition' => Store::json($field)]);
    }
    public static function state(int $id, string $state, int $revision): void
    {
        Access::need('publish', UPDATE);
        if (!in_array($state, ['draft', 'active', 'archived'], true)) {
            throw new ValidationException('État de modèle invalide.');
        }
        Store::transaction(function () use ($id, $state, $revision) {
            $template = Store::lock('templates', $id);
            Access::template($template, true);
            if ((int) $template['revision'] !== $revision || ($state === 'active' && !$template['active_versions_id'])) {
                throw new ValidationException('Modèle obsolète ou sans version publiée.');
            }
            Store::update('templates', $id, ['state' => $state, 'revision' => $revision + 1, 'date_mod' => Store::now()]);
        });
    }
    public static function version(int $id): array
    {
        $version = Store::one('versions', $id);
        if (!hash_equals($version['checksum'], hash('sha256', $version['definition']))) {
            throw new \RuntimeException('Workflow version checksum mismatch');
        }
        return [$version, Definition::decode($version['definition'])];
    }
}
