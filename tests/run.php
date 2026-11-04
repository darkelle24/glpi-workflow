<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
use WorkflowBuilder\{Condition, Definition, Engine, Fields, ValidationException};
$passed = 0;
$failed = 0;
function test(string $name, callable $run): void {
    global $passed, $failed;
    try { $run(); $passed++; echo "PASS $name\n"; }
    catch (Throwable $e) { $failed++; echo "FAIL $name: {$e->getMessage()}\n"; }
}
function same(mixed $expected, mixed $actual): void {
    if ($expected !== $actual) { throw new RuntimeException('Expected ' . json_encode($expected) . ', received ' . json_encode($actual)); }
}
function rejects(callable $run): void {
    try { $run(); } catch (ValidationException) { return; }
    throw new RuntimeException('Expected validation rejection');
}
function fixture(): array {
    return Definition::validate(Definition::empty() + []);
}
function workflow(): array {
    $d = Definition::empty();
    $d['fields'] = [['id'=>'computer','label'=>'Ordinateur nécessaire','type'=>'boolean','required'=>true]];
    $d['steps'] = [
        ['id'=>'a','title'=>'Vérifier','fields'=>[['id'=>'ok','label'=>'Contrôle effectué','type'=>'checkbox','required'=>true]]],
        ['id'=>'b','title'=>'Préparer','condition'=>['field'=>'form.computer','op'=>'true'],'depends_on'=>['a'],'fields'=>[['id'=>'mail','label'=>'Adresse créée','type'=>'email','required'=>true]]],
        ['id'=>'c','title'=>'Finaliser','depends_on'=>['a','b']],
        ['id'=>'optional','title'=>'Facultatif','required'=>false],
    ];
    return Definition::validate($d);
}
$checks = [
    ['eq', true, '1', true], ['ne', 3, '2', true], ['contains', ['a','b'], 'b', true],
    ['contains', 'abcd', 'bc', true], ['not_contains','abcd','z',true],
    ['gt',12,'10',true], ['lt',1,2,true], ['empty',null,'',true], ['not_empty',false,'',true],
    ['true','1','',true], ['false',false,'',true], ['false',null,'',false], ['gt','abc',1,false],
    ['eq',false,'0',true], ['eq',0,1,false], ['empty',[],'',true], ['ne',[],null,true],
];
foreach ($checks as $i => [$op,$actual,$expected,$result]) {
    test('condition ' . $op . ' #' . $i, fn()=>same($result,Condition::evaluate(['field'=>'x','op'=>$op,'value'=>$expected],['x'=>$actual])));
}
test('nested AND OR',fn()=>same(true,Condition::evaluate(['all'=>[['field'=>'a','op'=>'true'],['any'=>[['field'=>'b','op'=>'eq','value'=>'x'],['field'=>'a','op'=>'true']]]]],['a'=>true,'b'=>'z'])));
test('unknown operator rejected',fn()=>rejects(fn()=>Condition::validate(['field'=>'x','op'=>'php'],['x'])));
test('unknown variable rejected',fn()=>rejects(fn()=>Condition::validate(['field'=>'unknown','op'=>'eq'],['x'])));
test('empty condition group rejected',fn()=>rejects(fn()=>Condition::validate(['all'=>[]],[])));
test('oversized condition group rejected',fn()=>rejects(fn()=>Condition::validate(['all'=>array_fill(0,21,['field'=>'x','op'=>'true'])],['x'])));
test('condition depth bounded',function () {$n=['field'=>'x','op'=>'true'];for($i=0;$i<8;$i++)$n=['all'=>[$n]];rejects(fn()=>Condition::validate($n,['x']));});
test('schema version rejected',fn()=>rejects(fn()=>Definition::decode('{"schema":99}')));
test('malformed JSON rejected',fn()=>rejects(fn()=>Definition::decode('{')));
test('large import rejected',fn()=>rejects(fn()=>Definition::decode(str_repeat('x',524289))));
test('unknown property rejected',function () {$d=Definition::empty();$d['php']='system';rejects(fn()=>Definition::validate($d));});
test('duplicate field rejected',function () {$d=workflow();$d['fields'][]=$d['fields'][0];rejects(fn()=>Definition::validate($d));});
test('duplicate step rejected',function () {$d=workflow();$d['steps'][]=$d['steps'][0];rejects(fn()=>Definition::validate($d));});
test('circular dependency rejected',function () {$d=workflow();$d['steps'][0]['depends_on']=['c'];rejects(fn()=>Definition::validate($d));});
test('missing dependency rejected',function () {$d=workflow();$d['steps'][0]['depends_on']=['missing'];rejects(fn()=>Definition::validate($d));});
test('secret field rejected',fn()=>rejects(fn()=>Fields::definitions([['id'=>'password','label'=>'Secret','type'=>'text']],[])));
test('unknown field type rejected',fn()=>rejects(fn()=>Fields::definitions([['id'=>'a','label'=>'A','type'=>'php']],[])));
test('invalid field ID rejected',fn()=>rejects(fn()=>Fields::definitions([['id'=>'../foo','label'=>'A','type'=>'text']],[])));
test('invalid max length rejected',fn()=>rejects(fn()=>Fields::definitions([['id'=>'a','label'=>'A','type'=>'text','maxlength'=>[]]],[])));
test('forward form visibility reference rejected',function () {
    $d=Definition::empty();$d['fields']=[['id'=>'a','label'=>'A','type'=>'text','condition'=>['field'=>'form.b','op'=>'true']],['id'=>'b','label'=>'B','type'=>'boolean']];
    rejects(fn()=>Definition::validate($d));
});
test('field mandatory completion',function () {$d=workflow();rejects(fn()=>Fields::values($d['steps'][0]['fields'],[],[],true));});
test('partial save allowed',function () {$d=workflow();same(['ok'=>false],Fields::values($d['steps'][0]['fields'],[],[],false));});
test('required false boolean allowed',function () {$d=workflow();same(['computer'=>false],Fields::formValues($d['fields'],['computer'=>'0']));});
test('invalid scalar array rejected',function () {$f=Fields::definitions([['id'=>'a','label'=>'A','type'=>'text']],[]);rejects(fn()=>Fields::formValues($f,['a'=>['bad']]));});
test('multiselect nested data rejected',function () {$f=Fields::definitions([['id'=>'a','label'=>'A','type'=>'multiselect','choices'=>['A']]],[]);rejects(fn()=>Fields::formValues($f,['a'=>[['nested']]]));});
test('invalid UTF8 rejected',function () {$f=Fields::definitions([['id'=>'a','label'=>'A','type'=>'text']],[]);rejects(fn()=>Fields::formValues($f,['a'=>"\xFF"]));});
foreach (['email'=>['invalid','ok@example.org'], 'url'=>['javascript:alert(1)','https://example.org/a'], 'date'=>['2026-02-30','2026-11-04'], 'datetime'=>['2026-11-04T99:12','2026-11-04T09:12'], 'number'=>['INF','2.5']] as $type=>[$bad,$good]) {
    test($type.' validation',function () use($type,$bad,$good) {$f=Fields::definitions([['id'=>'a','label'=>'A','type'=>$type]],[]);rejects(fn()=>Fields::formValues($f,['a'=>$bad]));Fields::formValues($f,['a'=>$good]);});
}
test('number bounds',function () {$f=Fields::definitions([['id'=>'a','label'=>'A','type'=>'number','min'=>1,'max'=>5]],[]);rejects(fn()=>Fields::formValues($f,['a'=>6]));same(2.0,Fields::formValues($f,['a'=>2])['a']);});
test('invalid defaults rejected',fn()=>rejects(fn()=>Fields::definitions([['id'=>'a','label'=>'A','type'=>'email','default'=>'bad']],[])));
test('explicit null clears default',function () {$f=Fields::definitions([['id'=>'a','label'=>'A','type'=>'text','default'=>'default']],[]);same(null,Fields::formValues($f,['a'=>null])['a']);});
test('hidden mandatory field excluded',function () {$f=Fields::definitions([['id'=>'a','label'=>'A','type'=>'text','required'=>true,'condition'=>['field'=>'form.flag','op'=>'true']]],['form.flag']);same(['a'=>null],Fields::values($f,['a'=>'injected'],['form.flag'=>false],true));});
test('hidden local field cannot erase global form value',function () {
    $f=Fields::definitions([['id'=>'flag','label'=>'Flag','type'=>'text','condition'=>['field'=>'form.flag','op'=>'false']],['id'=>'next','label'=>'Next','type'=>'text','condition'=>['field'=>'form.flag','op'=>'true']]],['form.flag']);
    same(['flag'=>null,'next'=>'kept'],Fields::values($f,['flag'=>'x','next'=>'kept'],['form.flag'=>true],true));
});
test('initial states with applicable step',fn()=>same(['a'=>'available','b'=>'waiting','c'=>'waiting','optional'=>'available'],Engine::states(workflow(),['computer'=>true])));
test('inapplicable step excluded',fn()=>same('na',Engine::states(workflow(),['computer'=>false])['b']));
test('waiting step cannot start',fn()=>rejects(fn()=>Engine::transition(workflow(),['computer'=>true],[],'b','start',[])));
test('required fields prevent completion',fn()=>rejects(fn()=>Engine::transition(workflow(),['computer'=>true],[],'a','complete',[])));
test('completion unlocks dependency',function () {$r=Engine::transition(workflow(),['computer'=>true],[],'a','complete',['ok'=>'1']);same('available',$r['states']['b']);same(33,$r['progress']['percent']);});
test('NA satisfies dependencies',function () {$r=Engine::transition(workflow(),['computer'=>false],[],'a','complete',['ok'=>'1']);same('available',$r['states']['c']);same(50,$r['progress']['percent']);});
test('optional steps do not block finish',function () {$d=workflow();$r=Engine::transition($d,['computer'=>false],['a'=>'done'],'c','complete',[]);same('completed',$r['progress']['status']);same(100,$r['progress']['percent']);});
test('partial step values validated on completion',fn()=>rejects(fn()=>Engine::transition(workflow(),['computer'=>true],['a'=>'done'],'b','complete',['mail'=>'bad'])));
test('step saved in progress',function () {$r=Engine::transition(workflow(),['computer'=>true],['a'=>'done'],'b','save',['mail'=>'tech@example.org']);same('progress',$r['state']);same('tech@example.org',$r['values']['mail']);});
test('manual block and unblock',function () {$r=Engine::transition(workflow(),['computer'=>true],[],'a','block',[]);same('blocked',$r['state']);$r=Engine::transition(workflow(),['computer'=>true],$r['states'],'a','unblock',[]);same('available',$r['state']);});
test('rule-driven block cannot be bypassed',function () {$d=workflow();$d['rules']=[['step'=>'a','action'=>'block','when'=>['field'=>'form.computer','op'=>'true']]];$d=Definition::validate($d);rejects(fn()=>Engine::transition($d,['computer'=>true],[],'a','unblock',[]));});
test('reopen requires completed step',fn()=>rejects(fn()=>Engine::transition(workflow(),['computer'=>true],[],'a','reopen',[])));
test('reopen prevents invalidating dependent work',fn()=>rejects(fn()=>Engine::transition(workflow(),['computer'=>true],['a'=>'done','b'=>'progress'],'a','reopen',[])));
test('completion-only dependency allows start',function () {$d=workflow();$d['steps'][1]['dependency_mode']='complete';same('progress',Engine::transition($d,['computer'=>true],[],'b','start',[])['state']);rejects(fn()=>Engine::transition($d,['computer'=>true],[],'b','complete',['mail'=>'a@example.org']));});
test('unknown action rejected',fn()=>rejects(fn()=>Engine::transition(workflow(),['computer'=>true],[],'a','execute_php',[])));
test('unknown submitted field rejected',fn()=>rejects(fn()=>Fields::formValues(workflow()['fields'],['computer'=>'1','injected'=>'x'])));
test('ticket variables substitution',function () {$d=workflow();same('Nouveau workflow: Oui',Engine::substitute('{{workflow}}: {{computer}}',$d,['computer'=>true]));});
test('unknown ticket variable rejected',function () {$d=workflow();$d['ticket_title']='{{missing}}';rejects(fn()=>Definition::validate($d));});
test('version data remains immutable after draft edit',function () {$published=workflow();$snapshot=json_encode($published);$draft=$published;$draft['steps'][]=['id'=>'new','title'=>'New'];same(4,count(Definition::decode($snapshot)['steps']));});
test('zero mandatory steps yields completed',function () {$d=workflow();foreach($d['steps'] as &$s)$s['required']=false;unset($s);same(100,Engine::progress($d,Engine::states($d,['computer'=>false]))['percent']);});
foreach (glob(dirname(__DIR__).'/examples/*.json') as $file) {
    test('example ' . basename($file) . ' roundtrip',function () use($file) {$d=Definition::decode(file_get_contents($file));same($d,Definition::decode(json_encode($d)));});
}
test('example computer process completes generically',function () {
    $d=Definition::decode(file_get_contents(dirname(__DIR__).'/examples/preparation-ordinateur.json'));
    $form=Fields::formValues($d['fields'],['demandeur'=>12,'service'=>'IT','date_prevue'=>'2026-11-04','logiciels'=>'Bureautique','installer_os'=>'0','acces_specifiques'=>'0']);
    $values=['selectionner'=>['ordinateur'=>42,'etat'=>'Bon'],'maj'=>['ok'=>'1'],'logiciels'=>['liste'=>'Bureautique'],'securite'=>['chiffrement'=>'1','protection'=>'1'],'tester'=>['resultat'=>'Conforme','ok'=>'1'],'associer'=>['utilisateur'=>12,'ok'=>'1'],'finaliser'=>['compte_rendu'=>'Livré']];
    $states=[];
    foreach($d['steps'] as $step){$states=Engine::states($d,$form,$states);if($states[$step['id']]==='na')continue;$states=Engine::transition($d,$form,$states,$step['id'],'complete',$values[$step['id']])['states'];}
    same('completed',Engine::progress($d,$states)['status']);same('na',$states['installer']);same('na',$states['acces']);
});
test('hidden fields stay hidden in read-only rendering',function () {
    $f=Fields::definitions([['id'=>'hidden','label'=>'Champ masqué','type'=>'text','condition'=>['field'=>'form.flag','op'=>'true']]],['form.flag']);
    ob_start();\WorkflowBuilder\FormView::show($f,['hidden'=>'ignored'],true,null,['form.flag'=>false]);$html=ob_get_clean();
    same(false,str_contains($html,'Champ masqué'));
});
test('releasing progress makes the step available again',function () {
    $r=Engine::transition(workflow(),['computer'=>true],['a'=>'progress'],'a','release',[]);
    same('available',$r['state']);same(0,$r['progress']['percent']);
});
test('releasing a blocked step preserves the blockage',function () {
    $r=Engine::transition(workflow(),['computer'=>true],['a'=>'blocked'],'a','release',[]);
    same('blocked',$r['state']);
});
test('a completed step cannot be released',fn()=>rejects(fn()=>Engine::transition(workflow(),['computer'=>true],['a'=>'done'],'a','release',[])));
echo "\n$passed passed; $failed failed\n";
exit($failed ? 1 : 0);
