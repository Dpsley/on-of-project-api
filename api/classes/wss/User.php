<?php

namespace Wss;

use Bitrix\Main\Context;
use Bitrix24;
use Bitrix\Main\Mail\Event;
use Bitrix\Main\UserTable;
use CIBlockElement;
use CModule;
use CUser;
use \Bitrix\Main\Application;
use \Bitrix\Main\Request;
use Wss\Helper;
use Wss\Telegram\Telegram\Methods\ForHelp as ForHelp;

/**
 * TODO Сделать рефакторинг т.к.большая часть методов класса скопирована из старого api
 */

class User
{
    static public array $arErrMesAuth = ['status' => 'error', 'message' => 'Ошибка проверки авторизации'];
    static public string $errMesAuth = 'Ошибка проверки авторизации';

    public static function generatePassword($length = 10) // генератор паролей
    {
        $chars = 'qazxswedcvfrtgbnhyujmkiolp1234567890QAZXSWEDCVFRTGBNHYUJMKIOLP';
        $size = strlen($chars) - 1;
        $password = '';
        while ($length--) {
            $password .= $chars[random_int(0, $size)];
        }
        return $password;
    }

    // получение данных юзера по Email, Login или ID
    public static function getFields($userLogin)
    {
        $filter = [
            [
                "LOGIC" => "OR",
                [
                    "LOGIN" => $userLogin // на случай если передали логин, а не Email
                ],
                [
                    "EMAIL" => $userLogin
                ],
                [
                    "ID" => $userLogin
                ]
            ]
        ];
        $rsUsers = UserTable::getList(
            [
                "select" => ['*'],
                "filter" => $filter,
            ]
        );
        if ($arUser = $rsUsers->Fetch()) {
            return $arUser;
        }
        return false;
    }

    public static function getRoles($user_id = 0): array
    {

        global $USER;
        if ($user_id === 0) {
            $arIsAuth = User::isAuth($_REQUEST);
            if (!$arIsAuth['isAuth']) {
                return self::$arErrMesAuth;
            }
            $user_id = $arIsAuth['user_id'];
        }

        $result = [];

        $result['roles']['super_admin'] = in_array(1, CUser::GetUserGroup($user_id));
        $result['roles']['admins'] = false;
        $result['roles']['controllers'] = false;
        $result['roles']['marketers'] = false;
        $result['roles']['rops'] = false;
        $result['roles']['users'] = false;
        $result['roles']['moneys'] = false;
        $result['roles']['chef'] = false;
        //проверяем роли пользователя
        CModule::IncludeModule('iblock');

        // передадим id юзера во избежание зацикливания метода isAuth
        $company_id = "";
        $arSelect = array();
        $arFilter = Array(
            "IBLOCK_ID" => 1,
            "ACTIVE_DATE"=>"Y",
            "ACTIVE"=>"Y",
            [
                "LOGIC" => "OR",
                ["PROPERTY_admins" => $user_id],
                ["PROPERTY_chef" => $user_id],
                ["PROPERTY_controllers" => $user_id],
                ["PROPERTY_marketers" => $user_id],
                ["PROPERTY_rops" => $user_id],
                ["PROPERTY_users" => $user_id],
                ["PROPERTY_moneys" => $user_id],
            ]
        );
        $res = CIBlockElement::GetList(Array(), $arFilter, false, "", $arSelect);
        $conractors = array();
        while($ob = $res->GetNextElement()){
            $arFields = $ob->GetFields();
            $company_id = $arFields['ID'];
        }
        // ищем в компаниях


        /**$arSelect = Array("*");
        $arFilter = Array("IBLOCK_ID" => Iblock::$iblockCompanies);
        $res = CIBlockElement::GetList(Array(), $arFilter, false, Array("nPageSize"=>50), $arSelect);
         */
        $rsC = CIBlockElement::GetList(
            [],
            [
                "IBLOCK_ID" => Iblock::$iblockCompanies,
                "ACTIVE" => "Y",
                "ID" => $company_id,
                [
                    "LOGIC" => "OR",
                    ["PROPERTY_admins" => $user_id],
                    ["PROPERTY_chef" => $user_id],
                    ["PROPERTY_controllers" => $user_id],
                    ["PROPERTY_marketers" => $user_id],
                    ["PROPERTY_rops" => $user_id],
                    ["PROPERTY_users" => $user_id],
                    ["PROPERTY_moneys" => $user_id],
                ]
            ],
            false,
            false,
            ["ID", "IBLOCK_ID"]
        );

        if ($obC = $rsC->GetNextElement()) {
            $arPropsC = $obC->GetProperties();
            //return $arPropsC;
            if (in_array($user_id, $arPropsC['admins']['VALUE'])) {
                $result['roles']['admins'] = true;
            }
            if (in_array($user_id, $arPropsC['chef']['VALUE'])) {
                $result['roles']['chef'] = true;
            }
            if (in_array($user_id, $arPropsC['controllers']['VALUE'])) {
                $result['roles']['controllers'] = true;
            }
            if (in_array($user_id, $arPropsC['marketers']['VALUE'])) {
                $result['roles']['marketers'] = true;
            }
            if (in_array($user_id, $arPropsC['rops']['VALUE'])) {
                $result['roles']['rops'] = true;
            }
            if (in_array($user_id, $arPropsC['users']['VALUE'])) {
                $result['roles']['users'] = true;
            }
            if (in_array($user_id, $arPropsC['moneys']['VALUE'])) {
                $result['roles']['moneys'] = true;
            }

        }
        return $result;

    }

    public static function getSalons($user_id = 0) : array
    {
        if(is_array($user_id)){
            if($user_id["id"]){}else{
                $user_id = 0;
            }
        }

        if ($user_id === 0) {
            $arIsAuth = User::isAuth($_REQUEST);
            if (!$arIsAuth['isAuth']) {
                return self::$arErrMesAuth;
            }
            $user_id["roles"] = $arIsAuth['roles'];
            $user_id = CUser::GetID();

        }

        $result = [];

        //проверяем роли пользователя
        CModule::IncludeModule('iblock');

        // передадим id юзера во избежание зацикливания метода isAuth
        $company_id = Company::getId($user_id);

        // ищем в департаментах
        $rsD = \CIBlockElement::GetList(
            array(),
            array(
                "IBLOCK_ID" => 4,
                "ACTIVE" => "Y",
                "PROPERTY_company" => $company_id,
            ),
            false,
            false,
            array('ID', 'NAME', 'IBLOCK_ID')
        );
        $i = 0;

        $test = self::getRoles($user_id);

        while ($obD = $rsD->GetNextElement()) {
            $arFieldsD = $obD->GetFields();
            $arPropsD = $obD->GetProperties();

            $flag = false;
            if ($test["roles"]['admins'] || $test["roles"]['chef']) {
                $flag = true;
            }
            if (in_array($user_id, $arPropsD['rops']['VALUE'])) {
                $flag = true;
            }
            if (in_array($user_id, $arPropsD['users']['VALUE'])) {
                $flag = true;
            }
            if (in_array($user_id, $arPropsD['moneys']['VALUE'])) {
                $flag = true;
            }
            if (in_array($user_id, $arPropsD['controllers']['VALUE'])) {
                $flag = true;
            }
            if (!$result['roles']['admins'] && !$result['roles']['controllers'] && !$result['roles']['marketers']) {
                $result[$i]["status"] = $flag;
                $result[$i]["label"] = $arFieldsD["NAME"];
                $result[$i]["id"] = $arFieldsD["ID"];
            }
            $i++;
            //return $result;
        }

        return $result;
    }

    // проверка авторизации по логину и хешу
    public static function isAuth($request): array
    {
        $result = [];
        global $USER;
        if ($request['email'] && $request['token']) {
            $login = self::getFields($request['email'])['LOGIN'];
            if (!$login && $request['user_id']) {
                $rsUser = CUser::GetByID($request['user_id']);
                $arUser = $rsUser->Fetch();
            }

            $result['isAuth'] = false;
            if ($login || $arUser['LOGIN']) {
                $USER = new CUser;
                $result['isAuth'] = $USER->LoginByHash($login ?: $arUser['LOGIN'], $request['token']);
                if ($result['isAuth'] !== true) {
                    $result['isAuth'] = false;
                }

                $rsUser = CUser::GetByID($USER->GetID());
                $arUser = $rsUser->Fetch();
            }

            if ($result['isAuth'] === true) {
                $result['status'] = 'success';
                $result['user_id'] = $arUser['ID'];
                $result['message'] = 'Авторизация успешна';
                //$result['fields'] = $arUser;
                $result['fields']['ID'] = $arUser['ID'];
                $result['fields']['LOGIN'] = $arUser['LOGIN'];
                $result['fields']['NAME'] = $arUser['NAME'];
                $result['fields']['LAST_NAME'] = $arUser['LAST_NAME'];
                $result['fields']['EMAIL'] = $arUser['EMAIL'];
                if ($arUser['PERSONAL_PHONE'] || $arUser['PERSONAL_MOBILE'] || $arUser['PHONE_NUMBER']) {
                    $result['fields']['PHONE'] = $arUser['PERSONAL_PHONE'] ?: ($arUser['PERSONAL_MOBILE'] ?: $arUser['PHONE_NUMBER']);
                }
            } else {
                $result['status'] = 'error';
                $result['message'] = 'Авторизация не удалась';
            }

            if ($result['status'] != 'error') {
                $result['department'] = '';

                // передадим id юзера во избежание зацикливания метода isAuth
                $company_id = ForHelp::getElement($result['fields']['ID']);
                if($company_id) {
                    $result['billing'] = Billing::checkByCompanyId($company_id);
                }else{
                    $result['status'] = 'error';
                    $result['message'] = 'Вы не состоите в компании';
                }
                $result = array_merge($result, self::getRoles($result['fields']['ID']));
            }
        } else {
            $result['status'] = 'error';
            $result['message'] = 'Не указаны обязательные поля';
        }

        return $result;
    }

    public static function auth($request): array // авторизация
    {
        $result = [];
        global $USER;
        if ($request['email'] && $request['password']) {
            //if (!is_object($USER)) $USER = new \CUser;
            $login = self::getFields($request['email'])['LOGIN'];
            $arAuthResult = $USER->Login($login, $request['password'], "Y");
            if ($arAuthResult === true) {
                $result['status'] = 'success';
                $result['message'] = 'Авторизация успешна';
                $result['token'] = $USER->GetSessionHash();
            } else {
                $result['status'] = 'error';
                $result['message'] = $arAuthResult['MESSAGE'];
            }
        } else {
            $result['status'] = 'error';
            $result['message'] = 'Не указаны обязательные поля';
        }
        $zagl = (new User)->getzagl($request["email"],$result);
        return $result;
    }

    public function getzagl($request, $token){
        $id = ForHelp::getUserID($request);
        if (!$id) {
            return null;
        }
        $salons = self::getSalons($id);
        if (!$salons) {
            return null;
        }
        $_REQUEST["id"] = $id;
        $_REQUEST["email"] = $request;
        $_REQUEST["token"] = $token["token"];
        $_REQUEST["date"] = date("d.m.Y");
        $_REQUEST['helper'] = "getPlanByDate";
        $getPlanByDate = Iblock::getPlanByDate($_REQUEST['date']);
        $_REQUEST['helper'] = "getPlanByDateForDeals";
        $getPlanByDateForDeals = Iblock::getPlanByDate($_REQUEST['date']);
        $_REQUEST['helper'] = "getPrimaryDeal";
        $getPrimaryDeal = Iblock::getPlanByDate($_REQUEST['date']);
        $_REQUEST['helper'] = "getSecondaryDeal";
        $getSecondaryDeal = Iblock::getPlanByDate($_REQUEST['date']);
        $_REQUEST['helper'] = "getPrimaryKEV";
        $getPrimaryKEV = Iblock::getPlanByDate($_REQUEST['date']);
        $_REQUEST['helper'] = "getKEVDeal";
        $getKEVDeal = Iblock::getPlanByDate($_REQUEST['date']);
        $GetFactByDate = Iblock::getFactByDate($_REQUEST);
        $getTreatiesByDate = Iblock::getTreatiesByDate($_REQUEST);

        $preflight["getPrimaryDeal"] = $getPrimaryDeal;
        $preflight["getSecondaryDeal"] = $getSecondaryDeal;
        $preflight["getPrimaryKEV"] = $getPrimaryKEV;
        $preflight["getKEVDeal"] = $getKEVDeal;

        $save_package = [];

//return $salons;
            foreach($preflight as $key => $value){
            foreach($salons as $key2 => $value2){
                if(empty($preflight[$key]["data"][$value2["id"]])){
                    if($key == "getPrimaryDeal") {
                        $preflight[$key]["data"][$value2["id"]] = [
                            "department" => $value2["id"],
                            "price" => 15
                        ];
                    }elseif($key == "getSecondaryDeal"){
                        $preflight[$key]["data"][$value2["id"]] = [
                            "department" => $value2["id"],
                            "price" => 45
                        ];
                    }elseif($key == "getPrimaryKEV"){
                        $preflight[$key]["data"][$value2["id"]] = [
                            "department" => $value2["id"],
                            "price" => 30
                        ];
                    }elseif($key == "getKEVDeal"){
                        $preflight[$key]["data"][$value2["id"]] = [
                            "department" => $value2["id"],
                            "price" => 77
                        ];
                    }
                }
            }
        }
        //return $preflight;
        foreach($preflight as $key => $value){
            foreach($value["data"] as $key2 => $value2){
                if(!$save_package[$key][$value2["department"]]) {
                    if ($value2["price"] == "getPrimaryDeal") {
                        if($key == "getPrimaryDeal") {
                            $save_package[$key][$value2["department"]] = 15;
                        }elseif($key == "getSecondaryDeal"){
                            $save_package[$key][$value2["department"]] = 45;
                        }elseif($key == "getPrimaryKEV"){
                            $save_package[$key][$value2["department"]] = 30;
                        }elseif($key == "getKEVDeal"){
                            $save_package[$key][$value2["department"]] = 77;
                        }
                    } else {
                        $save_package[$key][$value2["department"]] = $value2["price"];
                    }
                }
            }
        }
        //return $save_package;
        foreach($save_package as $key => $value) {
            foreach ($value as $key2 => $value2) {
                $save_package[$key]["plans"][] = [
                    "departament_id" => $key2,
                    "price" => $value2,
                    ];
                unset ($save_package[$key][$key2]);
            }
        }
        //return $save_package;
        $_REQUEST['helper'] = "addPrimaryDeal";
        $addPrimaryDeal =  Iblock::addConvers($_REQUEST['date'], $save_package["getPrimaryDeal"]['plans']);
        $_REQUEST['helper'] = "addSecondaryDeal";
        $addSecondaryDeal = Iblock::addConvers($_REQUEST['date'], $save_package["getSecondaryDeal"]['plans']);
        $_REQUEST['helper'] = "addPrimaryKEV";
        $addPrimaryKEV = Iblock::addConvers($_REQUEST['date'], $save_package["getPrimaryKEV"]['plans']);
        $_REQUEST['helper'] = "addKEVDeal";
        $addKEVDeal = Iblock::addConvers($_REQUEST['date'], $save_package["getKEVDeal"]['plans']);

        return $addKEVDeal;
    }

    public static function registration($request): array // регистрация
    {
        $result = [];

        if ($request['company_name'] && $request['niche_id']) {
            $user = new CUser;

            // генерация случайной строки
            $confirm_code = substr(md5(time()), 0, 8);

            $arFields = [
                "NAME" => $request['name'],
                "LAST_NAME" => $request['last_name'],
                "EMAIL" => $request['email'],
                "LOGIN" => $request['email'],
                "LID" => "ru",
                "ACTIVE" => "N",
                "GROUP_ID" => [2],
                "PASSWORD" => $request['password1'],
                "CONFIRM_PASSWORD" => $request['password2'],
                "CONFIRM_CODE" => $confirm_code,
            ];

            $user_id = $user->Add($arFields);
            if ((int)$user_id > 0) {
                $request['password'] = $request['password1'];

                $result['status'] = 'success';
                $result['message'] = 'Вам отправлено письмо на указанную электронную почту для подтверждения регистрации.';

                if ($request['niche_id']) {
                    $result['company'] = Iblock::createCompany($request, $user_id);
                    $result['company_id'] = $result['company']['company_id'];

                    Billing::addDemo($result['company_id']);
                }

                $result['send_confirmation'] = self::sendConfirmation($user_id);
            } else {
                $result['status'] = 'error';
                $result['message'] = str_replace('<br>', '', $user->LAST_ERROR);
            }
        } elseif (!$request['company_name'] && $request['niche_id']) {
            $result['status'] = 'error';
            $result['message'] = 'Не указано "Название компании (company_name)"';
        } elseif (!$request['niche_id'] && $request['company_name']) {
            $result['status'] = 'error';
            $result['message'] = 'Не указана "Ниша (niche_id)"';
        } else {
            $result['status'] = 'error';
            $result['message'] = 'Не указаны "Название компании (company_name)" и "Ниша (niche_id)"';
        }

        return $result;
    }

    // отправка письма с кодом подтверждения регистрации
    public static function sendConfirmation($user): array
    {
        $result = [];
        $arUser = self::getFields($user['email'] ?? $user);

        if (!$arUser['EMAIL']) {
            return ['status' => 'error', 'message' => 'E-mail адрес не найден'];
        }
        if (filter_var($arUser['EMAIL'], FILTER_VALIDATE_EMAIL) === false) {
            return ['status' => 'error', 'message' => 'Некорректный формат E-mail адреса'];
        }

        if ($arUser['CONFIRM_CODE']) {
            Event::sendImmediate(
                [
                    "EVENT_NAME" => "CONFIRM_CODE",
                    "LID" => "s1",
                    "MESSAGE_ID" => 5,
                    "C_FIELDS" => [
                        "USER_ID" => $arUser['ID'],
                        "EMAIL" => $arUser['EMAIL'],
                        "CONFIRM_CODE" => $arUser['CONFIRM_CODE']
                    ],
                ]
            );
            $result['status'] = 'success';
            $result['message'] = 'Код подтверждения регистрации отправлен на указанную E-mail почту';
        } else {
            $result['status'] = 'error';
            $result['message'] = 'Почта уже подтверждена';
        }

        return $result;
    }

    // подтверждение регистрации кодом
    public static function confirmationReg($user, $code = ''): array
    {
        $result = [];
        $arUser = self::getFields($user['email'] ?? $user);

        if (!$arUser['CONFIRM_CODE']) {
            return ['status' => 'error', 'message' => 'Почта уже подтверждена'];
        }

        if ($arUser['CONFIRM_CODE'] == ($user['code'] ?? $code)) {
            $user = new CUser;
            $fields = [
                "ACTIVE" => "Y",
                "CONFIRM_CODE" => false // удалим код подтверждения
            ];
            if ($user->Update($arUser['ID'], $fields)) {
                $result['status'] = 'success';
                $result['message'] = 'Почта успешно подтверждена. Теперь Вы можете авторизоваться.';
                $group = Telegram::getElement($arUser["ID"]);
                $groupname = "";
                $obElement = CIBlockElement::GetByID($group);
                if($arEl = $obElement->GetNext())
                    $groupname = $arEl["NAME"];
                $CompanyCreate = \Wss\Bitrix24::CompanyAdd($arUser, $groupname);
                $companyid = json_decode($CompanyCreate,true);
                $ContactCreate = \Wss\Bitrix24::ContactAdd($arUser, $companyid["result"]);
                $Contactid = json_decode($ContactCreate,true);
                $GLOBALS["USER_FIELD_MANAGER"]->Update("USER", $arUser['ID'], Array("UF_B24_ID"=>$Contactid["result"]));
                $ELEMENT_ID = 18;  // код элемента
                $PROPERTY_CODE = "b24_id";  // код свойства
                CIBlockElement::SetPropertyValuesEx($group, 1, array($PROPERTY_CODE => $companyid));
                CIBlockElement::SetPropertyValuesEx($group, 1, array("user_crm_id" => $Contactid));
            } else {
                $result['status'] = 'error';
                $result['message'] = str_replace('<br>', '', $user->LAST_ERROR);
            }
        } else {
            $result['status'] = 'error';
            $result['message'] = 'Код подтверждения неверный';
        }

        return $result;
    }

    // отправка кода для восстановления пароля
    public static function sendRecoveryPassword($request): array
    {
        $userFields = self::getFields($request['user_email']);
        if ($userFields) {
            global $USER;

            $result = $USER::SendPassword($userFields['LOGIN'], $userFields['EMAIL']);

            if ($result['TYPE'] == 'OK') {
                return Helpers::getSuccess('На вашу почту отправлены инструкции по смене пароля');
            }else{
                return $USER::SendPassword($userFields['LOGIN'], $userFields['EMAIL']);
            }
        } else {
            return Helpers::getError('Указанный E-mail в системе не найден');
        }
    }

    // изменение пароля с помощью старого пароля или кода
    public static function changePassword($request): array
    {
        $userFields = self::getFields($request['user_email']);
        if ($userFields) {
            global $USER;
            $authResult = $USER->Login($userFields['LOGIN'], $request['user_code']);
            if ($authResult['TYPE'] == 'ERROR') {
                $result = $USER->ChangePassword(
                    $userFields['LOGIN'],
                    $request['user_code'],
                    $request['user_password_new_1'],
                    $request['user_password_new_2']
                );
                if ($result["TYPE"] == "OK") {
                    return Helpers::getSuccess('Пароль успешно изменен');
                }

                return Helpers::getError($result['MESSAGE']);
            }

            if ($request['user_code'] === $request['user_password_new_2']) {
                return Helpers::getError('Старый и новый пароли должны различаться!');
            }
            $fields['PASSWORD'] = $request['user_password_new_1'];
            $fields['CONFIRM_PASSWORD'] = $request['user_password_new_2'];

            $user = new CUser;
            $user->Update($userFields['ID'], $fields);
            if($user->LAST_ERROR){
                return Helpers::getError($user->LAST_ERROR);
            }

            return Helpers::getError('Пароль успешно изменен');
        }
        return Helpers::getError('Указанный E-mail в системе не найден');
    }

    public static function add($request): array
    {
        return self::addUser($request);
    }

    /**
     * @deprecated
     * TODO перенести в метод add после отключения его от фронта, согласовать этот вопрос с Михаилом
     */
    public static function addUser($request): array
    { // добавление пользователя администратором компании
        $result = [];
        $request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
        $input = array();
        $input = json_decode($request->getInput());
        $input = Helpers::object_to_array($input);
        $arIsAuth = self::isAuthV3($input);
        if (!$arIsAuth['isAuth']) {
            return $arIsAuth;
        }
        //return $arIsAuth;

        if (!$arIsAuth['roles']['admins']) {
            return ['status' => 'error', 'message' => 'У Вам нет прав на добавление/изменение пользователей'];
            http_response_code(403);
        }
        if (!$arIsAuth['billing']['active']) {
            return Billing::$arErrMessBilling;
        }

        /*if (!$input['user_type']) {
            $input['user_type'] = 'user';
        }*/

        $company_id = Company::getIdV2();
        if (!$company_id) {
            return ['status' => 'error', 'Текущий администратор не привязан ни к одной компании'];
            http_response_code(409);
        }

//        if(count($request['users']) == 0) return array('status'=>'error','message'=>'Не указаны подразделения (names)');

        $user = new CUser;


//        $event_iblock = \Wss\Iblock::getCurUserEventIblockCompany();// получим инфоблок событий компании
        if (!$input['user_email'] && !$input['user_id']) {
            return ['status' => 'error', 'Не указан E-mail адрес'];
            http_response_code(400);
        }
        if (!filter_var($input['user_email'], FILTER_VALIDATE_EMAIL) && $input['user_email']) {
            return ['status' => 'error', 'E-mail адрес указан некорректно'];
            http_response_code(400);
        }

        $password = self::generatePassword(16);// сгенерируем пароль

        /** Права для инфоблоков:
         * 33 - нет доступа
         * 34 - чтение
         * 39 - изменение
         * 40 - полный доступ
         */
        $new_user_id = false;
        $company_users = Iblock::getUsersCompany();
        if (!$input['user_id']) {
            $arFields = [
                "NAME" => $input['user_name'] ?: $input['user_email'],
                "LAST_NAME" => $input['user_last_name'] ?: "",
                "EMAIL" => $input['user_email'],
                "LOGIN" => $input['user_email'],
                "LID" => "ru",
                "ACTIVE" => "Y",
                "GROUP_ID" => [2],
                "PASSWORD" => $password,
                "CONFIRM_PASSWORD" => $password
            ];
            $new_user_id = $user->Add($arFields);
            //return [$new_user_id];
        }
        if ($input['user_id'] > 0) {
            $arFields = [
                "NAME" => $input['user_name'] ?: $input['user_email'],
                "EMAIL" => $input['user_email'],
                "LOGIN" => $input['user_email'],
            ];
            $user->Update($input['user_id'], $arFields);
            $new_user_id = $input['user_id'];
        }
        //return [$new_user_id];

        if (!$new_user_id) {
            http_response_code(400);
            return [
                'status' => 'error',
                //'message' => str_replace('<br>', '', $user->LAST_ERROR),
                    'message' => "Данная почта уже зарегистрирована"
            ];

        }
        foreach($input["roles"] as $key => $value) {
            if($key != "super_admin") {
                if (CModule::IncludeModule("iblock")) {
                    $VALUES = array();
                    $res = CIBlockElement::GetProperty(1, $company_id, array("sort" => "asc"), array("CODE" => $key));
                    while ($ob = $res->GetNext()) {
                        $VALUES[] = $ob['VALUE'];
                    }
                }

                $num = array_search($new_user_id, $VALUES);
                //return $num;
                $is_here = false;
                $key_in_array = 0;
                foreach($VALUES as $key2 => $value2){
                    if($value2 == $new_user_id){
                        $key_in_array = $key2;
                        $is_here = true;
                    }
                }
                if($value === true){
                    if($is_here === false) {
                        $VALUES[] = $new_user_id;
                    }

                }else{
                    if($is_here){
                        $VALUES[$key_in_array] = 0;
                    }elseif(!isset($VALUES[0])){
                        $VALUES[0] = 0;
                    }else{

                    }
                }
                //return $company_id;
                CIBlockElement::SetPropertyValuesEx($company_id, 1, array("$key" => $VALUES));
                       //CIBlockElement::SetPropertyValuesEx($company_id, false, ['users' => $company_users['users']]);

            }
        }
        $a = [
            "users", "rops", "controllers", "moneys"
        ];
        foreach ($input["salons"] as $key => $value) {
            foreach ($input["roles"] as $key2 => $value2) {
                if (CModule::IncludeModule("iblock")) {
                    if (in_array($key2, $a)) {
                        //return $key2;
                        $VALUES = array();
                        $res = CIBlockElement::GetProperty(4, $value["id"], array("sort" => "asc"), array("CODE" => $key2));
                        while ($ob = $res->GetNext()) {
                            $VALUES[] = $ob['VALUE'];
                        }

                        $num = array_search($new_user_id, $VALUES);
                        // return $VALUES;
                        $is_here = false;
                        $key_in_array = 0;
                        foreach ($VALUES as $key3 => $value2) {
                            if ($value2 == $new_user_id) {
                                $key_in_array = $key3;
                                $is_here = true;
                            }
                        }

                        if ($value["status"] === true && $input["roles"][$key2] === true) {
                            if ($is_here === false) {
                                $VALUES[] = (int)$new_user_id;
                            }

                        } else {
                            if ($is_here) {
                                $VALUES[$key_in_array] = 0;
                            } elseif (!isset($VALUES[0])) {
                                $VALUES[0] = 0;
                            } else {
                                $VALUES = $VALUES;
                            }

                        }
                        $result[$value["id"]][$key2] = $VALUES;
                        if ($key2 == "rops") {
                            //return $VALUES;
                            $result[$value["id"]]["rops"] = $VALUES;
                            CIBlockElement::SetPropertyValuesEx($value["id"], 4, array("rops" => $VALUES));
                        }
                        if ($key2 == "users") {
                            $result[$value["id"]]["users"] = $VALUES;
                            CIBlockElement::SetPropertyValuesEx($value["id"], 4, array("users" => $VALUES));
                        }
                        if ($key2 == "moneys") {
                            $result[$value["id"]]["moneys"] = $VALUES;
                            CIBlockElement::SetPropertyValuesEx($value["id"], 4, array("moneys" => $VALUES));
                        }
                        if ($key2 == "controllers") {
                            $result[$value["id"]]["controllers"] = $VALUES;
                            CIBlockElement::SetPropertyValuesEx($value["id"], 4, array("controllers" => $VALUES));
                        }

                    }
                }
            }
        }
       /*
        if (in_array($input['roles'], ['admin', 'controller', 'marketer', 'rop', 'user'])) {
            if (($key = array_search($new_user_id, $company_users['admins'])) !== false) {
                unset($company_users['admins'][$key]);
                CIBlockElement::SetPropertyValuesEx($company_id, false, ['admins' => $company_users['admins']]);
            }
            if (($key = array_search($new_user_id, $company_users['controllers'])) !== false) {
                unset($company_users['controllers'][$key]);
                CIBlockElement::SetPropertyValuesEx($company_id, false, ['controllers' => $company_users['controllers']]
                );
            }
            if (($key = array_search($new_user_id, $company_users['marketers'])) !== false) {
                unset($company_users['marketers'][$key]);
                CIBlockElement::SetPropertyValuesEx($company_id, false, ['marketers' => $company_users['marketers']]);
            }
            if (($key = array_search($new_user_id, $company_users['rops'])) !== false) {
                unset($company_users['rops'][$key]);
                CIBlockElement::SetPropertyValuesEx($company_id, false, ['rops' => $company_users['rops']]);
            }
            if (($key = array_search($new_user_id, $company_users['users'])) !== false) {
                unset($company_users['users'][$key]);
                CIBlockElement::SetPropertyValuesEx($company_id, false, ['users' => $company_users['users']]);
            }
            if ($input['user_type'] == 'admin') {
                Iblock::setRightsEventIblock(['user_id' => $new_user_id, 'task_id' => 39]
                );// установим права на изменение
                $company_users['admins'][] = $new_user_id;
                CIBlockElement::SetPropertyValuesEx($company_id, false, ['admins' => $company_users['admins']]
                ); // привяжем админа к компании
            }
            if ($input['user_type'] == 'controller') {
                Iblock::setRightsEventIblock(['user_id' => $new_user_id, 'task_id' => 39]
                );// установим права на изменение
                $company_users['controllers'][] = $new_user_id;
                CIBlockElement::SetPropertyValuesEx($company_id, false, ['controllers' => $company_users['controllers']]
                ); // привяжем админа к компании
            }
            if ($input['user_type'] == 'marketer') {
                Iblock::setRightsEventIblock(['user_id' => $new_user_id, 'task_id' => 34]);// установим права на чтение
                $company_users['marketers'][] = $new_user_id;
                CIBlockElement::SetPropertyValuesEx($company_id, false, ['marketers' => $company_users['marketers']]
                ); // привяжем маркетолога к компании
            }
            if ($input['user_type'] == 'rop') {
                Iblock::setRightsEventIblock(['user_id' => $new_user_id, 'task_id' => 34]);// установим права на чтение
                $company_users['rops'][] = $new_user_id;
                CIBlockElement::SetPropertyValuesEx($company_id, false, ['rops' => $company_users['rops']]
                ); // привяжем маркетолога к компании
            }
            if ($input['user_type'] == 'user') {
                Iblock::setRightsEventIblock(['user_id' => $new_user_id, 'task_id' => 34]);// установим права на чтение
                $company_users['users'][] = $new_user_id;
                CIBlockElement::SetPropertyValuesEx($company_id, false, ['users' => $company_users['users']]
                ); // привяжем маркетолога к компании
            }
        }
*/
        if ($result['status'] != 'error') {
            if (!$input['user_id']) {
                Event::sendImmediate(
                    [
                        "EVENT_NAME" => "NEW_USER",
                        "LID" => "s1",
                        "MESSAGE_ID" => 11,
                        "C_FIELDS" => [
                            "ROLE" => "Администратор",
                            "EMAIL" => $input['user_email'],
                            "PASSWORD" => $password
                        ],
                    ]
                );

                $result['status'] = 'success';
                $result['message'] = 'Пользователь успешно добавлен. Логин и пароль отправлены на указанный E-mail адрес.';
                http_response_code(201);
            } else {
                $result['status'] = 'success';
                $result['message'] = 'Пользователь успешно обновлен.';
                http_response_code(201);
            }
        }
        return $result;
    }

    // удаление (деактивация) пользователя по id
    public static function removeUser($request): array
    {
        $result = [];
        $userId = $request['user_id'] ?? 0;
        $arIsAuth = self::isAuth($_REQUEST);
        if (!$arIsAuth['isAuth']) {
            return self::$arErrMesAuth;
        }
        if (!$arIsAuth['billing']['active']) {
            return Billing::$arErrMessBilling;
        }

        if ($userId !== $arIsAuth['user_id']) {
            if ($arIsAuth['roles']['admins']) {
                $company_id = Company::getId();
                $company_id_by_user_id = Company::getId($userId);
                if (!$company_id) {
                    return ['status' => 'error', 'message' => 'Не определена компания текущего администратора'];
                }

                if (!$company_id_by_user_id) {
                    return ['status' => 'error', 'message' => 'Не определена компания удаляемого пользователя'];
                }

                if ($company_id == $company_id_by_user_id) {
                    $userFields = self::getFields($userId);
                    $user = new CUser;
                    $fields = [
                        "ACTIVE" => "N",
                        "EMAIL" => 'blocked.' . $userFields['EMAIL'],
                        "LOGIN" => 'blocked.' . $userFields['LOGIN'],
                    ];
                    if ($user->Update($userId, $fields)) {
                        $result['status'] = 'success';
                        $result['message'] = 'Пользователь успешно удален (деактивирован).';
                    } else {
                        $result['status'] = 'error';
                        $result['message'] = str_replace('<br>', '', $user->LAST_ERROR);
                    }
                } else { // если пользователь из другой компании
                    return ['status' => 'error', 'message' => 'У Вас нет прав на удаление пользователя'];
                }
            } else {
                return ['status' => 'error', 'message' => 'У Вам нет прав на удаление пользователя'];
            }
        } else {
            return ['status' => 'error', 'message' => 'Вы не можете удалить сами себя'];
        }

        return $result;
    }

    public static function addUserToDepartment($request): array
    {
        if (!$request['user_id']) {
            return ['status' => 'error', 'message' => 'Не указан id пользователя'];
        }
        if (!$request['department'] && $request['department'] != 0) {
            return ['status' => 'error', 'message' => 'Не указан департамент'];
        }

        $arIsAuth = self::isAuth($_REQUEST);
        if (!$arIsAuth['isAuth']) {
            return self::$arErrMesAuth;
        }
        if (!$arIsAuth['billing']['active']) {
            return Billing::$arErrMessBilling;
        }

        $departmentsId = [];

//        $companyId = Iblock::getCompanyIdByCurUser($request['user_id']);
        $companyId = Company::getId($request['user_id']);
        if ($companyId === false) {
            return ['status' => 'error', 'message' => 'Пользователь не найден ни в одной компании'];
        }

        $rsD = CIBlockElement::GetList(
            [],
            [
                "IBLOCK_ID" => Department::getIblockId(),
                "ACTIVE" => "Y",
                "PROPERTY_company" => $companyId,
            ],
            false,
            false,
            ["ID", "NAME", "IBLOCK_ID"]
        );
        while ($obD = $rsD->GetNextElement()) {
            $arFieldsD = $obD->GetFields();
            $arPropsD = $obD->GetProperties();
            $departmentsId[$arFieldsD['ID']] = [
                'id' => $arFieldsD['ID'],
                'name' => $arFieldsD['NAME'],
                'rop' => $arPropsD['rop']['VALUE'],
                'users' => $arPropsD['users']['VALUE'],
                '$arPropsD' => $arPropsD,
            ];
        }

        if (!$departmentsId[$request['department']] && $request['department'] != 0) {
            return ['status' => 'error', 'message' => 'Департамент с указанным id в текущей компании не найден'];
        }

        $isAdded = false;
        if ($departmentsId) {
            foreach ($departmentsId as $depId => $fields) {
                // сначала отвяжем пользователя от всех департаментов
//                if ($fields['rop'] == $request['user_id']) {
//                    $fields['rop'] = '';
//                    \CIBlockElement::SetPropertyValuesEx($depId, false, ['rop' => $fields['rop']]);
//                }
//                $key = array_search($request['user_id'], $fields['users']);
//                if ($key !== false && $key !== null) {
//                    unset($fields['users'][$key]);
//                    \CIBlockElement::SetPropertyValuesEx($depId, false, ['users' => $fields['users'] ?: [0]]);
//                }
                if ($request['department'] == $depId) {
                    $roles = self::getRoles($request['user_id'])['roles'];
                    $curRole = $request['user_type'] ?: ($roles['rops'] ? 'rops' : 'users');
                    if (($fields['rops'] == $request['user_id'] && $curRole == 'rops')
                        || (in_array($request['user_id'], $fields['users']) && $curRole == 'users')) {
                        return [
                            'status' => 'error',
                            'message' => 'Пользователь уже привязан к указанному департаменту (#' . $depId . ') и указанной роли (#' . $curRole . ')'
                        ];
                    }
                    if ($curRole == 'rops') {
                        $isAdded = true;
                        $fields['rops'] = $request['user_id'];
                        CIBlockElement::SetPropertyValuesEx($depId, false, ['rops' => $fields['rops']]);
                    } elseif ($curRole == 'users') {
                        $isAdded = true;
                        $fields['users'][] = $request['user_id'];
                        CIBlockElement::SetPropertyValuesEx($depId, false, ['users' => array_unique($fields['users'])]);
                    }
                }
            }
        }

        if ($isAdded) {
            return ['status' => 'success', 'message' => 'Пользователь успешно привязан'];
        } else {
            return ['status' => 'error', 'message' => 'Пользователь не привязан'];
        }
    }

    public static function getList(): array
    {

        $result = [];

        $arIsAuth = self::isAuth($_REQUEST);
        if (!$arIsAuth['isAuth']) {
            return self::$arErrMesAuth;
        }
        /*if (!$arIsAuth['roles']['admins']) {
            return ['status' => 'error', 'message' => 'У Вас нет прав'];
        }*/

        $arPropsEventByRequest = Iblock::getPropsByEventIblockCompany();

        $result['list'] = [];
//        $result['users_deps'] = $arPropsEventByRequest['user']['values'];
//        $result['users_comp'] = \Wss\Iblock::getUsersCompany();

        foreach ($arPropsEventByRequest['user']['values'] as $user) {
            $result['list'][$user['id']]['id'] = $user['id'];
            $result['list'][$user['id']]['name'] = $user['label'];
            $result['list'][$user['id']]['label'] = $user['label'];
            $result['list'][$user['id']]['email'] = self::getFields($user['id'])['EMAIL'];
            $result['list'][$user['id']]['roles'] = self::getRoles($user['id'])['roles'];
            $result['list'][$user['id']]['salons'] = self::getSalons($user['id']);
            unset($result['list'][$user['id']]['roles']['super_admin']);
        }

        foreach (Iblock::getUsersCompany() as $role) {
            foreach ($role as $user) {
                if (is_numeric($user)) {
                    $arUser = [];
                    $userFields = self::getFields($user);
                    if ($userFields['ACTIVE'] == 'N') {
                        continue;
                    }
                    $arUser['id'] = $user;
                    if ($userFields['NAME']) {
                        $arUser['name'] = $arUser['label'] = $userFields['NAME'];
                    }
                    if ($userFields['LAST_NAME']) {
                        $arUser['last_name'] = $userFields['LAST_NAME'];
                        if ($userFields['NAME']) {
                            $arUser['label'] .= ' ' . $userFields['LAST_NAME'];
                        } else {
                            $arUser['label'] .= $userFields['LAST_NAME'];
                        }
                    }
                    if (!$userFields['NAME'] && !$userFields['LAST_NAME']) {
                        $arUser['name'] = 'ID ' . $userFields['ID'] . ' (имя не указано)';
                    }
//                    $arUser['label'] = $arUser['name'];
                    $arUser['email'] = $userFields['EMAIL'];
                    $arUser['roles'] = self::getRoles($user)["roles"];
                    $arUser['salons'] = self::getSalons($user);
                    $result['list'][$user] = $arUser;

                }
                unset($result['list'][$user]["roles"]['super_admin']);
            }
        }
        //return $result;
        //return self::getRoles(440);
        $result = Helpers::SortByRoles($result, $arIsAuth);
        unset($result["list"][CUser::GetID()]);
        return $result;
    }

    public static function isAuthV3($request): array
    {

        $result = [];
        global $USER;
        if ($request['email'] && $request['token']) {
            $login = self::getFields($request['email'])['LOGIN'];
            if (!$login && $request['user_id']) {
                $rsUser = CUser::GetByID($request['user_id']);
                $arUser = $rsUser->Fetch();
            }

            $result['isAuth'] = false;
            if ($login || $arUser['LOGIN']) {
                $USER = new CUser;
                $result['isAuth'] = $USER->LoginByHash($login ?: $arUser['LOGIN'], $request['token']);
                if ($result['isAuth'] !== true) {
                    $result['isAuth'] = false;
                }
                $rsUser = CUser::GetByID($USER->GetID());
                $arUser = $rsUser->Fetch();
            }
            if ($result['isAuth'] === true) {
                http_response_code(200);
                $result['status'] = 'success';
                $result['user_id'] = $arUser['ID'];
                $result['message'] = 'Авторизация успешна';
                //$result['fields'] = $arUser;
                $result['fields']['ID'] = $arUser['ID'];
                $result['fields']['LOGIN'] = $arUser['LOGIN'];
                $result['fields']['NAME'] = $arUser['NAME'];
                $result['fields']['LAST_NAME'] = $arUser['LAST_NAME'];
                $result['fields']['EMAIL'] = $arUser['EMAIL'];
                if ($arUser['PERSONAL_PHONE'] || $arUser['PERSONAL_MOBILE'] || $arUser['PHONE_NUMBER']) {
                    $result['fields']['PHONE'] = $arUser['PERSONAL_PHONE'] ?: ($arUser['PERSONAL_MOBILE'] ?: $arUser['PHONE_NUMBER']);
                }
            } else {
                $result['status'] = 'error';
                $result['message'] = 'Неправильный email(логин) или токен';
                //http_response_code(401);
            }

            if ($result['status'] != 'error') {
                $result['department'] = '';

                // передадим id юзера во избежание зацикливания метода isAuth
                $company_id = Company::getId($result['user_id']);

                $result['billing'] = Billing::checkByCompanyId($company_id);

                $result = array_merge($result, self::getRoles($result['user_id']));
            }


        } else {
            $result['status'] = 'error';
            $result['message'] = 'Не указаны обязательные поля';
            $result['code'] = '400';
            http_response_code(400);
        }

        return $result;
    }

    public static function update($request)
    {
        global $USER;
        $result = [];


        $request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
        $input = array();
        $input = json_decode($request->getInput());
        $input = Helpers::object_to_array($input);
        //return $input;
        $arIsAuth = User::isAuthV3($input);


        $userId = $input['user_id'] ?? 0;
        //return $userId;
        if (!$arIsAuth['isAuth']) {
            return $arIsAuth;
        }

        if (!$arIsAuth['billing']['active']) {
            return Billing::$arErrMessBilling;
        }
        //return $arIsAuth;
        if (!$arIsAuth['roles']['admins'] && $userId > 0 && $userId !== $arIsAuth['user_id']) {
            return Helpers::getError('У Вас нет прав на изменение пользователей');
        }

        if (!$userId) {
            $userId = $arIsAuth['user_id'];
        }
        $company_id = Company::getIdV2();
        //return print_r($company_id);
        $company_id_by_user_id = Company::getIdV2($request["user_id"]);
        //return $company_id_by_user_id;
        if (!$company_id) {
            return Helpers::getError('Не определена компания текущего администратора');
        }
        if (!$company_id_by_user_id) {
            return Helpers::getError('Не определена компания изменяемого пользователя');
        }
        if ($company_id != $company_id_by_user_id) {
            return Helpers::getError('У Вас нет прав на изменение пользователя');
        }
        if ($input['user_email'] && !filter_var($input['user_email'], FILTER_VALIDATE_EMAIL)) {
            return Helpers::getError('E-mail адрес указан некорректно');
        }

        $userFields = self::getFields($userId);
        $fields = [];

        foreach ($input["roles"] as $key => $value) {
            if ($key != "super_admin") {
                if (CModule::IncludeModule("iblock")) {
                    $VALUES = array();
                    $res = CIBlockElement::GetProperty(1, $company_id, array("sort" => "asc"), array("CODE" => "$key"));
                    while ($ob = $res->GetNext()) {
                        $VALUES[] = $ob['VALUE'];
                    }
                }
                $num = array_search($userId, $VALUES);
                //return $num;
                $is_here = false;
                $key_in_array = 0;
                foreach ($VALUES as $key2 => $value2) {
                    if ($value2 == $userId) {
                        $key_in_array = $key2;
                        $is_here = true;
                    }
                }
                if ($value === true) {
                    if ($is_here === false) {
                        $VALUES[] = (int)$userId;
                    }

                } else {
                    if ($is_here) {
                        $VALUES[$key_in_array] = 0;
                    } elseif (!isset($VALUES[0])) {
                        $VALUES[0] = 0;
                    } else {

                    }

                }
                //return $VALUES;
                CIBlockElement::SetPropertyValuesEx($company_id, 1, array($key => $VALUES));
            }
        }
        $a = [
            "users", "rops", "controllers", "moneys"
        ];
        foreach ($input["salons"] as $key => $value) {
            foreach ($input["roles"] as $key2 => $value2) {
                if (CModule::IncludeModule("iblock")) {
                    if (in_array($key2, $a)) {
                        //return $key2;
                        $VALUES = array();
                        $res = CIBlockElement::GetProperty(4, $value["id"], array("sort" => "asc"), array("CODE" => $key2));
                        while ($ob = $res->GetNext()) {
                            $VALUES[] = $ob['VALUE'];
                        }

                        $num = array_search($userId, $VALUES);
                       // return $VALUES;
                        $is_here = false;
                        $key_in_array = 0;
                        foreach ($VALUES as $key3 => $value2) {
                            if ($value2 == $userId) {
                                $key_in_array = $key3;
                                $is_here = true;
                            }
                        }

                        if ($value["status"] === true && $input["roles"][$key2] === true) {
                            if ($is_here === false) {
                                $VALUES[] = (int)$userId;
                            }

                        } else {
                            if ($is_here) {
                                $VALUES[$key_in_array] = 0;
                            } elseif (!isset($VALUES[0])) {
                                $VALUES[0] = 0;
                            } else {
                                $VALUES = $VALUES;
                            }

                        }
                        $result[$value["id"]][$key2] = $VALUES;
                        if ($key2 == "rops") {
                            //return $VALUES;
                            $result[$value["id"]]["rops"] = $VALUES;
                            CIBlockElement::SetPropertyValuesEx($value["id"], 4, array("rops" => $VALUES));
                        }
                        if ($key2 == "users") {
                            $result[$value["id"]]["users"] = $VALUES;
                            CIBlockElement::SetPropertyValuesEx($value["id"], 4, array("users" => $VALUES));
                        }
                        if ($key2 == "moneys") {
                            $result[$value["id"]]["moneys"] = $VALUES;
                            CIBlockElement::SetPropertyValuesEx($value["id"], 4, array("moneys" => $VALUES));
                        }
                        if ($key2 == "controllers") {
                            $result[$value["id"]]["controllers"] = $VALUES;
                            CIBlockElement::SetPropertyValuesEx($value["id"], 4, array("controllers" => $VALUES));
                        }

                    }
                }
            }
        }
        //return $result;
            //if($input)
            if ($input['user_name']) {
                $fields['NAME'] = $input['user_name'];
            }
            if ($input['user_last_name']) {
                $fields['LAST_NAME'] = $input['user_last_name'];
            }
            if ($input['user_email']) {
                $fields['EMAIL'] = $input['user_email'];
                if ($userFields['LOGIN'] == $userFields['EMAIL']) {
                    $fields['LOGIN'] = $input['user_email'];
                }
            }
            if ($input['user_settings']) {
                $fields['UF_SETTINGS'] = $input['user_settings'];
            }
            if ($input['user_password_old'] && $input['user_password_new_1'] && $input['user_password_new_2']) {
                if ($input['user_password_new_1'] !== $input['user_password_new_2']) {
                    return Helpers::getError('Новый пароль не совпадает!');
                }
                $authResult = $USER->Login($userFields['LOGIN'], $input['user_password_old']);
                if ($authResult['TYPE'] == 'ERROR') {
                    return Helpers::getError('Старый пароль указан неверно!');
                }
                if ($input['user_password_old'] === $input['user_password_new_2']) {
                    return Helpers::getError('Старый и новый пароли должны различаться!');
                }
                $fields['PASSWORD'] = $input['user_password_new_1'];
                $fields['CONFIRM_PASSWORD'] = $input['user_password_new_2'];
            }

            if (empty($fields)) {
                return Helpers::getError('Нечего сохранять!');
            }
            //return $fields;
            $user = new CUser;
            $user->Update($userId, $fields);
            if ($user->LAST_ERROR) {
                $result['status'] = 'error';
                $result['message'] = $user->LAST_ERROR;
            } else {
                $result['status'] = 'success';
                $result['message'] = 'Пользователь изменен';
            }

            return $result;
    }

    public function getpossibleroles($request){
        $arIsAuth = self::isAuth($_REQUEST);
        if (!$arIsAuth['isAuth']) {
            return self::$arErrMesAuth;
        }
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
                "chef",
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
        $pass = [];
        $checker = Helpers::Check_Role($arIsAuth);
        foreach($visible as $key => $value){
            if($key == $checker){
                $pass = $value;
            }
        }
        return $checker;
    }

    public function getuser($request){

        global $USER;
        $result = [];
        $arIsAuth = self::isAuth($_REQUEST);
        if (!$arIsAuth['isAuth']) {
            return self::$arErrMesAuth;
        }
        if(isset($request["id"])) {
            $rsUser = CUser::GetByID($request["id"]);
        }else {
            $rsUser = CUser::GetByID(CUser::GetID());
        }
            $arUser = $rsUser->Fetch();
            $company = Company::getId($arUser["ID"]);
            $result["email"] = $arUser["EMAIL"];
            $result["id"] = $arUser["ID"];
            $result["label"] = $arUser["NAME"]." ".$arUser["LAST_NAME"];
            $result["name"] = $arUser["NAME"];
            $result["last_name"] = $arUser["LAST_NAME"];
            $result["roles"] = self::getRoles($result["id"])["roles"];
            $result["salons"] = self::getSalons($result["id"]);
            //return $result["salons"];
            $rsD = \CIBlockElement::GetList(
                [],
                [
                    "IBLOCK_ID" => 4,
                    "ACTIVE" => "Y",
                    "PROPERTY_company" => $company,
                    ["LOGIC" => "OR",
                        ["PROPERTY_rop" => $result["id"]],
                        ["PROPERTY_users" => $result["id"]]]
                ],
                false,
                false,
                ["ID", "IBLOCK_ID", '*']
            );
            $i = 0;
            while ($obD = $rsD->GetNextElement()) {
                $arFieldsD = $obD->GetFields();
                $arPropsD = $obD->GetProperties();

                //$result["salons"][$i] = self::getSalons($result["id"]);
                //$result["salons"][$i]["label"] = $arFieldsD["NAME"];
                //$result["salons"][$i]["id"] = $arFieldsD["ID"];
                //$result["salons"][$i]["status"] = true;
                //$result["roles"] = self::getRoles($result["id"])["roles"];
                //$result = self::getRoles($result["id"]);
                $i++;
            }


        return $result;
    }


    public function gets($request)
    {

        if ($user_id === 0) {
            $arIsAuth = User::isAuth($_REQUEST);
            if (!$arIsAuth['isAuth']) {
                return self::$arErrMesAuth;
            }
            $user_id = $arIsAuth['user_id'];
        }

        $result = [];
        $result['roles']['super_admin'] = in_array(1, CUser::GetUserGroup($user_id));
        $result['roles']['admins'] = false;
        $result['roles']['controllers'] = false;
        $result['roles']['marketers'] = false;
        $result['roles']['rops'] = false;
        $result['roles']['users'] = false;
        $result['roles']['moneys'] = false;
        $result['roles']['chef'] = false;
        //проверяем роли пользователя
        CModule::IncludeModule('iblock');

        // передадим id юзера во избежание зацикливания метода isAuth
        $company_id = Company::getId($user_id);

        // ищем в компаниях
        $rsC = CIBlockElement::GetList(
            [],
            [
                "IBLOCK_ID" => Iblock::$iblockCompanies,
                "ACTIVE" => "Y",
                "ID" => $company_id,
                [
                    "LOGIC" => "OR",
                    ["PROPERTY_admins" => $user_id],
                    ["PROPERTY_chef" => $user_id],
                    ["PROPERTY_controllers" => $user_id],
                    ["PROPERTY_marketers" => $user_id],
                    ["PROPERTY_rops" => $user_id],
                    ["PROPERTY_users" => $user_id],
                    ["PROPERTY_moneys" => $user_id],


                ]
            ],
            false,
            false,
            ["ID", "IBLOCK_ID"]
        );

        if ($obC = $rsC->GetNextElement()) {
            $arPropsC = $obC->GetProperties();
            //return $arPropsC;
            if (in_array($user_id, $arPropsC['admins']['VALUE'])) {
                $result['roles']['admins'] = true;
            }
            if (in_array($user_id, $arPropsC['chef']['VALUE'])) {
                $result['roles']['chef'] = $arPropsC;
            }
            if (in_array($user_id, $arPropsC['controllers']['VALUE'])) {
                $result['roles']['controllers'] = true;
            }
            if (in_array($user_id, $arPropsC['marketers']['VALUE'])) {
                $result['roles']['marketers'] = true;
            }
            if (in_array($user_id, $arPropsC['rops']['VALUE'])) {
                $result['roles']['rops'] = true;
            }
            if (in_array($user_id, $arPropsC['users']['VALUE'])) {
                $result['roles']['users'] = true;
            }
            if (in_array($user_id, $arPropsC['moneys']['VALUE'])) {
                $result['roles']['moneys'] = true;
            }

        }

        // ищем в департаментах
        $rsD = CIBlockElement::GetList(
            [],
            [
                "IBLOCK_ID" => Department::getIblockId(),
                "ACTIVE" => "Y",
                "PROPERTY_company" => $company_id,
                ["LOGIC" => "OR", ["PROPERTY_users" => $user_id], ["PROPERTY_rops" => $user_id]]
            ],
            false,
            false,
            ["ID", "IBLOCK_ID"]
        );
        if ($obD = $rsD->GetNextElement()) {
            $arFieldsD = $obD->GetFields();
            $arPropsD = $obD->GetProperties();
            //return $result;
            if (in_array($user_id, $arPropsD['rops']['VALUE'])) {
                $result['roles']['rops'] = true;
            }
            if (in_array($user_id, $arPropsD['user']['VALUE'])) {
                $result['roles']['users'] = true;
            }
            if (!$result['roles']['admins'] && !$result['roles']['controllers'] && !$result['roles']['marketers'] && !$result['roles']['chef']) {
                $result['department'] = $arFieldsD['ID'];
            }
        }

        return $result;
    }
}
