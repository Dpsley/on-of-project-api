<?php

namespace Wss\Graphs;

use CIBlockElement;
use Wss\Helper;

class SecondMainGraph
{
    public function SecondGraph($params)
    {
        $newmassive = [
            "Пн" => [],
            "Вт" => [],
            "Ср" => [],
            "Чт" => [],
            "Пт" => [],
            "Сб" => [],
            "Вс" => [],
        ];

      foreach ($params as $key => $value){
          $key = date('l',strtotime($key));
          $key = \Wss\Helper::DayFormat($key);
          foreach($value as $key2 => $values)
          $newmassive[$key][$key2] = $newmassive[$key][$key2] + $values;
      }
        return $newmassive;
    }
}