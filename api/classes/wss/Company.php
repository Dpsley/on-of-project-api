<?php

namespace Wss;

use Bitrix\Main\Data\Cache;
use CIBlockElement;

class Company
{
//    static public int $iblockCompanies = 1; // инфоблок компаний
    static public string $iblockCompaniesType = 'general'; // тип инфоблока компаний
    static public string $iblockCompaniesCode = 'companies'; // код инфоблока компаний

    public static function getIblockId(): int
    {
        return Helpers::getIblockIdByCode(self::$iblockCompaniesCode, self::$iblockCompaniesType);
    }

    // ID текущей компания пользователя, по умолчанию текущий
    public static function getId($user_id = 0)
    {
        $fieldsCompany = self::get($user_id);
        if ($fieldsCompany['status'] == 'error') {
            return $fieldsCompany;
        }
        return $fieldsCompany['FIELDS']['ID'] ?: false;
    }
    public static function getIdV2($user_id = 0)
    {
        $fieldsCompany = self::getV2($user_id);
        if ($fieldsCompany['status'] == 'error') {
            return $fieldsCompany;
        }
        return $fieldsCompany['FIELDS']['ID'] ?: false;
    }

    // поиск компании по юзеру
    private static function getListByUserId($user_id)
    {
        $result = [];
        $rs = CIBlockElement::GetList(
            [],
            [
                "IBLOCK_ID" => self::getIblockId(),
                "ACTIVE" => "Y",
                [
                    "LOGIC" => "OR",
                    ["PROPERTY_admins" => $user_id],
                    ["PROPERTY_controllers" => $user_id],
                    ["PROPERTY_marketers" => $user_id],
                    ["PROPERTY_rops" => $user_id],
                    ["PROPERTY_users" => $user_id],
                    ["PROPERTY_moneys" => $user_id],
                    ["PROPERTY_chef" => $user_id],
                ]
            ],
            false,
            false,
            []
        );
        while ($ob = $rs->GetNextElement()) {
            $result[] = [
                'FIELDS' => $ob->GetFields(),
                'PROPERTIES' => $ob->GetProperties(),
            ];
        }
        return $result;
    }

    private static function getById($id)
    {
        $result = [];
        $rs = CIBlockElement::GetList(
            [],
            [
                "IBLOCK_ID" => self::getIblockId(),
                "ACTIVE" => "Y",
                "ID" => $id,
            ],
            false,
            false,
            []
        );
        if ($ob = $rs->GetNextElement()) {
            $result = [
                'FIELDS' => $ob->GetFields(),
                'PROPERTIES' => $ob->GetProperties(),
            ];
        }
        return $result;
    }

    // текущая компания пользователя, по умолчанию текущий юзер
    public static function getV2($user_id = 0)
    {
        $request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
        $input = array();
        $input = json_decode($request->getInput());
        $input = Helpers::object_to_array($input);

        if (!is_numeric($user_id) || $user_id === 0) {
            $arIsAuth = User::isAuthV3($input);
            if (!$arIsAuth['isAuth']) {
                return Helpers::$arErrMesAuth;
            }
            $user_id = $arIsAuth['user_id'];
        }

        $fields = [];
        $cache = Cache::createInstance();
        $cacheId = 'getCompanyByCurUser' . md5(serialize($input)) . md5(serialize($user_id));
        if ($cache->initCache(Helpers::$cache_time_long, $cacheId, 'custom_cache')) {
            $fields = $cache->getVars();
        } elseif ($cache->startDataCache()) {

            $companies = self::getListByUserId($user_id);

            if (!isset($companies[0])) {
                // ищем в привязанных департаментах
                $departments = Department::getListAttachByUserId($user_id);

                $companies[0] = self::getById($departments[0]['PROPERTIES']['COMPANY']['VALUE'] ?? 0);
            }

            if ($companies[0]) {
                $fields['FIELDS'] = $companies[0]['FIELDS'];
                $fields['PROPERTIES'] = $companies[0]['PROPERTIES'];
                // if (!$arIsAuth['roles']['admin']) {
                //     unset($fields['PROPERTIES']['tg_token'], $fields['PROPERTIES']['tg_chat_id']);
                // }
            }

            $cache->endDataCache($fields);
        }
        if (!empty($fields)) {
            return $fields;
        }

        return false;
    }
    public static function get($user_id = 0)
    {
        if (!is_numeric($user_id) || $user_id === 0) {
            $arIsAuth = User::isAuth($_REQUEST);
            if (!$arIsAuth['isAuth']) {
                return Helpers::$arErrMesAuth;
            }
            $user_id = $arIsAuth['user_id'];
        }

        $fields = [];
        $cache = Cache::createInstance();
        $cacheId = 'getCompanyByCurUser' . md5(serialize($_REQUEST)) . md5(serialize($user_id));
        if ($cache->initCache(Helpers::$cache_time_long, $cacheId, 'custom_cache')) {
            $fields = $cache->getVars();
        } elseif ($cache->startDataCache()) {

            $companies = self::getListByUserId($user_id);

            if (!isset($companies[0])) {
                // ищем в привязанных департаментах
                $departments = Department::getListAttachByUserId($user_id);

                $companies[0] = self::getById($departments[0]['PROPERTIES']['COMPANY']['VALUE'] ?? 0);
            }

            if ($companies[0]) {
                $fields['FIELDS'] = $companies[0]['FIELDS'];
                $fields['PROPERTIES'] = $companies[0]['PROPERTIES'];
                // if (!$arIsAuth['roles']['admin']) {
                //     unset($fields['PROPERTIES']['tg_token'], $fields['PROPERTIES']['tg_chat_id']);
                // }
            }

            $cache->endDataCache($fields);
        }
        if (!empty($fields)) {
/*            $company_id = $fields["FIELDS"]["ID"];
            $code = substr(md5(rand()), 0, 70);
            $fields["telegram_key"] = $code;
            CIBlockElement::SetPropertyValuesEx($company_id, 1, array("telegram_check_string" => "$code"));
*/
            return $fields;
        }

        return false;
    }

    // обновление компании
    public static function update($request)
    {
        $result = [];
        $curUser = User::isAuth($_REQUEST);
        if (!$curUser['isAuth']) {
            return Helpers::getError(Helpers::$errorAuth);
        }
        if (!$curUser['roles']['admins']) {
            return Helpers::getError('У Ваc нет прав на изменение компании');
        }
        if (!$request['company_id']) {
            return Helpers::getError('Не указан ID компании');
        }
        $companyFields = self::get();

        if ($request['company_id'] != $companyFields['FIELDS']['ID']) {
            return Helpers::getError('Неверно указан ID компании');
        }

        $el = new CIBlockElement;
        $arLoadProductArray = [];

        if (!empty($request['company_name'])) {
            $arLoadProductArray['NAME'] = $request['company_name'];
        }

        foreach ($companyFields['PROPERTIES'] as $prop) {
                $arLoadProductArray['PROPERTY_VALUES'][$prop['ID']] =
                    $request['company_prop_' . mb_strtolower($prop['CODE'])] ?? $prop['VALUE'];
        }
        //return [$arLoadProductArray];
        if ($el->Update($request['company_id'], $arLoadProductArray)) {
            $result['status'] = 'success';
            $result['message'] = 'Компания изменена';
        } else {
            $result['status'] = 'error';
            $result['message'] = $el->LAST_ERROR;
        }

        return $result;
    }
}
