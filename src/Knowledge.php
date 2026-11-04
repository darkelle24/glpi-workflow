<?php
declare(strict_types=1);
namespace WorkflowBuilder;

/** Knowledge-base access always goes through the native item permissions. */
final class Knowledge
{
    public static function item(int $id): ?array
    {
        if ($id < 1) { return null; }
        $article = new \KnowbaseItem();
        if (!$article->can($id, READ) || !empty($article->fields['is_deleted'])) { return null; }
        $fields = $article->fields;
        $text = \Glpi\RichText\RichText::getTextFromHtml($fields['answer'] ?? '', false, true);
        return [
            'id' => $id, 'label' => $fields['name'], 'excerpt' => mb_substr(trim($text), 0, 240),
            'faq' => (bool) ($fields['is_faq'] ?? false), 'modified' => $fields['date_mod'] ?? '',
            'url' => \KnowbaseItem::getFormURLWithID($id),
        ];
    }

    public static function search(array $input): array
    {
        global $DB;
        $query = trim(Http::string($input, 'q'));
        $scope = Http::string($input, 'scope', 'all');
        $kind = Http::string($input, 'kind', 'all');
        $sort = Http::string($input, 'sort', 'newest');
        $since = Http::string($input, 'since');
        if (mb_strlen($query) > 100 || !in_array($scope, ['all', 'title'], true)
            || !in_array($kind, ['all', 'internal', 'faq'], true) || !in_array($sort, ['newest', 'oldest'], true)
            || !in_array(Http::string($input, 'mine', '0'), ['0', '1'], true)) {
            throw new ValidationException('Filtres de connaissance invalides.');
        }
        if ($since !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $since)
            || !checkdate((int) substr($since, 5, 2), (int) substr($since, 8, 2), (int) substr($since, 0, 4)))) {
            throw new ValidationException('Date de modification invalide.');
        }
        // Hydration ignores filters and never exposes inaccessible titles.
        if (isset($input['ids'])) {
            $ids = Http::string($input, 'ids');
            if (!preg_match('/^[1-9]\d*(,[1-9]\d*){0,199}$/D', $ids)) {
                throw new ValidationException('Sélection de fiches invalide.');
            }
            return ['items' => array_values(array_filter(array_map(self::item(...), array_unique(array_map('intval', explode(',', $ids)))))), 'next' => 0];
        }
        $where = [];
        $after = Http::integer($input, 'after');
        if ($after) { $where['id'] = [$sort === 'oldest' ? '>' : '<', $after]; }
        if ($query !== '') {
            $like = ['LIKE', '%' . $query . '%'];
            if ($scope === 'title') { $where['name'] = $like; }
            else { $where['OR'] = ['name' => $like, 'answer' => $like]; }
        }
        if ($kind !== 'all') { $where['is_faq'] = (int) ($kind === 'faq'); }
        if (Http::string($input, 'mine', '0') === '1') { $where['users_id'] = (int) \Session::getLoginUserID(); }
        if ($since !== '') { $where['date_mod'] = ['>=', $since . ' 00:00:00']; }
        $items = [];
        $last = $scanned = 0;
        foreach ($DB->request(['SELECT' => ['id'], 'FROM' => \KnowbaseItem::getTable(), 'WHERE' => $where,
            'ORDER' => ['id ' . ($sort === 'oldest' ? 'ASC' : 'DESC')], 'LIMIT' => 500]) as $row) {
            $scanned++;
            $last = (int) $row['id'];
            $item = self::item($last);
            if (!$item) { continue; }
            $items[] = $item;
            if (count($items) >= 20) { break; }
        }
        return ['items' => $items, 'next' => ($scanned >= 500 || count($items) >= 20) ? $last : 0];
    }

    public static function content(string $workflow, string $step, string $description, string $legacy = ''): string
    {
        $e = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<h1>' . $e($step) . '</h1><p><strong>Workflow :</strong> ' . $e($workflow) . '</p>';
        $sections = [
            'Objectif' => $description ?: 'À compléter : résultat attendu de cette étape.',
            'Prérequis' => 'À compléter : accès, matériel, outils et vérifications nécessaires.',
            'Procédure détaillée' => $legacy ?: "1. À compléter : première action.\n2. À compléter : actions suivantes.",
            'Contrôles et résultat attendu' => 'À compléter : vérifications permettant de valider cette étape.',
            'En cas de problème / retour arrière' => 'À compléter : diagnostic, annulation et personne à contacter.',
            'Références et pièces jointes' => 'À compléter : liens utiles et captures à joindre dans GLPI.',
        ];
        foreach ($sections as $title => $text) { $html .= '<h2>' . $e($title) . '</h2><p>' . nl2br($e($text)) . '</p>'; }
        return $html;
    }

    public static function create(array $input): array
    {
        $templateId = Http::integer($input, 'template');
        Access::need('template', $templateId ? UPDATE : CREATE);
        if ($templateId) { Access::template(Store::one('templates', $templateId), true); }
        $entity = Http::integer($input, 'entity');
        if (!\Session::haveAccessToEntity($entity)) { throw new ValidationException('Entité inaccessible.'); }
        $text = [];
        foreach (['title' => 255, 'workflow' => 255, 'step' => 255, 'description' => 10000, 'legacy' => 10000] as $key => $limit) {
            $text[$key] = trim(Http::string($input, $key));
            if (mb_strlen($text[$key]) > $limit || (in_array($key, ['title', 'step'], true) && $text[$key] === '')) {
                throw new ValidationException('Texte de fiche invalide : ' . $key);
            }
        }
        $visibility = Http::string($input, 'visibility', 'entity');
        if (!in_array($visibility, ['entity', 'private'], true)) { throw new ValidationException('Visibilité invalide.'); }
        $data = [
            'name' => $text['title'], 'answer' => self::content($text['workflow'], $text['step'], $text['description'], $text['legacy']),
            'users_id' => (int) \Session::getLoginUserID(), 'is_faq' => 0,
        ];
        if ($visibility === 'entity') {
            $data['_visibility'] = ['_type' => 'Entity', 'entities_id' => $entity, 'is_recursive' => 0];
        }
        $article = new \KnowbaseItem();
        if (!\Session::haveRight('knowbase', CREATE) || !$article->can(-1, CREATE, $data)) {
            throw new ValidationException('Vous ne disposez pas du droit GLPI de créer une fiche de connaissance.');
        }
        return Store::transaction(function () use ($article, $data): array {
            $id = $article->add($data);
            if (!$id || !($item = self::item((int) $id))) { throw new ValidationException('La fiche de connaissance n’a pas pu être créée.'); }
            return $item;
        });
    }
}
