<?php

namespace Wss\Telegram\Telegram\Methods;

use CIBlockElement;
use CUser;
use Wss\Telegram\Telegram\Methods as Methods;

class Crm{

    public function prepare($get_params){
        $IBLOCK_ID = CIBlockElement::GetIBlockByID($get_params["event_id"]);
        $res = CIBlockElement::GetProperty($IBLOCK_ID, $get_params["event_id"], array("sort" => "asc"), Array("CODE"=>"event_type"));
        while ($ob = $res->GetNext()) {
            $get_params["event_type"] = $ob['VALUE_ENUM'];
        }
        $res = CIBlockElement::GetProperty($IBLOCK_ID, $get_params["event_id"], array("sort" => "asc"), Array("CODE"=>"result"));
        while ($ob = $res->GetNext()) {
            $get_params["result"] = $ob['VALUE_ENUM'];
        }
        $res = CIBlockElement::GetProperty($IBLOCK_ID, $get_params["event_id"], "sort", "asc", array("CODE" => "user"));
        if ($ob = $res->GetNext())
        {
            $get_params["user"] = $ob["VALUE"]; //форматнуть из айди в название
            $get_params["user"] = CUser::GetByID($get_params["user"])->fetch()["NAME"]." ". CUser::GetByID($get_params["user"])->fetch()["LAST_NAME"];
        }
        $res = CIBlockElement::GetProperty($IBLOCK_ID, $get_params["event_id"], "sort", "asc", array("CODE" => "check_count"));
        if ($ob = $res->GetNext())
        {
            $get_params["check_count"] = $ob['VALUE'];
        }
        $res = CIBlockElement::GetProperty($IBLOCK_ID, $get_params["event_id"], "sort", "asc", array("CODE" => "comment"));
        if ($ob = $res->GetNext())
        {
            $get_params["comment"] = $ob['VALUE'];
        }
        $res = CIBlockElement::GetProperty($IBLOCK_ID, $get_params["event_id"], "sort", "asc", array("CODE" => "department"));
        if ($ob = $res->GetNext())
        {
            $get_params["department"] = $ob['VALUE']; //форматнуть из айди в название
            $obElement = CIBlockElement::GetByID($get_params["department"]);
            if($arEl = $obElement->GetNext())
                $get_params["department"] = $arEl["NAME"];

        }
        $res = CIBlockElement::GetProperty($IBLOCK_ID, $get_params["event_id"], "sort", "asc", array("CODE" => "date_time"));
        if ($ob = $res->GetNext())
        {
            $get_params["time"] = $ob['VALUE'];
        }
        $get_params["time"] = explode(" ", $get_params["time"]);
        $response["text"] = "Не внесено в CRM-систему" . "\n";
        $response["text"] = $response["text"] . "\n" . $get_params["time"][0];
        $response["text"] = $response["text"] . "\n" . $get_params["time"][1];
        $response["text"] = $response["text"] . "\n" . "\n" . "Салон:" . " ". $get_params["department"];
        $response["text"] = $response["text"] . "\n" . "Менеджер:" . " ". $get_params["user"];
        $response["text"] = $response["text"] . "\n" . "Вид трафика:" . " ". $get_params["event_type"];//список
        $response["text"] = $response["text"] . "\n" . "Результат:" . " ". $get_params["result"]; //список
        $response["text"] = $response["text"] . "\n" . "\n" . "Проверка:" . " ". $get_params["check_count"]."-ая проверка"; //строка
        $response["text"] = $response["text"] . "\n" . "Комментарий:" . " ". $get_params["comment"];//строка

        $first = Methods\SendMessage::SendMessage($response["text"]);
        //return $first;
        return $response["text"];
    }
}