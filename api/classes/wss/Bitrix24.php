<?php

namespace Wss;

use CIBlockElement;
use CRest;
use Wss\Telegram\Telegram\Methods as Methods;

class Bitrix24
{
    public function test()
    {
        $arUser = User::getFields("dimaprogma@gmail.com");
        $group = Methods\ForHelp::getElement($arUser["ID"]);
        $groupname = "";
        $obElement = CIBlockElement::GetByID($group);
        if($arEl = $obElement->GetNext())
            $groupname = $arEl["NAME"];
        print_r($group);
    }

    public function DealAdd($name, $date_to, $date_from, $companyID, $summ)
    {
        $date_to = date('d.m.Y', (strtotime($date_to)));
        $date_from = date('d.m.Y', (strtotime($date_from)));

        return CRest::call(
            'crm.deal.add',
            [
                'fields' =>[
                    'CATEGORY_ID' => 2,
                    'TITLE' => $name . "." . " Продление тарифа",
                    'COMMENTS' => "Период оплаты с " . $date_from . '<br>' . "до " . $date_to,
                    'COMPANY_ID' => $companyID,
                    "OPPORTUNITY" => $summ,
                    "ASSIGNED_BY_ID" => "22",
                    "UF_CRM_1669361966143" => $date_from,
                    "UF_CRM_1669361978974" => $date_to
                ]
            ]);
    }

    public function ContactAdd($user, $companyid)
    {

        $queryData = http_build_query(array(
            'fields' => array(
                "NAME"=> $user["NAME"],
                "COMPANY_ID"=> $companyid, /* ил нашей созданной компании*/
                'ASSIGNED_BY_ID'=>"22",
                "POST"=>"Должность",
                'EMAIL' => array(
                    array(
                        "VALUE" => $user["EMAIL"],
                        "VALUE_TYPE" => "WORK"
                    )
                )
            ),
        ));

        $ch = curl_init('https://trafficmeter.bitrix24.ru/rest/40/eic5nxbnn8gh0rng/crm.contact.add.json');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $queryData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $html = curl_exec($ch);
        curl_close($ch);
        return $html;
    }

    public function CompanyAdd($user, $name)
    {

        $queryData = http_build_query(array(
            'fields' => array(
                "TITLE" => $name,
                 "ASSIGNED_BY_ID" => "22",
                "COMPANY_TYPE" => "CUSTOMER",
                'EMAIL' => array(
                    array(
                        "VALUE" => $user["EMAIL"],
                        "VALUE_TYPE" => "WORK"
                    )
                )
            ),
            'params' => array("REGISTER_SONET_EVENT" => "Y")
        ));

        $ch = curl_init('https://trafficmeter.bitrix24.ru/rest/40/v59l7zhbuqkydjz1/crm.company.add.json');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $queryData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $html = curl_exec($ch);
        curl_close($ch);
        return $html;
    }
}