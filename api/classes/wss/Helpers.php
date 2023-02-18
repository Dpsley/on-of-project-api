<?php

namespace Wss;

use Bitrix\Iblock\IblockTable;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Main\Context;
use CIBlockElement;

class Helpers
{
    static public int $cache_time_short = 1;
    static public int $cache_time_long = 1;
    static public array $arErrMesAuth = array('status' => 'error', 'message' => 'Ошибка проверки авторизации');
    static public string $errorAuth = 'Ошибка проверки авторизации';

    function object_to_array($data)
    {
        if ((!is_array($data)) and (!is_object($data)))
            return 'xxx'; // $data;

        $result = array();

        $data = (array)$data;
        foreach ($data as $key => $value) {
            if (is_object($value))
                $value = (array)$value;
            if (is_array($value))
                $result[$key] = \Wss\Helpers::object_to_array($value);
            else
                $result[$key] = $value;
        }
        return $result;
    }

    public function Check_Role(array $array): string{
        $checker_role = "";
        foreach ($array["roles"] as $key => $value) {
            if ($array["roles"]["super_admin"] && $value) {
                $checker_role = "super_admin";
            } elseif ($array["roles"]["admins"] && $value) {
                $checker_role = "admins";
            } elseif ($array["roles"]["chef"] && $value) {
                $checker_role = "chef";
            } elseif ($array["roles"]["rops"] && $value) {
                $checker_role = "rops";
            } elseif ($array["roles"]["controllers"] && $value) {
                $checker_role = "controllers";
            } elseif ($array["roles"]["marketers"] && $value) {
                $checker_role = "marketers";
            } elseif ($array["roles"]["moneys"] && $value) {
                $checker_role = "moneys";
            }elseif ($array["roles"]["users"] && $value) {
                $checker_role = "users";
            }
        }
        return $checker_role;
    }

    public function SortByRoles($array, $current_user): array
    { //массив пользователей


        $visible = [
            "super_admin" => [
                "chef",
                "super_admin",
                "controllers",
                "marketers",
                "moneys",
                "rops",
                "users"
            ],
            "admins" => [
                "chef",
                "controllers",
                "marketers",
                "moneys",
                "rops",
                "users"
            ],
            "chef" => [
                "controllers",
                "marketers",
                "moneys",
                "rops",
                "users"
            ],
            "controllers" => [
                "users"
            ],
            "marketers" => [
                "users"
            ],
            "moneys" => [
                "users"
            ],
            "rops" => [
                "controllers",
                "marketers",
                "moneys",
                "users",
            ],
            "users" => [
                "users"
            ],
        ];

        $checker_role = self::Check_Role($current_user);
        foreach ($array["list"] as $key => $value) {
            $user_role = self::Check_Role($value);

            if (!in_array($user_role, $visible[$checker_role])){
                unset($array["list"][$key]);
                //$array["list"][$key]["user_role"] = $user_role;
                //$array["list"][$key]["checker_role"] = $checker_role;
                //$array["list"][$key]["can"] = $visible[$checker_role];
            }
        }
        return $array;
        //return (array)$checker_role;
    }

    public static function getIblockIdByCode($code, $type): int
    {
        return IblockTable::getRow(
            [
                'filter' => ['CODE' => $code, 'IBLOCK_TYPE_ID' => $type],
                'cache' => ['ttl' => 3600]
            ]
        )['ID'];
    }

    public static function getPropsByIblock($id): array
    {
        $props = [];
        if ($id > 0) {
            $res = PropertyTable::query()
                ->addSelect('*')
                ->where('IBLOCK_ID', $id)
                ->fetchAll();
            if ($res) {
                foreach ($res as $prop) {
                    $props[$prop['CODE']] = $prop;
                }
            }
        }
        return $props;
    }

    public static function getPropsByElement($id, $full = false): array
    {
        $props = [];
        if ($id > 0) {
            $res = CIBlockElement::GetByID($id);
            if ($ob = $res->GetNextElement()) {
                foreach ($ob->GetProperties() as $prop) {
                    if ($prop['VALUE'] && !$full) {
                        $props[$prop['ID']] = $prop['VALUE'];
                    } else {
                        $props[$prop['CODE']] = $prop;
                    }
                }
            }
        }
        return $props;
    }

    public static function getRequest()
    {
        return Context::getCurrent()->getRequest();
    }

    public static function getError($message): array
    {
        return ['status' => 'error', 'message' => $message];
    }

    public static function getSuccess($message): array
    {
        return ['status' => 'success', 'message' => $message];
    }

    public function massive_dates($first_date, $last_date){
        $start = $first_date;
        $finish = $last_date;

        $array = array();
        for($i = $start; $i <= $finish; $i += 86400) {
            $list = explode('.', date('d.m.Y', $i));
            $array[] = implode('.', $list);
        }
        return $array;
    }
}
