<?php
declare(strict_types=1);
/** Reproducible, fictional screenshots dataset; ONLY for a fresh, dedicated guide database. */
if (getenv('WORKFLOWBUILDER_ALLOW_GUIDE_DEMO') !== '1') { fwrite(STDERR, "Dedicated guide demo environment required.\n"); exit(2); }
$core=realpath($argv[1]??'');
if(!$core||!is_file($core.'/vendor/autoload.php')){exit(2);}
require $core.'/vendor/autoload.php';
$kernel=new \Glpi\Kernel\Kernel();$kernel->boot();
global $DB;
if(!preg_match('/^workflowbuilder_glpi12_docs_[a-f0-9]{8}$/D',$DB->dbdefault)){throw new RuntimeException('Refusing a non-demo database');}
$auth=new Auth();
if(!$auth->login('glpi','glpi',true)){throw new RuntimeException('Fresh test administrator required');}
use WorkflowBuilder\{Access,Definition,Instances,Store,Templates};
Access::reload();
if(Store::rows('templates')){throw new RuntimeException('The guide demo must start with no workflow models');}
$actor=(int)Session::getLoginUserID();
$user=new User();$user->update(['id'=>$actor,'firstname'=>'Technicien','realname'=>'Démonstration']);
$article=new KnowbaseItem();
$articleId=(int)$article->add(['name'=>'Préparer et sécuriser un ordinateur','answer'=>'<p>Procédure de démonstration : vérifier les mises à jour, les logiciels et la protection du poste.</p>','users_id'=>$actor,'_parents'=>[1]]);
if(!$articleId){throw new RuntimeException('Unable to create the demonstration article');}
function demoField(string $id,string $label,string $type='text',bool $required=true,array $extra=[]):array{return ['id'=>$id,'label'=>$label,'type'=>$type,'required'=>$required]+$extra;}
function demoStep(string $id,string $title,string $description,array $fields,array $depends=[]):array{return ['id'=>$id,'title'=>$title,'description'=>$description,'instructions'=>'Renseigner les contrôles effectués, puis enregistrer avant de valider cette étape.','required'=>true,'fields'=>$fields,'depends_on'=>$depends];}
$d=Definition::empty();
$d['name']='Préparation d’un ordinateur';
$d['description']='Préparer un poste, installer les logiciels, tester les accès et organiser sa remise.';
$d['category']='Matériel';
$d['ticket_title']='Préparation ordinateur — {{beneficiaire}}';
$d['fields']=[
    demoField('informations','Informations de la demande','section',false,['description'=>'Les informations suivantes permettent de préparer la demande.']),
    demoField('beneficiaire','Bénéficiaire','text',true,['description'=>'Nom de la personne qui recevra le poste.']),
    demoField('date_souhaitee','Date souhaitée','date'),
    demoField('reinstaller','Réinstaller le système','boolean',true,['description'=>'Oui affiche une étape d’installation supplémentaire.']),
    demoField('logiciels','Logiciels souhaités','multiselect',true,['choices'=>['Suite bureautique','Navigateur','MAGIS','UNI.T NG']]),
    demoField('commentaires','Commentaires complémentaires','textarea',false),
];
$d['steps']=[
    demoStep('verifier','Vérifier la demande','Vérifier le besoin, le bénéficiaire et la date souhaitée.',[demoField('verifie','Informations vérifiées','checkbox'),demoField('notes','Notes de préparation','textarea',false)]),
    demoStep('installer','Installer le système','Réinstaller le système uniquement lorsque la demande le prévoit.',[demoField('version','Version installée','select',true,['choices'=>['Windows 11','Linux','Autre']]),demoField('ok','Installation vérifiée','checkbox')],['verifier']),
    demoStep('logiciels','Préparer les logiciels et les accès','Installer les applications demandées et contrôler les accès.',[demoField('liste','Logiciels installés','textarea'),demoField('ok','Installation contrôlée','checkbox')],['verifier','installer']),
    demoStep('tester','Tester le poste','Vérifier la connexion, la messagerie et le fonctionnement des applications.',[demoField('adresse','Adresse utilisée pour le test','email'),demoField('connexion','Connexion réseau fonctionnelle','boolean'),demoField('conforme','Tests concluants','checkbox')],['logiciels']),
    demoStep('livrer','Finaliser la livraison','Mettre à jour le ticket et confirmer la remise du poste.',[demoField('date','Date de remise','date'),demoField('commentaire','Compte rendu','textarea')],['tester']),
];
$d['steps'][2]['knowbaseitems_id']=[$articleId];
$d['rules']=[['step'=>'installer','action'=>'show','when'=>['field'=>'form.reinstaller','op'=>'true','value'=>'']]];
$model=Templates::save(0,$d,0,false,0);Templates::publish($model,1);
foreach(['creation-utilisateur','depart-utilisateur'] as $example){
    $other=Definition::decode(file_get_contents(dirname(__DIR__).'/examples/'.$example.'.json'));
    Templates::save(0,$other,0,false,0);
}
$form=['beneficiaire'=>'Compte de démonstration','date_souhaitee'=>'2026-11-10','reinstaller'=>'0','logiciels'=>['Suite bureautique','Navigateur'],'commentaires'=>'Prévoir une remise au service administratif.'];
$instance=Instances::launch($model,0,0,$form,bin2hex(random_bytes(32)));
Instances::act($instance,'verifier','complete',['verifie'=>'1','notes'=>'Demande vérifiée avec le service.'],1);
Instances::act($instance,'logiciels','complete',['liste'=>'Suite bureautique et navigateur installés.','ok'=>'1'],2);
Instances::act($instance,'tester','start',[],3);
Instances::act($instance,'tester','save',['adresse'=>'poste.demo@example.org','connexion'=>'1','conforme'=>'0'],4);
$data=Instances::data($instance);
echo json_encode(['model'=>$model,'instance'=>$instance,'ticket'=>(int)$data['instance']['tickets_id'],'article'=>$articleId,'progress'=>$data['progress']['percent']],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
