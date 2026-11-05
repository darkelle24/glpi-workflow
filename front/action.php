<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
use WorkflowBuilder\{Access, Definition, Http, View, Store, Templates, Instances, ValidationException};
try {
    Http::post();
    $operation = Http::string($_POST, 'operation');
    $id = Http::integer($_POST, 'id');
    switch ($operation) {
        case 'publish':
            Templates::publish($id, Http::integer($_POST, 'revision'));
            Http::redirect(Http::url('template.form.php', ['id' => $id]));
        case 'state':
            Templates::state($id, Http::string($_POST, 'state'), Http::integer($_POST, 'revision'));
            Http::redirect(Http::url('template.form.php', ['id' => $id]));
        case 'duplicate':
            $template = Store::one('templates', $id);
            $definition = Access::template($template);
            $definition['name'] = mb_substr($definition['name'], 0, 240) . ' (copie)';
            $copy = Templates::save(0, $definition, (int) ($_SESSION['glpiactive_entity'] ?? 0), false, 0);
            Http::redirect(Http::url('template.form.php', ['id' => $copy]));
        case 'import':
            Access::need('template', CREATE);
            $example = Http::string($_POST, 'example');
            if ($example !== '') {
                if (!in_array($example, ['creation-utilisateur', 'depart-utilisateur', 'preparation-ordinateur', 'fiche-connexion-salarie', 'fiche-connexion-automatique'], true)) {
                    throw new ValidationException('Exemple inconnu.');
                }
                $json = file_get_contents(dirname(__DIR__) . '/examples/' . $example . '.json');
            } else {
                $file = $_FILES['model'] ?? [];
                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > Definition::MAX_BYTES
                    || !is_uploaded_file($file['tmp_name'] ?? '')) {
                    throw new ValidationException('Choisir un fichier JSON valide de 16 Mio maximum (images PDF comprises).');
                }
                $json = file_get_contents($file['tmp_name']);
            }
            $definition = Definition::decode($json);
            // IDs are installation-local: import never grants access based on foreign IDs.
            foreach (['launch_groups', 'execute_groups', 'manage_groups', 'launch_users', 'execute_users', 'manage_users'] as $key) {
                $definition[$key] = [];
            }
            foreach ($definition['steps'] as &$step) {
                $step['users_id'] = $step['groups_id'] = 0;
                $step['knowbaseitems_id'] = [];
            }
            unset($step);
            $copy = Templates::save(0, $definition, (int) ($_SESSION['glpiactive_entity'] ?? 0), false, 0);
            Http::redirect(Http::url('template.form.php', ['id' => $copy]));
        case 'step':
            $fromTicket = Http::integer($_POST, 'from_ticket');
            Instances::act($id, Http::string($_POST, 'step'), Http::string($_POST, 'action'),
                Http::values($_POST), Http::integer($_POST, 'revision'), Http::string($_POST, 'comment'),
                Http::integer($_POST, 'users_id'), Http::integer($_POST, 'groups_id'));
            $instance = Store::one('instances', $id);
            $step = Http::string($_POST, 'step');
            Http::redirect($fromTicket ? Http::ticketWorkflow((int) $instance['tickets_id'], $id, $step) : Http::url('instance.php', ['id' => $id]) . '#wb-step-' . rawurlencode($step));
        case 'rights':
            if (!Access::has('admin', UPDATE) && !Access::canBootstrapRights()) { throw new ValidationException('Administration des droits interdite.'); }
            $profile = new Profile();
            $profile->check($id, UPDATE); // Includes core step-up authentication.
            $posted = $_POST['rights'] ?? [];
            if (!is_array($posted) || array_diff(array_keys($posted), array_keys(Access::RIGHTS))) {
                throw new ValidationException('Droits de profil invalides.');
            }
            $rights = [];
            foreach (Access::RIGHTS as $name => $label) {
                $bits = $posted[$name] ?? [];
                if (!is_array($bits)) { throw new ValidationException('Droits invalides.'); }
                $rights[$name] = 0;
                foreach ($bits as $bit) {
                    if (!in_array($bit, [(string) READ, (string) CREATE, (string) UPDATE], true)) {
                        throw new ValidationException('Droit inconnu.');
                    }
                    $rights[$name] |= (int) $bit;
                }
            }
            ProfileRight::updateProfileRights($id, $rights);
            Access::reload();
            Http::redirect(Profile::getFormURLWithID($id));
        default:
            throw new ValidationException('Action inconnue.');
    }
} catch (Throwable $error) {
    if ($error instanceof ValidationException && ($operation ?? '') === 'step' && ($_POST['from_ticket'] ?? '') === '1' && !empty($id)) {
        try {
            $returnInstance = Store::one('instances', $id);
            Access::instance($returnInstance);
        } catch (Throwable) { $returnInstance = null; }
        if ($returnInstance) {
            $step = is_string($_POST['step'] ?? null) ? $_POST['step'] : '';
            if (is_array($_POST['values'] ?? null)) { $_SESSION['wb_failed'][$id][$step] = $_POST['values']; }
            Session::addMessageAfterRedirect(View::e($error->getMessage()), false, ERROR);
            Http::redirect(Http::ticketWorkflow((int) $returnInstance['tickets_id'], $id, $step));
        }
    }
    Http::start('Action non effectuée');
    Http::error($error);
    echo '<p>Les données n’ont pas été enregistrées. Revenir à la page précédente pour corriger la saisie, ou recharger si un autre utilisateur a modifié le workflow.</p>';
    Http::end();
}
