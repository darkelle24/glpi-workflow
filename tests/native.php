<?php
declare(strict_types=1);
/**
 * REAL GLPI/MariaDB integration test. Never runs against a production database.
 * Usage: WORKFLOWBUILDER_ALLOW_NATIVE_TESTS=1 php tests/native.php /path/to/installed/glpi
 * Use a database named workflowbuilder_glpi12_* or workflowbuilder_test_*.
 */
if (getenv('WORKFLOWBUILDER_ALLOW_NATIVE_TESTS') !== '1') { fwrite(STDERR,"Dedicated native test environment required.\n"); exit(2); }
$core = realpath($argv[1] ?? '');
if (!$core || !is_file($core.'/vendor/autoload.php')) { fwrite(STDERR,"Installed GLPI root required.\n"); exit(2); }
require $core.'/vendor/autoload.php';
$kernel = new \Glpi\Kernel\Kernel();
$kernel->boot();
global $DB;
if (!preg_match('/^workflowbuilder_(glpi12|test)_[a-z0-9_]+$/D', $DB->dbdefault)) { fwrite(STDERR,"Refusing a database not explicitly named as a workflowbuilder test database.\n"); exit(2); }
$auth=new Auth();
if (!$auth->login(getenv('WB_GLPI_TEST_USER')?:'glpi',getenv('WB_GLPI_TEST_PASSWORD')?:'glpi',true)) { fwrite(STDERR,"Test user login failed.\n");exit(2); }
\WorkflowBuilder\Access::reload();
use WorkflowBuilder\{Access,Definition,Engine,Instances,Store,Templates,ValidationException};
$passed=0;
function nativeCheck(string $name, bool $result):void { global $passed;if(!$result)throw new RuntimeException($name);$passed++;echo "PASS $name\n"; }
$d=Definition::empty();$d['name']='Recette native '.date('His');
$d['fields']=[['id'=>'request','label'=>'Demande','type'=>'text','required'=>true]];
$d['steps']=[['id'=>'check','title'=>'Vérifier','fields'=>[['id'=>'ok','label'=>'Contrôle effectué','type'=>'checkbox','required'=>true]]],['id'=>'finish','title'=>'Finaliser','depends_on'=>['check']]];
$template=Templates::save(0,$d,0,false,0);
Templates::publish($template,1);
nativeCheck('template and immutable version published with native DBAL',Store::one('templates',$template)['state']==='active');
$key=bin2hex(random_bytes(32));
$instance=Instances::launch($template,0,0,['request'=>'Éléments de recette native'],$key);
$data=Instances::data($instance);$ticketId=(int)$data['instance']['tickets_id'];$ticket=new Ticket();$ticket->getFromDB($ticketId);
nativeCheck('native Ticket::add creates a readable associated ticket',$ticketId>0 && $ticket->can($ticketId,READ));
nativeCheck('repeated launch creates no second instance',Instances::launch($template,0,0,['request'=>'Éléments de recette native'],$key)===$instance);
$ticket->update(['id'=>$ticketId,'status'=>CommonITILObject::SOLVED]);
$ticket->getFromDB($ticketId);
nativeCheck('real Ticket::update hook refuses premature resolution',(int)$ticket->fields['status']!==CommonITILObject::SOLVED);
$second=Instances::launch($template,$ticketId,0,['request'=>'Association au ticket existant'],bin2hex(random_bytes(32)));
nativeCheck('workflow can attach to existing native ticket',(int)Instances::data($second)['instance']['tickets_id']===$ticketId);
$rejected=false;
try{Instances::act($instance,'check','complete',[],1);}catch(ValidationException){$rejected=true;}
nativeCheck('native service refuses incomplete mandatory fields',$rejected);
Instances::act($instance,'check','save',['ok'=>'1'],1);
nativeCheck('saved step data survives native database reload',Instances::data($instance)['values']['check']['ok']===true);
Instances::act($instance,'check','complete',['ok'=>'1'],2);
nativeCheck('server progression and dependency update through native DBAL',Instances::data($instance)['progress']['percent']===50 && Instances::data($instance)['states']['finish']==='available');
Instances::act($instance,'finish','complete',[],3);
nativeCheck('mandatory steps finish workflow',Instances::data($instance)['progress']['status']==='completed');
$ticket->update(['id'=>$ticketId,'status'=>CommonITILObject::SOLVED]);$ticket->getFromDB($ticketId);
nativeCheck('all attached instances must finish before native resolution',(int)$ticket->fields['status']!==CommonITILObject::SOLVED);
$d['steps'][]=['id'=>'new_step','title'=>'Nouvelle étape'];
Templates::save($template,$d,0,false,2);Templates::publish($template,3);
nativeCheck('republishing preserves definition of existing native instances',count(Instances::data($instance)['definition']['steps'])===2);
Instances::act($second,'check','complete',['ok'=>'1'],1);
Instances::act($second,'finish','complete',[],2);
$ticket->update(['id'=>$ticketId,'status'=>CommonITILObject::SOLVED]);$ticket->getFromDB($ticketId);
nativeCheck('native resolution succeeds after all mandatory workflows finish',(int)$ticket->fields['status']===CommonITILObject::SOLVED);
$ticket->update(['id'=>$ticketId,'status'=>CommonITILObject::CLOSED]);$ticket->getFromDB($ticketId);
nativeCheck('native closure succeeds after completion',(int)$ticket->fields['status']===CommonITILObject::CLOSED);
nativeCheck('native audit stores actor and actions',count(Store::rows('history',['instances_id'=>$instance]))>=4);
$owned=Instances::launch($template,0,0,['request'=>'Prise en charge native'],bin2hex(random_bytes(32)));
Instances::act($owned,'check','start',[],1);
$operatorRow=array_column(Instances::data($owned)['steps'],null,'step_key')['check'];
nativeCheck('starting records real GLPI operator and start time',(int)$operatorRow['started_by']===(int)Session::getLoginUserID() && $operatorRow['started_at'] && $operatorRow['state']==='progress');
$stale=false;try{Instances::act($owned,'check','start',[],1);}catch(ValidationException){$stale=true;}
nativeCheck('a concurrent stale claim is rejected by native DBAL service',$stale);
Instances::act($owned,'check','release',[],2);
$operatorRow=array_column(Instances::data($owned)['steps'],null,'step_key')['check'];
nativeCheck('releasing clears ownership and makes the step available',$operatorRow['started_by']===null && $operatorRow['started_at']===null && $operatorRow['state']==='available');
Instances::act($owned,'check','save',['ok'=>'1'],3);
$ownedData=Instances::data($owned);$operatorRow=array_column($ownedData['steps'],null,'step_key')['check'];
nativeCheck('saving an unclaimed step persists ownership and values',(int)$operatorRow['started_by']===(int)Session::getLoginUserID() && $ownedData['values']['check']['ok']===true);
ob_start();WorkflowBuilder\View::instance($owned);$rendered=ob_get_clean();
nativeCheck('collapsed step summary displays native participant name',str_contains($rendered,'Pris en charge par '.WorkflowBuilder\View::e(getUserName(Session::getLoginUserID()))));
echo "\n$passed native GLPI integration checks passed\n";
