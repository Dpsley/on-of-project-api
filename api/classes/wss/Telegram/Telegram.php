<?php

namespace Wss\Telegram;

use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Mail\Event;
use CIBlock;
use CIBlockElement;
use CUser;
use Wss\Pay_Order;
use Wss\Telegram\Telegram\Methods\ForHelp as ForHelp;
use Wss\Telegram\Telegram\Methods as Methods;

class Telegram
{
    
    public function getconfig($request){
        $request["UserID"] = ForHelp::getUserID($_REQUEST["email"]) ?? CUser::GetID();
        $request["elementCheck"] = ForHelp::getElement($request["UserID"]);
        $request["chat_id"] = ForHelp::getChatId($request["elementCheck"]);
        $request["token"] = "5572236584:AAGfmx_TBHbT3gG3hQNbQAB6hS5Ox08IZOI";
        //$request["token"] = ForHelp::getTokenBot($request["elementCheck"]);
        return $request;
    }

    public function getstring($request){
        $user = ForHelp::getUserID($request["email"]);
        $company_id = ForHelp::getElement($user);
        //$company_id = $fields["FIELDS"]["ID"];
        $code = substr(md5(rand()), 0, 70);
        $fields["telegram_key"] = $code;
        CIBlockElement::SetPropertyValuesEx($company_id, 1, array("telegram_check_string" => "$code"));
        return $fields;
    }

    public function SalonCheck(){
        return Methods\SalonCheck::getParams($_REQUEST);
    }
    
    public function Crm(){
        return Methods\Crm::prepare($_REQUEST);
    }

    public function relation($text){
        $result = false;
        $check_string = explode( " ", $text["message"]["text"])[1];
        $telegram_chat_id = $text["message"]["chat"]["id"];
        $arFilter = array(
            "IBLOCK_ID" => 1,
            "PROPERTY_telegram_check_string" => $check_string,
        );
        \CModule::IncludeModule("iblock");
        $rs = \CIBlockElement::GetList(
            array(),
            $arFilter,
            false,
            false,
            array('ID')
        );
        while ($ob = $rs->GetNextElement()) {
            $result = $ob->GetFields()["ID"];
        }
        $snippet = "```\n" . "/start 0aa0a0a" . "\n```";
        if($result && explode( " ", $text["message"]["text"])[0] == "/start" && isset(explode( " ", $text["message"]["text"])[1])) {
            CIBlockElement::SetPropertyValuesEx($result, 1, array("tg_chat_id" => $telegram_chat_id));
            $response = Methods\SendMessage::SendMessage("Все прошло успешно, уведомления подключены", $telegram_chat_id);
        }elseif(explode(" ",$text["message"]["text"])[0] == "/start" && !isset(explode( " ", $text["message"]["text"])[1])){
            $response = Methods\SendMessage::SendMessage("Вам необходимо отправить боту сообщение с специальным кодом из личного кабинета, например.", $telegram_chat_id);
            $config = \Wss\Telegram\Telegram::getconfig($config);
            $response = array(
                'chat_id' => $telegram_chat_id,
                'text' => $snippet,
                'parse_mode' => "MarkdownV2"
            );
            $ch = curl_init('https://api.telegram.org/bot'.$config["token"].'/sendMessage');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            $res = curl_exec($ch);
            curl_close($ch);
        }elseif(explode( " ", $text["message"]["text"])[0] != "/start"){}else{
            $response = Methods\SendMessage::SendMessage("Проверьте что вы отправили верный код.", $telegram_chat_id);

        }
        Pay_Order::writeToLog($response);

    }
    
}