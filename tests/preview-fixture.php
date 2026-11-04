<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
use WorkflowBuilder\{Definition, Fields, Engine, FormView};
header('Content-Type: application/json');
try {
    $d=Definition::decode($_POST['definition']);
    $form=Fields::formValues($d['fields'],json_decode($_POST['input'],true),false);
    $states=Engine::states($d,$form);
    ob_start();FormView::show($d['fields'],$form);$html=ob_get_clean();
    $steps=[];foreach($d['steps'] as $s)$steps[]=['title'=>$s['title'],'state'=>$states[$s['id']],'required'=>$s['required']];
    echo json_encode(['html'=>$html,'steps'=>$steps,'progress'=>Engine::progress($d,$states)]);
} catch(Throwable $e) {http_response_code(422);echo json_encode(['error'=>$e->getMessage()]);}
