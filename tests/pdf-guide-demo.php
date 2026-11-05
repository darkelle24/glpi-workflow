<?php
declare(strict_types=1);
/** Local documentation router. Real controllers/renderers, in-memory GLPI doubles and fictional answers. */

$uri=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$isAsset=(bool)preg_match('~^/plugins/workflowbuilder/(images/guide/[a-z0-9-]+\.png|css/workflowbuilder\.css|js/workflowbuilder\.js)$~D',$uri);
$isPage=in_array($uri,['/plugins/workflowbuilder/front/guide.php','/plugins/workflowbuilder/front/example.php','/tests/pdf-guide-demo.php'],true);
if(!$isAsset&&!$isPage)return false;
if(getenv('WORKFLOWBUILDER_ALLOW_PDF_GUIDE_DEMO')!=='1'||!in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true)){http_response_code(403);exit('Local PDF documentation demo required.');}
if(preg_match('~^/plugins/workflowbuilder/(images/guide/([a-z0-9-]+\.png)|css/workflowbuilder\.css|js/workflowbuilder\.js)$~D',$uri,$m)){
    $file=dirname(__DIR__).'/public/'.$m[1];if(!is_file($file)){http_response_code(404);exit;}
    header('Content-Type: '.(str_ends_with($file,'.png')?'image/png':(str_ends_with($file,'.css')?'text/css':'application/javascript')));readfile($file);return true;
}
if(!in_array($uri,['/plugins/workflowbuilder/front/guide.php','/plugins/workflowbuilder/front/example.php','/tests/pdf-guide-demo.php'],true))return false;
$vendor=rtrim(getenv('WB_PDF_GUIDE_VENDOR')?:'','/\\');
if(!is_file($vendor.'/autoload.php')){http_response_code(503);exit('Set WB_PDF_GUIDE_VENDOR to isolated test dependencies.');}
if(!defined('K_PATH_FONTS'))define('K_PATH_FONTS',$vendor.'/tecnickcom/tc-lib-pdf-font/target/fonts/');
require $vendor.'/autoload.php';require __DIR__.'/support/FakeGlpi.php';require dirname(__DIR__).'/bootstrap.php';
use WorkflowBuilder\{Access,Definition,Http,Instances,PdfExport,Store,Templates,View};
use Symfony\Component\HttpFoundation\Response;
class Plugin{public static function isPluginActive($name):bool{return true;}}
class Html{
    public static function header($title,...$rest):void{echo '<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.View::e($title).'</title><link rel="stylesheet" href="/plugins/workflowbuilder/css/workflowbuilder.css"><style>body{font-family:system-ui;background:#f5f7fb;color:#253347;margin:0;padding:1.2rem}.wb{max-width:1440px;margin:auto}.btn{display:inline-block;padding:.5rem .75rem;background:white;border:1px solid #9cabbc;border-radius:.3rem;cursor:pointer;text-decoration:none;color:#253347}.btn-primary{background:#206bc4;color:white}.card{background:white;padding:1rem}.wb-toolbar{display:flex;flex-wrap:wrap;gap:.6rem}.wb-tabs{display:flex;flex-wrap:wrap;gap:.6rem}.form-control{padding:.5rem}.wb-guide{background:white;padding:1rem}</style><body>';}
    public static function footer():void{echo '</body></html>';}
}
session_start();$CFG_GLPI=['root_doc'=>''];define('GLPI_ROOT',dirname(__DIR__));
$_SESSION['glpifriendlyname']='Technicien de démonstration';
Session::$rights=array_fill_keys(array_keys(Access::RIGHTS),READ|UPDATE|CREATE);
Session::$rights+=['native_ticket'=>READ|UPDATE|CREATE,'native_followup'=>CREATE,'native_assign'=>UPDATE];
if(isset($_GET['no_rights']))Session::$rights=[];
try{
    if($uri!=='/tests/pdf-guide-demo.php'){
        $result=include dirname(__DIR__).'/front/'.basename($uri);if($result instanceof Response)$result->send();return true;
    }
    $DB=new FakeDb();$mode=($_GET['mode']??'automatic')==='free'?'free':'automatic';
    $d=Definition::decode(file_get_contents(dirname(__DIR__).'/examples/'.($mode==='free'?'fiche-connexion-salarie':'fiche-connexion-automatique').'.json'));
    $template=Templates::save(0,$d,0,false,0);Templates::publish($template,1);
    $form=['prenom'=>'Élodie','nom'=>'MARTIN','service'=>'Administration','fonction'=>'Gestionnaire','arrivee'=>'2026-11-09','code_delegue'=>'142'];
    $id=Instances::launch($template,0,0,array_intersect_key($form,array_flip(array_column($d['fields'],'id'))),str_repeat('d',64));
    $answers=['comptes'=>['identifiant'=>'e.martin','email'=>'elodie.martin@example.org','portail'=>'https://portail.example.org','mfa'=>'1','uni_magis'=>'Élodie MARTIN','caf'=>'ACC123'],
        'equipement'=>['poste'=>'PC-ACCUEIL-042','telephone'=>'07 50 56 52 73','interne'=>'1247','fixe'=>'05 53 06 42 47'],
        'remise'=>['support'=>'support@example.org','consignes'=>"Ouvrir le portail de connexion.\nUtiliser votre identifiant professionnel.\nValider la demande de connexion.\nContacter le support en cas de difficulté.",'test'=>'1','contact_administratif'=>'Contact administratif','comptable'=>'Contact comptabilité','referent_bancaire'=>'Contact gestion','referent_immo'=>'Contact immobilier']];
    foreach($d['steps']as$step){$values=array_intersect_key($answers[$step['id']],array_flip(array_column($step['fields'],'id')));Instances::act($id,$step['id'],'complete',$values,(int)Store::one('instances',$id)['revision'],'Vérification de démonstration');}
    if(($_GET['format']??'')==='pdf'){$data=PdfExport::instance($id);header('Content-Type: application/pdf');header('Cache-Control: no-store');echo PdfExport::render($data['definition'],PdfExport::variables($data),$data);return true;}
    Http::start('Workflow lancé — démonstration');echo '<p>Données fictives, affichage autonome des composants du plugin.</p>';View::instance($id);Http::end();
}catch(Throwable $e){http_response_code(500);echo $e->getMessage();}
return true;