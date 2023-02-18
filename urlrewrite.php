<?php
$arUrlRewrite=array (
  0 =>
  array (
    'CONDITION' => '#^/api/v2/(.*)#',
    'RULE' => 'action=$1',
    'ID' => NULL,
    'PATH' => '/api/v2/index.php',
    'SORT' => 100,
  ),
  1 =>
  array (
    'CONDITION' => '#^/rest/#',
    'RULE' => '',
    'ID' => NULL,
    'PATH' => '/bitrix/services/rest/index.php',
    'SORT' => 100,
  ),

);
