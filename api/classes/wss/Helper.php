<?php

namespace Wss;

use CIBlockElement;
use NumberFormatter;

class Helper
{
    public static function DateFormat($date)
    {
        $currentDate = $date; //может быть присвоена из другой переменной

//список месяцев с названиями для замены
        $_monthsList = array(
            ".01." => "Янв",
            ".02." => "Фев",
            ".03." => "Март",
            ".04." => "Апр",
            ".05." => "Мая",
            ".06." => "Июня",
            ".07." => "Июля",
            ".08." => "Авг",
            ".09." => "Сен",
            ".10." => "Окт",
            ".11." => "Ноя",
            ".12." => "Дек"
        );

//Наша задача - вывод русской даты,
//поэтому заменяем число месяца на название:
        $_mD = date(".m.", strtotime($currentDate)); //для замены
        $currentDate = str_replace($_mD, " ".$_monthsList[$_mD]." ", $currentDate);
        $currentDate = explode(' ', $currentDate);
        $currentDate = $currentDate[0]." ".$currentDate[1];
        return $currentDate;
    }
    public static function DayFormat($date)
    {
        $currentDate = $date; //может быть присвоена из другой переменной

//список месяцев с названиями для замены
        $days = array(
            "Monday" => 'Пн',
            "Tuesday" => 'Вт',
            "Wednesday" => 'Ср',
            "Thursday" => 'Чт',
            "Friday" => 'Пт',
            "Saturday" => 'Сб',
            "Sunday" => 'Вс'
        );

        foreach($days as $key => $value){
            if($currentDate == $key){
                $currentDate = $days[$key];
            }
        }
        return $currentDate;
    }

   public function str_price($value)
    {
        $value = explode('.', number_format($value, 2, '.', ''));

        $f = new \NumberFormatter('ru', NumberFormatter::SPELLOUT);
        $str = $f->format($value[0]);

        // Первую букву в верхний регистр.
        $str = mb_strtoupper(mb_substr($str, 0, 1)) . mb_substr($str, 1, mb_strlen($str));

        // Склонение слова "рубль".
        $num = $value[0] % 100;
        if ($num > 19) {
            $num = $num % 10;
        }
        switch ($num) {
            case 1: $rub = 'рубль'; break;
            case 2:
            case 3:
            case 4: $rub = 'рубля'; break;
            default: $rub = 'рублей';
        }

        return $str . ' ' . $rub . ' ' . "ноль" . ' копеек.';
    }

}