<?php
declare(strict_types=1);
// GLPI 12 bootstraps legacy controllers via public/index.php.
if (!defined('GLPI_ROOT')) {
    http_response_code(403);
    exit('Accéder via le point d’entrée public de GLPI 12.');
}
require_once dirname(__DIR__) . '/bootstrap.php';
Session::checkLoginUser();
if (!Plugin::isPluginActive('workflowbuilder')) {
    http_response_code(403);
    exit;
}
