<?php
declare(strict_types=1);
namespace Glpi\Plugin {
    class Hooks {
        public const MENU_TOADD='menu_toadd', ADD_CSS='add_css', ADD_JAVASCRIPT='add_javascript',
            CHANGE_PROFILE='change_profile', POST_PREPAREUPDATE='post_prepareupdate';
    }
}
namespace {
require __DIR__.'/support/FakeGlpi.php';
require dirname(__DIR__).'/bootstrap.php';
function __($text,$domain=''){return $text;}
class Plugin {
    public static array $classes=[];
    public static function registerClass($class,$attributes=[]):void{self::$classes[$class]=$attributes;}
}
class ProfileRight {public static function deleteProfileRights($rights):void{}}
class Config {public static function deleteConfigurationValues($context,$names):void{}}
require dirname(__DIR__).'/setup.php';
use WorkflowBuilder\{Access,Definition,Knowledge,Schema,Store,Templates,ValidationException};
$CFG_GLPI=['root_doc'=>'/glpi'];$passed=$failed=0;
function eq($expected,$actual):void{if($expected!==$actual)throw new RuntimeException(json_encode([$expected,$actual]));}
function denied(callable $run):string {
    try{$run();}catch(ValidationException $e){return $e->getMessage();}throw new RuntimeException('Expected refusal');
}
function check(string $name,callable $run):void {
    global $DB,$passed,$failed;$DB=new FakeDb();Session::$user=7;
    Session::$rights=array_fill_keys(array_keys(Access::RIGHTS),READ|UPDATE|CREATE);
    Session::$rights['knowbase']=READ|UPDATE;$_SESSION=['glpigroups'=>[12]];
    KnowbaseItem_Item::$deniedArticles=[];KnowbaseItem_Item::$failOnArticle=0;
    try{$run();$passed++;echo "PASS $name\n";}catch(Throwable $e){$failed++;echo "FAIL $name: {$e->getMessage()}\n";}
}
function definition(array $articles=[3,4]):array {
    $d=Definition::empty();$d['name']='Modèle <RH>';
    $d['steps']=[['id'=>'first','title'=>'Préparer','knowbaseitems_id'=>$articles],
        ['id'=>'second','title'=>'Vérifier','knowbaseitems_id'=>$articles]];
    return $d;
}
function draft(array $articles=[3,4]):int{return Templates::save(0,definition($articles),0,false,0);}
function links():array {global $DB;return array_values($DB->tables['glpi_knowbaseitems_items']??[]);}
check('native model item is registered as a knowledge association type',function(){
    plugin_init_workflowbuilder();eq(['kb_types'=>true],Plugin::$classes[PluginWorkflowbuilderTemplate::class]);
    eq(true,is_subclass_of(PluginWorkflowbuilderTemplate::class,CommonDBTM::class));
});
check('draft selection creates no knowledge link',function(){draft();eq([],links());});
check('publication links each distinct procedure to its model once',function(){
    $id=draft();Templates::publish($id,1);eq(2,count(links()));
    eq([3,4],array_column(links(),'knowbaseitems_id'));
    eq([$id,$id],array_column(links(),'items_id'));eq(['PluginWorkflowbuilderTemplate','PluginWorkflowbuilderTemplate'],array_column(links(),'itemtype'));
});
check('republication does not duplicate native associations',function(){
    $id=draft();Templates::publish($id,1);$before=links();Templates::publish($id,2);eq($before,links());
    eq(2,count(Store::rows('versions',['templates_id'=>$id])));
});
check('different models remain separate linked items on the same procedure',function(){
    $one=draft([3]);$two=draft([3]);Templates::publish($one,1);Templates::publish($two,1);
    eq([$one,$two],array_column(links(),'items_id'));eq([3,3],array_column(links(),'knowbaseitems_id'));
});
check('new publication adds procedures and preserves links used by old versions',function(){
    $id=draft([3]);Templates::publish($id,1);$previous=Store::one('templates',$id)['active_versions_id'];
    Templates::save($id,definition([4]),0,false,2);Templates::publish($id,3);
    eq([3,4],array_column(links(),'knowbaseitems_id'));
    eq([3],Templates::version((int)$previous)[1]['steps'][0]['knowbaseitems_id']);
    Templates::state($id,'archived',4);eq(2,count(links()));
});
check('native association rights are required and failed publication is fully rolled back',function(){
    global $DB;$id=draft();$before=$DB->tables;Session::$rights['knowbase']=READ;
    $message=denied(fn()=>Templates::publish($id,1));eq(true,str_contains($message,'modifier cette fiche'));
    eq($before,$DB->tables);eq('draft',Store::one('templates',$id)['state']);
});
check('an unwritable second procedure rolls back the first link and published version',function(){
    global $DB;$id=draft();$before=$DB->tables;KnowbaseItem_Item::$deniedArticles=[4];
    denied(fn()=>Templates::publish($id,1));eq($before,$DB->tables);eq([],links());
});
check('native write failure rolls back all projections and is safely retryable',function(){
    global $DB;$id=draft();$before=$DB->tables;KnowbaseItem_Item::$failOnArticle=4;
    $message=denied(fn()=>Templates::publish($id,1));eq(true,str_contains($message,'publication a été annulée'));
    eq($before,$DB->tables);KnowbaseItem_Item::$failOnArticle=0;Templates::publish($id,1);
    eq(2,count(links()));eq(1,Store::rows('versions',['templates_id'=>$id])[0]['number']);
});
check('existing relations need no extra write permission on republication',function(){
    $id=draft();Templates::publish($id,1);Session::$rights['knowbase']=READ;Templates::publish($id,2);eq(2,count(links()));
});
check('unreadable procedure or stale draft cannot publish or create partial links',function(){
    global $DB;$id=draft([3,404]);$before=$DB->tables;denied(fn()=>Templates::publish($id,1));eq($before,$DB->tables);
    $id=draft();$before=$DB->tables;denied(fn()=>Templates::publish($id,0));eq($before,$DB->tables);
});
check('publish and model manager permissions cannot be bypassed',function(){
    global $DB;$id=draft();$before=$DB->tables;Session::$rights['plugin_workflowbuilder_publish']=0;
    denied(fn()=>Templates::publish($id,1));eq($before,$DB->tables);
    Session::$rights['plugin_workflowbuilder_publish']=UPDATE;$d=definition();$d['manage_users']=[8];
    $id=Templates::save(0,$d,0,false,0);$before=$DB->tables;Session::$rights['plugin_workflowbuilder_admin']=0;
    denied(fn()=>Templates::publish($id,1));eq($before,$DB->tables);
});
check('models without procedures still publish without knowledge write rights',function(){
    Session::$rights['knowbase']=0;$id=draft([]);Templates::publish($id,1);eq('active',Store::one('templates',$id)['state']);eq([],links());
});
check('knowledge article content and other native relations remain untouched',function(){
    global $DB;$DB->tables['glpi_knowbaseitems']=[3=>['id'=>3,'name'=>'Procédure','answer'=>'Contenu original']];
    $DB->insert('glpi_knowbaseitems_items',['knowbaseitems_id'=>3,'itemtype'=>'Ticket','items_id'=>55]);
    $articles=$DB->tables['glpi_knowbaseitems'];$unrelated=links()[0];$id=draft([3]);Templates::publish($id,1);
    eq($articles,$DB->tables['glpi_knowbaseitems']);eq($unrelated,links()[0]);eq(2,count(links()));
});
check('native model item exposes its real table name and editor URL',function(){
    $id=draft();$item=new PluginWorkflowbuilderTemplate();eq(true,$item->can($id,READ));
    eq(Store::table('templates'),$item::getTable());eq('Modèle <RH>',$item->getName());
    eq('/glpi/plugins/workflowbuilder/front/template.form.php?id='.$id,$item->getLinkURL());
    eq('/plugins/workflowbuilder/front/template.form.php?id='.$id,$item::getFormURLWithID($id,false));
    eq('/glpi/plugins/workflowbuilder/front/index.php',$item::getSearchURL());
    eq(false,$item::canCreate());eq(false,$item::canUpdate());eq(false,$item::canDelete());eq(false,$item::canPurge());
});
check('native item read access follows model rights and entity restrictions',function(){
    $id=draft();$item=new PluginWorkflowbuilderTemplate();Session::$rights['plugin_workflowbuilder_template']=0;eq(false,$item->can($id,READ));
    Session::$rights['plugin_workflowbuilder_template']=READ;Store::update('templates',$id,['entities_id'=>1]);eq(false,$item->can($id,READ));
    eq(false,$item->can(9999,READ));
});
check('uninstall removes only plugin-model relations and keeps articles and other links',function(){
    global $DB;$id=draft([3]);Templates::publish($id,1);
    $DB->tables['glpi_knowbaseitems']=[3=>['id'=>3,'answer'=>'Conserver']];
    $DB->insert('glpi_knowbaseitems_items',['knowbaseitems_id'=>3,'itemtype'=>'Ticket','items_id'=>55]);
    $DB->insert('glpi_knowbaseitems_items',['knowbaseitems_id'=>3,'itemtype'=>'PluginOtherThing','items_id'=>7]);
    Schema::uninstall();eq(['Ticket','PluginOtherThing'],array_column(links(),'itemtype'));
    eq('Conserver',$DB->tables['glpi_knowbaseitems'][3]['answer']);
});
echo "\n$passed knowledge link tests passed; $failed failed (GLPI and database test doubles)\n";exit($failed?1:0);
}
