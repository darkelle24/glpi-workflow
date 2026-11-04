<?php
declare(strict_types=1);
namespace WorkflowBuilder;
final class Http
{
    public static function url(string $page, array $params = []): string
    {
        global $CFG_GLPI;
        return $CFG_GLPI['root_doc'] . '/plugins/workflowbuilder/front/' . $page . ($params ? '?' . http_build_query($params) : '');
    }
    public static function token(): string
    {
        return $_SESSION['workflowbuilder_csrf'] ??= bin2hex(random_bytes(32));
    }
    public static function post(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            throw new ValidationException('Une requête POST est requise.');
        }
        if (!is_string($_POST['_wb_token'] ?? null) || !hash_equals(self::token(), $_POST['_wb_token'])) {
            throw new ValidationException('Le formulaire a expiré. Recharger la page.');
        }
    }
    public static function start(string $title): void
    {
        \Html::header($title, '', 'helpdesk', 'PluginWorkflowbuilderMenu');
        echo '<main class="wb container-fluid" data-wb-root data-lookup="' . View::e(self::url('lookup.php')) . '">';
        echo '<nav class="wb-toolbar"><a class="btn btn-outline-secondary" href="' . View::e(self::url('index.php')) . '">Workflows</a>';
        echo '<a class="btn btn-outline-secondary" href="' . View::e(self::url('launch.php')) . '">Lancer un workflow</a>';
        if (Access::has('template', CREATE)) {
            echo '<a class="btn btn-outline-secondary" href="' . View::e(self::url('template.form.php')) . '">Nouveau modèle</a>';
        }
        if (Access::has('admin', UPDATE)) { echo '<a class="btn btn-outline-secondary" href="' . View::e(self::url('diagnostics.php')) . '">Diagnostic</a>'; }
        if (\User::isUserReauthenticationNeeded()) { echo '<a class="btn btn-outline-secondary" target="_blank" rel="noopener" href="' . View::e(self::url('reauth.php')) . '">Confirmer l’identité GLPI</a>'; }
        echo '<a class="btn btn-outline-secondary" href="' . View::e(self::url('guide.php')) . '">Guide utilisateur</a>';
        echo '</nav><h1>' . View::e($title) . '</h1>';
    }
    public static function end(): void
    {
        echo '</main>';
        \Html::footer();
    }
    public static function hidden(array $values = []): void
    {
        $values['_wb_token'] = self::token();
        foreach ($values as $key => $value) {
            echo '<input type="hidden" name="' . View::e($key) . '" value="' . View::e((string) $value) . '">';
        }
    }
    public static function redirect(string $url): never
    {
        \Html::redirect($url);
        exit;
    }
    public static function error(\Throwable $error): void
    {
        if ($error instanceof \Glpi\Exception\RedirectException || $error instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $error; }
        if ($error instanceof ValidationException) {
            echo '<div class="alert alert-danger" role="alert">' . View::e($error->getMessage()) . '</div>';
        } else {
            global $PHPLOGGER;
            $PHPLOGGER?->error('Workflow Builder: request failed', ['exception' => $error]);
            echo '<div class="alert alert-danger" role="alert">Erreur technique. Consulter les journaux GLPI.</div>';
        }
    }
    public static function string(array $data, string $key, string $default = ''): string
    {
        if (!isset($data[$key])) { return $default; }
        if (!is_string($data[$key])) { throw new ValidationException('Paramètre invalide : ' . $key); }
        return $data[$key];
    }
    public static function integer(array $data, string $key, int $default = 0): int
    {
        $value = $data[$key] ?? $default;
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 0) {
            throw new ValidationException('Identifiant invalide : ' . $key);
        }
        return (int) $value;
    }
    public static function values(array $data): array
    {
        if (!is_array($data['values'] ?? [])) { throw new ValidationException('Valeurs invalides.'); }
        return $data['values'] ?? [];
    }
}
