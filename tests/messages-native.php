<?php
declare(strict_types=1);
/** Only an explicitly named, disposable GLPI database may run these integration tests. */
if (getenv('WORKFLOWBUILDER_ALLOW_NATIVE_TESTS') !== '1') { fwrite(STDERR, "Dedicated native tests required.\n"); exit(2); }
$core = realpath($argv[1] ?? '');
if (!$core || !is_file($core . '/vendor/autoload.php')) { throw new RuntimeException('Installed GLPI required'); }
require $core . '/vendor/autoload.php';
$kernel = new \Glpi\Kernel\Kernel(); $kernel->boot();
global $DB;
if (!preg_match('/^workflowbuilder_(glpi12|test)_[a-z0-9_]+$/D', $DB->dbdefault)) { throw new RuntimeException('Dedicated test DB required'); }
$auth = new Auth();
if (!$auth->login(getenv('WB_GLPI_TEST_USER') ?: 'glpi', getenv('WB_GLPI_TEST_PASSWORD') ?: 'glpi', true)) { throw new RuntimeException('Test login failed'); }
use WorkflowBuilder\{Access, Assignees, Definition, Http, Instances, Schema, Store, Templates, ValidationException};
$beforeCount = count(Store::rows('instances')); Schema::install(); Schema::install(); Access::reload();
set_exception_handler(static function(Throwable $e):void { fwrite(STDERR,(string)$e.PHP_EOL); exit(1); });
$passed=0;
function verify(string $name, bool $ok):void { global $passed; if(!$ok)throw new RuntimeException($name);$passed++;echo "PASS $name\n"; }
function followups(int $ticket): array { global $DB;return iterator_to_array($DB->request(['FROM'=>'glpi_itilfollowups','WHERE'=>['itemtype'=>'Ticket','items_id'=>$ticket],'ORDER'=>['id']]),false); }
verify('schema 4 migration preserves existing instances and is repeatable', Schema::VERSION===4 && count(Store::rows('instances'))===$beforeCount);
$d=Definition::empty();$d['name']='Recette messages '.bin2hex(random_bytes(4));$d['description']='Préparer un poste pour un nouvel utilisateur.';
$d['fields']=[['id'=>'request','label'=>'Demande','type'=>'text','required'=>true]];
$d['steps']=[['id'=>'check','title'=>'Contrôler le poste','fields'=>[
    ['id'=>'ok','label'=>'Vérification effectuée','type'=>'checkbox','required'=>true],
    ['id'=>'details','label'=>'Compte rendu','type'=>'textarea']]],
    ['id'=>'finish','title'=>'Remise du poste','depends_on'=>['check']]];
$t=Templates::save(0,$d,0,false,0);Templates::publish($t,1);
$key=bin2hex(random_bytes(32));$me=(int)Session::getLoginUserID();
$id=Instances::launch($t,0,0,['request'=>'Données <script>alert(1)</script>'],$key,0,['users'=>[$me]]);
$data=Instances::data($id);$ticket=(int)$data['instance']['tickets_id'];$native=new Ticket();$native->getFromDB($ticket);
verify('workflow assignee persists through native database', Assignees::forInstance($data['instance'])===['users'=>[$me],'groups'=>[]]);
verify('opening contains workflow banner, escaped request and an authenticated workflow button',
    str_contains($native->fields['content'],'WORKFLOW') && str_contains($native->fields['content'],'request_key='.$key)
    && str_contains($native->fields['content'],'Ouvrir le workflow du ticket') && !str_contains($native->fields['content'],'<script>'));
$initial=followups($ticket);verify('initial active step generates one private followup',count($initial)===1&&(int)$initial[0]['is_private']===1&&str_contains($initial[0]['content'],'Étape disponible'));
Instances::launch($t,0,0,['request'=>'Retry'],$key);verify('repeated launch never duplicates active followups',count(followups($ticket))===1);
Instances::act($id,'check','start',[],1);$afterClaim=followups($ticket);
verify('native claim message records the GLPI operator',count($afterClaim)===2&&str_contains($afterClaim[1]['content'],'Étape prise en charge')&&str_contains($afterClaim[1]['content'],htmlspecialchars(getUserName($me),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')));
Instances::act($id,'check','save',['ok'=>'1','details'=>'Préparation terminée'],2);
verify('ordinary save does not duplicate claim followup',count(followups($ticket))===2);
Instances::act($id,'check','complete',['ok'=>'1','details'=>'Poste testé <script>danger</script>'],3,'Contrôle <validé>');
$done=followups($ticket);verify('native completion records final answers and percentage',count($done)===4&&str_contains($done[2]['content'],'50 %')&&str_contains($done[2]['content'],'Poste testé')&&str_contains($done[2]['content'],'Terminée par')&&!str_contains($done[2]['content'],'<script>'));
verify('dependency activation appears after the completion message',str_contains($done[3]['content'],'Étape disponible')&&str_contains($done[3]['content'],'Remise du poste'));
Instances::act($id,'finish','complete',[],4);
$final=followups($ticket);verify('direct completion generates a claim and 100 percent completion',count($final)===6&&str_contains($final[5]['content'],'100 %'));
verify('all automatic followups remain internal',!array_filter($final,static fn($r)=>(int)$r['is_private']!==1));
$native->getFromDB($ticket);verify('automatic followups do not reopen or resolve the ticket',!in_array((int)$native->fields['status'],[CommonITILObject::SOLVED,CommonITILObject::CLOSED],true));
// Prepare deterministic objects for the browser integration, without depending on previous test IDs.
$d['name']='Recette navigateur messages '.bin2hex(random_bytes(4));
$browserTemplate=Templates::save(0,$d,0,false,0);Templates::publish($browserTemplate,1);
if ($state=getenv('WB_MESSAGES_STATE_PATH')) {
    file_put_contents($state,json_encode(['template'=>$browserTemplate,'user'=>$me,'existing_instance'=>$id,'existing_ticket'=>$ticket],JSON_THROW_ON_ERROR));
}
echo "$passed native message checks passed\n";
