<?php

namespace Wss;

use Bitrix\Main\Loader;
use CIBlockElement;
use CIBlockPropertyEnum;
use CRest;
use CUser;
use DateTime;
use Wss\Telegram\Telegram\Methods as Methods;
use Wss\Helper as Helper;

class Pay_Order
{
    public function writeToLog($data, $title = '')
    {
        $log = "\n------------------------\n";
        $log .= date("Y.m.d G:i:s") . "\n";
        $log .= (strlen($title) > 0 ? $title : 'DEBUG') . "\n";
        $log .= print_r($data, 1);
        $log .= "\n------------------------\n";
        file_put_contents(getcwd() . '/hook.log', $log, FILE_APPEND);
        return true;
        //print_r(self::writeToLog($_REQUEST, 'delete'));
    }

    public function finder($deal_id)
    {
        $element = "";
        if (Loader::includeModule("iblock")) {
            $filter = array(
                "IBLOCK_ID" => 3,
                array("ID" => \CIBlockElement::SubQuery("ID", array("IBLOCK_ID" => 3, "PROPERTY_1090" => $deal_id))),
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

    static public function filteroldbilling($deal_id){
        $user = Cuser::GetID();
        $company_id = Methods\ForHelp::getElement($user);
        $elements = [];
        if (Loader::includeModule("iblock")) {
            $arSelect = Array("*","PROPERTY_*");//IBLOCK_ID и ID обязательно должны быть указаны, см. описание arSelectFields выше
            $arFilter = Array("IBLOCK_ID"=>3, "PROPERTY_1" => $company_id );
            $res = CIBlockElement::GetList(Array(), $arFilter, false, Array("nPageSize"=>50), $arSelect);
            $i = 0;
            while($ob = $res->GetNextElement()){
                $arFields = $ob->GetFields();
                //$elements[$i]["arFields"] = $arFields;
                $arProps = $ob->GetProperties();
                //$elements[$i]["arProps"] = $arProps;
                $elements[$i]["billing_record_id"] = $arFields["ID"];
                $elements[$i]["is_record_active"] = $arFields["ACTIVE"];
                $elements[$i]["record_summ"] = $ob->GetProperty(2)["VALUE"];
                $elements[$i]["crm_deal_id"] = $ob->GetProperty(1090)["VALUE"];
                $elements[$i]["count_salons"] = $ob->GetProperty(1609)["VALUE"];
                $elements[$i]["payment"] = $ob->GetProperty(1612)["VALUE"];
                $i++;
            }
        }
        //return $elements;
        foreach($elements as $key => $item){
            if($item["crm_deal_id"] != $deal_id){
               // CIBlockElement::Delete($item["billing_record_id"]);
                $obEl = new CIBlockElement();
                $PRODUCT_ID = $obEl->Update($item["billing_record_id"], array('ACTIVE' => 'N'));
                unset($elements[$key]);
            }
        }
        return $elements;
    }

    public function activate()
    {
        global $USER;
        //return self::filteroldbilling($_REQUEST["deal_id"]);
        if (isset($_REQUEST["deal_id"])){
            $deal_id = $_REQUEST["deal_id"];
        }
        $element = "";
        $result = [];
        $arFilter = array(
            "IBLOCK_ID" => 3,
            "ACTIVE" => "N",
            "PROPERTY_id_bitrix24_deal" => $deal_id,
        );
        \CModule::IncludeModule("iblock");
        $rs = \CIBlockElement::GetList(
            array('active_to' => 'desc'),
            $arFilter,
            false,
            false,
            array('ID', 'NAME', 'IBLOCK_ID', 'ACTIVE_TO')
        );
        while ($ob = $rs->GetNextElement()) {
            $result["ID"] = $ob->GetFields()["ID"];
            $result["сделка"] = $ob->GetProperty("id_bitrix24_deal");
        }
        //return 1;
        $el = new \CIBlockElement;
        $arLoadProductArray = array(
            "ACTIVE" => "Y",
            "DATE_ACTIVE_FROM" => ConvertTimeStamp(strtotime($_REQUEST["date_start"]."00:00:01"), "FULL"),
            "DATE_ACTIVE_TO" => ConvertTimeStamp(strtotime($_REQUEST["date_end"]."23:59:59"), "FULL"),// активен
        );
        CIBlockElement::SetPropertyValuesEx($result["ID"], 3, array("summ" => round($_REQUEST["summ"], 0)));
        CIBlockElement::SetPropertyValuesEx($result["ID"], 3, array("count_salons" => $_REQUEST["salons"]));
        CIBlockElement::SetPropertyValuesEx($result["ID"], 3, array("payment" => 1));

        $PRODUCT_ID = $el->Update($result["ID"], $arLoadProductArray);
        return $PRODUCT_ID;
    }

    public function deactivate($deal_id, $salons)
    {
        global $USER;
        if (isset($_REQUEST["deal_id"]) && $_REQUEST["salons"]){
            $deal_id = $_REQUEST["deal_id"];
            $salons = $_REQUEST["salons"];
        }
        $el = new \CIBlockElement;
        $PROP = array();
        $PROP[1] = $_REQUEST['company']; // account_id
        $PROP[2] = $_REQUEST['sum']; // summ
        $PROP[1090] = $deal_id;
        $PROP[1609] = $salons;
        $PROP[1612] = 0;
        $months = $_REQUEST["months"];
        $date_start = date("d.m.Y H:i:s");
        $date_end = date("d.m.Y H:i:s", strtotime($date_start."+$months month" ));

        $arLoadProductArray = array(
            "ACTIVE" => "N",      // активен
            "MODIFIED_BY" => $USER->GetID(), // элемент изменен текущим пользователем
            "IBLOCK_SECTION_ID" => false,          // элемент лежит в корне раздела
            "IBLOCK_ID" => Billing::$iblockBilling,
            "ACTIVE_FROM" => ConvertTimeStamp(strtotime($date_start), "FULL"),
//            "DATE_ACTIVE_FROM"      => \ConvertTimeStamp(strtotime($date_from), 'FULL'),
            "ACTIVE_TO" => \ConvertTimeStamp(strtotime($date_end), 'FULL'),
//            "DATE_ACTIVE_TO"      => \ConvertTimeStamp(strtotime($date_to),'FULL'),
            "PROPERTY_VALUES" => $PROP,
            "NAME" => "Оплата " . date('Y.m.d') . " " . $_REQUEST['sum'],

        );

        $PRODUCT_ID = $el->Add($arLoadProductArray);
        return $PRODUCT_ID;
    }

    public function delete()
    {
        $document_id = $_REQUEST["id"];
        $for_find = self::finder($document_id);
        $obEl = new CIBlockElement();
        $boolResult = $obEl->Delete($for_find);
    }

    public function getfields(){
        $userId = CUser::GetID();
        $CompanyId = Methods\ForHelp::getElement($userId);
        $res = CIBlockElement::GetByID($CompanyId);
        if ($obRes = $res->GetNextElement()) {
            $result["inn"] = $obRes->GetProperty("inn")["VALUE"];
            $result["telephone"] = $obRes->GetProperty("telephone")["VALUE"];
        }
        $result["email"] = $_REQUEST["email"];
        return $result;
    }

    public function get()
    {
        global $USER;
        $params = [
            "salons" => "Количество салонов",
            "months" => "Количество месяцев",
            "sum" => "Сумму",
            "inn" => "ИНН",
            "telephone" => "Номер телефона",
            "email" => "Адрес электронной почты"
        ];
        foreach ($params as $key => $value) {
            if (!isset($_REQUEST[$key]) || !$_REQUEST[$key]) {
                $response = [
                    "message" => "Укажите, пожалуйста, $value",
                    "status" => "error"
                ];
                http_response_code(400);
                return $response;
            } else {
                $$key = $_REQUEST[$key];
            }
        }
        $userId = CUser::GetID();
        $rsUser = CUser::GetByID($userId);
        $arUser = $rsUser->Fetch();
        $user["EMAIL"] = $arUser["EMAIL"];
        $user["NAME"] = $arUser["NAME"]." ".$arUser["LAST_NAME"];
        $CompanyId = Methods\ForHelp::getElement($userId);

        $date_from = date("d.m.Y H:i:s");
        $date_to = date('d.m.Y', strtotime("+$months month"));

        $groupname = "";
        $companyID_bitrix = "";
        $obElement = CIBlockElement::GetByID($CompanyId);

        if ($arEl = $obElement->GetNext())
            $groupname = $arEl["NAME"];
        $res = CIBlockElement::GetByID($CompanyId);
        if ($obRes = $res->GetNextElement()) {
            $fields = $obRes->GetFields();
            $company_title = $fields["NAME"];
            $companyID_bitrix = $obRes->GetProperty("b24_id");
            $user_crm_id = $obRes->GetProperty("user_crm_id");
        }
        //return $company_title;
        if (!$companyID_bitrix["VALUE"]) {
            //return $user;
            $companyID_bitrix["VALUE"] = json_decode(\Wss\Bitrix24::CompanyAdd($user, $company_title),true)["result"];
        }
        if (!$user_crm_id["VALUE"]) {
            $user_crm_id["VALUE"] = json_decode(\Wss\Bitrix24::ContactAdd($user, $CompanyId),true)["result"];
        }
        //return $user_crm_id;
        $date_to = date('d.m.Y', (strtotime($date_to)));
        $date_from = date('d.m.Y', (strtotime($date_from)));
        CIBlockElement::SetPropertyValuesEx($CompanyId, 1, array("b24_id" => $companyID_bitrix["VALUE"]));
        CIBlockElement::SetPropertyValuesEx($CompanyId, 1, array("user_crm_id" => $user_crm_id["VALUE"]));
        CIBlockElement::SetPropertyValuesEx($CompanyId, 1, array("inn" => $inn));
        CIBlockElement::SetPropertyValuesEx($CompanyId, 1, array("telephone" => $telephone));
        $requisites = self::SearchRequisites($inn);
        //return $requisites;
        if (!isset($requisites["suggestions"][0])) {
            $response = [
                "message" => "Не определить вашу компанию по указанному ИНН. Пожалуйста, обратитесь в поддержку.",
                "status" => "error"
            ];
            http_response_code(400);
            return $response;
        }
        $requisites_add = Crest::call(
            'crm.requisite.add',
            [
                'fields' => [
                    "ENTITY_TYPE_ID" => 4, // Тип родительской сущности (1 - Лиды, 2 - Сделки, 3 - Контакты, 4 - Компании, 5 - Счета, 6 - Дела, 7 - Предложения, 8 - Реквизиты, 9 - Направление сделки, 10 - Пользовательские действия, 11 - ожидания, 12 - обзвон, 13 - рекуррентные сделки, 14 - заказ, 15 - чек, 16 - отгрузка, 17 - оплата)
                    "ENTITY_ID" => $companyID_bitrix["VALUE"],  //В данном случае ID Компании так как ENTITY_TYPE_ID = 4
                    "PRESET_ID" => 1,
                    "NAME" => $inn, //Название реквизита
                    "RQ_COMPANY_NAME" => stripcslashes($requisites["suggestions"][0]["data"]["name"]["short_with_opf"]), //Сокращенное наименование организации
                    "RQ_COMPANY_FULL_NAME" => stripcslashes($requisites["suggestions"][0]["data"]["name"]["short_with_opf"]), //Полное наименование организации
                   // "RQ_COMPANY_REG_DATE" => "05.07.2019", //Дата регистрации компании
                   // "RQ_DIRECTOR" => "Ген директор", //Ген директор
                   // "RQ_ACCOUNTANT" => "Гл бухгалтер", //Гл бухгалтер
                    "RQ_INN" => stripcslashes($requisites["suggestions"][0]["data"]["inn"]), //ИНН
                    "RQ_KPP" => stripcslashes($requisites["suggestions"][0]["data"]["kpp"]), //КПП
                    "RQ_OGRN" => stripcslashes($requisites["suggestions"][0]["data"]["ogrn"]), //ОГРН
                    //"RQ_OGRNIP" => "", //ОГРН Индивидуального предпринимателя
                    "RQ_OKPO" => stripcslashes($requisites["suggestions"][0]["data"]["okpo"]), //ОКПО
                    "RQ_OKTMO" => stripcslashes($requisites["suggestions"][0]["data"]["oktmo"]), //ОКТМО
                    "RQ_OKVED" => stripcslashes($requisites["suggestions"][0]["data"]["okved"]), //ОКВЕД
                ]


            ]);
        /*$requisites_equip = Crest::call(
            'crm.requisite.bankdetail.add',
            [
                "id" => $companyID_bitrix,
                'fields' => [
                    "ENTITY_TYPE_ID" => 8, //Здесь указывается ID типа реквизита в данном случае (8 - Реквизиты)
                    "ENTITY_ID" => (int)$requisites_add["result"], //ID реквизита
                    "COUNTRY_ID" => 1, //Незнаю что это, скорей всего ID страны но списка сопоставления нет
                    "NAME" => $inn, //Банковские реквизиты
                    "RQ_BANK_NAME" => "Наименование банка", // Наименование банка
                    "RQ_BANK_ADDR" => "Адрес банка:", // Адрес банка
                    "RQ_BIK" => "55555555555", //БИК
                    "RQ_ACC_NUM" => "66666666666", //Расчетный счёт
                    "RQ_ACC_CURRENCY" => "tre", //Валюта счёта
                    "RQ_COR_ACC_NUM" => "777777777", // Кор. счёт
                    "RQ_SWIFT" => "SWIFT", //SWIFT
                    "COMMENTS" => "Комментарий" //Комментарий
                ]
            ]);
        */
        $requisites_add_address = Crest::call(
            'crm.address.add',
            [
                'fields' => [
                    "TYPE_ID" => 4, // Тип родительской сущности (1 - Лиды, 2 - Сделки, 3 - Контакты, 4 - Компании, 5 - Счета, 6 - Дела, 7 - Предложения, 8 - Реквизиты, 9 - Направление сделки, 10 - Пользовательские действия, 11 - ожидания, 12 - обзвон, 13 - рекуррентные сделки, 14 - заказ, 15 - чек, 16 - отгрузка, 17 - оплата)
                    "ENTITY_TYPE_ID" => 8,  //В данном случае ID Компании так как ENTITY_TYPE_ID = 4
                    "ENTITY_ID" => (int)$requisites_add["result"],
                    "ADDRESS_1" => stripcslashes($requisites["suggestions"][0]["data"]["address"]["unrestricted_value"]),
                    //"ADDRESS_2" => "Квартира  офис",
                    "CITY" => stripcslashes($requisites["suggestions"][0]["data"]["address"]["data"]["city"]),
                   // "REGION" => "Район",
                    "PROVINCE" => stripcslashes($requisites["suggestions"][0]["data"]["address"]["data"]["region"])." область",
                    "POSTAL_CODE" => stripcslashes($requisites["suggestions"][0]["data"]["address"]["data"]["postal_code"]),
                    "COUNTRY" => stripcslashes($requisites["suggestions"][0]["data"]["address"]["data"]["country"]),
                    "COUNTRY_CODE" => "RU"

                ]
            ]);
        //return $requisites["suggestions"][0]["data"]["address"]["data"]["region"];
        $newContactData = CRest::call(
            'crm.contact.get',
            [
                'id' => $user_crm_id["VALUE"]
            ]
        );
        $arUpdateEmail = [
            [//change
                'ID' => $newContactData['result']['EMAIL'][0]['ID'],
                'VALUE' => $email
            ],
        ];
        $Contact_update = Crest::call(
            'crm.contact.update',
            [
                "id" => $user_crm_id["VALUE"],
                'fields' => [
                    "PHONE" => array(
                        array(
                        "VALUE"=> $telephone,
                        "VALUE_TYPE" => "WORK"
                        )),
                    "EMAIL" => $arUpdateEmail
                ]
            ]);
        $Company_update = Crest::call(
            'crm.company.update',
            [
                "id" => $companyID_bitrix,
                'fields' => [
                    "TITLE" => $groupname,
                    // 'COMMENTS' => "Период оплаты с " . $date_from . '<br>' . "до " . $date_to,
                    'REG_ADDRESS' => $requisites_equip["result"],
                    "BANKING_DETAILS" => array(
                        array(
                            "ENTITY_TYPE_ID" => 4, // Тип родительской сущности (1 - Лиды, 2 - Сделки, 3 - Контакты, 4 - Компании, 5 - Счета, 6 - Дела, 7 - Предложения, 8 - Реквизиты, 9 - Направление сделки, 10 - Пользовательские действия, 11 - ожидания, 12 - обзвон, 13 - рекуррентные сделки, 14 - заказ, 15 - чек, 16 - отгрузка, 17 - оплата)
                            "ENTITY_ID" => 20,  //В данном случае ID Компании так как ENTITY_TYPE_ID = 4
                        )
                    ),
                    "UF_CRM_1671652988715" => $salons
                ]
            ]);
        $Deal = CRest::call(
            'crm.deal.add',
            [
                'fields' => [
                    'CATEGORY_ID' => 2,
                    'TITLE' => $groupname . "." . " Продление тарифа",
                    // 'COMMENTS' => "Период оплаты с " . $date_from . '<br>' . "до " . $date_to,
                    'COMPANY_ID' => $companyID_bitrix["VALUE"],
                    'CONTACT_ID' => $user_crm_id["VALUE"],
                    "OPPORTUNITY" => (int)$sum,
                    "ASSIGNED_BY_ID" => "22",
                    "UF_CRM_1669361966143" => date("d.m.Y H:i:s", strtotime($date_from)),
                    "UF_CRM_1669361978974" => date("d.m.Y H:i:s", strtotime($date_to)),
                    "UF_CRM_1671539149822" => $months,
                    "UF_CRM_1671611441992" => Helper::str_price($sum),
                    "UF_CRM_1671653199152" => $salons
                ]
            ]);
        $_REQUEST["company"] = $CompanyId;
        sleep(2);
        $res = CIBlockElement::GetByID($CompanyId);
        if ($obRes = $res->GetNextElement()) {
            $ar["companyID_bitrix"] = $obRes->GetProperty("b24_id");
            $ar["url"] = $obRes->GetProperty("url_inv");
        }
        $try = self::deactivate($Deal["result"], $salons);
        /*$ar["url"]["VALUE"] == "";
        while($ar["url"]["VALUE"] == "") {
            $res = CIBlockElement::GetByID($CompanyId);
            if ($obRes = $res->GetNextElement()) {
                $ar["companyID_bitrix"] = $obRes->GetProperty("b24_id");
                $ar["url"] = $obRes->GetProperty("url_inv");
            }
            sleep(1);
        }
        */
        $response = [
            "link" => $ar["url"]["VALUE"],
            "message" => "Запрос на выставление счета принят",
            "status" => "success"
        ];
        header("X-Frame-Options: SAMEORIGIN always");
        return $response;
    }

    public function SearchRequisites($inn)
    {
        $data = array(
            'query' => $inn
        );

        $ch = curl_init('https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/party');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json', "Authorization: Token b5c807d790984813c77b76ebbb485a535c57657f"));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $res = curl_exec($ch);
        curl_close($ch);

        //$res = json_encode($res, JSON_UNESCAPED_UNICODE);
        return json_decode($res, true);
    }

}