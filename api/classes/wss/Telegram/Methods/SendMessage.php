<?php

namespace Wss\Telegram\Telegram\Methods;

class SendMessage{

    public function SendMessage($text, $chat_id = false){
        $config = \Wss\Telegram\Telegram::getconfig($config);
        if($chat_id != false) {
            $config["chat_id"] = $chat_id;
        }else {
            $config["chat_id"] = $config["chat_id"];
        }
        $response = array(
            'chat_id' => $config["chat_id"],
            'text' => $text
        );
        $ch = curl_init('https://api.telegram.org/bot'.$config["token"].'/sendMessage');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $res = curl_exec($ch);
        curl_close($ch);
        $res = json_decode($res, true);
        $answer["first"] = $response["text"];
        $answer["add"] = $res;
        return $answer;

    }
}