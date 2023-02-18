<?php

namespace Wss\Telegram\Telegram\Methods;

use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use CIBlockElement;
use CUser;

class ForHelp{

    static public function getAdminsInGroups()
    {
        $res = CIBlockElement::GetList(
            array("SORT" => "ASC"),
            array("IBLOCK_ID" => 1, "ACTIVE" => "Y"),
            false,
            false,
            array("ID", "IBLOCK_ID")
        );
        $arrGalleries = [];
        while ($arr = $res->GetNextElement()) {
            $fields = $arr->GetFields();
            $arrGalleries[$fields['ID']] = $arr->GetProperty('admins');
        }
        return $arrGalleries;
    }

    static public function getMailUserById($id)
    {
        $rsUser = CUser::GetByID($id);
        $arUser = $rsUser->Fetch();
        return $arUser["EMAIL"];
    }

    static public function getUserID($email)
    {
        $rsUser = CUser::GetByLogin($email);
        if ($arUser = $rsUser->Fetch()) {
        } else {
            $filter = array("EMAIL" => $email);
            $rsUser = CUser::GetList(($by = "id"), ($order = "desc"), $filter);
            $arUser = $rsUser->Fetch();
        }
        return $arUser["ID"];
    }

    /**
     * @throws LoaderException
     */
    static public function getElement($UserID)
    {
        $element = "";
        $rolesToCheck = [
            "admins",
            "controllers",
            "marketers",
            "users",
            "rops",
            "moneys",
            "chef"
        ];
        foreach ($rolesToCheck as $role) {
            if (Loader::includeModule("iblock")) {
                $filter = array(
                    "IBLOCK_ID" => 1,
                    array("ID" => \CIBlockElement::SubQuery("ID", array("IBLOCK_ID" => 1, "PROPERTY_$role" => $UserID))),
                );
                $rsElement = CIBlockElement::GetList(false, $filter);
                if ($rsElement) {
                    if ($row = $rsElement->Fetch()) {
                        $element = $row["ID"];
                        return $element;
                    }
                }
            }
        }
    }

    static public function getSalonName($id)
    {
        if (Loader::includeModule("iblock")) {
            $filter = array(
                "IBLOCK_ID" => 4,
                array("ID" => \CIBlockElement::SubQuery("ID", array("IBLOCK_ID" => 4, "ID" => $id))),
            );
            $rsElement = CIBlockElement::GetList(false, $filter);
            if ($rsElement) {
                if ($row = $rsElement->Fetch()) {
                    $element = $row["NAME"];
                    return $element;
                }
            }
        }
    }
    
    static public function getChatId($element)
    {
        $res = CIBlockElement::GetByID($element);
        if ($obRes = $res->GetNextElement()) {
            $ar_res = $obRes->GetProperty("tg_chat_id");
            return $ar_res["VALUE"];
        }
    }

    static public function getTokenBot($element)
    {
        $res = CIBlockElement::GetByID($element);
        if ($obRes = $res->GetNextElement()) {
            $ar_res = $obRes->GetProperty("tg_token");
            return $ar_res["VALUE"];
        }
    }
}