<?php
declare(strict_types=1);
require __DIR__.'/support/FakeGlpi.php';require dirname(__DIR__).'/bootstrap.php';
use WorkflowBuilder\{Access,Definition,PdfAutomatic,PdfExport,PdfPresets,PdfTemplate,Store,Templates,ValidationException};
if(!empty($argv[1])){$vendor=rtrim($argv[1],'/\\');if(!defined('K_PATH_FONTS')&&is_dir($vendor.'/tecnickcom/tc-lib-pdf-font/target/fonts'))define('K_PATH_FONTS',$vendor.'/tecnickcom/tc-lib-pdf-font/target/fonts/');require $vendor.'/autoload.php';}
$DB=new FakeDb();$CFG_GLPI=['root_doc'=>''];Session::$rights=array_fill_keys(array_keys(Access::RIGHTS),READ|UPDATE|CREATE);
$passed=$failed=0;
function check($name,$fn){global $passed,$failed;try{$fn();$passed++;echo "PASS $name\n";}catch(Throwable $e){$failed++;echo "FAIL $name: {$e->getMessage()}\n";}}
function eq($a,$b){if($a!==$b)throw new RuntimeException(json_encode([$a,$b]));}
function denied($fn){try{$fn();}catch(ValidationException){return;}throw new RuntimeException('Expected refusal');}
function source(){return PdfTemplate::sources(Definition::empty());}
function auto(){return PdfAutomatic::defaults()[0]['automatic'];}
check('legacy PDF layouts default to free placement and retain all pages',function(){$d=Definition::decode(file_get_contents(dirname(__DIR__).'/examples/fiche-connexion-salarie.json'));eq('free',$d['pdf']['mode']);eq(2,count($d['pdf']['pages']));});
check('automatic example roundtrips and preserves the free mode option',function(){$d=Definition::decode(file_get_contents(dirname(__DIR__).'/examples/fiche-connexion-automatique.json'));eq('automatic',$d['pdf']['mode']);eq($d,Definition::decode(Store::json($d)));$d['pdf']['mode']='free';$d['pdf']['include_summary']=true;Definition::validate($d);});
check('unknown mode and automatic properties rejected',function(){$d=Definition::empty();$d['pdf']['mode']='html';denied(fn()=>Definition::validate($d));$a=auto();$a['script']='x';denied(fn()=>PdfAutomatic::validate($a,source()));});
check('group counts item counts widths fonts colors logo and booleans are bounded',function(){
    foreach(['groups'=>array_fill(0,31,auto()['groups'][0]),'font'=>'external','font_size'=>72,'accent'=>'red','logo'=>'https://example.org/logo.png','numbered'=>'yes'] as $k=>$v){$a=auto();$a[$k]=$v;denied(fn()=>PdfAutomatic::validate($a,source()));}
    $a=auto();$a['groups'][0]['items'][0]['width']=5;denied(fn()=>PdfAutomatic::validate($a,source()));
    $a=auto();$a['groups'][0]['items']=array_fill(0,201,$a['groups'][0]['items'][0]);denied(fn()=>PdfAutomatic::validate($a,source()));
});
check('active automatic sources and template tokens must exist in this workflow',function(){$d=Definition::empty();$d['pdf']['mode']='automatic';$d['pdf']['automatic']=auto();$d['pdf']['automatic']['groups'][0]['items'][0]['source']='form.foreign';denied(fn()=>Definition::validate($d));$d['pdf']['automatic']['groups'][0]['items'][0]['source']='';$d['pdf']['automatic']['title']='{{steps.foreign.a}}';denied(fn()=>Definition::validate($d));});
check('preset cannot store references belonging to an unrelated workflow',function(){$a=auto();$a['groups'][0]['items'][0]['source']='form.nom';denied(fn()=>PdfPresets::save(0,'Bad',$a,0,true,0));});
$id=PdfPresets::save(0,'Accueil chaleureux',auto(),0,true,0);
check('admin creates a reusable model containing groups styles and static texts',function()use($id){$r=Store::one('pdfpresets',$id);eq('Accueil chaleureux',$r['name']);eq(1,$r['revision']);eq(auto(),PdfPresets::read($r));eq(3,count(PdfPresets::catalog()));});
check('preset editing requires admin update even for workflow editors',function()use($id){$r=Session::$rights;try{Session::$rights['plugin_workflowbuilder_admin']=READ;denied(fn()=>PdfPresets::save($id,'Changed',auto(),1,true,0));eq('Accueil chaleureux',Store::one('pdfpresets',$id)['name']);}finally{Session::$rights=$r;}});
check('catalog requires workflow template read access',function(){$r=Session::$rights;try{Session::$rights['plugin_workflowbuilder_template']=0;denied(fn()=>PdfPresets::catalog());}finally{Session::$rights=$r;}});
check('entity access checked on creation reading updating and catalog filtering',function()use($id){denied(fn()=>PdfPresets::save(0,'Foreign',auto(),0,true,99));$foreign=Store::insert('pdfpresets',['name'=>'Hidden','entities_id'=>99,'definition'=>Store::json(auto()),'is_active'=>1,'revision'=>1]);denied(fn()=>PdfPresets::read(Store::one('pdfpresets',$foreign)));denied(fn()=>PdfPresets::save($foreign,'Changed',auto(),1,true,0));eq(3,count(PdfPresets::catalog()));});
check('stale preset edits are refused transactionally',function()use($id){denied(fn()=>PdfPresets::save($id,'Obsolete',auto(),0,true,0));eq(1,Store::one('pdfpresets',$id)['revision']);});
$d=Definition::empty();$d['steps']=[['id'=>'finish','title'=>'Terminer']];$d['pdf']['mode']='automatic';$d['pdf']['enabled']=true;$d['pdf']['automatic']=PdfPresets::read(Store::one('pdfpresets',$id));$t=Templates::save(0,$d,0,false,0);Templates::publish($t,1);
check('changing or disabling a preset never changes the published workflow copy',function()use($id,$t){$a=auto();$a['title']='Nouveau titre';PdfPresets::save($id,'Changed',$a,1,false,0);$version=Store::one('templates',$t)['active_versions_id'];[, $d]=Templates::version((int)$version);eq(auto()['title'],$d['pdf']['automatic']['title']);eq(2,count(PdfPresets::catalog()));eq(false,(bool)Store::one('pdfpresets',$id)['is_active']);});
check('disabled preset can be reactivated without losing its content',function()use($id){$r=Store::one('pdfpresets',$id);PdfPresets::save($id,$r['name'],PdfPresets::read($r),2,true,0);eq(3,count(PdfPresets::catalog()));});
if(class_exists(TCPDF::class)){
    check('real automatic PDF supports grouped columns Unicode logo and dynamic page breaks',function(){
        $d=Definition::decode(file_get_contents(dirname(__DIR__).'/examples/fiche-connexion-automatique.json'));
        $d['pdf']['automatic']['logo']='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWP4//8/AAX+Av5Y8msOAAAAAElFTkSuQmCC';
        $v=['form.nom'=>'MARTIN','form.prenom'=>'Élodie','form.service'=>'Administration','form.code_delegue'=>'142','ticket'=>'42',
            'steps.comptes.identifiant'=>'e.martin','steps.comptes.email'=>'elodie.martin@example.org','steps.equipement.interne'=>'1247',
            'steps.equipement.telephone'=>'07 50 56 52 73','steps.equipement.fixe'=>'05 53 06 42 47','steps.comptes.uni_magis'=>'Élodie MARTIN',
            'steps.comptes.caf'=>'ACC123','steps.remise.contact_administratif'=>'Contact A','steps.remise.comptable'=>'Contact B',
            'steps.remise.referent_bancaire'=>'Contact C','steps.remise.referent_immo'=>'Contact D'];
        $bytes=PdfExport::render($d,$v);if(!str_starts_with($bytes,'%PDF-'))throw new RuntimeException('Invalid PDF');
        if(getenv('WB_AUTO_PDF_OUTPUT'))file_put_contents(getenv('WB_AUTO_PDF_OUTPUT').'-normal.pdf',$bytes);
        $d['pdf']['automatic']['groups'][2]['items'][0]['source']='';$d['pdf']['automatic']['groups'][2]['items'][0]['text']=str_repeat('Consigne détaillée Élodie. ',350).str_repeat('A',200).' FIN_LONGUE_REPONSE';
        $bytes=PdfExport::render($d,$v);if(!str_starts_with($bytes,'%PDF-'))throw new RuntimeException('Invalid long PDF');
        if(getenv('WB_AUTO_PDF_OUTPUT'))file_put_contents(getenv('WB_AUTO_PDF_OUTPUT').'-long.pdf',$bytes);
    });
}else echo "SKIP actual TCPDF rendering (pass an isolated vendor directory)\n";
echo "$passed automatic PDF checks passed; $failed failed\n";exit($failed?1:0);