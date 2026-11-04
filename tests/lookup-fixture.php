<?php
header('Content-Type: application/json');
echo json_encode(['items'=>[['id'=>7,'label'=>'Élément de test']], 'next'=>0]);
