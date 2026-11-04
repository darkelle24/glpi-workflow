<?php
declare(strict_types=1);
require __DIR__.'/support/FakeGlpi.php';
require dirname(__DIR__).'/bootstrap.php';
use WorkflowBuilder\{Access, Definition, Instances, Store, Templates, TicketGuard, ValidationException};
$DB=new FakeDb();
$CFG_GLPI=['root_doc'=>''];
$passed=$failed=0;
function check(string $name,callable $run):void {
    global $passed,$failed;
    try{$run();$passed++;echo "PASS $name\n";}catch(Throwable $e){$failed++;echo "FAIL $name: {$e->getMessage()}\n";}
}
function eq($a,$b):void{if($a!==$b)throw new RuntimeException(json_encode([$a,$b]));}
function denied(callable $run):void{try{$run();}catch(ValidationException){return;}throw new RuntimeException('Expected refusal');}
function rights():void {
    Session::$rights=array_fill_keys(array_keys(Access::RIGHTS),READ|UPDATE|CREATE);
    Session::$rights['native_ticket']=READ|UPDATE|CREATE;
    Session::$rights['native_followup']=CREATE;
    $_SESSION['glpigroups']=[12];
}
function definition():array {
    $d=Definition::empty();$d['name']='Test générique';
    $d['fields']=[['id'=>'request','label'=>'Demande','type'=>'text','required'=>true]];
    $d['steps']=[['id'=>'check','title'=>'Vérifier','fields'=>[['id'=>'ok','label'=>'Vérifié','type'=>'checkbox','required'=>true]]],
        ['id'=>'finish','title'=>'Finaliser','depends_on'=>['check']]];
    return Definition::validate($d);
}
rights();
$template=Templates::save(0,definition(),0,false,0);
check('draft stored in database',fn()=>eq('draft',Store::one('templates',$template)['state']));
check('launch of unpublished template refused',fn()=>denied(fn()=>Instances::launch($template,0,0,['request'=>'A'],str_repeat('a',64))));
Templates::publish($template,1);
check('publication freezes version and relational projections',function()use($template){$t=Store::one('templates',$template);eq('active',$t['state']);eq(2,count(Store::rows('steps',['versions_id'=>$t['active_versions_id']])));eq(2,count(Store::rows('fields',['versions_id'=>$t['active_versions_id']])));});
check('stale draft revision rejected',fn()=>denied(fn()=>Templates::save($template,definition(),0,false,1)));
$instance=Instances::launch($template,0,0,['request'=>'A'],str_repeat('a',64));
check('native ticket and instance persisted together',function()use($instance){$i=Store::one('instances',$instance);eq(1,$i['tickets_id']);eq(2,count(Store::rows('stepinstances',['instances_id'=>$instance])));});
check('retry with same launch key is idempotent',function()use($template,$instance){eq($instance,Instances::launch($template,0,0,['request'=>'A'],str_repeat('a',64)));eq(1,count(Store::rows('instances')));});
check('required step fields refused without writes',function()use($instance){$before=Store::rows('history');denied(fn()=>Instances::act($instance,'check','complete',[],1));eq($before,Store::rows('history'));eq(1,Store::one('instances',$instance)['revision']);});
check('native ticket read access required',function()use($instance){Session::$rights['native_ticket']=0;denied(fn()=>Instances::data($instance));rights();});
check('plugin view access required',function()use($instance){Session::$rights['plugin_workflowbuilder_view']=0;denied(fn()=>Instances::data($instance));rights();});
check('execute cannot substitute for validate right',function()use($instance){Session::$rights['plugin_workflowbuilder_validate']=0;denied(fn()=>Instances::act($instance,'check','complete',['ok'=>'1'],1));rights();});
check('group restriction enforced',function(){ $d=definition();$d['execute_groups']=[999];Session::$rights['plugin_workflowbuilder_admin']=0;denied(fn()=>Access::step($d,['users_id'=>0,'groups_id'=>0],'save'));rights(); });
check('assigned user restriction enforced',function(){Session::$rights['plugin_workflowbuilder_admin']=0;denied(fn()=>Access::step(definition(),['users_id'=>999,'groups_id'=>0],'save'));rights();});
check('matching assigned group allowed',function(){Session::$rights['plugin_workflowbuilder_admin']=0;Access::step(definition(),['users_id'=>999,'groups_id'=>12],'save');rights();});
check('closure guard applies with no plugin rights',function()use($instance){$t=new Ticket();$t->fields=['id'=>1];$t->input=['id'=>1,'status'=>CommonITILObject::CLOSED];Session::$rights=[];TicketGuard::beforeUpdate($t);eq(false,$t->input);rights();});
check('completing a step persists values, actor and history',function()use($instance){Instances::act($instance,'check','complete',['ok'=>'1'],1);$data=Instances::data($instance);eq(true,$data['values']['check']['ok']);eq(50,$data['progress']['percent']);eq(7,(int)$data['steps'][0]['completed_by']);});
check('stale step update rejected',fn()=>denied(fn()=>Instances::act($instance,'finish','complete',[],1)));
check('reopening requires explicit right',function()use($instance){Session::$rights['plugin_workflowbuilder_reopen']=0;denied(fn()=>Instances::act($instance,'check','reopen',[],2,'Correction'));rights();});
check('old instance keeps version after republishing',function()use($template,$instance){$old=Store::one('instances',$instance)['versions_id'];$d=definition();$d['steps'][]=['id'=>'extra','title'=>'Nouvelle étape'];Templates::save($template,$d,0,false,2);Templates::publish($template,3);eq($old,Store::one('instances',$instance)['versions_id']);eq(2,count(Instances::data($instance)['definition']['steps']));});
check('publication change prevents stale launch form',function()use($template){denied(fn()=>Instances::launch($template,0,0,['request'=>'B'],str_repeat('b',64),1));});
check('last mandatory step completes workflow',function()use($instance){Instances::act($instance,'finish','complete',[],2);eq('completed',Store::one('instances',$instance)['status']);});
check('closure allowed after all mandatory steps',function(){$t=new Ticket();$t->fields=['id'=>1];$t->input=['id'=>1,'status'=>CommonITILObject::CLOSED];TicketGuard::beforeUpdate($t);eq(CommonITILObject::CLOSED,$t->input['status']);});
check('deleted or inaccessible knowledge article blocks publication',function()use($template){$d=definition();$d['steps'][0]['knowbaseitems_id']=[404];Templates::save($template,$d,0,false,4);denied(fn()=>Templates::publish($template,5));});
check('archive prevents new launches but retains instances',function()use($template,$instance){Templates::state($template,'archived',5);denied(fn()=>Instances::launch($template,0,0,['request'=>'B'],str_repeat('c',64)));eq('completed',Instances::data($instance)['instance']['status']);});
check('inaccessible referenced user rejected',function(){$f=WorkflowBuilder\Fields::definitions([['id'=>'u','label'=>'Utilisateur','type'=>'user']],[]);denied(fn()=>Access::references($f,['u'=>404]));});
check('GET cannot mutate and CSRF token required',function(){$_SERVER['REQUEST_METHOD']='GET';denied(fn()=>WorkflowBuilder\Http::post());$_SERVER['REQUEST_METHOD']='POST';$_POST=[];denied(fn()=>WorkflowBuilder\Http::post());$_POST=['_wb_token'=>WorkflowBuilder\Http::token()];WorkflowBuilder\Http::post();});
check('publishing inaccessible assignments is refused',function() {
    $d=definition();$d['steps'][0]['users_id']=404;
    $id=Templates::save(0,$d,0,false,0);
    denied(fn()=>Templates::publish($id,1));
});
function ownershipFixture():int {
    Session::$user=7;rights();
    $d=definition();$d['steps'][0]['groups_id']=12;
    $t=Templates::save(0,$d,0,false,0);Templates::publish($t,1);
    return Instances::launch($t,0,0,['request'=>'Prise en charge'],bin2hex(random_bytes(32)));
}
function stepRow(int $id):array {
    return array_column(Instances::data($id)['steps'],null,'step_key')['check'];
}
function revision(int $id):int { return (int)Store::one('instances',$id)['revision']; }
check('starting records the actual operator independently from assignment',function() {
    $id=ownershipFixture();Session::$rights['plugin_workflowbuilder_admin']=0;
    Instances::act($id,'check','start',[],revision($id));
    $row=stepRow($id);eq('progress',$row['state']);eq(7,$row['started_by']);eq(0,$row['users_id']);eq(12,$row['groups_id']);
    if(!$row['started_at'])throw new RuntimeException('Start date missing');
    $events=Store::rows('history',['instances_id'=>$id]);$event=end($events);eq(7,$event['users_id']);
    if(!in_array('started_by',Store::unpack($event['changed_fields']),true))throw new RuntimeException('Missing ownership audit');
    rights();
});
check('another technician cannot replace an owner even with admin rights',function() {
    $id=ownershipFixture();Instances::act($id,'check','start',[],revision($id));$before=Store::rows('history',['instances_id'=>$id]);
    try{Session::$user=8;
        foreach(['start','save','complete','block','release'] as $action)denied(fn()=>Instances::act($id,'check',$action,['ok'=>'1'],revision($id),'Test'));
        eq(7,stepRow($id)['started_by']);eq($before,Store::rows('history',['instances_id'=>$id]));
    }finally{Session::$user=7;rights();}
});
check('a stale concurrent claim is rejected without changing the operator',function() {
    $id=ownershipFixture();$old=revision($id);Instances::act($id,'check','start',[],$old);
    try{Session::$user=8;denied(fn()=>Instances::act($id,'check','start',[],$old));eq(7,stepRow($id)['started_by']);}
    finally{Session::$user=7;rights();}
});
check('an authorized colleague can comment without taking ownership',function() {
    $id=ownershipFixture();Instances::act($id,'check','start',[],revision($id));
    try{Session::$user=8;Session::$rights['plugin_workflowbuilder_admin']=0;
        Instances::act($id,'check','comment',[],revision($id),'Je prépare la suite');
        eq(7,stepRow($id)['started_by']);
    }finally{Session::$user=7;rights();}
});
check('release retains values and permits an authorized colleague to claim',function() {
    $id=ownershipFixture();Instances::act($id,'check','save',['ok'=>'1'],revision($id));
    Instances::act($id,'check','release',[],revision($id));
    $row=stepRow($id);eq(null,$row['started_by']);eq(null,$row['started_at']);eq('available',$row['state']);eq(true,Instances::data($id)['values']['check']['ok']);
    try{Session::$user=8;Session::$rights['plugin_workflowbuilder_admin']=0;
        Instances::act($id,'check','start',[],revision($id));eq(8,stepRow($id)['started_by']);eq(12,stepRow($id)['groups_id']);
    }finally{Session::$user=7;rights();}
});
check('an unclaimed step cannot be released',function() {
    $id=ownershipFixture();denied(fn()=>Instances::act($id,'check','release',[],revision($id)));
});
check('saving or directly completing also identifies the operator',function() {
    $id=ownershipFixture();Instances::act($id,'check','save',[],revision($id));eq(7,stepRow($id)['started_by']);
    $id=ownershipFixture();Instances::act($id,'check','complete',['ok'=>'1'],revision($id));eq(7,stepRow($id)['started_by']);eq(7,stepRow($id)['completed_by']);
});
check('reopening identifies the new operator and preserves previous audit',function() {
    $id=ownershipFixture();Instances::act($id,'check','complete',['ok'=>'1'],revision($id));$before=Store::rows('history',['instances_id'=>$id]);
    try{Session::$user=8;Instances::act($id,'check','reopen',[],revision($id),'Contrôle complémentaire');
        eq(8,stepRow($id)['started_by']);eq('progress',stepRow($id)['state']);eq(null,stepRow($id)['completed_by']);
        if(count(Store::rows('history',['instances_id'=>$id]))<=count($before))throw new RuntimeException('Audit lost');
    }finally{Session::$user=7;rights();}
});
check('explicit admin reassignment releases current ownership',function() {
    $id=ownershipFixture();Instances::act($id,'check','save',['ok'=>'1'],revision($id));
    try{Session::$user=8;Instances::act($id,'check','assign',[],revision($id),'Relais',9,12);
        eq(null,stepRow($id)['started_by']);eq('available',stepRow($id)['state']);eq(9,stepRow($id)['users_id']);eq(true,Instances::data($id)['values']['check']['ok']);
    }finally{Session::$user=7;rights();}
});
check('legacy progress can be claimed without losing its values',function() {
    global $DB;
    $id=ownershipFixture();$row=stepRow($id);
    $DB->update(Store::table('stepinstances'),['state'=>'progress','started_at'=>'2026-01-01 00:00:00'],['id'=>$row['id']]);
    Instances::act($id,'check','start',[],revision($id));
    eq(7,stepRow($id)['started_by']);
    if(stepRow($id)['started_at']==='2026-01-01 00:00:00')throw new RuntimeException('Ownership timestamp was not updated');
});
check('ownership never bypasses native ticket write permissions',function() {
    $id=ownershipFixture();Instances::act($id,'check','start',[],revision($id));
    try{Session::$rights['native_ticket']=READ;denied(fn()=>Instances::act($id,'check','release',[],revision($id)));}
    finally{rights();}
});
check('summary identifies the operator and a colleague sees read-only step fields',function() {
    global $CFG_GLPI;$CFG_GLPI=['root_doc'=>''];
    $id=ownershipFixture();Instances::act($id,'check','start',[],revision($id));
    try{Session::$user=8;Session::$rights['plugin_workflowbuilder_admin']=0;ob_start();WorkflowBuilder\View::instance($id);$html=ob_get_clean();
        if(!str_contains($html,'Pris en charge par Technicien A'))throw new RuntimeException('Operator name not visible');
        if(str_contains($html,'type="checkbox"'))throw new RuntimeException('Other worker can edit claimed fields');
    }finally{Session::$user=7;rights();}
});
check('guide permissions allow plugin readers and reject unrelated profiles',function() {
    WorkflowBuilder\Guide::requireAccess();
    try{Session::$rights['plugin_workflowbuilder_view']=0;Session::$rights['plugin_workflowbuilder_template']=0;
        denied(fn()=>WorkflowBuilder\Guide::requireAccess());
        Session::$rights['plugin_workflowbuilder_template']=READ;WorkflowBuilder\Guide::requireAccess();
    }finally{rights();}
});
function messageFixture(array $overrides = [], array $team = []): int {
    Session::$user = 7; rights();
    $d = definition();
    $d['steps'][0] = array_replace($d['steps'][0], $overrides);
    $t = Templates::save(0, $d, 0, false, 0); Templates::publish($t, 1);
    return Instances::launch($t, 0, 0, ['request' => 'Demande <script>'], bin2hex(random_bytes(32)), 0, $team);
}
function messages(int $id): array {
    global $DB;
    $ticket = Store::one('instances', $id)['tickets_id'];
    return iterator_to_array($DB->request(['FROM'=>'glpi_itilfollowups','WHERE'=>['items_id'=>$ticket]]), false);
}
check('new and legacy definitions enable all three ticket messages by default', function() {
    $step = definition()['steps'][0];
    foreach (['notify_available','notify_taken','notify_done'] as $key) { eq(true, $step[$key]); }
    $d=definition();$d['steps'][0]['notify_done']='invalid'; denied(fn()=>Definition::validate($d));
});
check('launch writes a structured opening and only announces available steps', function() {
    global $DB;
    $id=messageFixture();$ticket=Store::one('instances',$id)['tickets_id'];$content=$DB->tables['glpi_tickets'][$ticket]['content'];
    foreach (['WORKFLOW','Informations du lancement','request_key=','Ouvrir le workflow du ticket','0 %','&lt;script&gt;'] as $part) {
        if(!str_contains($content,$part))throw new RuntimeException('Opening misses '.$part);
    }
    eq(1,count(messages($id)));eq(1,messages($id)[0]['is_private']);
    if(!str_contains(messages($id)[0]['content'],'Étape disponible'))throw new RuntimeException('Missing activation');
});
check('repeated launch key does not duplicate native followups',function(){
    $id=messageFixture();$i=Store::one('instances',$id);$before=messages($id);
    eq($id,Instances::launch((int)$i['templates_id'],0,0,['request'=>'Retry'],$i['request_key']));
    eq($before,messages($id));
});
check('claim announces the operator once and saves do not spam the ticket',function(){
    $id=messageFixture();Instances::act($id,'check','start',[],revision($id));eq(2,count(messages($id)));
    Instances::act($id,'check','save',['ok'=>'1'],revision($id));eq(2,count(messages($id)));
    $body=messages($id)[1]['content'];
    if(!str_contains($body,'Étape prise en charge')||!str_contains($body,'Technicien A'))throw new RuntimeException('Missing operator');
});
check('completion includes answers, safe comment, actor, link and final progress and activates dependencies',function(){
    $id=messageFixture();Instances::act($id,'check','complete',['ok'=>'1'],revision($id),'<script>comment</script>');
    $m=messages($id);eq(4,count($m));
    foreach(['Étape terminée','Technicien A','Vérifié','Oui','50 %','instance.php?id=','#wb-step-check','&lt;script&gt;comment'] as $part){
        if(!str_contains($m[2]['content'],$part))throw new RuntimeException('Completion misses '.$part);
    }
    if(str_contains($m[2]['content'],'<script>'))throw new RuntimeException('Unescaped comment');
    if(!str_contains($m[3]['content'],'Étape disponible')||!str_contains($m[3]['content'],'Finaliser'))throw new RuntimeException('Dependency missing');
});
check('checkboxes independently disable claim and completion messages',function(){
    $id=messageFixture(['notify_taken'=>false,'notify_done'=>false]);
    Instances::act($id,'check','start',[],revision($id));eq(1,count(messages($id)));
    Instances::act($id,'check','complete',['ok'=>'1'],revision($id));eq(2,count(messages($id)));
    if(!str_contains(messages($id)[1]['content'],'Étape disponible'))throw new RuntimeException('Dependency must still notify');
});
check('availability checkbox disables only that step activation message',function(){
    $id=messageFixture(['notify_available'=>false]);eq([],messages($id));
    Instances::act($id,'check','start',[],revision($id));eq(1,count(messages($id)));
});
check('release announces availability and a new claim announces the new operator',function(){
    $id=messageFixture();Instances::act($id,'check','start',[],revision($id));Instances::act($id,'check','release',[],revision($id));
    Session::$user=8;Instances::act($id,'check','start',[],revision($id));Session::$user=7;
    eq(4,count(messages($id)));
    if(!str_contains(messages($id)[3]['content'],'Technicien B'))throw new RuntimeException('New operator missing');
});
check('followup failure rolls back ownership, values, revision and history',function(){
    $id=messageFixture();$before=Instances::data($id);$history=Store::rows('history',['instances_id'=>$id]);$oldMessages=messages($id);
    ITILFollowup::$failNext=true;denied(fn()=>Instances::act($id,'check','complete',['ok'=>'1'],revision($id)));
    eq($before,Instances::data($id));eq($history,Store::rows('history',['instances_id'=>$id]));eq($oldMessages,messages($id));
});
check('native followup permissions cannot be bypassed by workflow rights',function(){
    $id=messageFixture();$before=Instances::data($id);Session::$rights['native_followup']=0;
    try{denied(fn()=>Instances::act($id,'check','start',[],revision($id)));eq($before,Instances::data($id));}finally{rights();}
});
check('hidden answers never appear in completion messages and false and zero are preserved',function(){
    $fields=WorkflowBuilder\Fields::definitions([
        ['id'=>'show','label'=>'Afficher','type'=>'boolean'],
        ['id'=>'hidden','label'=>'Caché','type'=>'text','condition'=>['field'=>'self.show','op'=>'true']],
        ['id'=>'count','label'=>'Quantité','type'=>'number']
    ],['self.show']);
    $html=WorkflowBuilder\TicketMessages::values($fields,['show'=>false,'hidden'=>'CONFIDENTIEL','count'=>0]);
    if(str_contains($html,'CONFIDENTIEL')||!str_contains($html,'Non')||!str_contains($html,'>0<'))throw new RuntimeException('Invalid visible answers');
});
check('workflow team is persisted with multiple users and groups',function(){
    $id=messageFixture([],['users'=>[7,8],'groups'=>[12]]);
    eq(['users'=>[7,8],'groups'=>[12]],WorkflowBuilder\Assignees::forInstance(Instances::data($id)['instance']));
});
check('workflow team restricts unassigned steps while matching members may execute',function(){
    $id=messageFixture([],['users'=>[8]]);Session::$rights['plugin_workflowbuilder_admin']=0;
    denied(fn()=>Instances::act($id,'check','start',[],revision($id)));
    Session::$user=8;Instances::act($id,'check','start',[],revision($id));eq(8,stepRow($id)['started_by']);Session::$user=7;rights();
});
check('workflow group membership permits execution',function(){
    $id=messageFixture([],['users'=>[999],'groups'=>[12]]);Session::$rights['plugin_workflowbuilder_admin']=0;
    Instances::act($id,'check','start',[],revision($id));eq(7,stepRow($id)['started_by']);rights();
});
check('explicit step assignment takes priority over workflow team',function(){
    $id=messageFixture(['users_id'=>8],['users'=>[7]]);Session::$rights['plugin_workflowbuilder_admin']=0;
    denied(fn()=>Instances::act($id,'check','start',[],revision($id)));Session::$user=8;
    Instances::act($id,'check','start',[],revision($id));Session::$user=7;rights();
});
check('bad workflow assignee references are rejected before ticket creation',function(){
    global $DB;
    $d=definition();$t=Templates::save(0,$d,0,false,0);Templates::publish($t,1);$count=count($DB->tables['glpi_tickets']);
    denied(fn()=>Instances::launch($t,0,0,['request'=>'A'],bin2hex(random_bytes(32)),0,['users'=>[404]]));
    eq($count,count($DB->tables['glpi_tickets']));
    denied(fn()=>WorkflowBuilder\Assignees::fromInput(['assigned_users'=>'7']));
});
check('workflow assignment never substitutes for ticket access',function(){
    $id=messageFixture([],['users'=>[7]]);Session::$rights['native_ticket']=0;
    try{denied(fn()=>Instances::act($id,'check','start',[],revision($id)));}finally{rights();}
});
check('attaching a workflow writes its opening as an internal native followup',function(){
    $id=messageFixture();$i=Store::one('instances',$id);
    $other=Instances::launch((int)$i['templates_id'],(int)$i['tickets_id'],0,['request'=>'Autre'],bin2hex(random_bytes(32)));
    $m=messages($other);eq(3,count($m));
    if(!str_contains($m[1]['content'],'Informations du lancement')||$m[1]['is_private']!==1)throw new RuntimeException('Missing attachment opening');
});


echo "\n$passed service tests passed; $failed failed (GLPI and database test doubles)\n";exit($failed?1:0);
