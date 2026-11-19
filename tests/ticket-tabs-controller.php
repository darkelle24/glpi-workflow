<?php
declare(strict_types=1);
namespace Symfony\Component\HttpKernel\Exception {
    class NotFoundHttpException extends \RuntimeException {}
    class BadRequestHttpException extends \RuntimeException { public function __construct($message, $previous) { parent::__construct($message, 0, $previous); } }
    class MethodNotAllowedHttpException extends \RuntimeException { public function __construct($methods) { parent::__construct(implode(',', $methods)); } }
}
namespace {
    define('GLPI_ROOT', dirname(__DIR__));
    define('READ', 1);
    class Session { public static bool $logged = true; public static function checkLoginUser(): void { if (!self::$logged) throw new RuntimeException('login'); } }
    class Plugin { public static function isPluginActive($key): bool { return true; } }
    class Ticket {
        public static bool $allowed = true;
        public function check($id, $right): void { if (!self::$allowed || $id !== 11 || $right !== READ) throw new RuntimeException('denied'); }
        public function getID(): int { return 11; }
        public function defineAllTabs(): array { return ['Ticket$main'=>'Ticket', 'Ticket$4'=>'Statistics']; }
        public static function getFormURLWithID($id): string { return '/front/ticket.form.php?id=' . $id; }
    }
    class Html { public static function redirect($url): void { throw new RuntimeException('redirect:' . $url); } }
    $CFG_GLPI = ['root_doc'=>''];
    $count = 0;
    function request(array $params, string $method = 'GET'): array {
        $_GET = $params; $_SERVER['REQUEST_METHOD'] = $method;
        ob_start(); $error = null;
        try { include dirname(__DIR__) . '/front/_init.php'; include dirname(__DIR__) . '/front/ticket-tabs.php'; } catch (Throwable $e) { $error = $e; }
        return [ob_get_clean(), $error];
    }
    function verify(bool $ok): void { global $count; if (!$ok) throw new RuntimeException('Controller assertion failed #' . ($count + 1)); $count++; }
    [$json, $error] = request(['id'=>'11']);
    verify($error === null && isset(json_decode($json, true)['tabs']['Ticket$4']));
    [, $error] = request(['id'=>'11','tab'=>'statistiques']);
    verify($error?->getMessage() === 'redirect:/front/ticket.form.php?id=11&forcetab=Ticket%244');
    [, $error] = request(['id'=>'11','tab'=>'workflow']);
    verify($error instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException);
    [, $error] = request(['id'=>'11','tab'=>'../statistiques']);
    verify($error instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException);
    [, $error] = request(['id'=>['11']]);
    verify($error instanceof \Symfony\Component\HttpKernel\Exception\BadRequestHttpException);
    [, $error] = request(['id'=>'11','tab'=>['statistiques']]);
    verify($error instanceof \Symfony\Component\HttpKernel\Exception\BadRequestHttpException);
    [, $error] = request(['id'=>'11'], 'POST');
    verify($error instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException);
    Ticket::$allowed = false;
    [$json, $error] = request(['id'=>'11']);
    verify($json === '' && $error?->getMessage() === 'denied');
    Ticket::$allowed = true; Session::$logged = false;
    [$json, $error] = request(['id'=>'11']);
    verify($json === '' && $error?->getMessage() === 'login');
    echo "$count ticket tab controller checks passed\n";
}