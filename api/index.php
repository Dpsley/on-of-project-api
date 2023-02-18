<?php

header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token, Authorization, Accept,charset,boundary,Content-Length');
header('Access-Control-Allow-Origin: *');

const NO_KEEP_STATISTIC = true;

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
require_once __DIR__ . '/include_classes.php';


//print_r($namespace);
//http_response_code(404);
//global $USER;
if ($_REQUEST['manager'] && !$_REQUEST['user']) $_REQUEST['user'] = $_REQUEST['manager']; // фикс бага, когда вместо user передают manager

$_SERVER["REQUEST_URI"] = str_replace("/v2/", "/", $_SERVER["REQUEST_URI"]);

if(!strpos($_SERVER["REQUEST_URI"], "?")){
    $Class = explode('/', $_SERVER["REQUEST_URI"])[2];
    $Method = explode('/', $_SERVER["REQUEST_URI"])[3];
    $Route_Info = Routes::parser($Class, $Method);
    $Class = mb_strtolower('Wss\\' . $Route_Info["class"]);
    $Method = $Route_Info["function"];
    /*return print_r([
        class_exists($Class),
        method_exists($Class, $Method),
        $Route_Info
    ]);*/
    return print json_encode($Class::$Method($_REQUEST), JSON_THROW_ON_ERROR);
}else{
if ($_REQUEST['action']) {
    $namespace = explode('/', $_SERVER["REQUEST_URI"])[2];
    $method = $_REQUEST['action'];
    $Route_Info = Routes::parser($namespace, $method);
    $Method = $Route_Info["function"];
    $Class = mb_strtolower('Wss\\' . $Route_Info["class"]);
    //print_r($Route_Info);
    if (class_exists($Class) && method_exists($Class, $Method)) {
        if ($Route_Info["method"] == "GET") {
            return print json_encode($Class::$Method($_REQUEST), JSON_THROW_ON_ERROR);
        } elseif ($Route_Info["method"] == "POST") {
            return print json_encode($Class::$Method(json_decode(file_get_contents("php://input"), true)), JSON_THROW_ON_ERROR);
        } else {
            http_response_code(404);
        }
    } else {
        http_response_code(404);
    }
} else {
    $args = explode('/', $_SERVER['REQUEST_URI']);
    $namespace = $args[2];
    if (strpos($args[3], "?") == 0) {
        $Method = (explode('&', $args[3])[0]);
        $Method = str_replace("?", "", $Method);
        $Method = str_replace("=", "", $Method);
    } else {
        $Method = explode('?', $args[3])[0];
    }
    //print_r($_SERVER["REQUEST_URI"]);
    $Route_Info = Routes::parser($namespace, $Method);
    //print_r ($Route_Info);
    $Method = $Route_Info["function"];
    $Class = ('Wss\\' . $Route_Info["class"]);
    //print json_encode($Class::$Method($_REQUEST), JSON_THROW_ON_ERROR);
    //print_r($Route_Info["class"]);
    if (class_exists($Class) && method_exists($Class, $Method)) {
        if ($Route_Info["method"] == "GET") {
            return print json_encode($Class::$Method($_REQUEST), JSON_THROW_ON_ERROR);
        } elseif ($Route_Info["method"] == "POST") {
            return print json_encode($Class::$Method(json_decode(file_get_contents("php://input"), true)), JSON_THROW_ON_ERROR);
        } else {
            http_response_code(404);
        }
    } else {
        //print_r ($Method);
        http_response_code(404);
    }
}
}
