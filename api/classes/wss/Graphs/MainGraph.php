<?php

namespace Wss\Graphs;

use CIBlock;
use CIBlockElement;
use CIBlockPropertyEnum;
use CModule;
use CUser;
use DateTime;
use Wss\Helper;
use Wss\Telegram\Telegram\Methods as Methods;
use Wss\User;

class MainGraph
{
    public function BigGraph($params)
    {
        $UserID = Methods\ForHelp::getUserID($params["email"]);

        $elementCheck = Methods\ForHelp::getElement($UserID);
        Cmodule::IncludeModule('iblock');
        $rsEnum = CIBlock::GetList(array(), array("NAME" => "mebel-manufacturing_" . $elementCheck));
        $arEnum = $rsEnum->GetNext();
        $elementCheck = $arEnum["ID"];
        if (!isset($params["date_from"]) || !isset($params["date_to"])) {
            $today = date('d.m.Y');
            $first = date('01.m.Y');
            // $today = \Wss\Helper::DateFormat($today);
            // $first = \Wss\Helper::DateFormat($first);
        } else {
            $first = strtotime($params["date_from"]);
            $today = strtotime($params["date_to"]);
            $first = date('d.m.Y', $first);
            $today = date('d.m.Y', $today);
            //$today = \Wss\Helper::DateFormat($today);
            //$first = \Wss\Helper::DateFormat($first);
        }

        $results = ForTables::Search($_REQUEST, $elementCheck, $today, $first, 0);
        //$second_massive = ForTables::Search($_REQUEST, $elementCheck, $today, $first, 1);
        /*return [
            $results,
            $second_massive
                ];
        */
        $array = ForTables::Convert($results, $first, $today);
        //return $array;
        $SecondGraph = SecondMainGraph::SecondGraph($array);
        $ThirdGraph = ThirdMainGraph::ThirdGraph($results);
        $array2 = [];
        foreach ($array as $key => $value) {
            $key = Helper::DateFormat($key);
            $array2[$key] = $value;
        }

        $array2 = self::formatter($array2);
        $SecondGraph = self::formatter($SecondGraph);
        $ThirdGraph = self::formatter($ThirdGraph);
        $Total_Traffic = self::totaler($array, $first, $today, $elementCheck);
       // return 1;
        $Totaler = [
            "score" => $Total_Traffic,
            "total_chart" => $array2,
            "day_chart" => $SecondGraph,
            "hour_chart" => $ThirdGraph,
            "date_to" => $today,
            "date_from" => $first
        ];
        return $Totaler;
    }

    public static function totaler($arr, $date_to, $date_from, $elementCheck)
    {
        //$date_from = strtotime($date_from) + 86399;
        //$date_from = date("d-m-Y H:i:s", "$date_from");
        $date_tos = new DateTime($date_to);
        $date_froms = new DateTime($date_from);
        $interval = $date_froms->diff($date_tos);
        $interval = $interval->format('%a')+1;

        $result = [
            0 => [
                "score_name" => "Общий трафик",
                "score_value" => 0,
                "score_value_unit" => "",
                "period" => $interval,
                "trend_value" => "",
                "trend_value_unit" => "%"
            ],
            1 => [
                "score_name" => "Целевой трафик",
                "score_value" => 0,
                "score_value_unit" => "",
                "period" => $interval,
                "trend_value" => "",
                "trend_value_unit" => "%"
            ],
            2 => [
                "score_name" => "Нецелевой трафик",
                "score_value" => 0,
                "score_value_unit" => "",
                "period" => $interval,
                "trend_value" => "",
                "trend_value_unit" => "%"
            ],
            3 => [
                "score_name" => "Упущенный трафик",
                "score_value" => 0,
                "score_value_unit" => "",
                "period" => $interval,
                "trend_value" => "",
                "trend_value_unit" => "%"
            ],
            4 => [
                "score_name" => "Первичный трафик",
                "score_value" => 0,
                "score_value_unit" => "",
                "period" => $interval,
                "trend_value" => "",
                "trend_value_unit" => "%"
            ],
            5 => [
                "score_name" => "Вторичный трафик",
                "score_value" => 0,
                "score_value_unit" => "",
                "period" => $interval,
                "trend_value" => "",
                "trend_value_unit" => "%"
            ],
        ];

        $second = $result;
        $second_massive = ForTables::Search($_REQUEST, $elementCheck, $date_from, $date_to, 1);
        //return $second_massive;
        $second_massive = ForTables::Convert($second_massive,$date_to, $date_from);
        //return $second_massive;
        foreach ($second_massive as $key => $value) {
            foreach($value as $key2 => $value2){
                if($key2 == "Общий трафик"){
                    $second[0]["score_value"] = $second[0]["score_value"] + $value2;

                }elseif($key2 == "Целевой трафик"){
                    $second[1]["score_value"] = $second[1]["score_value"] + $value2;

                }elseif($key2 == "Нецелевой трафик"){
                    $second[2]["score_value"] = $second[2]["score_value"] + $value2;

                }elseif($key2 == "Упущенный трафик"){
                    $second[3]["score_value"] = $second[3]["score_value"] + $value2;
                }
                elseif($key2 == "Первичный"){
                    $second[4]["score_value"] = $second[4]["score_value"] + $value2;
                }
                elseif($key2 == "Вторичный"){
                    $second[5]["score_value"] = $second[5]["score_value"] + $value2;
                }
            }
        }
        //return $second_massive;
        foreach($second as $key => $value){
            if($value["score_value"] == 0){
                $second[$key]["score_value"] = "0";
            }
        }

        foreach ($arr as $key => $value) {
            foreach($value as $key2 => $value2){
                if($key2 == "Общий трафик"){
                    $result[0]["score_value"] = $result[0]["score_value"] + $value2;
                    if($result[0]["score_value"] == 0){
                        $result[0]["trend_value"] = -round($second[0]["score_value"]*100, 2);
                    }elseif($second[0]["score_value"] == 0){
                        $result[0]["trend_value"] = round($result[0]["score_value"]*100, 2);
                    }elseif($result[0]["score_value"] > $second[0]["score_value"]){
                        $result[0]["trend_value"] =  round((round($result[0]["score_value"]*100 / $second[0]["score_value"], 2))-100, 2);
                    }else{
                        $result[0]["trend_value"] = round(-(100-round($result[0]["score_value"]*100 / $second[0]["score_value"], 2)),2);
                    }
                    //$result[0]["trend_value"] = [$second[0]["score_value"],$result[0]["score_value"]];

                }elseif($key2 == "Целевой трафик"){
                    $result[1]["score_value"] = $result[1]["score_value"] + $value2;
                    if($result[1]["score_value"] == 0){
                        $result[1]["trend_value"] = -round($second[1]["score_value"]*100, 2);
                    }elseif($second[1]["score_value"] == 0){
                        $result[1]["trend_value"] = round($result[1]["score_value"]*100, 2);
                    }elseif($result[1]["score_value"] > $second[1]["score_value"]){
                        $result[1]["trend_value"] =  round((round($result[1]["score_value"] *100 / $second[1]["score_value"], 2))-100, 2);
                    }else{
                        $result[1]["trend_value"] = round(-(100-round($result[1]["score_value"]*100 / $second[1]["score_value"], 2)), 2);
                    }
                    //$result[1]["trend_value"] = [$second[1]["score_value"],$result[1]["score_value"]];

                }elseif($key2 == "Нецелевой трафик"){
                    $result[2]["score_value"] = $result[2]["score_value"] + $value2;
                    if($result[2]["score_value"] == 0){
                        $result[2]["trend_value"] = -round($second[2]["score_value"]*100, 2);
                    }elseif($second[2]["score_value"] == 0){
                        $result[2]["trend_value"] = round($result[2]["score_value"]*100, 2);
                    }elseif($result[2]["score_value"] > $second[2]["score_value"]){
                        $result[2]["trend_value"] =  round((round($result[2]["score_value"] *100 / $second[2]["score_value"], 2))-100, 2);
                    }else{
                        $result[2]["trend_value"] = round(-(100-round($result[2]["score_value"]*100 / $second[2]["score_value"], 2)), 2);
                    }
                    //$result[2]["trend_value"] = [$second[2]["score_value"],$result[2]["score_value"]];

                }elseif($key2 == "Упущенный трафик"){
                    $result[3]["score_value"] = $result[3]["score_value"] + $value2;
                    if($result[3]["score_value"] == 0){
                        $result[3]["trend_value"] = -round($second[3]["score_value"]*100, 2);
                    }elseif($second[3]["score_value"] == 0){
                        $result[3]["trend_value"] = round($result[3]["score_value"]*100, 2);
                    }elseif($result[3]["score_value"] > $second[3]["score_value"]){
                        $result[3]["trend_value"] = round((round($result[3]["score_value"] *100 / $second[3]["score_value"], 2))-100, 2);
                    }else{
                        $result[3]["trend_value"] = round(-(100-round($result[3]["score_value"]*100 / $second[3]["score_value"], 2)), 2);
                    }
                    //$result[3]["trend_value"] = [$second[3]["score_value"],$result[3]["score_value"]];

                }elseif($key2 == "Первичный"){
                    $result[4]["score_value"] = $result[4]["score_value"] + $value2;
                    if($result[4]["score_value"] == 0){
                        $result[4]["trend_value"] = -round($second[4]["score_value"]*100, 2);
                    }elseif($second[4]["score_value"] == 0){
                        $result[4]["trend_value"] = round($result[4]["score_value"]*100, 2);
                    }elseif($result[4]["score_value"] > $second[4]["score_value"]){
                        $result[4]["trend_value"] =  round((round($result[4]["score_value"] *100 / $second[4]["score_value"], 2))-100, 2);
                    }else{
                        $result[4]["trend_value"] = round(-(100-round($result[4]["score_value"]*100 / $second[4]["score_value"], 2)), 2);
                    }
                    //$result[4]["trend_value"] = [$second[4]["score_value"],$result[4]["score_value"]];

                }elseif($key2 == "Вторичный"){
                    $result[5]["score_value"] = $result[5]["score_value"] + $value2;
                    if($result[5]["score_value"] == 0){
                        $result[5]["trend_value"] = -round($second[5]["score_value"]*100, 2);
                    }elseif($second[5]["score_value"] == 0){
                        $result[5]["trend_value"] = round($result[5]["score_value"]*100, 2);
                    }elseif($result[5]["score_value"] > $second[5]["score_value"]){
                        $result[5]["trend_value"] =  round((round($result[5]["score_value"] *100 / $second[5]["score_value"], 2))-100, 2);
                    }else{
                        $result[5]["trend_value"] = round(-(100-round($result[5]["score_value"]*100 / $second[5]["score_value"], 2)), 2);
                    }
                    //$result[5]["trend_value"] = [$second[5]["score_value"],$result[5]["score_value"]];
                }
            }
        }
        return $result;
    }

    public static function formatter($arr)
    {
        $result = [
            "categories" => [

            ],
            "series" => [
                0 => [
                    "data" => [],
                    "name" => "Общий трафик"
                ],
                1 => [
                    "data" => [],
                    "name" => "Целевой трафик"
                ],
                2 => [
                    "data" => [],
                    "name" => "Нецелевой трафик"
                ],
                3 => [
                    "data" => [],
                    "name" => "Упущенный трафик"
                ]
            ]
        ];

        foreach ($arr as $key => $value) {
            //$key = \Wss\Helper::DateFormat($key);
            $result["categories"][] = $key;
            foreach ($value as $key2 => $value2) {
                if ($key2 == "Общий трафик") {
                    $result["series"][0]["data"][] = $value2;
                } elseif ($key2 == "Целевой трафик") {
                    $result["series"][1]["data"][] = $value2;
                } elseif ($key2 == "Нецелевой трафик") {
                    $result["series"][2]["data"][] = $value2;
                } elseif ($key2 == "Упущенный трафик") {
                    $result["series"][3]["data"][] = $value2;
                }
            }
        }
        return $result;
    }
}