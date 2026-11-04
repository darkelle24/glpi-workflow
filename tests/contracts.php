<?php
declare(strict_types=1);
/** Source-level contract checks, no installed GLPI or database bootstrapping required. */
$root = $argv[1] ?? '';
if (!$root) { fwrite(STDERR, "Usage: php tests/contracts.php /path/to/glpi-source\n"); exit(2); }
$contracts = [
    'src/DBmysql.php' => ['doQuery','fetchAssoc','request','insert','insertId','update','updateOrInsert','delete','tableExists','beginTransaction','commit','rollBack'],
    'src/Plugin.php' => ['registerClass','isPluginActive','doHook'],
    'src/CommonGLPI.php' => ['getTypeName','getMenuContent','getTabNameForItem','displayTabContentForItem','getFormURLWithID','isUserReauthenticationNeeded','checkReAuthenticationOrRedirect'],
    'src/CommonDBTM.php' => ['can','check','getFromDB','getTable','add','update'],
    'src/Session.php' => ['checkLoginUser','haveRight','haveAccessToEntity','getLoginUserID','getCurrentTime','addMessageAfterRedirect'],
    'src/ProfileRight.php' => ['updateProfileRights','deleteProfileRights'],
    'src/Config.php' => ['setConfigurationValues','getConfigurationValues','deleteConfigurationValues'],
    'src/Document.php' => ['canViewFile'],
    'src/KnowbaseItem.php' => ['canViewItem','getFormURLWithID'],
    'src/DbUtils.php' => ['getUserName'],
    'src/Html.php' => ['header','footer','redirect'],
];
$failed = 0;
foreach ($contracts as $file=>$methods) {
    $path = $root . '/' . $file;
    if (!is_file($path)) { $path = $root . '/' . str_replace('/','__',$file); }
    $source = is_file($path) ? file_get_contents($path) : '';
    foreach ($methods as $method) {
        if (!preg_match('/function\s+'.preg_quote($method,'/').'\s*\(/',$source)) {
            // getFormURLWithID for KnowbaseItem is inherited from CommonGLPI.
            if ($file==='src/KnowbaseItem.php' && $method==='getFormURLWithID') { continue; }
            fwrite(STDERR,"MISSING $file::$method\n");$failed++;
        }
    }
}
$hooks = $root . '/src/Glpi/Plugin/Hooks.php';
if (!is_file($hooks)) { $hooks=$root.'/src__Glpi__Plugin__Hooks.php'; }
$source = file_get_contents($hooks);
foreach (['MENU_TOADD','ADD_CSS','ADD_JAVASCRIPT','CHANGE_PROFILE','POST_PREPAREUPDATE'] as $hook) {
    if (!str_contains($source,'const '.$hook.' ')) { fwrite(STDERR,"MISSING hook $hook\n");$failed++; }
}
$core = $root.'/src/CommonDBTM.php';
if (!is_file($core)) { $core=$root.'/src__CommonDBTM.php'; }
if (!str_contains(file_get_contents($core),'Plugin::doHook(Hooks::POST_PREPAREUPDATE, $this)')) { $failed++; }
echo $failed ? "$failed contract failures\n" : "GLPI extension contracts found in target source\n";
exit($failed ? 1 : 0);
