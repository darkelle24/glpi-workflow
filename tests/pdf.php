<?php
declare(strict_types=1);
require __DIR__.'/support/FakeGlpi.php';
require dirname(__DIR__).'/bootstrap.php';
use WorkflowBuilder\{Access, Definition, Fields, Instances, PdfExport, PdfTemplate, Store, Templates, ValidationException};
if (!empty($argv[1])) {
    $vendor = rtrim($argv[1], '/\\');
    if (!defined('K_PATH_FONTS') && is_dir($vendor . '/tecnickcom/tc-lib-pdf-font/target/fonts')) { define('K_PATH_FONTS', $vendor . '/tecnickcom/tc-lib-pdf-font/target/fonts/'); }
    require $vendor . '/autoload.php';
}
$DB=new FakeDb();$CFG_GLPI=['root_doc'=>''];
Session::$rights=array_fill_keys(array_keys(Access::RIGHTS),READ|UPDATE|CREATE);
Session::$rights+=['native_ticket'=>READ|UPDATE|CREATE,'native_followup'=>CREATE,'native_assign'=>UPDATE];
$passed=$failed=0;
function check($name,$run){global $passed,$failed;try{$run();$passed++;echo "PASS $name\n";}catch(Throwable $e){$failed++;echo "FAIL $name: {$e->getMessage()}\n";}}
function eq($a,$b){if($a!==$b)throw new RuntimeException(json_encode([$a,$b]));}
function denied($run){try{$run();}catch(ValidationException){return;}throw new RuntimeException('Expected refusal');}
function example(){return Definition::decode(file_get_contents(dirname(__DIR__).'/examples/fiche-connexion-salarie.json'));}
function modified($change){$d=example();$change($d);return Definition::validate($d);}
check('legacy workflows keep PDF disabled',function(){$d=Definition::empty();unset($d['pdf']);eq(false,Definition::validate($d)['pdf']['enabled']);});
check('portable PDF example roundtrips without losing pages or styles',function(){$d=example();eq($d,Definition::decode(Store::json($d)));eq(2,count($d['pdf']['pages']));});
check('unknown PDF and page properties refused',function(){denied(fn()=>modified(function(&$d){$d['pdf']['script']='evil';}));denied(fn()=>modified(function(&$d){$d['pdf']['pages'][0]['url']='https://example.org';}));});
check('unknown or removed field variables refused',fn()=>denied(fn()=>modified(function(&$d){$d['pdf']['pages'][0]['boxes'][0]['text']='{{steps.missing.field}}';})));
check('box bounds font sizes fonts colors and alignments validated',function(){foreach(['x'=>-1,'y'=>296,'width'=>1000,'height'=>0,'font_size'=>100,'font'=>'../../etc/passwd','color'=>'red','align'=>'J'] as $key=>$value)denied(fn()=>modified(function(&$d)use($key,$value){$d['pdf']['pages'][0]['boxes'][0][$key]=$value;}));});
check('booleans filename and page count validated',function(){denied(fn()=>modified(function(&$d){$d['pdf']['enabled']='yes';}));denied(fn()=>modified(function(&$d){$d['pdf']['filename']='../../a';}));denied(fn()=>modified(function(&$d){$d['pdf']['pages']=array_fill(0,11,$d['pdf']['pages'][0]);}));});
check('empty enabled PDF refused unless summary requested',function(){denied(fn()=>modified(function(&$d){$d['pdf']['pages']=[];$d['pdf']['include_summary']=false;}));$d=modified(function(&$d){$d['pdf']['pages']=[];});eq(true,$d['pdf']['include_summary']);});
$png='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWP4//8/AAX+Av5Y8msOAAAAAElFTkSuQmCC';
check('embedded PNG retained and larger JSON supported',function()use($png){$d=modified(function(&$d)use($png){$d['pdf']['pages'][0]['background']=$png;});eq($png,Definition::decode(Store::json($d))['pdf']['pages'][0]['background']);$json=Store::json($d);eq($d,Definition::decode($json.str_repeat(' ',524288)));});
check('remote paths SVG corrupt and oversized images refused',function()use($png){foreach(['https://example.org/a.png','file:///etc/passwd','data:image/svg+xml;base64,PHN2Zz4=','data:image/png;base64,YmFk',str_repeat('x',2800001)] as $bad)denied(fn()=>PdfTemplate::image($bad));});
check('definition total size limit applies at decode and direct validation',function()use($png){
    denied(fn()=>Definition::decode(str_repeat(' ',Definition::MAX_BYTES+1)));
    $d=example();$page=$d['pdf']['pages'][0];
    $page['background']='data:image/png;base64,'.base64_encode(base64_decode(explode(',',$png,2)[1]).str_repeat("\0",1500000));
    $d['pdf']['pages']=array_fill(0,10,$page);
    denied(fn()=>Definition::validate($d));
});
check('substitutions preserve plain text and never recursively expand answers',fn()=>eq('<script>{{ticket}}</script>',PdfExport::text('{{form.nom}}',['form.nom'=>'<script>{{ticket}}</script>','ticket'=>'12'])));
$form=['prenom'=>'Élodie','nom'=>'Martin','service'=>'Administration','fonction'=>'Gestionnaire','arrivee'=>'2026-11-09'];
$d=example();$t=Templates::save(0,$d,0,false,0);Templates::publish($t,1);$id=Instances::launch($t,0,0,$form,str_repeat('f',64));
check('running instance cannot download a final PDF',fn()=>denied(fn()=>PdfExport::instance($id)));
$answers=['comptes'=>['identifiant'=>'e.martin','email'=>'elodie.martin@example.org','portail'=>'https://portail.example.org','mfa'=>'1'],
    'equipement'=>['poste'=>'PC-ACCUEIL-042','telephone'=>'01 02 03 04 05'],
    'remise'=>['support'=>'support@example.org','consignes'=>"Ouvrir le portail.\nUtiliser votre identifiant.\nValider la demande de connexion.",'test'=>'1']];
foreach($answers as $step=>$values)Instances::act($id,$step,'complete',$values,(int)Store::one('instances',$id)['revision'],'Vérification de démonstration');
$data=PdfExport::instance($id);
check('completed instance resolves initial and step values dates and booleans',function()use($data){$v=PdfExport::variables($data);eq('Élodie',$v['form.prenom']);eq('e.martin',$v['steps.comptes.identifiant']);eq('Oui',$v['steps.comptes.mfa']);if(!$v['date_completion'])throw new RuntimeException('Missing completion date');});
check('PDF access requires plugin and native ticket read permissions',function()use($id){$rights=Session::$rights;try{Session::$rights['native_ticket']=0;denied(fn()=>PdfExport::instance($id));Session::$rights=$rights;Session::$rights['plugin_workflowbuilder_view']=0;denied(fn()=>PdfExport::instance($id));}finally{Session::$rights=$rights;}});
check('published PDF layout remains frozen after draft changes',function()use($t,$id){$d=example();$d['pdf']['pages'][0]['boxes'][0]['text']='CHANGÉ';Templates::save($t,$d,0,false,2);Templates::publish($t,3);eq('BIENVENUE',PdfExport::instance($id)['definition']['pdf']['pages'][0]['boxes'][0]['text']);});
check('hidden and non applicable answers omitted while zero survives',function()use($data){$d=$data;$d['definition']['fields'][]=array_merge($d['definition']['fields'][0],['id'=>'hidden','condition'=>['field'=>'form.prenom','op'=>'eq','value'=>'Other']]);$d['values']['']['hidden']='MUST NOT LEAK';$d['states']['comptes']='na';$d['values']['equipement']['poste']='0';$v=PdfExport::variables($d);eq('',$v['form.hidden']);eq('',$v['steps.comptes.email']);eq('0',$v['steps.equipement.poste']);});
check('reopening makes the final download unavailable again',function()use($id){Instances::act($id,'remise','reopen',[],(int)Store::one('instances',$id)['revision'],'Correction');denied(fn()=>PdfExport::instance($id));});
if(class_exists(TCPDF::class)) {
    check('real TCPDF renders Unicode template pages summary image and long text',function()use($data,$png){
        $data['definition']['pdf']['pages'][0]['background']=$png;
        $data['values']['remise']['consignes']=str_repeat('Instructions détaillées pour Élodie. ',100);
        $bytes=PdfExport::render($data['definition'],PdfExport::variables($data),$data);
        if(!str_starts_with($bytes,'%PDF-')||strlen($bytes)<5000)throw new RuntimeException('Invalid PDF');
        if(getenv('WB_PDF_TEST_OUTPUT'))file_put_contents(getenv('WB_PDF_TEST_OUTPUT'),$bytes);
    });
    check('real TCPDF supports landscape and summary-only preview',function(){foreach([false,true] as $summaryOnly){$d=example();$d['pdf']['pages']=$summaryOnly?[]:[['orientation'=>'landscape','background'=>'','boxes'=>[['text'=>'Paysage','x'=>15,'y'=>15,'width'=>200,'height'=>20]]]];if(!str_starts_with(PdfExport::render($d,[]),'%PDF-'))throw new RuntimeException('Invalid preview');}});
} else { echo "SKIP real TCPDF rendering (pass an isolated TCPDF vendor directory as first argument)\n"; }
echo "$passed PDF checks passed; $failed failed\n";exit($failed?1:0);