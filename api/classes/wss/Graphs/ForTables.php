<?php

namespace Wss\Graphs;

use CIBlockElement;
use CIBlockProperty;
use CIBlockPropertyEnum;
use CModule;
use CUser;
use Wss\Helper;
use Wss\User;

class ForTables
{
    public function Search($params, $elementCheck, $date_from, $date_to, $interval)
    {
        if($interval == 0){
            $interval= "- 0 day";
            $today = date('Y-m-d 23:59:59', strtotime($date_from )) ;
            $first = date('Y-m-d 00:00:00', strtotime ( $date_to) ) ;
        }else{
            $interval= "- 1 month";

        $date_check = explode(".", $date_from);
            if($date_check[1] != "01"){
        if(!checkdate($date_check[1]-1, $date_check[0], $date_check[2])) {
            $date_check[1] = $date_check[1] - 1;
            $number = cal_days_in_month(CAL_GREGORIAN, $date_check[1], $date_check[2]);
            if ($date_check[1] < 10) {
                $date_check[1] = "0" . "$date_check[1]";
            }
            $date_from = $number . "." . $date_check[1] . "." . $date_check[2];
            $today = date('Y-m-d 23:59:59', strtotime($date_from));
        }else{$today = date('Y-m-d 23:59:59',(strtotime ( "$interval" , strtotime($date_from))));}
        }else {
            $today = date('Y-m-d 23:59:59',(strtotime ( "$interval" , strtotime($date_from))));
        }
        }
        //$today = date('Y-m-d 23:59:59',(strtotime ( "$interval" , strtotime ( $date_from) ) ));
        $first = date('Y-m-d 00:00:00',(strtotime ( "$interval" , strtotime ( $date_to) ) ));
        /*return [
            $first,
            $today,
            $elementCheck
        ];*/
        $results = [];
        if (!isset($params["salon"]) && !isset($params["manager"])) {
            $arFilter = array(
                "IBLOCK_ID" => $elementCheck,
                ">=PROPERTY_date_time" => date('Y-m-d H:i:s', strtotime($first)),
                "<=PROPERTY_date_time" => date('Y-m-d H:i:s', strtotime($today)),
                "ACTIVE" => "Y",
            );
        } elseif (isset($params["salon"]) && !isset($params["manager"])) {
            $arFilter = array(
                "IBLOCK_ID" => $elementCheck,
                "PROPERTY_department" => $params["salon"],
                ">=PROPERTY_date_time" => date('Y-m-d H:i:s', strtotime($first)),
                "<=PROPERTY_date_time" => date('Y-m-d H:i:s', strtotime($today)),
                "ACTIVE" => "Y",
            );
        } elseif (!isset($params["salon"]) && isset($params["manager"])) {
            $arFilter = array(
                "IBLOCK_ID" => $elementCheck,
                "PROPERTY_user" => $params["manager"],
                ">=PROPERTY_date_time" => date('Y-m-d H:i:s', strtotime($first)),
                "<=PROPERTY_date_time" => date('Y-m-d H:i:s', strtotime($today)),
                "ACTIVE" => "Y",
            );
        } elseif (isset($params["salon"]) && isset($params["manager"])) {
            $arFilter = array(
                "IBLOCK_ID" => $elementCheck,
                "PROPERTY_department" => $params["salon"],
                "PROPERTY_user" => $params["manager"],
                ">=PROPERTY_date_time" => date('Y-m-d H:i:s', strtotime($first)),
                "<=PROPERTY_date_time" => date('Y-m-d H:i:s', strtotime($today)),
                "ACTIVE" => "Y",
            );
        }

        $res = CIBlockElement::GetList(
            array("SORT" => "ASC", "PROPERTY_DATE_TIME_VALUE" => "ASC"),
            $arFilter,
            array(
                "PROPERTY_date_time",
                "PROPERTY_event_type",
                "PROPERTY_result",
                "ID",
                "PROPERTY_department"));
        $i = 0;
        $possible = User::getSalons((int)CUser::GetId());
        //return $possible;
        foreach($possible as $key => $value){
            $possible[$value["id"]] = $value;
            unset($possible[$key]);
        }

        while ($ar_fields = $res->GetNext()) {
            //return $ar_fields;
            foreach ($ar_fields as $key => $value) {
                if ($key == "PROPERTY_RESULT_ENUM_ID") {
                    Cmodule::IncludeModule('iblock');
                    $rsEnum = CIBlockPropertyEnum::GetList(array(),
                        array(
                            "IBLOCK_ID" => $elementCheck,
                            "ID" => $ar_fields["PROPERTY_RESULT_ENUM_ID"]
                        ));
                    $arEnum = $rsEnum->GetNext();
                    $ar_fields["PROPERTY_RESULT_XML_ID"] = $arEnum["XML_ID"];
                }
                $rsEnums = CIBlockElement::GetProperty(
                    $elementCheck,
                    $ar_fields["ID"],
                    array("sort" => "asc"),
                    array("CODE" => "department"));
                $arEnums = $rsEnums->GetNext();
                $ar_fields["SALON"] = $arEnums["VALUE"];
            }

            //return $ar_fields;
            foreach($ar_fields as $key => $value){
                //                if($arUser)
                if(array_key_exists($ar_fields["SALON"], $possible) && $possible[$ar_fields["SALON"]]["status"] === true){
                }else{
                    unset($ar_fields);
                }
            }
            //return $ar_fields;
            if($ar_fields != null)
                $results[$i] = $ar_fields;

            $i++;
        }

        return $results;
    }
    public function Convert($results, $first, $today){
        //return $today;
        $array = array();
        for ($i = strtotime($first); $i <= strtotime($today); $i += 86400) {
            //$list = \Wss\Helper::DateFormat(date('d.m.Y', $i));
            $list = date('d.m.Y', $i);
            //$array[$list] = [
            $array[$list] = [
                "Общий трафик" => 0,
                "Целевой трафик" => 0,
                "Нецелевой трафик" => 0,
                "Упущенный трафик" => 0,
                "Первичный" => 0,
                "Вторичный" => 0
            ];
        }
        if(empty($results)){
            return $array;
        }
        foreach ($results as $key => $value) {
            $date_in_value = explode(' ', $value["PROPERTY_DATE_TIME_VALUE"]);
            //$date_in_value[0] = \Wss\Helper::DateFormat($date_in_value[0]);
            $array[$date_in_value[0]]["Общий трафик"] = $array[$date_in_value[0]]["Общий трафик"] + 1;
            if ($value["PROPERTY_EVENT_TYPE_VALUE"] == "Первичный" || $value["PROPERTY_EVENT_TYPE_VALUE"] == "Вторичный") {
                $array[$date_in_value[0]][$value["PROPERTY_EVENT_TYPE_VALUE"]] = $array[$date_in_value[0]][$value["PROPERTY_EVENT_TYPE_VALUE"]] + 1;
                //return $value;
                $missed = strpos($value["PROPERTY_RESULT_XML_ID"], "missed");
                $targeted = strpos($value["PROPERTY_RESULT_XML_ID"], "targeted");
                if ($missed) {
                    $array[$date_in_value[0]]["Упущенный трафик"] = $array[$date_in_value[0]]["Упущенный трафик"] + 1;
                } elseif($targeted){
                    $array[$date_in_value[0]]["Целевой трафик"] = $array[$date_in_value[0]]["Целевой трафик"] + 1;
                }
            } elseif ($value["PROPERTY_EVENT_TYPE_VALUE"] == "Нецелевой") {
                $array[$date_in_value[0]]["Нецелевой трафик"] = $array[$date_in_value[0]]["Нецелевой трафик"] + 1;
            }
            $array[$date_in_value[0]]["Целевой трафик"] = $array[$date_in_value[0]]["Общий трафик"] - $array[$date_in_value[0]]["Нецелевой трафик"];
        }
        return $array;
    }
}