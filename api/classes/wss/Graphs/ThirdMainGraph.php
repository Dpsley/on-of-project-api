<?php

namespace Wss\Graphs;

use Wss\Helper;

class ThirdMainGraph
{
    public function ThirdGraph($results)
    {
        $newmassive = [
            1 => [],
            2 => [],
            3 => [],
            4 => [],
            5 => [],
            6 => [],
            7 => [],
            8 => [],
            9 => [],
            10 => [],
            11 => [],
            12 => [],
            13 => [],
            14 => [],
            15 => [],
            16 => [],
            17 => [],
            18 => [],
            19 => [],
            20 => [],
            21 => [],
            22 => [],
            23 => [],
        ];
        foreach ($newmassive as $key => $value) {
            $newmassive[$key] = [
                "Общий трафик" => 0,
                "Нецелевой трафик" => 0,
                "Целевой трафик" => 0,
                "Упущенный трафик" => 0
            ];
        }
        foreach ($results as $key => $value) {
            $date_in_value = explode(' ', $value["PROPERTY_DATE_TIME_VALUE"]);
            $date_in_value[1] = $rounded = date('H', round(strtotime($date_in_value[1]) / 3600) * 3600);
            $split = str_split($date_in_value[1]);
            if ($split[0] == 0) {
                $date_in_value[1] = $split[1];
            } else {
                $date_in_value[1] = $split[0] . $split[1];
            }
            $newmassive[$date_in_value[1]]["Общий трафик"] = $newmassive[$date_in_value[1]]["Общий трафик"] + 1;
            if ($value["PROPERTY_EVENT_TYPE_VALUE"] == "Первичный") {
                $newmassive[$date_in_value[1]]["Целевой трафик"] = $newmassive[$date_in_value[1]]["Целевой трафик"] + 1;
            } elseif ($value["PROPERTY_EVENT_TYPE_VALUE"] == "Вторичный") {
                $newmassive[$date_in_value[1]]["Упущенный трафик"] = $newmassive[$date_in_value[1]]["Упущенный трафик"] + 1;
            } elseif ($value["PROPERTY_EVENT_TYPE_VALUE"] == "Нецелевой") {
                $newmassive[$date_in_value[1]]["Нецелевой трафик"] = $newmassive[$date_in_value[1]]["Нецелевой трафик"] + 1;
            }
        }
        ksort($newmassive);
        return $newmassive;
        //return print_r($newmassive);
    }
}