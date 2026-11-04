<?php
declare(strict_types=1);
/** Real GLPI/MariaDB tests, restricted to an explicitly named test database. */
if (getenv('WORKFLOWBUILDER_ALLOW_NATIVE_TESTS') !== '1') { exit("Dedicated native test environment required.\n"); }
$core = realpath($argv[1] ?? '');
if (!$core || !is_file($core . '/vendor/autoload.php')) { throw new RuntimeException('Installed GLPI required'); }
require $core . '/vendor/autoload.php';
$kernel = new \Glpi\Kernel\Kernel(); $kernel->boot();
global $DB;
if (!preg_match('/^workflowbuilder_(glpi12|test)_[a-z0-9_]+$/D', $DB->dbdefault)) { throw new RuntimeException('Dedicated test DB required'); }
$auth = new Auth();
if (!$auth->login(getenv('WB_GLPI_TEST_USER') ?: 'glpi', getenv('WB_GLPI_TEST_PASSWORD') ?: 'glpi', true)) { throw new RuntimeException('Test login failed'); }
\WorkflowBuilder\Access::reload();
use WorkflowBuilder\{Access, Definition, Http, Knowledge, Store, Templates, ValidationException};
$passed = 0;
function verify(string $label, bool $ok): void { global $passed; if (!$ok) { throw new RuntimeException($label); } echo "PASS $label\n"; $passed++; }
function refuses(callable $run): bool { try { $run(); } catch (ValidationException) { return true; } return false; }
$input = ['template' => 0, 'entity' => 0, 'title' => 'Procédure native ' . bin2hex(random_bytes(6)),
    'workflow' => 'Workflow <test>', 'step' => 'Étape <test>', 'description' => 'Objectif <script>alert(1)</script>',
    'legacy' => 'tokencontenunatif et consignes existantes', 'visibility' => 'entity'];
$item = Knowledge::create($input);
$id = $item['id'];
verify('native item creation and readable association', $id > 0 && Knowledge::item($id)['label'] === $input['title']);
$article = new KnowbaseItem(); $article->getFromDB($id);
verify('six procedure sections with escaped content', substr_count($article->fields['answer'], '<h2>') === 6 && !str_contains($article->fields['answer'], '<script>'));
verify('legacy instructions copied into knowledge base', str_contains($article->fields['answer'], $input['legacy']));
verify('native entity visibility targets the selected entity', $DB->request(['FROM' => 'glpi_entities_knowbaseitems', 'WHERE' => ['knowbaseitems_id' => $id, 'entities_id' => 0]])->count() === 1);
verify('empty-query browsing works', count(Knowledge::search([])['items']) > 0);
verify('full text finds procedure body', in_array($id, array_column(Knowledge::search(['q' => 'tokencontenunatif'])['items'], 'id'), true));
verify('title-only excludes body matches', !in_array($id, array_column(Knowledge::search(['q' => 'tokencontenunatif', 'scope' => 'title'])['items'], 'id'), true));
verify('title search and internal filter', in_array($id, array_column(Knowledge::search(['q' => $input['title'], 'kind' => 'internal', 'mine' => '1'])['items'], 'id'), true));
verify('FAQ filter excludes internal procedures', !in_array($id, array_column(Knowledge::search(['q' => $input['title'], 'kind' => 'faq'])['items'], 'id'), true));
verify('modification filter excludes older entries', !in_array($id, array_column(Knowledge::search(['q' => $input['title'], 'since' => '2099-01-01'])['items'], 'id'), true));
verify('hydration validates existing article permissions', Knowledge::search(['ids' => $id . ',2147483647'])['items'] === [$item]);
verify('missing and zero IDs cannot return creation defaults', Knowledge::item(0) === null && Knowledge::item(2147483647) === null);
verify('invalid filters and date rejected', refuses(fn() => Knowledge::search(['sort' => 'sql'])) && refuses(fn() => Knowledge::search(['since' => '2026-02-31'])));
verify('malformed hydration rejected', refuses(fn() => Knowledge::search(['ids' => '0,1'])) && refuses(fn() => Knowledge::search(['ids' => ['1']])));
$private = Knowledge::create(array_replace($input, ['title' => $input['title'] . ' privée', 'visibility' => 'private']));
verify('personal procedure has no explicit entity visibility', $DB->request(['FROM' => 'glpi_entities_knowbaseitems', 'WHERE' => ['knowbaseitems_id' => $private['id']]])->count() === 0);
$rights = $_SESSION['glpiactiveprofile'];
$_SESSION['glpiactiveprofile']['knowbase'] = READ;
verify('native creation permission required', refuses(fn() => Knowledge::create($input)));
$_SESSION['glpiactiveprofile'] = $rights;
$_SESSION['glpiactiveprofile']['plugin_workflowbuilder_template'] = READ;
verify('plugin creation permission required', refuses(fn() => Knowledge::create($input)));
$_SESSION['glpiactiveprofile'] = $rights;
verify('inaccessible entity cannot receive new article', refuses(fn() => Knowledge::create(array_replace($input, ['entity' => 2147483647]))));
$d = Definition::empty(); $d['name'] = 'Knowledge permissions'; $d['manage_users'] = [2147483647];
$template = Templates::save(0, $d, 0, false, 0);
$_SESSION['glpiactiveprofile']['plugin_workflowbuilder_admin'] = 0;
verify('template manager restrictions enforced on creation', refuses(fn() => Knowledge::create(array_replace($input, ['template' => $template]))));
$_SESSION['glpiactiveprofile'] = $rights;
// can() must hide a private article even when a caller knows its ID.
$user = $_SESSION['glpiID'];
$_SESSION['glpiID'] = 2147483646;
$_SESSION['glpiactiveprofile']['knowbase'] = READ;
verify('private article metadata is not disclosed to another reader', Knowledge::item($private['id']) === null && Knowledge::search(['ids' => (string) $private['id']])['items'] === []);
$_SESSION['glpiID'] = $user; $_SESSION['glpiactiveprofile'] = $rights;
verify('ascending and descending cursors preserve direction',
    !in_array($id, array_column(Knowledge::search(['after' => $id, 'sort' => 'newest'])['items'], 'id'), true)
    && !in_array($id, array_column(Knowledge::search(['after' => $id, 'sort' => 'oldest'])['items'], 'id'), true));
echo "$passed native knowledge checks passed\n";
