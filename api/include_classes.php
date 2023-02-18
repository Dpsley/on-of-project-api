<?php
//require_once __DIR__.'/classes/wss/User.php';
load(glob( __DIR__.'/classes/wss'. '/*.php'));
require_once __DIR__.'/classes/wss/Telegram/Telegram.php';
load(glob(__DIR__."/classes/wss/Graphs" . '/*.php'));
load(glob(__DIR__."/classes/Crest" . '/*.php'));
load(glob(__DIR__."/classes/wss/Telegram/Methods" . '/*.php'));
load(glob(__DIR__."/routes/Routes.php"));

function load($files)
{
    foreach ($files as $file) {
        require_once($file);
    }
}


