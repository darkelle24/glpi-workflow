<?php
declare(strict_types=1);
/** Render against the CommonMark library shipped by an installed GLPI 12. */
$core=realpath($argv[1]??'');
if(!$core||!is_file($core.'/vendor/autoload.php')){fwrite(STDERR,"Usage: php tests/guide.php /path/to/installed/glpi\n");exit(2);}
require $core.'/vendor/autoload.php';
require dirname(__DIR__).'/bootstrap.php';
use WorkflowBuilder\Guide;
$passed=$failed=0;
function guideTest(string $name,callable $run):void{global $passed,$failed;try{$run();$passed++;echo "PASS $name\n";}catch(Throwable $e){$failed++;echo "FAIL $name: {$e->getMessage()}\n";}}
function guideAssert(bool $result,string $message='Unexpected guide output'):void{if(!$result)throw new RuntimeException($message);}
function guideDom(string $html):DOMDocument{$d=new DOMDocument();$old=libxml_use_internal_errors(true);$d->loadHTML('<html><head><meta charset="UTF-8"></head><body>'.$html.'</body></html>');libxml_clear_errors();libxml_use_internal_errors($old);return $d;}
guideTest('raw download source is the single repository Markdown',fn()=>guideAssert(Guide::source()===file_get_contents(dirname(__DIR__).'/docs/user-guide.md')));
guideTest('French accented heading links work and duplicates remain unique',function(){
    $dom=guideDom(Guide::render("## Prendre en charge une étape\n\n## Prendre en charge une étape\n"));
    guideAssert($dom->getElementsByTagName('h2')->item(0)->getAttribute('id')==='prendre-en-charge-une-étape');
    guideAssert($dom->getElementsByTagName('h2')->item(1)->getAttribute('id')==='prendre-en-charge-une-étape-1');
});
guideTest('GitHub style tables and lists render',function(){
    $html=Guide::render("| Action | Effet |\n| --- | --- |\n| Prendre | En cours |\n\n- Premier\n- Second\n");
    guideAssert(str_contains($html,'table-responsive')&&str_contains($html,'<th>Action</th>')&&str_contains($html,'<li>Premier</li>'));
});
guideTest('relative screenshots resolve under a GLPI subdirectory',function(){
    $dom=guideDom(Guide::render('![Écran](../public/images/guide/01-workflows.png)','/glpi'));
    guideAssert($dom->getElementsByTagName('img')->item(0)->getAttribute('src')==='/glpi/plugins/workflowbuilder/images/guide/01-workflows.png');
});
guideTest('HTML and event handler injection cannot execute',function(){
    $html=Guide::render('<script>alert(1)</script>'."\n\n".'<img src=x onerror=alert(1)><iframe src="https://example.org"></iframe>');
    guideAssert(!str_contains($html,'<script')&&!str_contains($html,'<iframe')&&!str_contains($html,'onerror='));
});
guideTest('script data file and protocol-relative links are rejected',function(){
    $html=Guide::render("[a](javascript:alert%281%29)\n\n[b](data:text/html,test)\n\n[c](file:///etc/passwd)\n\n[d](//example.org/track)");
    guideAssert(!preg_match('~(?:href|src)="(?:javascript:|data:|file:|//)~i',$html));
});
guideTest('remote inline and traversing images are not loaded',function(){
    foreach(['https://example.org/track.png','data:image/png;base64,AA','../public/images/guide/../../config.png'] as $src){
        $html=Guide::render('![Image non autorisée]('.$src.')');
        guideAssert(!str_contains($html,'<img'));
    }
});
guideTest('external documentation links use a safe relationship',function(){
    $d=guideDom(Guide::render('[GLPI](https://help.glpi-project.org)'));
    guideAssert($d->getElementsByTagName('a')->item(0)->getAttribute('rel')==='noopener noreferrer');
});
$source=Guide::source();$rendered=Guide::render($source,'/test-glpi');$dom=guideDom($rendered);
guideTest('every table of contents link targets an existing heading',function()use($dom){
    $ids=[];foreach((new DOMXPath($dom))->query('//*[@id]') as $heading)$ids[]=$heading->getAttribute('id');
    foreach($dom->getElementsByTagName('a') as $link){
        $href=$link->getAttribute('href');if(str_starts_with($href,'#'))guideAssert(in_array(rawurldecode(substr($href,1)),$ids,true),'Broken anchor: '.$href);
    }
});
guideTest('all ten illustrated screens exist as genuine PNG files',function()use($source,$dom){
    preg_match_all('~!\[[^\]]*\]\((\.\./public/images/guide/[a-z0-9-]+\.png)\)~u',$source,$matches);
    guideAssert(count($matches[1])===10,'Expected ten screenshots');
    foreach($matches[1] as $file){
        $path=dirname(__DIR__).'/docs/'.$file;$info=is_file($path)?getimagesize($path):false;
        guideAssert($info&&$info[2]===IMAGETYPE_PNG&&$info[0]>=600,'Missing or invalid screenshot: '.$file);
    }
    guideAssert($dom->getElementsByTagName('img')->length===10);
});
guideTest('PDF tutorial has its own unchanged Markdown source and eight valid illustrations',function(){
    $source=Guide::source('pdf');guideAssert($source===file_get_contents(dirname(__DIR__).'/docs/pdf-guide.md'));
    preg_match_all('~!\[[^\]]*\]\((\.\./public/images/guide/[a-z0-9-]+\.png)\)~u',$source,$m);
    guideAssert(count($m[1])===8,'Expected eight PDF tutorial illustrations');
    foreach($m[1]as$file){$info=getimagesize(dirname(__DIR__).'/docs/'.$file);guideAssert($info&&$info[2]===IMAGETYPE_PNG&&$info[0]>=600,'Invalid PDF tutorial image: '.$file);}
    $dom=guideDom(Guide::render($source,'/test-glpi'));$ids=[];foreach((new DOMXPath($dom))->query('//*[@id]')as$node)$ids[]=$node->getAttribute('id');
    foreach($dom->getElementsByTagName('a')as$link){$href=$link->getAttribute('href');if(str_starts_with($href,'#'))guideAssert(in_array(rawurldecode(substr($href,1)),$ids,true),'Broken PDF tutorial anchor: '.$href);}
});
guideTest('known guide example and background links work below a GLPI subdirectory',function(){
    $html=Guide::render('[PDF](pdf-guide.md#choisir-la-bonne-méthode) [User](user-guide.md) [JSON](../examples/fiche-connexion-salarie.json) [Fond](../public/images/guide/pdf-demo-background.png)','/glpi');
    guideAssert(str_contains(rawurldecode($html),'/glpi/plugins/workflowbuilder/front/guide.php?guide=pdf#choisir-la-bonne-méthode'));
    guideAssert(str_contains($html,'/glpi/plugins/workflowbuilder/front/guide.php?guide=user'));
    guideAssert(str_contains($html,'/glpi/plugins/workflowbuilder/front/example.php?name=fiche-connexion-salarie'));
    guideAssert(str_contains($html,'/glpi/plugins/workflowbuilder/images/guide/pdf-demo-background.png'));
});
guideTest('unknown guides examples and relative traversal links are rejected',function(){
    foreach(['../config','pdf-guide.md','/etc/passwd']as$key){try{Guide::source($key);throw new RuntimeException('Arbitrary guide accepted');}catch(\WorkflowBuilder\ValidationException){}}
    foreach(['../AGENTS','creation-utilisateur','fiche-connexion-salarie.json']as$key){try{Guide::example($key);throw new RuntimeException('Arbitrary example accepted');}catch(\WorkflowBuilder\ValidationException){}}
    $dom=guideDom(Guide::render('[a](../config/config_db.php) [b](../examples/../../AGENTS.md)'));
    foreach($dom->getElementsByTagName('a')as$a)guideAssert(!$a->hasAttribute('href'));
});
guideTest('both downloadable examples match the repository and use different PDF modes',function(){
    foreach(['fiche-connexion-automatique'=>'automatic','fiche-connexion-salarie'=>'free']as$name=>$mode){$json=Guide::example($name);guideAssert($json===file_get_contents(dirname(__DIR__).'/examples/'.$name.'.json'));$d=\WorkflowBuilder\Definition::decode($json);guideAssert($d['pdf']['mode']===$mode);if($mode==='free')guideAssert(str_starts_with($d['pdf']['pages'][0]['background'],'data:image/png;base64,'));}
});
echo "\n$passed guide checks passed; $failed failed\n";exit($failed?1:0);
