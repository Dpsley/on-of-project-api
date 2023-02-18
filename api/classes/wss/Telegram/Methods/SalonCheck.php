<?php

namespace Wss\Telegram\Telegram\Methods;

use Bitrix\Main\Mail\Event;
use CIBlock;
use CIBlockElement;
use Wss\Telegram\Telegram\Methods\ForHelp as ForHelp;
use Wss\Telegram\Telegram\Methods as Methods;

class SalonCheck{

    static public function getParams($params)
    {
        if (isset($params["email"]) && isset($params["date"]) && isset($params["salon"]) && isset($params["time"]) && isset($params["user"])) {
            $params["email"] = strtolower($params["email"]);
            $UserID = ForHelp::getUserID($params["email"]);
            $elementCheck = ForHelp::getElement($UserID);
            $res = CIBlock::GetList(
                array(),
                array(
                    'TYPE' => 'companies',
                    'ACTIVE' => 'Y',
                    "CNT_ACTIVE" => "Y",
                    "NAME" => "mebel-manufacturing_$elementCheck"
                ), true
            );
            $event_iblock_id = "";
            while ($ar_res = $res->Fetch()) {
                $event_iblock_id = $ar_res['ID'];
            }

            $result = self::constructur($event_iblock_id, $params["date"], $params["salon"]);
            $salonName = ForHelp::getSalonName($params["salon"]);
            $counter = self::counter($result, $salonName, $params["date"]);
            if ($UserID) {
                $params["text"] = $counter;
                if (isset($params)) {
                    //return $params["text"];
                    $html = self::prepare($params);
                    //return $html;
                    //$html = Methods\SendMessage::SendMessage($params["text"]);
                    if ($html["add"]["ok"]) {
                        $response = ["message" => "Сообщение отправлено", "status" => "success", "text" => $html["first"]];
                        return $response;
                    } else {
                        $adminsprobe = ForHelp::getAdminsInGroups();
                        $admins = $adminsprobe[$elementCheck]["VALUE"];
                        foreach ($admins as $key => $id) {
                            $mail[$key] = ForHelp::getMailUserById($id);
                        }
                        // D7
                        foreach ($mail as $mailAd) {
                            Event::send(array(
                                "EVENT_NAME" => "MESS",
                                "LID" => "s1",
                                "C_FIELDS" => array(
                                    "EMAIL" => $mailAd,
                                    "TEXT" => $html["first"],
                                ),
                            ));
                        }
                        return ["message" => "Сообщение отправлено администраторам", "status" => "success"];
                    }
                } else {
                    return ["message" => "Не заданы параметры", "status" => "error"];
                }
            } else {
                return ["message" => "Пользователя с таким логином или почтовым адресом не найдено", "status" => "error"];
            }
        } else {
            return ["message" => "Не указан email, салон, время проверки, ФИО контроллера или дата", "status" => "error"];
        }

    }

    public static function constructur($iblock_id, $date, $salon)
    {
        $results = [];
        $arFilter = array(
            "IBLOCK_ID" => IntVal($iblock_id),
            "PROPERTY_department" => $salon,
            ">=PROPERTY_date_time" => date('Y-m-d H:i:s', strtotime($date)),
            "<=PROPERTY_date_time" => date('Y-m-d H:i:s', strtotime($date) + 86400),
            "ACTIVE" => "Y",
        );
        $res = CIBlockElement::GetList(array("SORT" => "ASC", "PROPERTY_DATE_TIME_VALUE" => "ASC"), $arFilter, array("PROPERTY_date_time", "PROPERTY_event_type", "PROPERTY_result", "ID"));
        $i = 0;
        while ($ar_fields = $res->GetNext()) {
            $results[$i] = $ar_fields;
            $i++;
        }
        return $results;
    }

    public function counter($massive, $salon, $date)
    {
        $result = [
            "Салон" => $salon,
            "Дата" => $date,
            "Упущенных" => [
                "Отказ от контакта (менеджер)" => 0,
                "Отказ от контакта (клиент)" => 0,
                "Провел презентацию" => 0
            ],
            "Первичный" => [
                "Взял контакт (моб.тел)" => 0,
                "Закрыл на КЭВ (замер)" => 0,
                "Отрисовал дизайн-проект" => 0,
                "Заключил договор" => 0,
            ],
            "Вторичный" => [
                "Взял контакт (моб.тел)" => 0,
                "Закрыл на КЭВ (замер)" => 0,
                "Отрисовал дизайн-проект" => 0,
                "Заключил договор" => 0,
            ],
            "Нецелевой" => [
                "" => 0
            ]
        ];
        foreach ($massive as $key => $value) {
            if ($value["PROPERTY_RESULT_VALUE"] == "Отказ от контакта (менеджер)") {
                $result["Упущенных"]["Отказ от контакта (менеджер)"] = $result["Упущенных"]["Отказ от контакта (менеджер)"] + 1;
            } elseif ($value["PROPERTY_RESULT_VALUE"] == "Отказ от контакта (клиент)") {
                $result["Упущенных"]["Отказ от контакта (клиент)"] = $result["Упущенных"]["Отказ от контакта (клиент)"] + 1;
            } elseif ($value["PROPERTY_RESULT_VALUE"] == "Провел презентацию") {
                $result["Упущенных"]["Провел презентацию"] = $result["Упущенных"]["Провел презентацию"] + 1;
            } else {
                $result[$value["PROPERTY_EVENT_TYPE_VALUE"]][$value["PROPERTY_RESULT_VALUE"]] = $result[$value["PROPERTY_EVENT_TYPE_VALUE"]][$value["PROPERTY_RESULT_VALUE"]] + 1;
            }
        }
        return $result;
    }

    public function prepare($get_params){

        $response["text"] = $get_params["text"]["Салон"] . "," . " " . $get_params["text"]["Дата"] . "\n";
        $response["text"] = $response["text"] . "Первичный" . ":" . " " . (array_sum($get_params["text"]["Упущенных"]) + array_sum($get_params["text"]["Первичный"])) . "\n" . " " . "\n";
        $response["text"] = $response["text"] . "Из них Упущенных" . " -" . " " . array_sum($get_params["text"]["Упущенных"]) . "\n";
        foreach ($get_params["text"]["Упущенных"] as $key => $value) {
            $response["text"] = $response["text"] . $key . ":" . " " . $value . "\n";
        }
        $response["text"] = $response["text"] . "\n" . "Результативных" . " -" . " " . array_sum($get_params["text"]["Первичный"]) . "\n";
        foreach ($get_params["text"]["Первичный"] as $key => $value) {
            $response["text"] = $response["text"] . $key . ":" . " " . $value . "\n";
        }
        $response["text"] = $response["text"] . "\n" . "Вторичный" . ":" . " " . array_sum($get_params["text"]["Вторичный"]) . "\n";
        foreach ($get_params["text"]["Вторичный"] as $key => $value) {
            $response["text"] = $response["text"] . $key . ":" . " " . $value . "\n";
        }
        $response["text"] = $response["text"] . "\n" . "Нецелевой" . ":" . " " . $get_params["text"]["Нецелевой"][0] . "\n";
        $response["text"] = $response["text"] . "\n" . "Контроллер" . ":" . " " . $get_params["user"];
        //$checker = YandexRequest::GetParams($get_params);
        $first = Methods\SendMessage::SendMessage($response["text"]);
        return $first;
    }
}