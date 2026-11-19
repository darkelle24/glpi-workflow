<?php
declare(strict_types=1);
namespace Glpi\Exception { class RedirectException extends \RuntimeException {} }
namespace {
require __DIR__ . '/support/FakeGlpi.php';
require dirname(__DIR__) . '/bootstrap.php';
function __($text, $domain = '') { return $text; }
require dirname(__DIR__) . '/inc/ticket.class.php';
require dirname(__DIR__) . '/inc/mystepsmenu.class.php';
use WorkflowBuilder\{Access, Definition, Http, Instances, MySteps, Store, Templates, ValidationException, View, TicketTabs};
$CFG_GLPI = ['root_doc' => '/glpi'];
$passed = $failed = 0;
function eq($expected, $actual): void {
    if ($expected !== $actual) { throw new RuntimeException(json_encode([$expected, $actual], JSON_UNESCAPED_UNICODE)); }
}
function denied(callable $run): void {
    try { $run(); } catch (ValidationException) { return; }
    throw new RuntimeException('Expected refusal');
}
function check(string $name, callable $run): void {
    global $DB, $passed, $failed;
    $DB = new FakeDb(); Session::$user = 7; Session::$now = '2026-11-19 12:00:00';
    Session::$rights = array_fill_keys(array_keys(Access::RIGHTS), READ | UPDATE | CREATE);
    Session::$rights += ['native_ticket' => READ | UPDATE | CREATE, 'native_followup' => CREATE];
    $_SESSION = ['glpigroups' => [12]];
    putenv('WORKFLOWBUILDER_TICKET_URLS=0');
    try { $run(); $passed++; echo "PASS $name\n"; }
    catch (Throwable $error) { $failed++; echo "FAIL $name: {$error->getMessage()}\n"; }
}
function fixture(array $steps = [], array $team = [], array $options = [], int $ticket = 0): int {
    $d = array_replace(Definition::empty(), ['name' => 'Procédure RH', 'ticket_title' => 'Préparation ordinateur'], $options);
    $d['steps'] = $steps ?: [['id' => 'check', 'title' => 'Vérifier', 'users_id' => 7],
        ['id' => 'finish', 'title' => 'Finaliser', 'depends_on' => ['check'], 'users_id' => 7]];
    $template = Templates::save(0, $d, 0, false, 0); Templates::publish($template, 1);
    return Instances::launch($template, $ticket, 0, [], bin2hex(random_bytes(32)), 0, $team);
}
function ticketId(int $id): int { return (int) Store::one('instances', $id)['tickets_id']; }
function setStep(int $id, string $key, array $data): void {
    $row = Store::rows('stepinstances', ['instances_id' => $id, 'step_key' => $key])[0];
    Store::update('stepinstances', (int) $row['id'], $data);
}
function setDeadline(int $instance, mixed $deadline): void {
    global $DB; $DB->tables['glpi_tickets'][ticketId($instance)]['time_to_resolve']=$deadline;
}
function keys(int $ticket = 0, string $query = '', string $state = ''): array {
    return array_column(MySteps::rows($ticket, $query, $state), 'step_key');
}
function render(callable $run): string {
    ob_start(); try { $run(); return ob_get_contents(); } finally { ob_end_clean(); }
}
check('ready personal steps only; completion exposes dependent steps', function () {
    $id = fixture(); eq(['check'], keys());
    Instances::act($id, 'check', 'complete', [], 1);
    eq(['finish'], keys());
    Instances::act($id, 'finish', 'complete', [], 2); eq([], keys());
});
check('user and group assignments are a union; admin does not see others assignments', function () {
    fixture([['id'=>'user','title'=>'Personnel','users_id'=>7], ['id'=>'group','title'=>'Équipe','groups_id'=>12],
        ['id'=>'either','title'=>'Utilisateur ou groupe','users_id'=>8,'groups_id'=>12],
        ['id'=>'other','title'=>'Autre','users_id'=>8], ['id'=>'elsewhere','title'=>'Autre groupe','groups_id'=>13]]);
    eq(['user','group','either'], keys());
    Session::$rights['plugin_workflowbuilder_admin']=0; eq(['user','group','either'], keys());
});
check('workflow team applies only without explicit step assignment', function () {
    fixture([['id'=>'personal','title'=>'Moi','users_id'=>7], ['id'=>'team','title'=>'Équipe'],
        ['id'=>'foreign','title'=>'Autre','users_id'=>8]], ['users'=>[8]]);
    eq(['personal'], keys());
});
check('workflow team supports multiple users and group membership', function () {
    fixture([['id'=>'team','title'=>'Équipe']], ['users'=>[8], 'groups'=>[12]]); eq(['team'], keys());
    $_SESSION['glpigroups']=[13]; eq([], keys()); Session::$user=8; eq(['team'], keys());
});
check('unassigned steps are offered to authorized executors', function () {
    fixture([['id'=>'open','title'=>'Sans affectation']]);
    Session::$rights['plugin_workflowbuilder_admin']=0; eq(['open'], keys());
});
check('steps owned by another technician disappear even for admins', function () {
    $id=fixture(); setStep($id,'check',['state'=>'progress','started_by'=>8]); eq([], keys());
    setStep($id,'check',['started_by'=>7]); eq(['check'], keys());
});
check('blocked steps require the unblock permission and personal ownership', function () {
    $id=fixture([['id'=>'blocked','title'=>'Débloquer','initial_state'=>'blocked','groups_id'=>12]]);
    eq(['blocked'], keys()); Session::$rights['plugin_workflowbuilder_reopen']=0; eq([], keys());
    Session::$rights['plugin_workflowbuilder_reopen']=UPDATE;
    setStep($id,'blocked',['started_by'=>8]); eq([], keys());
});
check('execution restrictions and action rights apply', function () {
    fixture([],[],['execute_users'=>[8]]); Session::$rights['plugin_workflowbuilder_admin']=0; eq([], keys());
    Session::$user=8; eq([], keys());
});
check('validate-only users see steps they can complete; read-only users see none', function () {
    fixture(); Session::$rights['plugin_workflowbuilder_execute']=0; eq(['check'], keys());
    Session::$rights['plugin_workflowbuilder_validate']=READ; eq([], keys());
});
check('validate-only users cannot act before completion-mode prerequisites', function () {
    $id=fixture([['id'=>'first','title'=>'Préalable','users_id'=>8],
        ['id'=>'next','title'=>'Suite','users_id'=>7,'depends_on'=>['first'],'dependency_mode'=>'complete']]);
    Session::$rights['plugin_workflowbuilder_execute']=0; eq([],keys());
    setStep($id,'first',['state'=>'done']); eq(['next'],keys());
});
check('native ticket update and plugin view permissions are required' , function () {
    $id=fixture(); Session::$rights['native_ticket']=READ; eq([], keys());
    Session::$rights['plugin_workflowbuilder_view']=0; denied(fn()=>MySteps::rows());
    denied(fn()=>View::mySteps(ticketId($id)));
});
check('deleted inaccessible resolved closed and missing tickets are excluded', function () {
    global $DB; $id=fixture(); $ticket=ticketId($id); $original=$DB->tables['glpi_tickets'][$ticket];
    foreach ([['is_deleted'=>1],['entities_id'=>1],['status'=>CommonITILObject::SOLVED],['status'=>CommonITILObject::CLOSED]] as $change) {
        $DB->tables['glpi_tickets'][$ticket]=array_replace($original,$change); eq([], keys());
    }
    unset($DB->tables['glpi_tickets'][$ticket]); eq([], keys()); denied(fn()=>MySteps::rows($ticket));
});
check('conditions use effective engine state rather than stored state', function () {
    $id=fixture([['id'=>'conditional','title'=>'Condition','condition'=>['field'=>'form.active','op'=>'true']]], [],
        ['fields'=>[['id'=>'active','label'=>'Actif','type'=>'checkbox','default'=>false]]]);
    setStep($id,'conditional',['state'=>'available']); eq([], keys());
});
check('ticket scope includes all its workflows and never another ticket', function () {
    $id=fixture(); $ticket=ticketId($id); $second=fixture([],[],[],$ticket); $third=fixture();
    $rows=MySteps::rows($ticket); eq([$second,$id],array_column($rows,'instance_id')); eq([$ticket,$ticket],array_column($rows,'ticket_id'));
    eq([],keys($ticket,(string)ticketId($third)));
});
check('ticket filters accept exact ID with hash and Unicode title fragments', function () {
    $id=fixture(); $ticket=ticketId($id); fixture([],[],['ticket_title'=>'Autre ticket']);
    eq([$id],array_column(MySteps::rows(0,'#'.$ticket),'instance_id'));
    eq([$id],array_column(MySteps::rows(0,(string)$ticket),'instance_id'));
    eq([$id],array_column(MySteps::rows(0,'  PRÉPARATION  '),'instance_id'));
    eq([],keys(0,'ordinateur.*')); eq([],keys(0,'#99999'));
});
check('state filter composes with ticket filter and rejects unknown states', function () {
    $id=fixture(); eq(['check'],keys(0,'','available')); eq([],keys(0,'','progress'));
    Instances::act($id,'check','start',[],1); eq(['check'],keys(0,'#'.ticketId($id),'progress'));
    eq([],keys(0,'#'.ticketId($id),'available')); denied(fn()=>MySteps::rows(0,'','done'));
});
check('optional actionable steps remain after mandatory workflow completion', function () {
    $id=fixture([['id'=>'required','title'=>'Obligatoire'],['id'=>'optional','title'=>'Facultative','required'=>false]]);
    Instances::act($id,'required','complete',[],1); eq('completed',Store::one('instances',$id)['status']); eq(['optional'],keys());
});
check('ticket queue opens the personal tab and escapes ticket and workflow labels', function () {
    $id=fixture([['id'=>'check','title'=>'<b>Étape</b>']],[],['name'=>'<script>Workflow</script>','ticket_title'=>'<img src=x>']);
    $html=render(fn()=>View::mySteps());
    eq(true,str_contains($html,View::e(Http::ticketMySteps(ticketId($id)))));
    foreach (['&lt;script&gt;Workflow&lt;/script&gt;','&lt;img src=x&gt;','Ouvrir mes étapes'] as $part) { eq(true,str_contains($html,$part)); }
    eq(false,str_contains($html,'<img src=x>'));
});
check('pagination counts tickets rather than steps and retains filters', function () {
    for($n=1;$n<=25;$n++){fixture();}
    $html=render(fn()=>View::mySteps(0,'préparation','available',2));
    eq(5,substr_count($html,'Ouvrir mes étapes'));
    eq(5,substr_count(render(fn()=>View::mySteps(0,'préparation','available',999)),'Ouvrir mes étapes'));
    eq(true,str_contains($html,'q=pr%C3%A9paration&amp;state=available&amp;page=1'));
});
check('ticket tab displays every pending step with details without pagination', function () {
    $steps=[];for($n=1;$n<=25;$n++){$steps[]=['id'=>'step'.$n,'title'=>'Étape '.$n];}
    $id=fixture($steps); Session::$rights['plugin_workflowbuilder_admin']=0;
    $html=render(fn()=>View::mySteps(ticketId($id)));
    eq(25,substr_count($html,'class="wb-step wb-step-'));
    eq(25,substr_count($html,'name="from_my_steps" value="1"'));
    eq(true,str_contains($html,'data-wb-runtime'));eq(false,str_contains($html,'Pagination des tickets'));
});
check('empty results and query markup are safe and readable', function () {
    fixture(); $html=render(fn()=>View::mySteps(0,'"><svg onload=x>'));
    eq(true,str_contains($html,'Aucun ticket à traiter')); eq(true,str_contains($html,'&quot;&gt;&lt;svg onload=x&gt;'));
    eq(false,str_contains($html,'<svg'));
});
check('side menu and ticket tabs respect view rights and preserve workflow tab number', function () {
    $id=fixture(); $ticket=Access::ticket(ticketId($id));
    $tabs=(new PluginWorkflowbuilderTicket())->getTabNameForItem($ticket);
    eq([1=>'Workflow',2=>'Mes étapes à traiter'],array_map('strip_tags',$tabs));
    eq(true,str_contains($tabs[1],'ti ti-checklist'));eq(true,str_contains($tabs[2],'ti ti-list-check'));
    eq(true,str_contains($tabs[1],'aria-hidden="true"'));eq(true,str_contains($tabs[2],'aria-hidden="true"'));
    eq('/plugins/workflowbuilder/front/my-steps.php',PluginWorkflowbuilderMyStepsMenu::getMenuContent()['page']);
    $html=render(fn()=>PluginWorkflowbuilderTicket::displayTabContentForItem($ticket,2));
    eq(true,str_contains($html,'Mes étapes à traiter pour le ticket #'.ticketId($id)));
    eq(true,str_contains($html,'Informations du lancement'));
    eq(true,str_contains($html,'name="from_my_steps" value="1"'));
    eq(false,PluginWorkflowbuilderTicket::displayTabContentForItem($ticket,3));
    Session::$rights['plugin_workflowbuilder_view']=0; eq('',(new PluginWorkflowbuilderTicket())->getTabNameForItem($ticket));
    eq(false,PluginWorkflowbuilderMyStepsMenu::getMenuContent());
});
check('new ticket tab has stable native and pretty URLs', function () {
    eq('/front/ticket.form.php?id=11&forcetab=PluginWorkflowbuilderTicket%242',TicketTabs::url(11,'PluginWorkflowbuilderTicket$2'));
    putenv('WORKFLOWBUILDER_TICKET_URLS=1');eq('/glpi/front/ticket/11/mes-etapes',TicketTabs::url(11,'PluginWorkflowbuilderTicket$2'));
});
check('multiple workflows create one ticket row and aggregate personal progress', function () {
    $id=fixture(); $ticket=ticketId($id); $second=fixture([],[],[],$ticket);
    Instances::act($id,'check','complete',[],1);
    $queue=MySteps::tickets();eq(1,count($queue));$item=$queue[0];
    eq(1,$item['done']);eq(3,$item['remaining']);eq(4,$item['total']);eq(25,$item['percent']);
    eq(2,count($item['workflows']));eq(2,$item['actionable']);eq(1,$item['states']['waiting']);
    $html=render(fn()=>View::mySteps());eq(1,substr_count($html,'Ouvrir mes étapes'));
    eq(true,str_contains($html,'1 / 4 traitée(s) par vous'));
});
check('colleagues completions are not attributed to the current user', function () {
    $id=fixture([['id'=>'first','title'=>'Première','groups_id'=>12],['id'=>'next','title'=>'Suite','groups_id'=>12]]);
    Session::$user=8;Instances::act($id,'first','complete',[],1);Session::$user=7;
    $item=MySteps::tickets()[0];eq(0,$item['done']);eq(1,$item['remaining']);eq(1,$item['total']);
    Instances::act($id,'next','complete',[],2);eq([],MySteps::tickets());
});
check('waiting-only tickets stay listed and state filters never alter totals', function () {
    $id=fixture([['id'=>'foreign','title'=>'Autre','users_id'=>8],
        ['id'=>'mine','title'=>'À venir','users_id'=>7,'depends_on'=>['foreign']]]);
    $item=MySteps::tickets()[0];eq(1,$item['remaining']);eq(0,$item['actionable']);
    eq(1,count(MySteps::tickets(0,'#'.ticketId($id),'waiting')));eq([],MySteps::tickets(0,'','available'));
    $id=fixture();$item=MySteps::tickets(ticketId($id),'','available')[0];eq(2,$item['total']);
});
check('personal details show fields procedures and actions while hiding others and done steps', function () {
    $id=fixture([['id'=>'mine','title'=>'À faire','description'=>'Contrôler le matériel','users_id'=>7,
        'knowbaseitems_id'=>[3],'fields'=>[['id'=>'serial','label'=>'Numéro de série','type'=>'text','required'=>true]]],
        ['id'=>'other','title'=>'Travail du collègue','users_id'=>8],['id'=>'done','title'=>'Déjà terminé','users_id'=>7],
        ['id'=>'waiting','title'=>'Après le contrôle','users_id'=>7,'depends_on'=>['mine']]]);
    setStep($id,'done',['state'=>'done','completed_by'=>7]);
    $html=render(fn()=>View::mySteps(ticketId($id)));
    foreach(['Contrôler le matériel','name="values[serial]"','Voir la procédure','Commentaire','Je prends en charge','value="complete"',
        'id="wb-my-instance-'.$id.'-step-mine"','id="wb-my-instance-'.$id.'-step-waiting"'] as $part){eq(true,str_contains($html,$part));}
    foreach(['Travail du collègue','Déjà terminé','id="wb-my-instance-'.$id.'-step-done"'] as $part){eq(false,str_contains($html,$part));}
    $full=render(fn()=>View::ticket(ticketId($id)));
    eq(true,str_contains($full,'Travail du collègue'));eq(true,str_contains($full,'Déjà terminé'));
});
check('personal forms cannot complete before prerequisites and waiting fields stay read-only', function () {
    $id=fixture([['id'=>'foreign','title'=>'Préalable','users_id'=>8],
        ['id'=>'mine','title'=>'Suite','users_id'=>7,'depends_on'=>['foreign'],'dependency_mode'=>'complete',
            'fields'=>[['id'=>'answer','label'=>'Réponse','type'=>'text']]],
        ['id'=>'waiting','title'=>'Attente','users_id'=>7,'depends_on'=>['foreign'],
            'fields'=>[['id'=>'future','label'=>'À venir','type'=>'text']]]]);
    Session::$rights['plugin_workflowbuilder_execute']=0;Session::$rights['plugin_workflowbuilder_admin']=0;
    $html=render(fn()=>View::mySteps(ticketId($id)));
    eq(false,str_contains($html,'name="action" value="complete"'));
    eq(false,str_contains($html,'name="values[answer]"'));eq(false,str_contains($html,'name="values[future]"'));
    eq(0,MySteps::tickets()[0]['actionable']);eq(2,MySteps::tickets()[0]['remaining']);
});
check('workflow and personal anchors remain unique when both tabs are rendered', function () {
    $id=fixture(); $html=render(function()use($id){View::ticket(ticketId($id));View::mySteps(ticketId($id));});
    preg_match_all('/id="(wb-(?:my-)?instance-[^"]+)"/',$html,$matches);
    eq(count($matches[1]),count(array_unique($matches[1])));
});
check('reopening personal work updates progress counts', function () {
    $id=fixture();Instances::act($id,'check','complete',[],1);eq(1,MySteps::tickets()[0]['done']);
    Instances::act($id,'check','reopen',[],2,'Correction');$item=MySteps::tickets()[0];
    eq(0,$item['done']);eq(2,$item['remaining']);eq(2,$item['total']);
});
check('aggregate queue preserves assignment and native ticket restrictions', function () {
    global $DB;
    $id=fixture([['id'=>'other','title'=>'Autre','users_id'=>8]]);eq([],MySteps::tickets());
    $id=fixture(); $ticket=ticketId($id);$original=$DB->tables['glpi_tickets'][$ticket];
    foreach([['is_deleted'=>1],['entities_id'=>1],['status'=>CommonITILObject::SOLVED],['status'=>CommonITILObject::CLOSED]] as $change){
        $DB->tables['glpi_tickets'][$ticket]=array_replace($original,$change);eq([],MySteps::tickets());
    }
    $DB->tables['glpi_tickets'][$ticket]=$original;Session::$rights['native_ticket']=READ;eq([],MySteps::tickets());
});
check('nearest deadline sorts overdue first, equal dates deterministically and undated last', function () {
    $none=fixture();$far=fixture();$late=fixture();$soon=fixture();$tie=fixture();
    setDeadline($far,'2026-12-20 09:00:00');setDeadline($late,'2026-11-17 09:00:00');
    setDeadline($soon,'2026-11-19 12:30:00');setDeadline($tie,'2026-11-19 12:30:00');
    $expected=array_map('ticketId',[$late,$tie,$soon,$far,$none]);
    $items=MySteps::tickets();eq($expected,array_column($items,'ticket_id'));
    eq(true,$items[0]['overdue']);eq(false,$items[1]['overdue']);eq(null,$items[4]['deadline']);
    eq($expected,array_column(MySteps::tickets(0,'PRÉPARATION','available'),'ticket_id'));
});
check('reverse deadline order keeps undated last and recent sort remains available', function () {
    $near=fixture();$far=fixture();$none=fixture();
    setDeadline($near,'2026-11-20 09:00:00');setDeadline($far,'2026-12-20 09:00:00');
    eq(array_map('ticketId',[$far,$near,$none]),array_column(MySteps::tickets(0,'','','deadline_desc'),'ticket_id'));
    eq(array_map('ticketId',[$none,$far,$near]),array_column(MySteps::tickets(0,'','','recent'),'ticket_id'));
    denied(fn()=>MySteps::tickets(0,'','','invalid'));
});
check('deadline is read live from ticket and shared by all its workflows', function () {
    $id=fixture();$ticket=ticketId($id);$second=fixture([],[],[],$ticket);
    eq(null,MySteps::tickets()[0]['deadline']);
    setDeadline($id,'2026-11-20 08:30:00');$item=MySteps::tickets()[0];
    eq('2026-11-20 08:30:00',$item['deadline']);eq(2,count($item['workflows']));eq(4,$item['total']);
    setDeadline($second,'2026-11-18 08:30:00');$item=MySteps::tickets()[0];
    eq('2026-11-18 08:30:00',$item['deadline']);eq(true,$item['overdue']);
    setDeadline($id,null);eq(null,MySteps::tickets()[0]['deadline']);eq(false,MySteps::tickets()[0]['overdue']);
});
check('missing legacy and malformed deadline values are treated as undated', function () {
    $values=[null,'','0000-00-00 00:00:00','2026-02-30 15:00:00','2026-11-19 25:00:00','<img src=x>'];
    foreach($values as $value){$id=fixture();setDeadline($id,$value);}
    foreach(MySteps::tickets() as $item){eq(null,$item['deadline']);eq(false,$item['overdue']);}
    $html=render(fn()=>View::mySteps());eq(count($values),substr_count($html,'Sans échéance'));
    eq(false,str_contains($html,'<img src=x>'));
});
check('deadline validation is independent of server daylight-saving transitions', function () {
    $timezone=date_default_timezone_get();
    try {
        date_default_timezone_set('Europe/Berlin');$id=fixture();setDeadline($id,'2027-03-28 02:30:00');
        eq('2027-03-28 02:30:00',MySteps::tickets()[0]['deadline']);
    } finally { date_default_timezone_set($timezone); }
});
check('overdue status compares the current GLPI clock including the exact boundary', function () {
    $id=fixture();setDeadline($id,'2026-11-19 12:00:00');eq(false,MySteps::tickets()[0]['overdue']);
    Session::$now='2026-11-19 12:00:01';eq(true,MySteps::tickets()[0]['overdue']);
    Session::$now='2026-11-19 11:59:59';eq(false,MySteps::tickets()[0]['overdue']);
});
check('list and personal tab show formatted deadlines and overdue labels', function () {
    $id=fixture();setDeadline($id,'2026-11-18 09:15:00');
    foreach([0,ticketId($id)] as $ticket){
        $html=render(fn()=>View::mySteps($ticket));
        eq(true,str_contains($html,'Échéance du ticket'));eq(true,str_contains($html,'18/11/2026 09:15'));
        eq(true,str_contains($html,'En retard'));eq(true,str_contains($html,'text-danger'));
    }
    setDeadline($id,'2026-11-20 09:15:00');$html=render(fn()=>View::mySteps());
    eq(true,str_contains($html,'20/11/2026 09:15'));eq(false,str_contains($html,'En retard'));
    setDeadline($id,null);eq(true,str_contains(render(fn()=>View::mySteps(ticketId($id))),'Sans échéance'));
});
check('deadline sort is applied before pagination and survives filtered page links', function () {
    $ids=[];
    for($n=1;$n<=21;$n++){$id=fixture();$ids[]=ticketId($id);setDeadline($id,'2026-12-'.sprintf('%02d',$n).' 09:00:00');}
    $html=render(fn()=>View::mySteps(0,'préparation','available',2));
    preg_match_all('/<tr><td><a[^>]+>#([0-9]+) —/u',$html,$matches);eq([(string)$ids[20]],$matches[1]);
    eq(true,str_contains($html,'page=1&amp;sort=deadline_asc'));
    $html=render(fn()=>View::mySteps(0,'préparation','available',2,'deadline_desc'));
    preg_match_all('/<tr><td><a[^>]+>#([0-9]+) —/u',$html,$matches);eq([(string)$ids[0]],$matches[1]);
    eq(true,str_contains($html,'value="deadline_desc" selected'));
    eq(true,str_contains($html,'q=pr%C3%A9paration&amp;state=available&amp;page=1&amp;sort=deadline_desc'));
});
// Exercise the real GET controller without a GLPI installation.
class Html {
    public static array $headers=[];
    public static function header($title,$unused,$sector,$menu):void{self::$headers[]=[$title,$sector,$menu];}
    public static function footer():void{}
    public static function convDateTime($date):string{return (new \DateTimeImmutable($date))->format('d/m/Y H:i');}
    public static function redirect($url):void{throw new \Glpi\Exception\RedirectException($url);}
}
class Plugin {public static function isPluginActive($name):bool{return true;}}
define('GLPI_ROOT', dirname(__DIR__));
check('controller validates filters and selects the dedicated side menu', function () {
    $id=fixture(); $_GET=['q'=>'#'.ticketId($id),'state'=>'available','page'=>'1'];
    global $DB; $before=$DB->tables;
    $html=render(function(){include dirname(__DIR__).'/front/my-steps.php';});
    eq($before,$DB->tables);
    eq(true,str_contains($html,'Ouvrir mes étapes'));
    eq(['Mes étapes à traiter','helpdesk','PluginWorkflowbuilderMyStepsMenu'],end(Html::$headers));
    foreach ([['q'=>[]],['state'=>'done'],['page'=>'-1'],['sort'=>'unknown'],['sort'=>[]]] as $query) {
        $_GET=$query; $html=render(function(){include dirname(__DIR__).'/front/my-steps.php';});
        eq(true,str_contains($html,'role="alert"'));eq(false,str_contains($html,'Ouvrir mes étapes'));
    }
    Session::$rights['plugin_workflowbuilder_view']=0; $_GET=[];
    $html=render(function(){include dirname(__DIR__).'/front/my-steps.php';});
    eq(true,str_contains($html,'role="alert"'));eq(false,str_contains($html,'Ouvrir mes étapes'));
});
check('GET controller honors explicit deadline order and exposes default urgency order', function () {
    $near=fixture();$far=fixture();setDeadline($near,'2026-11-20 09:00:00');setDeadline($far,'2026-12-20 09:00:00');
    foreach(['deadline_asc'=>[$near,$far],'deadline_desc'=>[$far,$near]] as $sort=>$expected){
        $_GET=['q'=>'préparation','state'=>'available','sort'=>$sort];
        $html=render(function(){include dirname(__DIR__).'/front/my-steps.php';});
        preg_match_all('/<tr><td><a[^>]+>#([0-9]+) —/u',$html,$matches);
        eq(array_map(fn($id)=>(string)ticketId($id),$expected),$matches[1]);
    }
    $_GET=[];$html=render(function(){include dirname(__DIR__).'/front/my-steps.php';});
    eq(true,str_contains($html,'value="deadline_asc" selected'));
});
function actionRequest(array $input): string {
    $_SERVER['REQUEST_METHOD']='POST'; $_POST=$input+['_wb_token'=>Http::token()];
    $url='';ob_start();
    try { include dirname(__DIR__).'/front/action.php'; }
    catch (\Glpi\Exception\RedirectException $redirect) { $url=$redirect->getMessage(); }
    finally { ob_end_clean(); }
    return $url;
}
check('personal actions return to the same ticket tab and retain failed input', function () {
    $id=fixture([['id'=>'check','title'=>'Vérifier','users_id'=>7,'fields'=>[['id'=>'answer','label'=>'Réponse','type'=>'text','required'=>true],['id'=>'note','label'=>'Note','type'=>'text']]],
        ['id'=>'next','title'=>'Suite','users_id'=>7,'depends_on'=>['check']]]);
    $base=['operation'=>'step','id'=>(string)$id,'step'=>'check','from_ticket'=>'1','from_my_steps'=>'1'];
    $url=actionRequest($base+['action'=>'complete','revision'=>'1','values'=>['answer'=>'','note'=>'Saisie conservée']]);
    eq(Http::ticketMySteps(ticketId($id),$id,'check'),$url);eq(1,Store::one('instances',$id)['revision']);
    eq(['answer'=>'','note'=>'Saisie conservée'],$_SESSION['wb_failed'][$id]['check']);
    $failedHtml=render(fn()=>View::mySteps(ticketId($id)));
    eq(true,str_contains($failedHtml,'value="Saisie conservée"'));
    eq(false,isset($_SESSION['wb_failed'][$id]['check']));
    $url=actionRequest($base+['action'=>'complete','revision'=>'1','values'=>['answer'=>'Fait']]);
    eq(Http::ticketMySteps(ticketId($id),$id,'check'),$url);
    $item=MySteps::tickets()[0];eq(1,$item['done']);eq(1,$item['remaining']);
    $html=render(fn()=>View::mySteps(ticketId($id)));eq(false,str_contains($html,'id="wb-my-instance-'.$id.'-step-check"'));
    eq(true,str_contains($html,'id="wb-my-instance-'.$id.'-step-next"'));
});
check('existing workflow and standalone actions retain their original destinations', function () {
    $id=fixture();$base=['operation'=>'step','id'=>(string)$id,'step'=>'check','action'=>'start','revision'=>'1'];
    eq(Http::ticketWorkflow(ticketId($id),$id,'check'),actionRequest($base+['from_ticket'=>'1']));
    $id=fixture();$base['id']=(string)$id;
    eq(Http::url('instance.php',['id'=>$id]).'#wb-step-check',actionRequest($base+['from_ticket'=>'0']));
});
echo "\n$passed personal step queue tests passed; $failed failed (GLPI and database test doubles)\n";
exit($failed ? 1 : 0);

}
