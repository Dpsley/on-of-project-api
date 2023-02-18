<?

namespace Wss;

use Bitrix\Main\Data\Cache;
use Bitrix\Main\Mail\Event;
use CIBlock;
use CIBlockElement;
use CModule;
use CUser;
use DateTime;
use Wss\Telegram\Telegram\Methods as Methods;


\CModule::IncludeModule("iblock");

/**
 * @deprecated
 * Развитие класса приостановлено, методы данного класса будут разделены на самостоятельные для удобства обслуживания.
 * Новые классы постепенно будут пополняться в директории api/v2/classes/wss.
 */
class Iblock
{
    static public $cache_time_short = 1;
    static public $cache_time_long = 1;

    static public $timeoutDebug = 1;

    static public $iblockTypeNiches = 'niches'; // инфоблок рыночных ниш (шаблоны)
    static public $iblockTypeEventCompanies = 'companies'; // инфоблок событий компаний

    static public $iblockCompanies = 1; // инфоблок компаний
    static public $iblockDepartments = 4; // инфоблок департаментов
    static public $iblockBillings = 3; // инфоблок биллингов
    static public $iblockPlans = 46; // инфоблок Планов поступлений
    static public $iblockPlansByDeal = 136; // инфоблок План по договорам
    static public $iblockFact = 47; // инфоблок Фактов
    static public $iblockTreaty = 49; // инфоблок Договоров

    static public $iblock_from_primary_to_deal = 150; // инфоблок из первичного в договор
    static public $iblock_from_secondary_to_deal = 149; // инфоблок Договоров
    static public $iblock_from_primary_to_KEV = 148; // инфоблок Договоров
    static public $iblock_from_KEV_to_deal = 151; // инфоблок Договоров

    static public $iblockSettings = 48; // инфоблок настроек типов

    static public $arErrMesAuth = array('status' => 'error', 'message' => 'Ошибка проверки авторизации');
    static public $arMonthRusShort = array('01' => 'Янв', '02' => 'Фев', '03' => 'Мар', '04' => 'Апр', '05' => 'Мая', '06' => 'Июн', '07' => 'Июл', '08' => 'Авг', '09' => 'Сен', '10' => 'Окт', '11' => 'Ноя', '12' => 'Дек');
    static public $arMonthRusFull = array('01' => 'Январь', '02' => 'Февраль', '03' => 'Март', '04' => 'Апрель', '05' => 'Май', '06' => 'Июнь', '07' => 'Июль', '08' => 'Август', '09' => 'Сентябрь', '10' => 'Октябрь', '11' => 'Ноябрь', '12' => 'Декабрь');
    static public $arMonthRusFullRP = array('01' => 'Января', '02' => 'Февраля', '03' => 'Марта', '04' => 'Апреля', '05' => 'Мая', '06' => 'Июня', '07' => 'Июля', '08' => 'Августа', '09' => 'Сентября', '10' => 'Октября', '11' => 'Ноября', '12' => 'Декабря');

    static public function getDatesFromRange($startTime, $endTime)
    { // получение всех дат между двумя датами
        $dates = array_map(function ($value) {
            return date('Y-m-d', $value);
        }, range(strtotime($startTime), strtotime($endTime), 86400));
        if (!$dates && $startTime) $dates[] = date('Y-m-d', strtotime($startTime));
        return $dates;
    }

    static public function MainTrafficQuadro($request){
        $UserID = Methods\ForHelp::getUserID($request["email"]);
        $elementCheck = Methods\ForHelp::getElement($UserID);
        Cmodule::IncludeModule('iblock');
        $rsEnum = CIBlock::GetList(array(), array("NAME" => "mebel-manufacturing_" . $elementCheck));
        $arEnum = $rsEnum->GetNext();
        $elementCheck = $arEnum["ID"];
        if (!isset($request["date_from"]) || !isset($request["date_to"])) {
            $today = date('d.m.Y');
            $first = date('01.m.Y');
        } else {
            $first = strtotime($request["date_from"]);
            $today = strtotime($request["date_to"]);
            $first = date('d.m.Y', $first);
            $today = date('d.m.Y', $today);
        }

        $results = \Wss\Graphs\ForTables::Search($request, $elementCheck, $today, $first, 0);
        $array = \Wss\Graphs\ForTables::Convert($results, $first, $today);
        $first_last_year = date('Y-m-d',(strtotime ( "-1 year" , strtotime($first))));
        $today_last_year = date('Y-m-d',(strtotime ( "-1 year" , strtotime($today))));
        $results_last_year = \Wss\Graphs\ForTables::Search($request, $elementCheck, $today_last_year, $first_last_year, 0);
        $array_last_year = \Wss\Graphs\ForTables::Convert($results_last_year, $first_last_year, $today_last_year);
        $array_last_year  = \Wss\Graphs\MainGraph::totaler($array_last_year, $first_last_year, $today_last_year, $elementCheck);
        $Total_Traffic = \Wss\Graphs\MainGraph::totaler($array, $first, $today, $elementCheck);

        foreach ($Total_Traffic as $i => $value){
            if($array_last_year[$i]["score_value"] == 0){
                $Total_Traffic[$i]["year_scale"] = -round($value["score_value"]*100, 2);
            }elseif($value["score_value"] == 0){
                $Total_Traffic[$i]["year_scale"] = round($array_last_year[$i]["score_value"]*100, 2);
            }elseif($Total_Traffic[$i]["score_value"] > $array_last_year[$i]["score_value"]){
                $Total_Traffic[$i]["year_scale"] = round((round($value["score_value"]*100 / $array_last_year[$i]["score_value"], 2))-100, 2);
            }elseif($Total_Traffic[$i]["score_value"] < $array_last_year[$i]["score_value"]){
                $Total_Traffic[$i]["year_scale"] = round(-(100-round($value["score_value"]*100 / $array_last_year[$i]["score_value"], 2)),2);
            }else{
                $Total_Traffic[$i]["year_scale"] = 0;
            }
            $response[] = $Total_Traffic[$i];
        }
        return $response;
    }

    static public function getNiches() // получение списка инфоблоков-ниш (шаблонов)
    {
        $result = array();

        $cache = Cache::createInstance();
        $cacheId = 'getNiches';
        if ($cache->initCache(self::$cache_time_long, $cacheId, 'custom_cache')) {
            $result = $cache->getVars();
        } elseif ($cache->startDataCache()) {

            $result['status'] = 'error';
            $result['message'] = 'Нишы не найдены';

            $res = \CIBlock::GetList(
                array(),
                array(
                    'TYPE' => self::$iblockTypeNiches,
                    'ACTIVE' => 'Y',
                    "CNT_ACTIVE" => "Y",
                    "CHECK_PERMISSIONS" => "N"
                ),
                true
            );
            while ($ar_res = $res->Fetch()) {
                $result['status'] = 'success';
                $result['message'] = '';
                $result['list'][] = array('id' => $ar_res['ID'], 'value' => $ar_res['ID'], 'label' => $ar_res['NAME']);
            }
            $cache->endDataCache($result);

        }
        return $result;
    }

    /*** DEPRECATED ***/
    static public function issetEventIBlockCompanyByCurUser() // проверка существует ли у пользователя уже созданный инфоблок событий компании с привязанными правами
    {
        $arIsAuth = \Wss\User::isAuth($_REQUEST);
        if (!$arIsAuth['isAuth']) return self::$arErrMesAuth;

        $result = array();
        $res = \CIBlock::GetList( // получим инфоблоки с событиями компаний
            array(),
            array(
                'TYPE' => self::$iblockTypeEventCompanies,
                'ACTIVE' => 'Y',
                "CNT_ACTIVE" => "Y"
            ),
            true
        );
        while ($ar_res = $res->Fetch()) {
            $ob = new \CIBlockRights($ar_res['ID']);
            $ar = $ob->GetRights();
            if (is_array($ar)) {
                foreach ($ar as $perm) { // проверим привязан ли текущий юзер к инфоблоку
                    if ($perm['GROUP_CODE'] == 'U' . $arIsAuth['user_id']) $result[] = $ar_res['ID'];
                }
            }
        }

        return $result;
    }

    static public function getCompanyByCurUser($user_id = 0) // текущая компания пользователя
    {
        if ($user_id === 0) {
            $arIsAuth = \Wss\User::isAuth($_REQUEST);
            if (!$arIsAuth['isAuth']) return self::$arErrMesAuth;
            $user_id = $arIsAuth['user_id'];
        }
        $fields = [];
        $cache = Cache::createInstance();
        $cacheId = 'getCompanyByCurUser' . md5(serialize($_REQUEST)) . md5(serialize($user_id));
        if ($cache->initCache(self::$cache_time_long, $cacheId, 'custom_cache')) {
            $fields = $cache->getVars();
        } elseif ($cache->startDataCache()) {
            // ищем в компаниях
            $rs = \CIBlockElement::GetList(
                [],
                [
                    "IBLOCK_ID" => self::$iblockCompanies,
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

            if (!$ob = $rs->GetNextElement()) {
                // ищем в департаментах
                $rs2 = \CIBlockElement::GetList(
                    [],
                    [
                        "IBLOCK_ID" => self::$iblockDepartments,
                        "ACTIVE" => "Y",
                        ["LOGIC" => "OR", ["PROPERTY_users" => $user_id], ["PROPERTY_rop" => $user_id]]
                    ],
                    false,
                    false,
                    ["ID", "IBLOCK_ID", "PROPERTY_company"]
                );
                $ar = $rs2->GetNext();
                if ($ar['PROPERTY_COMPANY_VALUE']) {
                    $ID = $ar['PROPERTY_COMPANY_VALUE'];

                    $rs = \CIBlockElement::GetList(
                        [],
                        [
                            "IBLOCK_ID" => self::$iblockCompanies,
                            "ACTIVE" => "Y",
                            "ID" => $ID,
                        ],
                        false,
                        false,
                        []
                    );
                    if ($ob = $rs->GetNextElement()) {
                        $fields['FIELDS'] = $ob->GetFields();
                        $fields['PROPERTIES'] = $ob->GetProperties();
                    }
                }
            } else {
                $fields['FIELDS'] = $ob->GetFields();
                $fields['PROPERTIES'] = $ob->GetProperties();
            }

            $cache->endDataCache($fields);
        }
        if (!empty($fields)) return $fields;

        return false;
    }

    static public function getCompanyIdByCurUser($user_id = 0) // ID текущей компания пользователя
    {
        $fieldsCompany = self::getCompanyByCurUser($user_id);
        if ($fieldsCompany['status'] == 'error') return $fieldsCompany;
        return $fieldsCompany['FIELDS']['ID'] ?: false;
    }

    static public function getCompanyNameByCurUser($user_id = 0) // название текущей компания пользователя
    {
        $fieldsCompany = self::getCompanyByCurUser($user_id);

        return $fieldsCompany['FIELDS']['NAME'] ?: false;
    }

    static public function getCurUserEventIblockCompany()
    { // текущий инфоблок событий компании
        $result = array();

        $cache = Cache::createInstance();
        $cacheId = 'getCurUserEventIblockCompany' . md5(serialize($_REQUEST));
        if ($cache->initCache(self::$cache_time_long, $cacheId, 'custom_cache')) {
            $result = $cache->getVars();
        } elseif ($cache->startDataCache()) {

            $company_id = self::getCompanyIdByCurUser();
            //return $company_id;
            $res = CIBlock::GetList(array(), array('TYPE' => self::$iblockTypeEventCompanies, "NAME" => '%_' . $company_id), true);
            if ($ar_res = $res->Fetch()) {
                $result = $ar_res;
            }

            $cache->endDataCache($result);
        }

        return $result;
    }

    static public function setRightsEventIblock($request)
    { // добавление прав к инфоблоку событий компании

        if ($request['iblock_id']) {
            $event_iblock['ID'] = $request['iblock_id'];
        } else {
            $event_iblock = \Wss\Iblock::getCurUserEventIblockCompany(); // получим инфоблок событий текущей компании пользователя
        }
        if (!$event_iblock['ID']) return array('status' => 'message', 'Неизвестен инфоблок событий');
        /** Права для инфоблоков:
         * 33 - нет доступа
         * 34 - чтение
         * 39 - изменение
         * 40 - полный доступ
         */
        $ob = new \CIBlockRights($event_iblock['ID']);
        if ($request['new_iblock']) {
            $arRights = array(
                'n0' => array(
                    'GROUP_CODE' => 'G2', // все пользователи
                    'DO_INHERIT' => 'Y',
                    'IS_INHERITED' => 'N',
                    'OVERWRITED' => 0,
                    'TASK_ID' => 33, //нет доступа
                    'XML_ID' => null,
                    'ENTITY_TYPE' => 'iblock',
                    'ENTITY_ID' => $event_iblock['ID']
                ),
                'n1' => array(
                    'GROUP_CODE' => 'G1', // администраторы
                    'DO_INHERIT' => 'Y',
                    'IS_INHERITED' => 'N',
                    'OVERWRITED' => 0,
                    'TASK_ID' => 40, //полный доступ
                    'XML_ID' => null,
                    'ENTITY_TYPE' => 'iblock',
                    'ENTITY_ID' => $event_iblock['ID']
                )
            );
        } else {
            $ob = new \CIBlockRights($event_iblock['ID']);
            $arRights = $ob->GetRights();
        }
        $arRights['n2'] = array(
            'GROUP_CODE' => 'U' . $request['user_id'], // конкретный пользователь
            'DO_INHERIT' => 'Y',
            'IS_INHERITED' => 'N',
            'OVERWRITED' => 0,
            'TASK_ID' => $request['task_id'],
            'XML_ID' => null,
            'ENTITY_TYPE' => 'iblock',
            'ENTITY_ID' => $event_iblock['ID']
        );
        $ar = $ob->SetRights($arRights); // установим права на инфоблок

        return true;
    }

    public static function createCompany($request, $user_id = false): array // создание компании
    {
        $result = array();
        if (!$user_id) {
            $arIsAuth = \Wss\User::isAuth($_REQUEST);
            $user_id = $arIsAuth['user_id'];
        }
        $el = new \CIBlockElement;

        $PROP = array();
        $PROP[8] = $user_id; // admins

        $arLoadProductArray = array(
            "IBLOCK_SECTION_ID" => false, // элемент лежит в корне раздела
            "IBLOCK_ID" => self::$iblockCompanies,
            "PROPERTY_VALUES" => $PROP,
            "NAME" => $request['company_name'],
            "ACTIVE" => "Y",
        );

        if ($ID = $el->Add($arLoadProductArray)) {
            $result['status'] = 'success';
            $result['message'] = 'Создана компания с ID ' . $ID;
            $result['company_id'] = $request['company_id'] = $ID;

            //            $result['$request'] = $request;
            //            $result['$_REQUEST'] = $_REQUEST;
            $result['debug_createEventIBlockCompany'] = self::createEventIBlockCompany($request, $user_id);
        } else {
            $result['status'] = 'error';
            $result['message'] = $el->LAST_ERROR;
        }


        return $result;
    }

    static public function updateCompany($request)
    {
        $result = array();

        $arIsAuth = \Wss\User::isAuth($_REQUEST);
        if (!$arIsAuth['isAuth']) return self::$arErrMesAuth;
        if (!$arIsAuth['roles']['admin']) return array('status' => 'error', 'message' => 'У Вам нет прав на добавление/изменение пользователей');
        if (!$arIsAuth['billing']['active']) return \Wss\Billing::$arErrMessBilling;

        if (!$request['name']) {
            return ['status' => 'error', 'message' => 'Не указано название компании'];
        }

        $company_id = \Wss\Iblock::getCompanyIdByCurUser();

        $el = new \CIBlockElement;
        $ar = array(
            "ID" => $company_id,
            "NAME" => $request['name'],
        );
        if ($el->Update($company_id, $ar)) {
            $result['status'] = 'success';
            $result['message'] = 'Компания изменена';
        } else {
            $result['status'] = 'error';
            $result['message'] = $el->LAST_ERROR;
        }

        return $result;
    }

    static public function getUsersCompany()
    { // получить привязанных пользователей к текущей компании
        $result = array();
        $arIsAuth = \Wss\User::isAuth($_REQUEST);
        if (!$arIsAuth['isAuth']) return self::$arErrMesAuth;

        $company_id = self::getCompanyIdByCurUser();
        $rs = \CIBlockElement::GetList(
            array(),
            array(
                "IBLOCK_ID" => self::$iblockCompanies,
                "ACTIVE" => "Y",
                "ID" => $company_id
            ),
            false,
            false,
            array('ID', 'NAME', 'IBLOCK_ID')
        );
        $result['admins'] = array();
        $result['controllers'] = array();
        while ($ob = $rs->GetNextElement()) {
            $arProps = $ob->GetProperties();
            $result['admins'] = $arProps['admins']['VALUE'];
            $result['controllers'] = $arProps['controllers']['VALUE'];
            $result['marketers'] = $arProps['marketers']['VALUE'];
            $result['rops'] = $arProps['rops']['VALUE'];
            $result['users'] = $arProps['users']['VALUE'];
            $result["moneys"] = $arProps['moneys']['VALUE'];
            $result["chef"] = $arProps['chef']['VALUE'];
        }

        return $result;
    }

    static public function createEventIBlockCompany($request, $user_id = false) // создание(копирование) инфоблока в типе инфоблока "События компаний", согласно выбранной рыночной нише
    {
        $result = array();
        if (!$user_id) {
            $arIsAuth = \Wss\User::isAuth($_REQUEST);
            if (!$arIsAuth['isAuth']) return self::$arErrMesAuth;
            $user_id = $arIsAuth['user_id'];
        }

        //            $issetEventIBlockCompanyByCurUser = self::issetEventIBlockCompanyByCurUser(); // проверка есть ли у текущего юзера уже созданный инфоблок с событиями компании, на случай если в будущем компания будет создаваться после регистрации
        //            if(intval($request["niche_id"])>0 && !$issetEventIBlockCompanyByCurUser){
        if (intval($request["niche_id"]) > 0) {
            //if(intval($request["niche_id"])>0){
            $IBLOCK_ID = intval($request["niche_id"]);
            $bError = false;
            $ib = new \CIBlock;
            $arFields = \CIBlock::GetArrayByID($IBLOCK_ID);
            $arFields["NAME"] = $arFields["CODE"] . "_" . $request['company_id'];
            $arFields["CODE"] = $arFields["CODE"] . "-" . $request['company_id'];
            unset($arFields["ID"]);
            $arFields["IBLOCK_TYPE_ID"] = self::$iblockTypeEventCompanies;
            $arFields["RIGHTS_MODE"] = "E";

            //                $ob = new \CIBlockRights(2);
            //                $result['tasks'] = \CIBlockRights::GetRightsList(); // список всех возможных прав
            //                $result['RIGHTSCURRENT'] = $ob->GetRights();// получение списка прав инфоблока
            //                $result['arFields'] = $arFields;

            $IBLOCK_ID_NEW = $ib->Add($arFields);
            if (intval($IBLOCK_ID_NEW) <= 0) {
                $bError = true;
                $result['status'] = 'error';
                $result['message'] = 'Ошибка создания компании (инфоблока)';
            } else {
                self::setRightsEventIblock(array('iblock_id' => $IBLOCK_ID_NEW, 'user_id' => $user_id, 'task_id' => 39, 'new_iblock' => true)); // установим права

                // копируем свойства ИБ
                $ibp = new \CIBlockProperty;
                $properties = \CIBlockProperty::GetList(array(), array("ACTIVE" => "Y", "IBLOCK_ID" => $IBLOCK_ID));
                while ($prop_fields = $properties->GetNext()) {
                    if ($prop_fields["PROPERTY_TYPE"] == "L") {
                        $property_enums = \CIBlockPropertyEnum::GetList(
                            array("ID" => "ASC", "SORT" => "ASC"),
                            array("IBLOCK_ID" => $IBLOCK_ID, "CODE" => $prop_fields["CODE"])
                        );
                        while ($enum_fields = $property_enums->GetNext()) {
                            $prop_fields["VALUES"][] = array(
                                "XML_ID" => $enum_fields["XML_ID"],
                                "VALUE" => $enum_fields["VALUE"],
                                "DEF" => $enum_fields["DEF"],
                                "SORT" => $enum_fields["SORT"]
                            );
                        }
                    }
                    $prop_fields["IBLOCK_ID"] = $IBLOCK_ID_NEW;
                    unset($prop_fields["ID"]);
                    foreach ($prop_fields as $k => $v) {
                        if (!is_array($v)) $prop_fields[$k] = trim($v);
                        if ($k{
                            0} == '~') unset($prop_fields[$k]);
                    }
                    $PropID = $ibp->Add($prop_fields);
                    if (intval($PropID) <= 0)
                        $bError = true;
                }
            }

            if (!$bError && $IBLOCK_ID > 0) {
                $result['status'] = 'success';
                $result['message'] = 'Инфоблок событий компании успешно создан - ' . $IBLOCK_ID_NEW;
                $result['event_iblock_company_id'] = $IBLOCK_ID_NEW;
            } else {
                $result['status'] = 'error';
                $result['message'] = 'Ошибка создания свойств инфоблока событий компании';
            }
            //            }elseif(intval($request["niche_id"])>0 && $issetEventIBlockCompanyByCurUser){
            //                $result['status'] = 'error';
            //                $result['message'] = 'У текущего пользователя уже создан инфоблок с событиями компании - '.$issetEventIBlockCompanyByCurUser;
            //                $result['company_id'] = $issetEventIBlockCompanyByCurUser;
        } else {
            $result['status'] = 'error';
            $result['message'] = 'Не указана ниша (id)';
        }


        return $result;
    }

    static public function getPropsByEventIblockCompany($request = array())
    { // получение свойств инфоблока событий компании
        $result = array();
        //        $result['$request'] = $request;
        $arIsAuth = \Wss\User::isAuth($_REQUEST);
        if (!$arIsAuth['isAuth']) return self::$arErrMesAuth;

        $cache = Cache::createInstance();
        $cacheId = 'getPropsByEventIblockCompany' . md5(serialize($_REQUEST)) . md5(serialize($request));
        if ($cache->initCache(self::$cache_time_long, $cacheId, 'custom_cache')) {
            $result = $cache->getVars();
        } elseif ($cache->startDataCache()) {
            $settingsEvent = self::getSettingsEventTypes();

            // типы значений свойств
            //            $props_types['event_type']['fields'] = array('Приход менеджера','Консультация (первичная)','Консультация (вторичная)','Нецелевой','Упущенный','Уход менеджера'); // типы событий
            //            $props_types['event_type']['targeted'] = array('Консультация','Упущенный'); // целевые типы событий
            //            $props_types['event_type']['no_targeted'] = array('Нецелевой','Приход менеджера','Уход менеджера'); // не целевые типы событий
            //            $props_types['event_type']['missed'] = array('Упущенный'); // не целевые типы событий
            //            $props_types['event_type']['primary'] = array('первичн'); // первичные типы событий
            //            $props_types['event_type']['secondary'] = array('вторичн'); // вторичные типы событий

            //        $props_types['result']['fields'] = array('Договор (первый/второй визит)','Презентация','Замер','Дизайн-проект','Моб.тел (лид)','Отказ от контакта клиент','Продавец был занят с другим клиентом','Продавец разговаривал по телефону','Отсутствие продавца в салоне','Отказ от контакта продавец'); // результаты событий
            //        $props_types['result']['targeted'] = array('договор','замер','проект','моб.тел'); // целевые результаты событий
            //        $props_types['result']['no_targeted'] = array(); // не целевые действия (результат события)


            $event_iblock = self::getCurUserEventIblockCompany();

            $departmentsList = self::getDepartments()['list'];

            $properties = \CIBlockProperty::GetList(array('sort' => 'asc'), array("IBLOCK_ID" => $event_iblock['ID']));
            while ($prop_fields = $properties->GetNext()) {
                $prop_fields2 = $prop_fields;
                unset($prop_fields);
                //            $prop_fields['$prop_fields'] = $prop_fields2;
                $prop_fields['id'] = (int)$prop_fields2['ID'];
                $prop_fields['code'] = $prop_fields2['CODE'];
                $prop_fields['name'] = $prop_fields2['NAME'];
                $prop_fields['type'] = $prop_fields2['PROPERTY_TYPE'];
                $prop_fields['required'] = $prop_fields2['IS_REQUIRED'] == 'Y';
                //            $prop_fields['user_type'] = $prop_fields2['USER_TYPE'];
                if ($prop_fields['type'] == 'L') {
                    $property_enums = \CIBlockPropertyEnum::GetList(array('sort' => 'asc'), array("IBLOCK_ID" => $event_iblock['ID'], "CODE" => $prop_fields['code']));
                    while ($enum_fields = $property_enums->GetNext()) {
                        $enum_fields2 = $enum_fields;
                        $enum_fields = array();
                        $enum_fields['id'] = (int)$enum_fields2['ID'];
                        $enum_fields['value'] = (int)$enum_fields2['ID']; //для Quasar Select
                        $enum_fields['label'] = $enum_fields2['VALUE']; //для Quasar Select
                        $enum_fields['xml_id'] = $enum_fields2['XML_ID'] ?? '';

                        if ($prop_fields['code'] == 'event_type' || $prop_fields['code'] == 'result') {
                            foreach (['targeted', 'no_targeted', 'missed', 'primary', 'secondary', 'contract'] as $type) {
                                if ($settingsEvent[$type]) {
                                    foreach ($settingsEvent[$type] as $setting) {
                                        $enum_fields[$type] = false;
                                        if (strpos($enum_fields2['XML_ID'], $setting[1]) !== false) {
                                            $enum_fields[$type] = true;
                                            break;
                                        }
                                    }
                                }
                            }

                            // commented 29.08.2022
                            //                            $enum_fields['targeted'] = mb_strpos($enum_fields2['XML_ID'], 'targeted') !== false && mb_strpos($enum_fields2['XML_ID'], 'no_targeted') === false;
                            //                            $enum_fields['no_targeted'] = mb_strpos($enum_fields2['XML_ID'], 'no_targeted') !== false;
                            //                            $enum_fields['missed'] = mb_strpos($enum_fields2['XML_ID'], 'missed') !== false;
                            //                            $enum_fields['primary'] = mb_strpos($enum_fields2['XML_ID'], 'primary') !== false;
                            //                            $enum_fields['secondary'] = mb_strpos($enum_fields2['XML_ID'], 'secondary') !== false;
                            //                            $enum_fields['contract'] = mb_strpos($enum_fields2['XML_ID'], 'contract') !== false;

                        }

                        //                        foreach ($props_types[$prop_fields['code']] as $key_type => $vals_type){ // расставим типы типов
                        //                            foreach ($vals_type as $val_type){
                        //                                $enum_fields[$key_type] = false;
                        //                                if(mb_strpos(mb_strtolower($enum_fields['label']), mb_strtolower($val_type)) !== false){
                        //                                    $enum_fields[$key_type] = true;
                        //                                    break;
                        //                                }
                        //                            }
                        //                        }

                        $prop_fields['values'][] = $enum_fields;
                    }
                } elseif ($prop_fields['type'] == 'E' && $prop_fields['code'] == 'department') {
                    $prop_fields['values'] = $departmentsList;
                }
                // проставим типы для упрощения разбора на фронте
                if ($prop_fields2['USER_TYPE'] == 'UserID') {
                    $prop_fields['type'] = 'list';
                    if ($prop_fields2['CODE'] == 'user' && $departmentsList) {
                        $prop_fields['values'] = [];
                        foreach ($departmentsList as $dep) {
                            if (($request['department'] && $dep['id'] !== $request['department']) || !$dep['prop_users']['values'])
                                continue;
                            $prop_fields['values'] = array_merge($prop_fields['values'], $dep['prop_users']['values']);
                        }
                    }
                } elseif ($prop_fields2['USER_TYPE'] == 'DateTime') {
                    $prop_fields['type'] = 'date_time';
                } elseif ($prop_fields['type'] == 'E') {
                    $prop_fields['type'] = 'list';
                } elseif ($prop_fields['type'] == 'L') {
                    $prop_fields['type'] = 'list';
                } elseif ($prop_fields['type'] == 'S') {
                    $prop_fields['type'] = 'string';
                }
                if ($prop_fields['code'] == 'repeat_client') {
                    $prop_fields['type'] = 'boolean';
                }
                $result[mb_strtolower($prop_fields['code'])] = $prop_fields;
            }

            if ($request['event_id']) {
                // получим оригинальные массивы значений свойств для модели Vue, согласно сохраненным в событии
                $events = self::getEventsCompany($request);
                $new_result = array();
                foreach ($result as $code => $prop) {
                    $event_prop = $events['list'][0][$code];
                    if ($code == 'department' || !$event_prop) continue;
                    if ($prop['type'] == 'string') {
                        $new_result[$code] = $event_prop['value'];
                    } elseif ($prop['type'] == 'date_time') {
                        $new_result[$code] = $event_prop['value'];
                    } elseif ($prop['type'] == 'boolean') {
                        $new_result[$code] = $event_prop['value'] ? true : false;
                    } elseif ($prop['type'] == 'list') {
                        if ($code == 'user') {
                            $event_department = $events['list'][0]['department'];
                            $department = array_filter($result['department']['values'], function ($value) use ($event_department) {
                                return $value['id'] == $event_department['value_id'];
                            });
                            $user = array_filter(current($department)['prop_users']['values'], function ($value) use ($event_prop) {
                                return $value['id'] == (string)$event_prop['value_id'];
                            });
                            $new_result[$code] = current($user);
                        } else {
                            $new_result[$code] = array_filter($result[$code]['values'], function ($value) use ($event_prop) {
                                return $value['id'] == $event_prop['value_id'];
                            });
                            if (is_array($new_result[$code])) $new_result[$code] = current($new_result[$code]);
                            if ($new_result[$code] === false) $new_result[$code] = array();
                        }
                    }
                }
                $result = $new_result;
            }

            $cache->endDataCache($result);
        }

        return $result;
    }

    static public function addEventCompany($request)
    { // добавление события компании текущего пользователя
        $result = array();
        //        $result['request'] = $request;
        $arIsAuth = \Wss\User::isAuth($_REQUEST);
        if (!$arIsAuth['isAuth']) return self::$arErrMesAuth;
        if (!$arIsAuth['billing']['active']) return \Wss\Billing::$arErrMessBilling;

        if (!$arIsAuth['roles']['admins'] && !$arIsAuth['roles']['controllers']) return array('status' => 'error', 'message' => 'У Вас нет прав на ' . ($request['update'] === true ? 'изменение' : 'добавление') . ' событий');
        //        $requiredFields = array('user','date','date_time','event_type','result','department');

        /***
         * Пользователь         / Привязка к пользователю / user
         * Компания             / Привязка к элементам    / company (ИБ Компании ID 1)
         * Дата и время события / Дата/Время              / date_time
         * День недели          / Список                  / week_day
         * Тип события          / Список                  / event_type
         * Комментарий          / Строка                  / comment
         * Результат            / Список                  / result
         * Повторный клиент     / Строка                  / repeat_client
         * Подразделение        / Привязка к элементам    / department (ИБ Департаменты ID 4)
         */

        //        $company_id = self::getCompanyIdByCurUser();

        $event_iblock = self::getCurUserEventIblockCompany();
        $arProps = self::getPropsByEventIblockCompany();
        $strErrorRequireFields = array();
        foreach ($arProps as $propcode => $prop) { // проверим на заполненность обязательные поля
            if ($prop['required'] === true && !$request[$propcode]) {
                //                if(empty($strErrorRequireFields)){
                //                    $strErrorRequireFields = $propcode == 'date' ? 'Дата' : $arProps[$propcode]['name'];
                //                }else{
                //                    $strErrorRequireFields .= ', '.($propcode == 'date' ? 'Дата' : $arProps[$propcode]['name']);
                //                }
                $strErrorRequireFields[] = ($propcode == 'date_time' ? 'Время' : $arProps[$propcode]['name']);
            }
        }
        if (!$request['date']) $strErrorRequireFields[] = 'Дата';

        if (!empty($strErrorRequireFields)) return array('status' => 'error', 'message' => 'Не заполнены обязательные поля ' . implode(', ', $strErrorRequireFields));

        $el = new \CIBlockElement;
        $arDays = array(1 => 'Понедельник', 2 => 'Вторник', 3 => 'Среда', 4 => 'Четверг', 5 => 'Пятница', 6 => 'Суббота', 7 => 'Воскресенье');
        $PROP = array();

        // переберем все свойства и проверим пришедшие в запросе
        $date_time = ($request['date'] && $request['date_time']) ? strtotime($request['date'] . ' ' . $request['date_time']) : time();
        $weekDayId = $arProps['week_day']['values'][array_search($arDays[date('N', $date_time)], array_column($arProps['week_day']['values'], 'label'))]['id'];
        foreach ($arProps as $propcode => $prop) {
            if ($request[$propcode]) {
                if ($propcode == 'date_time') {
                    $PROP[$prop['id']] = \ConvertTimeStamp($date_time, 'FULL');
                } elseif ($propcode == 'week_day') {
                    $PROP[$arProps['week_day']['id']] = $weekDayId;
                } else {
                    $PROP[$prop['id']] = $request[$propcode];
                }
            } else {
                $PROP[$prop['id']] = '';
            }
        }


        //        $PROP[$arProps['date_time']['id']] = \ConvertTimeStamp($date_time, 'FULL');
        //        $weekDayId = $arProps['week_day']['values'][array_search($arDays[date('N',$date_time)], array_column($arProps['week_day']['values'], 'label'))]['id'];

        //        $PROP[$arProps['week_day']['id']] = $weekDayId;
        //        $PROP[$arProps['user']['id']] = $request['user'];
        //        $PROP[$arProps['event_type']['id']] = $arProps['event_type']['values'][$request['event_type']]['id'];
        //        $PROP[$arProps['event_type']['id']] = $request['event_type'];
        //        $PROP[$arProps['comment']['id']] = $request['comment'];
        //        $PROP[$arProps['result']['id']] = $arProps['result']['values'][$request['result']]['id'];
        //        $PROP[$arProps['result']['id']] = $request['result'];
        //        $PROP[$arProps['repeat_client']['id']] = $request['repeat_client'];
        //        $PROP[$arProps['department']['id']] = $request['department'];


        $arLoadProductArray = array(
            "IBLOCK_SECTION_ID" => false,
            "IBLOCK_ID" => $event_iblock['ID'],
            "PROPERTY_VALUES" => $PROP,
            "NAME" => 'Событие',
            "ACTIVE" => $request['delete'] == 'y' ? "N" : "Y",
        );
        //        $result['$arLoadProductArray'] = $arLoadProductArray;
        //        $result['$arProps'] = $arProps;
        //        $result['week_day'] = $prop_fields['week_day']['values'];
        //        $result['$arDays'] = $arDays[date('N')];

        if ($request['update'] === true) {
            if ($el->Update($request['event_id'], $arLoadProductArray)) {
                $result['status'] = 'success';
                $result['message'] = 'Событие с ID ' . $request['event_id'] . ' обновлено';
            } else {
                $result['status'] = 'error';
                $result['message'] = $el->LAST_ERROR;
            }
        } else {
            if ($ID = $el->Add($arLoadProductArray)) {
                $result['status'] = 'success';
                $result['message'] = 'Создано событие с ID ' . $ID;
            } else {
                $result['status'] = 'error';
                $result['message'] = $el->LAST_ERROR;
            }
        }


        //        $rs = \CIBlockElement::GetList(
        //            array(),
        //            array(
        //                "IBLOCK_TYPE" => self::$iblockTypeEventCompanies,
        //                "IBLOCK_NAME" => '%_'.$company_id
        //            ),
        //            false,
        //            false,
        //            array("*")
        //        );
        //        if($ar = $rs->GetNext()) {
        //            $result['eventIblock'] = $ar;
        //        }


        return $result;
    }

    // проверка события к принадлежности компании
    static private function checkEventIdByCurrentCompany(int $eventId): bool
    {
        $event_iblock = self::getCurUserEventIblockCompany();
        $res = \Bitrix\Iblock\ElementTable::query()
            ->addSelect('ID')
            ->where('ID', $eventId)
            ->where('IBLOCK_ID', $event_iblock['ID'])
            ->fetch();
        if ($res) {
            return true;
        }
        return false;
    }

    static public function updateEventCompany($request)
    { // обновление события компании текущего пользователя
        $request['update'] = true;
        if (!$request['event_id']) return array('status' => 'error', 'message' => 'Не указан обязательный параметр event_id');
        if (!self::checkEventIdByCurrentCompany($request['event_id'])) {
            return array('status' => 'error', 'message' => 'Указанное событие не найдено');
        }
        return self::addEventCompany($request);
    }

    static public function checkNameDepartment($name) // проверка названия департамента (подразделения) компании
    {
        $arIsAuth = \Wss\User::isAuth($_REQUEST);
        if (!$arIsAuth['isAuth']) return self::$arErrMesAuth;

        $company_id = self::getCompanyIdByCurUser();
        $rs = \CIBlockElement::GetList(
            array(),
            array(
                "IBLOCK_ID" => self::$iblockDepartments,
                "ACTIVE" => "Y",
                "=NAME" => $name,
                "PROPERTY_company" => $company_id
            ),
            false,
            false,
            array("ID", "NAME")
        );
        if ($ar = $rs->GetNext()) {
            return $ar['NAME'];
        } else {
            return false;
        }
    }

    static public function addDepartment($request) // создание департамента (подразделения) компании
    {
        $result = array();
        $arIsAuth = \Wss\User::isAuth($_REQUEST);
        if (!$arIsAuth['isAuth']) return self::$arErrMesAuth;
        if (!$arIsAuth['billing']['active']) return \Wss\Billing::$arErrMessBilling;

        if (!$arIsAuth['roles']['admin']) return array('status' => 'error', 'message' => 'У Вам нет прав на добавление департамента');

        $company_id = self::getCompanyIdByCurUser();
        if ($company_id) {
            if (count($request['names']) == 0) return array('status' => 'error', 'message' => 'Не указаны подразделения (names)');

            $departments = self::getDepartments()['list'];

            $result['result'] = array();

            foreach ($request['names'] as $id => $name) {
                $PROP = array();
                if (is_numeric($id)) {
                    // получим все свойства, чтобы избежать потери не переданных значений при сохранении
                    $res = CIBlockElement::GetByID($id);
                    if ($ob = $res->GetNextElement()) {
                        $props = $ob->GetProperties();
                        foreach ($props as $prop) {
                            if ($prop['VALUE']) {
                                $PROP[$prop['ID']] = $prop['VALUE'];
                            }
                        }
                    }
                }
                $el = new \CIBlockElement;
                $PROP[10] = $company_id; // company (element)
                //        $PROP[9] = $USER->GetId(); // users (user multi)
                //        $PROP[13] = $USER->GetId(); // rop (user multi)
                $arLoadProductArray = array(
                    "IBLOCK_SECTION_ID" => false,
                    "IBLOCK_ID" => self::$iblockDepartments,
                    "PROPERTY_VALUES" => $PROP,
                    "NAME" => $name,
                    "ACTIVE" => "Y",
                );
                if (!array_filter($departments, function ($value) use ($id) {
                    return $value['id'] == $id;
                })) { // добавим
                    if (self::checkNameDepartment($name)) {
                        $result['result'][] = 'Указанное название подразделения уже существует - (' . $name . ')';
                    } else {
                        if ($ID = $el->Add($arLoadProductArray)) {
                            //                    $result['status'] = 'success';
                            $result['result'][] = 'Создан департамент с ID ' . $ID;
                        } else {
                            $result['status'] = 'error';
                            $result['message'] = $el->LAST_ERROR;
                        }
                    }
                } else { // обновим
                    if ($res = $el->Update($id, $arLoadProductArray)) {
                        //                    $result['status'] = 'success';
                        $result['result'][] = 'Изменен департамент с ID ' . $id;
                    } else {
                        $result['status'] = 'error';
                        $result['message'] = $el->LAST_ERROR;
                    }
                }
            }

            if ($result['status'] != 'error') {
                foreach ($departments as $dep) {
                    if (!$request['names'][$dep['id']]) {
                        $el = new \CIBlockElement;
                        $arLoadProductArray = array(
                            "ACTIVE" => "N"
                        );
                        if ($res = $el->Update($dep['id'], $arLoadProductArray)) {
                            $result['result'][] = 'Удален департамент с ID ' . $dep['id'];
                        } else {
                            $result['status'] = 'error';
                            $result['message'] = $el->LAST_ERROR;
                        }
                    }
                }

                $result['status'] = 'success';
                $result['message'] = 'Департаменты сохранены';
            }
        } else {
            $result['status'] = 'error';
            $result['message'] = 'Текущий пользователь не привязан ни к одной компании';
        }

        return $result;
    }

    static public function getDepartmentsByRequest($request)
    { // получение списка департаментов текущего пользователя
        //        global $USER;
        $arIsAuth = \Wss\User::isAuth($request);
        if (!$arIsAuth['isAuth']) return self::$arErrMesAuth;

        $result = array();

        $cache = Cache::createInstance();
        $cacheId = 'getDepartments' . md5(serialize($request)) . md5(serialize($arIsAuth));
        if ($cache->initCache(self::$cache_time_long, $cacheId, 'custom_cache')) {
            $result = $cache->getVars();
        } elseif ($cache->startDataCache()) {

            $company_id = self::getCompanyIdByCurUser();
            if ($company_id) {
                $rs = \CIBlockElement::GetList(
                    array(),
                    array(
                        "IBLOCK_ID" => self::$iblockDepartments,
                        "ACTIVE" => "Y",
                        "PROPERTY_company" => $company_id,
                        "ID" => $arIsAuth['department'] // отфильтруем департаменты для обычных сотрудников и роп, для других значение пустое
                    ),
                    false,
                    false,
                    array('ID', 'NAME', 'IBLOCK_ID')
                );
                while ($ob = $rs->GetNextElement()) {
                    $arFields = $ob->GetFields();
                    $arFields['value'] = (int)$arFields['ID'];
                    $arFields['label'] = $arFields['NAME']; //для Quasar Select
                    $arProps = $ob->GetProperties();
                    $arProps2 = array();
                    foreach ($arProps as $pv) {
                        $arProps2['prop_' . $pv['CODE']]['name'] = $pv['NAME'];
                        $arProps2['prop_' . $pv['CODE']]['code'] = $pv['CODE'];
                        if (($pv['CODE'] == 'users' || $pv['CODE'] == 'rop') && $pv['VALUE']) {
                            $rsUsers = \Bitrix\Main\UserTable::getList(array(
                                "order" => array('NAME' => 'ASC'),
                                "select" => array('ID', 'NAME', 'LAST_NAME', 'SECOND_NAME', 'EMAIL'),
                                "filter" => array('ID' => $pv['VALUE']),
                            ));
                            unset($pv['VALUE']);
                            while ($arUser = $rsUsers->Fetch()) {
                                $arUser['value'] = (int)$arUser['ID']; //для Quasar Select
                                if ($arUser['NAME']) $arUser['label'] = $arUser['NAME']; //для Quasar Select
                                if ($arUser['LAST_NAME']) {
                                    if ($arUser['NAME']) $arUser['label'] .= ' ' . $arUser['LAST_NAME'];
                                    else $arUser['label'] .= $arUser['LAST_NAME'];
                                }
                                if (!$arUser['NAME'] && !$arUser['LAST_NAME']) $arUser['label'] = 'ID ' . $arUser['ID'] . ' (имя не указано)';

                                $pv['VALUE'][] = array_change_key_case($arUser, CASE_LOWER);
                            }
                        }
                        $arProps2['prop_' . $pv['CODE']]['values'] = $pv['VALUE'] ?: "";
                    }

                    $result['list'][] = array_merge(array_change_key_case($arFields, CASE_LOWER), $arProps2);
                }

                if ($result['list']) {
                    $result['status'] = 'success';
                } else {
                    $result['status'] = 'error';
                    $result['message'] = 'У текущего пользователя департаменты не найдены';
                }
            } else {
                $result['status'] = 'error';
                $result['message'] = 'Текущий пользователь не привязан ни к одной компании';
            }

            $cache->endDataCache($result);
        }

        return $result;
    }


    static public function getDepartments()
    { // получение списка департаментов текущего пользователя
        //        global $USER;
        $arIsAuth = \Wss\User::isAuth($_REQUEST);
        if (!$arIsAuth['isAuth']) return self::$arErrMesAuth;

        $result = array();

        $cache = Cache::createInstance();
        $cacheId = 'getDepartments' . md5(serialize($_REQUEST)) . md5(serialize($arIsAuth));
        if ($cache->initCache(self::$cache_time_long, $cacheId, 'custom_cache')) {
            $result = $cache->getVars();
        } elseif ($cache->startDataCache()) {
            $user = CUser::GetId();
            $company_id = "";
            $rs = \CIBlockElement::GetList(
                [],
                [
                    "IBLOCK_ID" => self::$iblockCompanies,
                    "ACTIVE" => "Y",
                    [
                        "LOGIC" => "OR",
                        ["PROPERTY_admins" => $user],
                        ["PROPERTY_controllers" => $user],
                        ["PROPERTY_marketers" => $user],
                        ["PROPERTY_rops" => $user],
                        ["PROPERTY_users" => $user],
                        ["PROPERTY_moneys" => $user],
                        ["PROPERTY_chef" => $user],
                    ]
                ],
                false,
                false,
                [],
            );
            while ($ob = $rs->GetNextElement()) {
                $arFields = $ob->GetFields();
                $company_id = $arFields["ID"];
            }
            // return [$company_id];
            if ($company_id) {
                $rs = \CIBlockElement::GetList(
                    array(),
                    array(
                        "IBLOCK_ID" => self::$iblockDepartments,
                        "ACTIVE" => "Y",
                        "PROPERTY_company" => $company_id,
                        //"ID" => $arIsAuth['department'] // отфильтруем департаменты для обычных сотрудников и роп, для других значение пустое
                    ),
                    false,
                    false,
                    array('ID', 'NAME', 'IBLOCK_ID')
                );
                while ($ob = $rs->GetNextElement()) {
                    $arFields = $ob->GetFields();
                    $arFields['value'] = (int)$arFields['ID'];
                    $arFields['label'] = $arFields['NAME']; //для Quasar Select
                    $arProps = $ob->GetProperties();
                    $arProps2 = array();
                    foreach ($arProps as $pv) {
                        $arProps2['prop_' . $pv['CODE']]['name'] = $pv['NAME'];
                        $arProps2['prop_' . $pv['CODE']]['code'] = $pv['CODE'];
                        if (($pv['CODE'] == 'users' || $pv['CODE'] == 'rop' || $pv["CODE"] == 'moneys') && $pv['VALUE']) {
                            $rsUsers = \Bitrix\Main\UserTable::getList(array(
                                "order" => array('NAME' => 'ASC'),
                                "select" => array('ID', 'NAME', 'LAST_NAME', 'SECOND_NAME', 'EMAIL'),
                                "filter" => array('ID' => $pv['VALUE']),
                            ));
                            unset($pv['VALUE']);
                            while ($arUser = $rsUsers->Fetch()) {
                                $arUser['value'] = (int)$arUser['ID']; //для Quasar Select
                                if ($arUser['NAME']) $arUser['label'] = $arUser['NAME']; //для Quasar Select
                                if ($arUser['LAST_NAME']) {
                                    if ($arUser['NAME']) $arUser['label'] .= ' ' . $arUser['LAST_NAME'];
                                    else $arUser['label'] .= $arUser['LAST_NAME'];
                                }
                                if (!$arUser['NAME'] && !$arUser['LAST_NAME']) $arUser['label'] = 'ID ' . $arUser['ID'] . ' (имя не указано)';

                                $pv['VALUE'][] = array_change_key_case($arUser, CASE_LOWER);
                            }
                        }
                        $arProps2['prop_' . $pv['CODE']]['values'] = $pv['VALUE'] ?: "";
                    }

                    $result['list'][] = array_merge(array_change_key_case($arFields, CASE_LOWER), $arProps2);
                }

                if ($result['list']) {
                    $result['status'] = 'success';
                } else {
                    $result['status'] = 'error';
                    $result['message'] = 'У текущего пользователя департаменты не найдены';
                }
            } else {
                $result['status'] = 'error';
                $result['message'] = 'Текущий пользователь не привязан ни к одной компании';
            }

            $cache->endDataCache($result);
        }

        $current_role = Helpers::Check_Role($arIsAuth);
        // return $result;
        foreach ($result["list"] as $key => $value) {
            if ($current_role == "rops" || $current_role == "controllers" || $current_role == "moneys") {
                if (is_array($value["prop_$current_role"]["values"])) {
                    foreach ($value["prop_$current_role"]["values"] as $key2 => $value2) {
                        if (!$value2 == $arIsAuth["fields"]["ID"]) {
                            unset($result["list"][$key]);
                        }
                    }
                } else {
                    unset($result["list"][$key]);
                }
            }
        }

        return $result;
    }

    static public function getEventsCompany($request)
    { // получение событий компании текущего пользователя
        $arIsAuth = \Wss\User::isAuth($_REQUEST);

        if (!$arIsAuth['isAuth'])
            return self::$arErrMesAuth;

        $result = array();

        $cache = Cache::createInstance();
        $cacheId = 'getEventsCompany' . md5(serialize($_REQUEST) . serialize($request)) . md5(serialize($arIsAuth));
        if ($cache->initCache(self::$cache_time_long, $cacheId, 'custom_cache')) {
            $result = $cache->getVars();
        } elseif ($cache->startDataCache()) {

            $company_id = self::getCompanyIdByCurUser();
            if ($company_id) {
                $result['company_id'] = $company_id;
                $event_iblock = self::getCurUserEventIblockCompany();
                //return self::getCurUserEventIblockCompany();
                if (empty($event_iblock)) {
                    return [
                        'status' => 'error',
                        'message' => 'Инфоблок событий не найден',
                    ];
                }
                $arPropsEvent = self::getPropsByEventIblockCompany();

                $arFilterDate = array();
                if ($request['date_from']) {

                    if ($request['date_from'] == 'day') { // за 24 часа
                        $datefrom = date('Y-m-d H:i:s', strtotime(" -1 day"));
                        $dateto = date('Y-m-d 23:59:59');
                    } elseif ($request['date_from'] == 'cur_day') { // текущий день с 00 часов
                        $datefrom = date('Y-m-d 00:00:00');
                        $dateto = date('Y-m-d 23:59:59');
                    } elseif ($request['date_from'] == 'last_day') { // прошлый день с 00 часов
                        $datefrom = date('Y-m-d 00:00:00', strtotime(" -1 day"));
                        $dateto = date('Y-m-d 23:59:59', strtotime(" -1 day"));
                    } elseif ($request['date_from'] == 'week') { // за 7 дней
                        $datefrom = date('Y-m-d 00:00:00', strtotime(" -1 week"));
                        $dateto = date('Y-m-d 23:59:59');
                    } elseif ($request['date_from'] == 'cur_week') { // текущая неделя
                        $datefrom = date('Y-m-d 00:00:00', strtotime("monday this week"));
                        $dateto = date('Y-m-d 23:59:59', strtotime("sunday this week"));
                    } elseif ($request['date_from'] == 'last_week') { // прошлая неделя
                        $datefrom = date('Y-m-d 00:00:00', strtotime("monday last week"));
                        $dateto = date('Y-m-d 23:59:59', strtotime("sunday last week"));
                    } elseif ($request['date_from'] == 'month') { // за 30 дней
                        $datefrom = date('Y-m-d 00:00:00', strtotime(" -1 month"));
                        $dateto = date('Y-m-d 23:59:59');
                    } elseif ($request['date_from'] == 'cur_month') { // текущий месяц
                        $datefrom = date('Y-m-1 00:00:00');
                        $dateto = date('Y-m-d 23:59:59');
                    } elseif ($request['date_from'] == 'last_month') { // прошлый месяц
                        $datefrom = date('Y-m-d 00:00:00', strtotime("first day of last month"));
                        $dateto = date('Y-m-d 23:59:59', strtotime("last day of last month"));
                    } elseif ($request['date_from'] == 'year') { // за 365 дней
                        $datefrom = date('Y-m-d 00:00:00', strtotime(" -1 year"));
                        $dateto = date('Y-m-d 23:59:59');
                    } elseif ($request['date_from'] == 'cur_year') { // текущий год
                        $datefrom = date('Y-01-01 00:00:00');
                        $dateto = date('Y-m-d 23:59:59');
                    } elseif ($request['date_from'] == 'last_year') { // прошлый год
                        $datefrom = date('Y-01-01 00:00:00', strtotime(" -1 year"));
                        $dateto = date('Y-12-31 23:59:59', strtotime(" -1 year"));
                    } elseif ($request['date_from'] && $request['date_to']) { // период между датами
                        $datefrom = date('Y-m-d 00:00:00', strtotime($request['date_from']));
                        $dateto = date('Y-m-d 23:59:59', strtotime($request['date_to']));
                    }
                } elseif ($request['date']) {
                    $datefrom = date('Y-m-d 00:00:00', strtotime($request['date']));
                    $dateto = date('Y-m-d 23:59:59', strtotime($request['date']));
                }

                if ($datefrom && $dateto) {
                    $result['date_from'] = $datefrom;
                    $result['date_to'] = $dateto;

                    $arFilterDate[">=PROPERTY_date_time"] = trim(\CDatabase::CharToDateFunction(\ConvertTimeStamp(strtotime($datefrom), 'FULL')), "\'");
                    $arFilterDate["<=PROPERTY_date_time"] = trim(\CDatabase::CharToDateFunction(\ConvertTimeStamp(strtotime($dateto), 'FULL')), "\'");
                }

                $sort = array('property_date_time' => 'DESC'); // default sorting
                if ($request['sort']) {
                    $sort = $request['sort'];
                    $arSort = explode('-', $sort);

                    if ($arPropsEvent[$arSort[0]]) {
                        $sort = array('property_' . $arSort[0] => $arSort[1]);
                    } elseif ($arSort[0] == 'date') {
                        $sort = array('property_date_time' => $arSort[1]);
                    } else {
                        $sort = array($arSort[0] => $arSort[1]);
                    }
                }

                if (!$request['page_size']) $request['page_size'] = '';
                if (!$request['page_num']) $request['page_num'] = '';

                $arFilter = array("IBLOCK_ID" => $event_iblock['ID'], "ACTIVE" => "Y");
                //$arFilter['PROPERTY_department'] = $arIsAuth['department']; // отфильтруем департамент для роп и сотрудников
                if ($arIsAuth['roles']['users']) $arFilter['PROPERTY_user'] = $arIsAuth['user_id']; // если текущий пользователь обычный сотрудник, то покажем только его события

                foreach ($arPropsEvent as $propcode => $prop) { //переберем свойства и проверим есть ли пришедшие свойства в запросе фильтрации
                    if (isset($request[$propcode])) {
                        if (substr($request[$propcode], 0, 1) == '!') {
                            $arFilter['!PROPERTY_' . $propcode . '_VALUE'] = substr($request[$propcode], 1);
                        } elseif ($request[$propcode] === true) {
                            $arFilter['!PROPERTY_' . $propcode] = false;
                        } else {
                            $arFilter['PROPERTY_' . $propcode] = $request[$propcode];
                        }
                    }
                }

                $arFilter = array_merge($arFilter, $arFilterDate);

                if ($request['event_id']) $arFilter['ID'] = $request['event_id'];

                // добавим фильтрацию, согласно текущей роли пользователя


                //            $result['$arFilter'] = $arFilter;
                $result['total'] = (int)\CIBlockElement::GetList(array(), $arFilter, array(), false, array());

                $rs = \CIBlockElement::GetList(
                    $sort,
                    $arFilter,
                    false,
                    array("nPageSize" => $request['page_size'] ?: 9999, "iNumPage" => $request['page_num'] ?: 1),
                    array(
                        'ID',
                        'IBLOCK_ID',
                        $request['type'] ? 'PROPERTY_' . $request['type'] : '',
                        $request['group_by'] ? 'PROPERTY_' . $request['group_by'] : '',
                        'PROPERTY_event_type',
                        'PROPERTY_result',
                        'PROPERTY_DEPARTMENT'
                    ),
                );
                //            $result['$arPropsEvent'] = $arPropsEvent;
                $result['list'] = array();
                $result['$arPropsEvent'] = $arPropsEvent;
                while ($ob = $rs->GetNextElement()) {
                    $event = array();
                    $arFields = $ob->GetFields();
                    //return $arFields;
                    if ($request['simple'] !== true && $request['simple'] !== 'y') {
                        $arProps = $ob->GetProperties();
                        $event['date']['value'] = date('Y-m-d', strtotime($arProps['date_time']['VALUE']));
                    } elseif (($request['simple'] === true || $request['simple'] === 'y') && ($request['type'] || $request['group_by'])) {
                        if ($arPropsEvent[$request['type']]['values']) {
                            $event_filter = array_filter(
                                $arPropsEvent[$request['type']]['values'],
                                fn($value) => $value['value'] == $arFields['PROPERTY_' . strtoupper($request['type']) . '_VALUE']
                            );
                            if (current($event_filter)) {
                                $event[$request['type']]['value'] = current($event_filter)['name'] ?: current(
                                    $event_filter
                                )['value'];
                            }
                        }
                        if ($arPropsEvent[$request['group_by']]['values'] && $request['type'] != $request['group_by']) {
                            $event_filter = array_filter(
                                $arPropsEvent[$request['group_by']]['values'],
                                fn($value) => $value['value'] == $arFields['PROPERTY_' . strtoupper($request['group_by']) . '_VALUE']
                            );
                            if (current($event_filter)) {
                                $event[$request['group_by']]['value'] = current($event_filter)['name'] ?: current(
                                    $event_filter
                                )['value'];
                            }
                        }
                        if ($arFields['PROPERTY_DEPARTMENT_VALUE']) {
                            $event['department']['value_id'] = $arFields['PROPERTY_DEPARTMENT_VALUE'];
                        }
                        if ($arFields['PROPERTY_EVENT_TYPE_VALUE']) {
                            $event['event_type']['value'] = $arFields['PROPERTY_EVENT_TYPE_VALUE'];
                            if ($arPropsEvent['event_type']['values']) {
                                $eventTypeFilter = array_filter(
                                    $arPropsEvent['event_type']['values'],
                                    fn($value) => $value['label'] == $arFields['PROPERTY_EVENT_TYPE_VALUE']
                                );
                                $event['event_type']['value_xml_id'] = current($eventTypeFilter)['xml_id'];
                            }
                        }
                        if ($arFields['PROPERTY_RESULT_VALUE']) {
                            $event['result']['value'] = $arFields['PROPERTY_RESULT_VALUE'];
                            if ($arPropsEvent['result']['values']) {
                                $eventTypeFilter = array_filter(
                                    $arPropsEvent['result']['values'],
                                    fn($value) => $value['label'] == $arFields['PROPERTY_RESULT_VALUE']
                                );
                                $event['result']['value_xml_id'] = current($eventTypeFilter)['xml_id'];
                            }
                        }
                    }
                    $event['date']['name'] = $request['date_name'];
                    foreach ($arProps as $codeProp => $prop) {
                        //                        $event[$codeProp]['all'] = $prop;
                        $event[$codeProp]['id'] = $prop['ID'];
                        $event[$codeProp]['name'] = $prop['NAME'];
                        $event[$codeProp]['value'] = $prop['VALUE']; // default
                        if ($prop['VALUE_ENUM_ID']) {
                            $event[$codeProp]['value_id'] = $prop['VALUE_ENUM_ID'];
                            $event[$codeProp]['value_xml_id'] = $prop['VALUE_XML_ID'];
                        }
                        if ($codeProp == 'department') {
                            $event[$codeProp]['name'] = $request[$codeProp . '_name'];
                            $event[$codeProp]['value'] = $arProps['department']['VALUE'];
                        }
                        if ($codeProp == 'date_time') {
                            $event[$codeProp]['name'] = $request[$codeProp . '_name'];
                            $event[$codeProp]['value'] = date('H:i', strtotime($arProps['date_time']['VALUE']));
                        } elseif ($codeProp == 'user') {
                            $arUser = \Wss\User::getFields($prop['VALUE']);
                            $event[$codeProp]['value_id'] = $prop['VALUE'];
                            if ($arUser['NAME']) {
                                $arUser['value'] = $arUser['NAME'];
                            } //для Quasar Select
                            if ($arUser['LAST_NAME']) {
                                if ($arUser['NAME']) {
                                    $arUser['value'] .= ' ' . $arUser['LAST_NAME'];
                                } else {
                                    $arUser['value'] .= $arUser['LAST_NAME'];
                                }
                            }
                            if (!$arUser['NAME'] && !$arUser['LAST_NAME']) {
                                $arUser['value'] = 'ID ' . $arUser['ID'] . ' (имя не указано)';
                            }
                            $event[$codeProp]['value'] = $arUser['value'];
                        } else {
                            if (is_numeric($prop['VALUE'])) {
                                $event[$codeProp]['value_id'] = $prop['VALUE'];
                            }
                            if ($arPropsEvent[$codeProp]['values']) {
                                $event_filter = array_filter(
                                    $arPropsEvent[$codeProp]['values'],
                                    fn($value) => $value['value'] == $prop['VALUE']
                                );
                                if (current($event_filter)) {
                                    $event[$codeProp]['value'] = current($event_filter)['name'] ?: current(
                                        $event_filter
                                    )['value'];
                                }
                            }
                        }
                        //                    if($arPropsEvent[$codeProp]) {
                        //                        $event[$codeProp]['type'] = $arPropsEvent[$codeProp]['type'];
                        //                    }
                    }

                    unset($event['company']);
                    $event['event_id']['name'] = 'ID';
                    $event['event_id']['value'] = $arFields['ID'];
                    $result['list'][] = $event;
                    //                $result['list'][$arFields['ID']]['fields'] = $arFields;
                    //                $result['list'][$arFields['ID']]['props'] = $arProps;
                }
                if (empty($result['list'])) {
                    $result['message'] = 'События не найдены';
                }

                $result['status'] = 'success';
            } else {
                $result['status'] = 'error';
                $result['message'] = 'Текущий пользователь не привязан ни к одной компании';
            }

            $cache->endDataCache($result);
        }
        //return ["1" => 3];
        //return $result;
        //$user = CUser::GetByID(CUser::GetID());
        $salons = User::getSalons($arIsAuth['fields']["ID"]);
        //return $salons;
        foreach ($salons as $key => $value) {
            $salons[$value["id"]] = $value;
            unset($salons[$key]);
        };
        foreach ($result["list"] as $key => $value) {
            if (array_key_exists($value["department"]["value_id"], $salons) && $salons[$value["department"]["value_id"]]["status"] === true) {

            } else {
                unset($result["list"][$key]);
            }
        }

        return $result;
    }

    // настройки из инфоблока "тип событий"
    public static function getSettingsEventTypes()
    {
        $result = [];

        $cache = Cache::createInstance();
        $cacheId = 'getSettingsEventTypes' . md5(serialize($_REQUEST));
        if ($cache->initCache(self::$cache_time_long, $cacheId, 'custom_cache')) {
            $result = $cache->getVars();
        } elseif ($cache->startDataCache()) {

            //            $properties = [];
            //            foreach (['event_type', 'result'] as $prop) {
            //                $property_enums = \CIBlockPropertyEnum::GetList(
            //                    ["DEF" => "DESC", "SORT" => "ASC"],
            //                    ["IBLOCK_ID" => self::$iblockSettings, "CODE" => $prop]
            //                );
            //                while ($enum_fields = $property_enums->GetNext()) {
            //                    $properties[$enum_fields['PROPERTY_ID']][$enum_fields["ID"]] = $enum_fields;
            //                }
            //            }

            //            $result['$properties'] = $properties;

            //            $company_id = self::getCompanyIdByCurUser();
            $rs = \CIBlockElement::GetList(
                [],
                [
                    "IBLOCK_ID" => self::$iblockSettings,
                    "ACTIVE" => "Y",
                    //                    "PROPERTY_company" => $company_id,
                ],
                false,
                false,
                ['ID', 'NAME', 'IBLOCK_ID']
            );

            while ($ob = $rs->GetNextElement()) {
                //                $arFields = $ob->GetFields();
                $arProps = $ob->GetProperties();
                //                $result[$arFields['ID']]['NAME'] = $arFields['NAME'];
                //                $result[$arFields['ID']]['PROPERTIES'] = $arProps;

                if ($arProps['type_of_event_type']['VALUE_XML_ID'] && $arProps['sostav']['VALUE']) {
                    foreach ($arProps['sostav']['VALUE'] as $sostav) {
                        if ($sostav['SUB_VALUES']['event_type']['VALUE_XML_ID'] && $sostav['SUB_VALUES']['result']['VALUE_XML_ID']) {
                            $result[$arProps['type_of_event_type']['VALUE_XML_ID']][] = [
                                $sostav['SUB_VALUES']['event_type']['VALUE_XML_ID'],
                                $sostav['SUB_VALUES']['result']['VALUE_XML_ID'],
                            ];
                        }
                    }
                }
            }
            $cache->endDataCache($result);
        }
        return $result;
    }

    static public function getEventsCompanyCounts($request)
    {
        $result = array();

        $events = self::getEventsCompany($request);
        $result['score']['value'] = 0;
        $result['score']['unit'] = '';

        $requestTypes = explode('-', $request['type']);

        if ($events['status'] == 'error') return $events; // событий нет


        $arTypesName = array('all' => 'Общий трафик', 'targeted' => 'Целевой трафик', 'no_targeted' => 'Нецелевой трафик', 'missed' => 'Упущенный трафик', 'no_missed' => 'Не упущенный трафик', 'primary' => 'Первичный трафик', 'secondary' => 'Вторичный трафик', 'contract' => 'Количество договоров');
        $result['score']['name'] = $request['type'] ? $arTypesName[$request['type']] : 'Общий';
        if (count($requestTypes) > 1) {
            $result['score']['name'] = '';
            foreach ($requestTypes as $requestType) {
                $result['score']['name'] .= $arTypesName[$requestType] ? $arTypesName[$requestType] . ' ' : 'Общий ';
            }
            $result['score']['name'] = trim($result['score']['name']);
        }
        //return $result;
        //return $result;
        $arPropsEvent = self::getPropsByEventIblockCompany();

        //        $result['$arPropsEvent'] = $arPropsEvent;

        //        if(!$events['date_from']) return array('status'=>'error','Не указан период `date_from`, за который необходимо получить данные');

        $events_last = array();
        if ($events['date_from'] && $events['date_to']) {
            if ($request['group_by_date'] == 'y') {
                // получим все даты за период
                $datesFromPeriod = self::getDatesFromRange($events['date_from'], $events['date_to']);
                // преобразуем даты в формат вида '01 Янв'
                $result['dates'] = array_map(function ($value) {
                    return date('d', strtotime($value)) . ' ' . self::$arMonthRusShort[date('m', strtotime($value))];
                }, $datesFromPeriod);
            }

            $result['trend']['value'] = 0;
            $result['trend']['unit'] = '%';
            $events['date_from'] = strtotime($events['date_from']);
            $events['date_to'] = strtotime($events['date_to']);

            $result['period'] = floor((($events['date_to'] - $events['date_from']) + 1) / 86400); // дней между датами (прибавили 1 секунду, чтобы округляло корректно т.к.дата часто идет до 23:59:59)

            $date_from_last = $events['date_from'] - $events['date_to'] + $events['date_from']; // вычислим дату на предыдущий период
            $date_to_last = strtotime('-1 second', $events['date_from']);

            //            $result['date_from'] = date('d-m-Y H:i:s',$events['date_from']);
            //            $result['date_to'] = date('d-m-Y H:i:s',$events['date_to']);
            //            $result['date_from_last'] = date('d-m-Y H:i:s',$date_from_last);
            //            $result['date_to_last'] = date('d-m-Y H:i:s',$date_to_last);

            // получим события за предыдущий период
            $request_last = $request;
            $request_last['date_from'] = date('d-m-Y H:i:s', $date_from_last);
            $request_last['date_to'] = date('d-m-Y H:i:s', $date_to_last);
            $events_last = self::getEventsCompany($request_last);
        }

        //        $result['events_list'] = $events;
        //        $result['events_list_last'] = $events_last;

        $settingsEvent = self::getSettingsEventTypes();
        //        $result['$events'] = $events;
        $events_list = array();
        $events_list_last = array();

        foreach (array($events, $events_last) as $ke => $evs) {
            if (empty($evs['list'])) continue;
            foreach ($evs['list'] as $event) {
                //                $event_type_filter = array_filter($arPropsEvent['event_type']['values'], function ($value) use ($event) {
                //                    return $value['label'] == $event['event_type']['value'];
                //                });
                //                $result_filter = array_filter($arPropsEvent['result']['values'], function ($value) use ($event) {
                //                    return $value['label'] == $event['result']['value'];
                //                });
                foreach ($requestTypes as $type) {
                    if ($request['type'] && $type !== 'all') {
                        if (!$settingsEvent[$type]) {
                            return [
                                'status' => 'error',
                                'message' => 'Указанный тип не существует',
                            ];
                        }

                        foreach ($settingsEvent[$type] as $setting) {
                            if (
                                strpos($event['event_type']['value_xml_id'], $setting[0]) === 0
                                && strpos($event['result']['value_xml_id'], $setting[1]) === 0
                            ) {
                                if ($ke == 0) { // если первый массив
                                    $events_list[] = $event;
                                    $result['score']['value']++;
                                } else {
                                    $events_list_last[] = $event;
                                    $result['trend']['value']++;
                                }
                                break;
                            }
                        }
                    } else {
                        if ($ke == 0) { // если первый массив
                            $events_list[] = $event;
                            $result['score']['value']++;
                        } else {
                            $events_list_last[] = $event;
                            $result['trend']['value']++;
                        }
                    }
                }


                //                if(current($event_type_filter)['targeted'] && $request['type'] == 'targeted'
                //                    || (!current($event_type_filter)['targeted'] || current($event_type_filter)['no_targeted']) && $request['type'] == 'no_targeted'
                //                    || current($event_type_filter)['missed'] && $request['type'] == 'missed'
                //                    || !current($event_type_filter)['missed'] && $request['type'] == 'no_missed'

                //                if (
                //                    (in_array('targeted', $requestTypes)
                //                        && (
                //                            (current($result_filter)['targeted'] && current($event_type_filter)['targeted'] && !current($event_type_filter)['secondary'])
                //                            || ($request['get_contracts_all'] === true && current($result_filter)['contract'])
                //                        )
                //                    )
                //                    ||
                //                    (in_array('no_targeted', $requestTypes)
                //                        && !current($result_filter)['targeted']
                //                        && current($result_filter)['no_targeted']
                //                        && current($event_type_filter)['no_targeted']
                //                        && !current($event_type_filter)['primary']
                //                    )
                //                    ||
                //                    (in_array('missed', $requestTypes)
                //                        && current($result_filter)['missed']
                //                        && current($event_type_filter)['missed']
                //                    )
                //                    ||
                //                    (in_array('no_missed', $requestTypes)
                //                        && !current($result_filter)['missed']
                //                        && !current($event_type_filter)['missed']
                //                    )
                //                    ||
                //                    (in_array('primary', $requestTypes)
                //                        && current($result_filter)['primary']
                //                        && current($event_type_filter)['primary']
                //                    )
                //                    ||
                //                    (in_array('secondary', $requestTypes)
                //                        && current($result_filter)['secondary']
                //                        && current($event_type_filter)['secondary']
                //                    )
                //                    ||
                //                    (in_array('contract', $requestTypes)
                //                        && current($result_filter)['contract']
                //                    )
                //                    || $request['type'] == 'all'
                //                    || !$request['type']
                //                ) {
                //                    if($ke == 0) { // если первый массив
                //                        $events_list[] = $event;
                //                        $result['score']['value']++;
                //                    }else{
                //                        $events_list_last[] = $event;
                //                        $result['trend']['value']++;
                //                    }
                //                }


                //                if(
                //                    ((((current($result_filter)['targeted'] || current($event_type_filter)['targeted']) && !current($event_type_filter)['secondary']) || ($request['get_contracts_all'] === true && current($result_filter)['contract'])) && in_array('targeted', $requestTypes))
                //                    || (!current($result_filter)['targeted'] || current($result_filter)['no_targeted'] || current($event_type_filter)['no_targeted']) && !current($event_type_filter)['primary'] && in_array('no_targeted', $requestTypes)
                //                    || ((current($result_filter)['missed'] || current($event_type_filter)['missed']) && in_array('missed', $requestTypes))
                //                    || !current($result_filter)['missed'] && in_array('no_missed', $requestTypes)
                //                    || current($event_type_filter)['primary'] && current($result_filter)['primary'] && in_array('primary', $requestTypes)
                //                    || current($event_type_filter)['secondary'] && current($result_filter)['secondary'] && in_array('secondary', $requestTypes)
                //                    || current($result_filter)['contract'] && in_array('contract', $requestTypes)
                //                    || $request['type'] == 'all'
                //                    || !$request['type']
                //                ){
                //                    if($ke == 0) { // если первый массив
                //                        $events_list[] = $event;
                //                        $result['score']['value']++;
                //                    }else{
                //                        $events_list_last[] = $event;
                //                        $result['trend']['value']++;
                //                    }
                //                }

                if ($datesFromPeriod && $request['group_by_date'] == 'y' && !$request['group_by']) {
                    $result['score']['data'] = array();
                    foreach ($datesFromPeriod as $date) {
                        $result['score']['data'][] = count(array_filter($events_list, function ($value) use ($date) {
                            return strtotime($value['date']['value']) == strtotime($date);
                        }));
                    }
                }
            }
        }

        if ($request['get_list']) { // параметр для получения списка событий с полями
            $result['events_list'] = $events_list;
            $result['events_list_last'] = $events_list_last;
        }

        if ($result['trend']) {
            $value_trend = $result['trend']['value'];
            // вычислим разницу в процентах
            $value_percent = 0;
            if ($result['score']['value'] === 0) { // если ноль т.к.на ноль делить нельзя
                $value_percent = $result['trend']['value'] * 100;
                if ($value_percent > 0) $value_percent = '-' . (string)round($value_percent, 1);
            } elseif ($result['trend']['value'] === 0) {
                $value_percent = $result['score']['value'] * 100;
                if ($value_percent > 0) $value_percent = '+' . (string)round($value_percent, 1);
            } elseif ($result['score']['value'] < $result['trend']['value']) {
                $value_percent = '-' . (string)round(($result['trend']['value'] - $result['score']['value']) / $result['score']['value'] * 100, 1);
            } elseif ($result['score']['value'] > $result['trend']['value']) {
                $value_percent = '+' . (string)round(($result['score']['value'] - $result['trend']['value']) / $result['trend']['value'] * 100, 1);
            }

            $result['trend']['value'] = $value_percent;
            //            $result['trend']['value_count'] = $value_trend;

        }

        //        $result['$events'] = $events;
        //        $result['$settingsEvent'] = $settingsEvent;
        //        $result['$arPropsEvent'] = $arPropsEvent;
        //        $result['$events_list'] = $events_list;
        // группировка по свойству

        if ($request['group_by']) {
            //            $result['$events_list'] = $events_list;
            $arPropsEvent[$request['group_by']]['values'][] = array('label' => '');
            foreach ($arPropsEvent[$request['group_by']]['values'] as $val) { // группировка по свойству
                $event_filter = array_filter($events_list, function ($value) use ($val, $request) {

                    return $value[$request['group_by']]['value'] == $val['label'];
                });

//return $event_filter;
                if ($request["key"] && $request["key"] == "traffic") {
                    foreach ($event_filter as $key => $value) {
                        if ($value["event_type"]["value"] == "Первичный") {
                        } else {
                            unset($event_filter[$key]);
                        }

                    }
                }
                //return $event_filter;
                // если группировка по результату и тип первичный или вторичный, то оставим только соответствующие типу результаты
                //                if($request['group_by'] == 'result' && in_array('targeted', $requestTypes) && !$val['primary']) continue;
                //                if($request['group_by'] == 'result' && in_array('primary', $requestTypes) && !$val['primary']) continue;
                //                if($request['group_by'] == 'result' && in_array('secondary', $requestTypes) && !$val['secondary']) continue;
                //                if($request['group_by'] == 'result' && in_array('missed', $requestTypes) && !$val['missed']) continue;

                if (
                    $request['group_by'] != 'result'
                    ||
                    $request['result_all'] == 'y'
                    ||
                    ($request['group_by'] == 'result' &&
                        (
                            (in_array('targeted', $requestTypes) && $val['primary'])
                            || (in_array('primary', $requestTypes) && $val['primary'])
                            || (in_array('secondary', $requestTypes) && $val['secondary'])
                            || (in_array('missed', $requestTypes) && $val['missed'])
                            || in_array('all', $requestTypes)
                        )
                    )
                ) {
                    $group = [];
                    $score_value_count = count($event_filter);
                    $group['score']['name'] = $val['label'];
                    $group['score']['value_percent'] = $result['score']['value'] == 0
                        ? 0
                        : (string)round(
                            $score_value_count / $result['score']['value'] * 100,
                            1
                        );
                    $group['score']['value'] = $score_value_count;
                    $group['score']['unit'] = '';

                    $events_list = array_reverse($events_list);
                    foreach ($events_list as $event) {
                        if ($group['score']['name'] === $event['result']['value']) {
                            if ($event['client_category']['value']) {
                                $group['score']['comment'][] = $event['client_category']['value'] . ': ' . $event['comment']['value'];
                            } else {
                                $group['score']['comment'][] = $event['comment']['value'];
                            }
                        }
                    }

                    if ($datesFromPeriod && $request['group_by_date'] == 'y') {
                        $group['score']['data'] = [];
                        foreach ($datesFromPeriod as $date) {
                            $group['score']['data'][] = count(
                                array_filter(
                                    $event_filter,
                                    function ($value) use ($date) {
                                        return strtotime($value['date']['value']) == strtotime($date);
                                    }
                                )
                            );
                        }
                    }

                    if ($result['trend'] && $request['group_by_date'] != 'y') {
                        $event_last_filter = array_filter(
                            $events_list_last,
                            function ($value) use ($val, $request) {
                                return $value[$request['group_by']]['value'] == $val['label'];
                            }
                        );
                        $trend_value_count = count($event_last_filter);
                        $group['trend']['value'] = (string)round(($score_value_count - $trend_value_count) * 100, 1);
                        //                    if($group['trend']['value'] > 0) $group['trend']['value'] = '+'.$group['trend']['value'];

                        if ($score_value_count > $trend_value_count) {
                            $group['trend']['value'] = '+' . $group['trend']['value'];
                        }

                        //                    $group['trend']['value_count'] = count($event_last_filter);
                        $group['trend']['unit'] = '%';

                        if ($datesFromPeriod && $request['group_by_date'] == 'y') {
                            $group['trend']['data'] = [];
                            foreach ($datesFromPeriod as $date) {
                                $group['trend']['data'][] = count(
                                    array_filter(
                                        $event_last_filter,
                                        function ($value) use ($date) {
                                            return strtotime($value['date']['value']) == strtotime($date);
                                        }
                                    )
                                );
                            }
                        }
                        //                    if($score_value_count > 0 || $trend_value_count > 0) $result['groups'][] = $group;
                    } else {
                        //                    if($score_value_count > 0) $result['groups'][] = $group;
                    }
                    if (!empty($score_value_count) || !empty($trend_value_count) || !empty($group['score']['name'])) {
                        $result['groups'][] = $group;
                    }
                }
            }
        }

        return $result;
    }

    static public function getEventsCompanyCountsConversion($request)
    {
        $result = self::getEventsCompanyCounts(array_merge($request, array('get_contracts_all' => true)));

        if ($result['status'] == 'error') return $result;

        return $result;
    }

    static public function getEventsCompanyCountsContractConversion($request)
    {
        $result = $events_count_targeted = self::getEventsCompanyCounts($request);

        if ($events_count_targeted['status'] == 'error') return $events_count_targeted;

        $events_count_contract = self::getEventsCompanyCounts(array_merge($request, array('type' => 'contract')));


        foreach ($events_count_targeted['groups'] as $kgroup => $group) {
            foreach ($group['score']['data'] as $kdata => $data) {
                $count_contracts = $events_count_contract['groups'][$kgroup]['score']['data'][$kdata];
                if ($data > 0) $count_contracts = $count_contracts / $data * 100;
                else $count_contracts = 0;
                $events_count_contract['groups'][$kgroup]['score']['data'][$kdata] = round($count_contracts, 1);
                $events_count_contract['groups'][$kgroup]['score']['unit'] = '%';
            }
        }


        //        $result['$events_count_contract'] = $events_count_contract;
        return $events_count_contract;
    }

    static public function getEventsCompanyCountsByTypes($request)
    {
        $result = array();
        if ($request['date_from'] && $request['date_to']) {
            // получим все даты за период
            $datesFromPeriod = self::getDatesFromRange($request['date_from'], $request['date_to']);
            // преобразуем даты в формат вида '01 Янв'
            $result['dates'] = array_map(function ($value) {
                return date('d', strtotime($value)) . ' ' . self::$arMonthRusShort[date('m', strtotime($value))];
            }, $datesFromPeriod);
        }

        $result['days'] = array(1 => 'Пн', 2 => 'Вт', 3 => 'Ср', 4 => 'Чт', 5 => 'Пт', 6 => 'Сб', 7 => 'Вс');
        $result['hours'] = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23');

        $types = array('all', 'targeted', 'no_targeted', 'missed');

        foreach ($types as $type) {
            $events = self::getEventsCompanyCounts(array_merge($request, array('type' => $type, 'get_list' => true)));
            if ($events['status'] != 'error') {
                if ($datesFromPeriod) {
                    foreach ($datesFromPeriod as $date) {
                        $events['score']['data_date'][] = count(array_filter($events['events_list'], function ($value) use ($date) {
                            return strtotime($value['date']['value']) == strtotime($date);
                        }));
                        if ($events['events_list_last']) {
                            $events['trend']['data_date'][] = count(array_filter($events['events_list_last'], function ($value) use ($date) {
                                return strtotime($value['date']['value']) == strtotime($date);
                            }));
                        }
                    }

                    foreach ($result['days'] as $kday => $day) {
                        $events['score']['data_day'][] = count(array_filter($events['events_list'], function ($value) use ($kday) {
                            return date('N', strtotime($value['date']['value'])) == $kday;
                        }));
                        if ($events['events_list_last']) {
                            $events['trend']['data_day'][] = count(array_filter($events['events_list_last'], function ($value) use ($kday) {
                                return date('N', strtotime($value['date']['value'])) == $kday;
                            }));
                        }
                    }

                    foreach ($result['hours'] as $hour) {
                        $events['score']['data_hour'][] = count(array_filter($events['events_list'], function ($value) use ($hour) {
                            return date('G', strtotime($value['date']['value'] . ' ' . $value['date_time']['value'])) == $hour;
                        }));
                        if ($events['events_list_last']) {
                            $events['trend']['data_hour'][] = count(array_filter($events['events_list_last'], function ($value) use ($hour) {
                                return date('G', strtotime($value['date']['value'] . ' ' . $value['date_time']['value'])) == $hour;
                            }));
                        }
                    }

                    //                    unset($events['events_list'],$events['events_list_last']);
                }
                $result['groups'][$type] = $events;
            } else {
                return $events;
            }
        }

        foreach ($result['groups']['all']['score']['data_hour'] as $kh => $hour) { // удалим пустые значения в начале
            if (
                $result['groups']['all']['score']['data_hour'][$kh] === 0
                && $result['groups']['all']['trend']['data_hour'][$kh] === 0
                && $result['groups']['targeted']['score']['data_hour'][$kh] === 0
                && $result['groups']['targeted']['trend']['data_hour'][$kh] === 0
                && $result['groups']['no_targeted']['score']['data_hour'][$kh] === 0
                && $result['groups']['no_targeted']['trend']['data_hour'][$kh] === 0
                && $result['groups']['missed']['score']['data_hour'][$kh] === 0
                && $result['groups']['missed']['trend']['data_hour'][$kh] === 0
            ) {
                foreach ($types as $type) {
                    unset($result['groups'][$type]['score']['data_hour'][$kh]);
                    unset($result['groups'][$type]['trend']['data_hour'][$kh]);
                    unset($result['hours'][$kh]);
                }
            } else {
                break;
            }
        }
        foreach (array_reverse($result['groups']['all']['score']['data_hour'], true) as $kh => $hour) { // удалим пустые значения в конце
            if (
                $result['groups']['all']['score']['data_hour'][$kh] === 0
                && $result['groups']['all']['trend']['data_hour'][$kh] === 0
                && $result['groups']['targeted']['score']['data_hour'][$kh] === 0
                && $result['groups']['targeted']['trend']['data_hour'][$kh] === 0
                && $result['groups']['no_targeted']['score']['data_hour'][$kh] === 0
                && $result['groups']['no_targeted']['trend']['data_hour'][$kh] === 0
                && $result['groups']['missed']['score']['data_hour'][$kh] === 0
                && $result['groups']['missed']['trend']['data_hour'][$kh] === 0
            ) {
                foreach ($types as $type) {
                    unset($result['groups'][$type]['score']['data_hour'][$kh]);
                    unset($result['groups'][$type]['trend']['data_hour'][$kh]);
                    unset($result['hours'][$kh]);
                }
            } else {
                break;
            }
        }

        return $result;
    }

    static public function getEventsCompanyCountsPrimary($request)
    {
        $result = array();
        $requestOriginal = $request;
        $events_primary = self::getEventsCompanyCounts(array_merge($request, array('type' => 'primary', 'get_list' => true)));
        if ($events_primary['status'] == 'error') return $events_primary;
        $result['$request'] = $request;
        $events_primary_group_departments_filtered = self::getEventsCompanyCounts(array_merge($request, array('type' => 'primary', 'group_by' => 'department', 'group_by_date' => 'y')));


        $events_primary_group_results = self::getEventsCompanyCounts(array_merge($request, array('type' => 'primary', 'group_by' => 'result', 'group_by_date' => 'y')));
        unset($request['department']); // уберем некоторые фильтры для круговых диаграмм
        unset($request['user']); // уберем некоторые фильтры для круговых диаграмм
        $events_missed_group_departments = self::getEventsCompanyCounts(array_merge($request, array('type' => 'missed', 'group_by' => 'department', 'group_by_date' => 'y')));
        $events_primary_group_departments = self::getEventsCompanyCounts(array_merge($request, array('type' => 'primary', 'group_by' => 'department', 'group_by_date' => 'y')));
        $request = $requestOriginal;


        $result['groups_type']['primary']['score'] = $events_primary['score'];
        $result['groups_type']['primary']['trend'] = $events_primary['trend'];
        $result['groups_type']['primary']['period'] = $events_primary['period'];

        $result['groups_type']['primary_medium']['score']['name'] = 'В среднем первичных за день';
        $result['groups_type']['primary_medium']['score']['value'] = round($events_primary['score']['value'] / $events_primary['period'], 1);
        $result['groups_type']['primary_medium']['score']['unit'] = '';
        $result['groups_type']['primary_medium']['trend']['value'] = $events_primary['trend']['value'];
        $result['groups_type']['primary_medium']['trend']['unit'] = '%';
        $result['groups_type']['primary_medium']['period'] = $events_primary['period'];

        $result['dates'] = $events_primary_group_departments['dates'];
        $result['groups_department_primary_filtered'] = $events_primary_group_departments_filtered['groups'];
        $result['groups_department_primary'] = $events_primary_group_departments['groups'];
        $result['groups_department_missed'] = $events_missed_group_departments['groups'];
        $result['groups_results_primary'] = $events_primary_group_results['groups'];


        //        $result['events'] = $events_primary_group_departments;


        return $result;
    }

    static public function getEventsCompanyCountsSecondary($request)
    {
        $result = array();
        $events_secondary = self::getEventsCompanyCounts(array_merge($request, array('type' => 'secondary', 'group_by_date' => 'y')));
        if ($events_secondary['status'] == 'error') return $events_secondary;
        $events_secondary_group_departments_filtered = self::getEventsCompanyCounts(array_merge($request, array('type' => 'secondary', 'group_by' => 'department', 'group_by_date' => 'y')));
        $events_secondary_group_contracts = self::getEventsCompanyCounts(array_merge($request, array('type' => 'contract', 'group_by_date' => 'y')));
        $events_secondary_group_results = self::getEventsCompanyCounts(array_merge($request, array('type' => 'secondary', 'group_by' => 'result', 'group_by_date' => 'y')));
        unset($request['department']); // уберем некоторые фильтры для круговых диаграмм
        unset($request['user']); // уберем некоторые фильтры для круговых диаграмм
        $events_secondary_group_departments = self::getEventsCompanyCounts(array_merge($request, array('type' => 'secondary', 'group_by' => 'department', 'group_by_date' => 'y')));

        $result['dates'] = $events_secondary['dates'];

        $result['groups_type']['secondary']['score'] = $events_secondary['score'];
        unset($result['groups_type']['secondary']['score']['data']);
        $result['groups_type']['secondary']['trend'] = $events_secondary['trend'];
        $result['groups_type']['secondary']['period'] = $events_secondary['period'];

        $result['groups_type']['secondary_medium']['score']['name'] = 'В среднем вторичных за день';
        $result['groups_type']['secondary_medium']['score']['value'] = round($events_secondary['score']['value'] / $events_secondary['period'], 1);
        $result['groups_type']['secondary_medium']['score']['unit'] = '';
        $result['groups_type']['secondary_medium']['trend']['value'] = $events_secondary['trend']['value'];
        $result['groups_type']['secondary_medium']['trend']['unit'] = '%';
        $result['groups_type']['secondary_medium']['period'] = $events_secondary['period'];

        $result['groups_department_secondary_filtered'] = $events_secondary_group_departments_filtered['groups'];
        $result['groups_department_secondary'] = $events_secondary_group_departments['groups'];
        $result['groups_results_secondary'] = $events_secondary_group_results['groups'];
        $result['groups_contracts'][] = array('score' => $events_secondary_group_contracts['score']);
        $result['groups_contracts'][] = array('score' => $events_secondary['score']);

        return $result;
    }

    static public function getEventsCompanyCountsMissed($request)
    {
        $result = array();

        $events = self::getEventsCompanyCounts(array_merge($request, array('type' => 'all')));
        if ($events['status'] == 'error') return $events;
        //        $events_targeted = self::getEventsCompanyCounts(array_merge($request,array('type'=>'targeted','group_by_date'=>'y')));
        $events_missed = self::getEventsCompanyCounts(array_merge($request, array('type' => 'missed', 'group_by_date' => 'y')));
        $events_targeted_group_departments = self::getEventsCompanyCounts(array_merge($request, array('type' => 'targeted', 'group_by' => 'department', 'group_by_date' => 'y')));
        $events_missed_group_departments = self::getEventsCompanyCounts(array_merge($request, array('type' => 'missed', 'group_by' => 'department', 'group_by_date' => 'y')));
        $events_missed_group_results = self::getEventsCompanyCounts(array_merge($request, array('type' => 'missed', 'group_by' => 'result', 'group_by_date' => 'y')));

        $result['dates'] = $events_missed['dates'];

        $result['groups_type']['missed']['score'] = $events_missed['score'];
        unset($result['groups_type']['missed']['score']['data']);
        $result['groups_type']['missed']['trend'] = $events_missed['trend'];
        $result['groups_type']['missed']['period'] = $events_missed['period'];

        $result['groups_type']['missed_percent']['score']['name'] = '% упущенных';
        $result['groups_type']['missed_percent']['score']['value'] = round($events_missed['score']['value'] * 100 / $events['score']['value'], 1);
        $result['groups_type']['missed_percent']['score']['unit'] = '%';
        $result['groups_type']['missed_percent']['trend']['value'] = $events_missed['trend']['value'];
        $result['groups_type']['missed_percent']['trend']['unit'] = '%';
        $result['groups_type']['missed_percent']['period'] = $events_missed['period'];

        $result['groups_department_missed'] = $events_missed_group_departments['groups'];

        foreach ($events_targeted_group_departments['groups'] as $key_group => $group) { // получим процент упущенных от целевого трафика
            foreach ($group['score']['data'] as $key_data => $d) {
                if ((int)$d !== 0) {
                    $events_missed_group_departments['groups'][$key_group]['score']['data'][$key_data] = round($events_missed_group_departments['groups'][$key_group]['score']['data'][$key_data] * 100 / $d, 1);
                }
            }
        }

        $result['groups_department_missed_percent'] = $events_missed_group_departments['groups'];
        //        $result['groups_result_missed_percent'] = $events_missed_group_results['groups'];
        $result['groups_department_missed_reasons'] = $events_missed_group_results['groups'];

        return $result;
    }

    static public function DailyControlReport($request)
    {
        $response = [];
        $users = [];
        $current_events = [];
        $events = self::getEventsCompany($request)['list'];
        $dates = self::getDatesFromRange($request['date_from'], $request['date_to']);
        $event_names = self::getPropsByEventIblockCompany()['result']['values'];
        $types = ['primary' => 'Первичный', 'secondary' => 'Вторичный'];
        $all_users = User::getList()['list'];
        $dates = Helpers::massive_dates(strtotime($request['date_from']), strtotime($request['date_to']));
        foreach ($all_users as $key => $user) {
            if ($user["roles"]["users"] === true) {
            } else {
                unset($all_users[$key]);
            }
            if (isset($request["department"])) {
                $key3 = array_search($request["department"], array_column($user["salons"], 'id'));
                if ($user["salons"][$key3]["status"] === true) {
                } else {
                    unset($all_users[$key]);
                }
            }
        }
        //return $all_users;
        foreach ($all_users as $key => $value) {
            unset($all_users[$key]["roles"]);
            unset($all_users[$key]["salons"]);
            $all_users[$key]["score"] = 0;
            $all_users[$key]["percent"] = null;
            $all_users[$key]["comments"] = [];
        }

        foreach ($event_names as $key => $event) {
            $response_by_events[$key]["event_name"] = $event["label"];
            $response_by_events[$key]["managers"] = $all_users;
        }
        foreach ($dates as $key => $value) {
            $response[$key]["date"] = $value;
            $response[$key]["rows"] = $response_by_events; // $response_by_events
        }
        foreach ($events as $key => $event) {
            $day_index = array_search($event["date_check_crm"]["value"], array_column($response, 'date'));
            $row_index = array_search($event["result"]["value"], array_column($response[$day_index]["rows"], 'event_name'));
            $response[$day_index]["rows"][$row_index]["managers"][$event["user"]["value_id"]]["score"] = $response[$day_index]["rows"][$row_index]["managers"][$event["user"]["value_id"]]["score"] + 1;
            $response[$day_index]["rows"][$row_index]["managers"][$event["user"]["value_id"]]["comments"][] = [
                "date" => date("d.m.Y", strtotime($event["date"]["value"])),
                "text" => $event["comment"]["value"],
                "time" => $event["date_time"]["value"]
            ];
        }
        //return $response = ["events" => $events, "event_names" => $event_names, "all_users" => $all_users];

        return $response;
    }

    static public function TotalyControlReport($request)
    {
        $response = [];
        $users = [];
        $current_events = [];
        $events = self::getEventsCompany($request)['list'];
        $dates = self::getDatesFromRange($request['date_from'], $request['date_to']);
        $event_names = self::getPropsByEventIblockCompany()['result']['values'];
        $types = ['primary' => 'Первичный', 'secondary' => 'Вторичный'];
        $dates = Helpers::massive_dates(strtotime($request['date_from']), strtotime($request['date_to']));
        if (isset($_REQUEST["department"])) {
            $current_department = $_REQUEST["department"];
        } else {
            $current_department = "";
        }
        $all_users = User::getList()['list'];
        foreach ($all_users as $key => $user) {
            if ($user["roles"]["users"] === true) {
            } else {
                unset($all_users[$key]);
            }
            if (isset($request["department"])) {
                $key3 = array_search($request["department"], array_column($user["salons"], 'id'));
                if ($user["salons"][$key3]["status"] === true) {
                } else {
                    unset($all_users[$key]);
                }
            }
        }
        //return $all_users;
        foreach ($all_users as $key => $value) {
            unset($all_users[$key]["roles"]);
            unset($all_users[$key]["salons"]);
            $all_users[$key]["score"] = 0;
            $all_users[$key]["comments"] = [];
        }

        foreach ($event_names as $key => $event) {
            $response_by_events[$key]["event_name"] = $event["label"];
            $response_by_events[$key]["managers"] = $all_users;
        }
        //return $response_by_events;
        $response["rows"] = $response_by_events; // $response_by_events
        foreach ($events as $key => $event) {
            if ($event["event_type"]["value"] != "Нецелевой" && $event["event_type"]["value"] == $types[$_REQUEST["type"]]) {
                if ($current_department != "") {
                    if ($current_department == $event["department"]["value_id"]) {
                        $row_index = array_search($event["result"]["value"], array_column($response["rows"], 'event_name'));
                        $response["rows"][$row_index]["managers"][$event["user"]["value_id"]]["score"] = $response["rows"][$row_index]["managers"][$event["user"]["value_id"]]["score"] + 1;
                        $response["rows"][$row_index]["managers"][$event["user"]["value_id"]]["comments"][] = [
                            "date" => date("d.m.Y", strtotime($event["date"]["value"])),
                            "text" => $event["comment"]["value"],
                            "time" => $event["date_time"]["value"]
                        ];
                    }
                } else {
                    $row_index = array_search($event["result"]["value"], array_column($response["rows"], 'event_name'));
                    $response["rows"][$row_index]["managers"][$event["user"]["value_id"]]["score"] = $response["rows"][$row_index]["managers"][$event["user"]["value_id"]]["score"] + 1;
                    $response["rows"][$row_index]["managers"][$event["user"]["value_id"]]["comments"] = [
                        "date" => date("d.m.Y", strtotime($event["date"]["value"])),
                        "text" => $event["comment"]["value"],
                        "time" => $event["date_time"]["value"]
                    ];
                }
            }
        }
        $response["rows"][7]["event_name"] = "Общий итог";
        $response["rows"][7]["managers"] = $all_users;
        foreach ($response["rows"] as $key => $value) {
            foreach ($value["managers"] as $key2 => $value2) {
                $response["rows"][$key]["summary"] = $response["rows"][$key]["summary"] + $value2["score"];
                $response["rows"][7]["managers"][$key2]["score"] = $response["rows"][7]["managers"][$key2]["score"] + $value["managers"][$key2]["score"];
            }
            $response["rows"][7]["summary"] = $response["rows"][0]["summary"] +
                $response["rows"][1]["summary"] +
                $response["rows"][2]["summary"] +
                $response["rows"][3]["summary"] +
                $response["rows"][4]["summary"] +
                $response["rows"][5]["summary"] +
                $response["rows"][6]["summary"];
        }
        //return $response = ["events" => $events, "event_names" => $event_names, "all_users" => $all_users];

        return $response;
    }

    static public function getReportEfficiency($request)
    {
        /*$request = {
            "getReportEfficiencyByDate": "",
            "date_from": "01.09.2022",
            "date_to": "30.09.2022",
            "type": "primary",
            "department": "4",
            "group_by": "user",
            "email": "test@test.com",
            "token": "ba9a571ec977b40ab8915959eb5d72d7",
            "PHPSESSID": "0HIY3OCkWpWtv5kaAnWnB8qAj1n4XjeZ",
            "group_by_date": "y"
        }*/
        $response = [];
        $events = self::getEventsCompany($request)['list'];
        //return  self::getEventsCompany($request);
        //return self::getEventsCompany($request);
        $dates = self::getDatesFromRange($request['date_from'], $request['date_to']);
        $event_names = self::getPropsByEventIblockCompany()['result']['values'];
        $types = ['primary' => 'Первичный', 'secondary' => 'Вторичный'];
        $all_users = User::getList()['list'];
        $users = [];
        $current_events = [];
        foreach ($events as $event) {
            //return $request['type'];
            if ($types[$request['type']] === $event['event_type']['value']) {
                //if ($event['event_type']['value'] == "Первичный" || $event['event_type']['value'] == "Вторичный"){
                $current_events[] = $event;
                if (array_key_exists($event['user']['value'], $users)) {
                    continue;
                }
                // Получаем пользователей с текущим траффиком
                foreach ($all_users as $item) {
                    $users[$event['user']['value']] = 0;
                }
                //return $users;
            }
        }
        // Считаем весь траффик пользователей
        foreach ($current_events as $current_event) {
            foreach ($users as $user => $value) {
                if ($current_event['user']['value'] === $user) {
                    $users[$user] += 1;
                }
            }
        }
        //return $users;;
        foreach ($dates as $keyDate => $date) {
            $day_report = [
                'date' => '',
                'score' => [
                    []
                ]
            ];
            $day_report['date'] = $date;

            foreach ($event_names as $key => $event_name) {
                $day_report['score'][$key]['event_name'] = $event_name['label'];
                foreach (array_keys($users) as $user) {
                    $day_report['score'][$key][$user] = 0;
                    foreach ($current_events as $current_event) {
                        if (
                            $current_event['user']['value'] === $user
                            && $current_event['date']['value'] === $date
                            && $current_event['result']['value'] === $event_name['label']
                        ) {
                            $day_report['score'][$key][$user] += 1;
                            if ($current_event['client_category']['value']) {
                                $day_report['score'][$key]['comments'][$user][] = $current_event['date_time']['value'] .
                                    ' ' .
                                    $current_event['client_category']['value'] .
                                    ': ' . $current_event['comment']['value'];
                            } else {
                                $day_report['score'][$key]['comments'][$user][] = $current_event['date_time']['value'] .
                                    ' ' .
                                    $current_event['comment']['value'];
                            }
                        }
                        if ($day_report['score'][$key][$user] === 0) {
                            $day_report['score'][$key]['comments'][$user] = [];
                        }
                        $day_report['score'][$key]['comments'][$user] = array_reverse($day_report['score'][$key]['comments'][$user]);
                    }
                }
            }

            $response['daily_report'][$keyDate] = $day_report;
        }

        // Отчёт за период
        $total_report = [];
        $summary = ['event_name' => 'Общий итог'];
        //return $event_names;
        foreach ($dates as $keyDate => $date) {
            foreach ($event_names as $key => $event_name) {
                $total_report['score'][$key]['event_name'] = $event_name['label'];
                foreach (array_keys($users) as $user) {
                    $total_report['score'][$key][$user] = 0;
                    foreach ($current_events as $current_event) {
                        if (
                            $response['daily_report'][$keyDate]['score'][$key]['event_name'] === $current_event['result']['value']
                            && $current_event['user']['value'] === $user
                        ) {
                            $total_report['score'][$key][$user] += 1;
                            if ($current_event['client_category']['value']) {
                                $total_report['score'][$key]['comments'][$user][]
                                    = $current_event['client_category']['value'] . ': ' . $current_event['comment']['value'];
                                $total_report['score'][$key]['comments']['result'][]
                                    = $current_event['client_category']['value'] . ': ' . $current_event['comment']['value'];
                            } else {
                                $total_report['score'][$key]['comments'][$user][] = $current_event['comment']['value'];
                                $total_report['score'][$key]['comments']['result'][] = $current_event['comment']['value'];
                            }
                        }
                        $day_report['score'][$key]['comments'][$user] = array_reverse(
                            $day_report['score'][$key]['comments'][$user]
                        );
                    }
                }
            }
        }
//return $users;
        foreach ($total_report['score'] as $key => $value) {
            if (!$total_report['score'][$key]["result"]) {
                $total_report['score'][$key]["result"] = 0;
            }
        }
        foreach ($total_report['score'] as $key => $item) {
            //return $item['result'];
            if (!empty($users)) {
                foreach ($users as $user => $value) {

                    if ($user) {
                        // $percentage = 0;
                        $percentage = round($total_report['score'][$key][$user] / $value * 100, 1);
                    } else {
                        $percentage = 0;
                    }

                    $total_report['score'][$key][$user] .= ' (' . $percentage . '%)';
                    $total_report['score'][$key]['result'] += $total_report['score'][$key][$user];
                    $summary['result'] += $total_report['score'][$key][$user];
                    $summary[$user] += $total_report['score'][$key][$user];
                }
            } else {
                $percentage = 0;
                $total_report['score'][$key]['result'] = ' (' . ((int)$total_report['score'][$key]['result'] + $percentage) . '%)';
                $summary['result'] = 0;
            }
        }

        if ($summary['result'] != 0) {
            foreach ($total_report['score'] as &$item) {
                $item['result'] .= ' (' . round($item['result'] / $summary['result'] * 100, 1) . '%)';
            }
        } else {
            foreach ($total_report['score'] as &$item) {
                $item['result'] = ' 0 (' . 0 . '%)';
            }
        }
        unset($item);

        $total_report['result'] = $summary;
        $response['users'] = array_keys($users);
        $response['total_report'] = $total_report;

        return $response;
    }

    static public function getReportEfficiencyByDate($request)
    {
        return self::getReportEfficiency(array_merge($request, ['group_by_date' => 'y']));
    }

    private static function addOrUpdateElems(int $blockId, string $date, array $plans, ?string $nameStart = ''): array
    {
        $unixTime = strtotime($date);
        $addedElems = [];
        $savedElems = [];
        $errors = 0;

        $res = \CIBlockElement::GetList(array(), ['IBLOCK_ID' => $blockId, 'DATE_ACTIVE_FROM' => ConvertTimeStamp($unixTime, "SHORT")], false, false, ['ID', 'NAME', 'DATE_ACTIVE_FROM', 'PROPERTY_department']);
        $existingElements = [];
        while ($obRes = $res->Fetch()) {
            $existingElements[$obRes['PROPERTY_DEPARTMENT_VALUE']] = $obRes;
        }

        $departmentIds = Department::getIds();
        foreach ($plans as $planElem) {
            if (!in_array($planElem['departament_id'], $departmentIds)) {
                //return [$planElem];
                return Helpers::getError('Указан некорректный ID департамента - #' . $planElem['departament_id']);
            }
            if ($existingElements[$planElem['departament_id']]) {
                //Update
                \CIBlockElement::SetPropertyValuesEx($existingElements[$planElem['departament_id']]['ID'], false, ['summ' => $planElem['price']]);
                $savedElems[] = $existingElements[$planElem['departament_id']]['ID'];
            } else {
                //Add
                $el = new \CIBlockElement;
                $arLoadProductArray = array(
                    "IBLOCK_ID" => $blockId,
                    "DATE_ACTIVE_FROM" => ConvertTimeStamp($unixTime, "SHORT"),
                    "PROPERTY_VALUES" => [
                        'summ' => $planElem['price'],
                        'department' => $planElem['departament_id'],
                    ],
                    "NAME" => ($nameStart ?: 'План'),
                    "ACTIVE" => "Y",
                );

                if ($ID = $el->Add($arLoadProductArray)) {
                    $addedElems[] = $ID;
                } else {
                    $errors++;
                }
            }

            if (!$errors) {
                $result['status'] = 'success';
                $result['message'] = '';
                if ($addedElems)
                    //$result['message'] .= 'Добавлены записи: ' . implode(',', $addedElems); //фикс от 6.12
                    $result['message'] = 'Сохранено';
                if ($savedElems)
                    $result['message'] = 'Сохранено';
            } else {
                $result['status'] = 'error';
                $result['message'] = 'Ошибка добавления элементов:';
                $result["error"] = $errors;
            }
        }
        return $result;
    }

    public static function addPlanWithMounth(string $date, array $plans): array
    {
        $arIsAuth = \Wss\User::isAuth($_REQUEST);
        if (!$arIsAuth['billing']['active']) return \Wss\Billing::$arErrMessBilling;
        $tempDate = explode('.', $date);
        $tempDate[0] = '01';
        $date = implode('.', $tempDate);
        $unixTime = strtotime($date);
        $elemsName = 'План на ' . FormatDate('f', $unixTime);
        return self::addOrUpdateElems(self::$iblockPlans, $date, $plans, $elemsName);
    }

    public static function addPlanByDeals(string $date, array $plans): array
    {
        $arIsAuth = \Wss\User::isAuth($_REQUEST);
        if (!$arIsAuth['billing']['active']) return \Wss\Billing::$arErrMessBilling;
        $tempDate = explode('.', $date);
        $tempDate[0] = '01';
        $date = implode('.', $tempDate);
        $unixTime = strtotime($date);
        $elemsName = 'План на ' . FormatDate('f', $unixTime);
        return self::addOrUpdateElems(self::$iblockPlansByDeal, $date, $plans, $elemsName);
    }

    public static function getResForTable(string $type, string $date_start, string $salon)
    {
        $date = $date_start;
        if ($type == "treaties") {
            $res = \CIBlockElement::GetList(
                ['PROPERTY_department' => 'ASC'],
                [
                    'IBLOCK_ID' => 47,
                    'DATE_ACTIVE_FROM' => $date,
                    'PROPERTY_department' => $salon,
                ],
                false,
                false,
                ['ID', 'NAME', 'DATE_ACTIVE_FROM', 'PROPERTY_department', 'PROPERTY_summ']
            );
        } else {
            $res = \CIBlockElement::GetList(
                ['PROPERTY_department' => 'ASC'],
                [
                    'IBLOCK_ID' => 49,
                    'DATE_ACTIVE_FROM' => $date,
                    'PROPERTY_department' => $salon,
                ],
                false,
                false,
                ['ID', 'NAME', 'DATE_ACTIVE_FROM', 'PROPERTY_department', 'PROPERTY_summ']
            );
        }

        while ($obRes = $res->Fetch()) {
            $data = $obRes["PROPERTY_SUMM_VALUE"];
        }
        if (!$data || $data == 0) {
            $sum = "-";
        } else {
            $sum = $data;
        }
        return $sum;
    }

    public static function BigTable($request): array
    {
        $date = $request["date"];
        $salon = $request["salon"];
        $tempDate = explode('.', $date);
        $date_start = "01.$tempDate[0].$tempDate[1]";
        $start = date("01.$tempDate[0].$tempDate[1]");
        $finish = new DateTime($start);
        $finish->modify('+1 month');
        $finish->modify('-1 day');
        $finish = $finish->format("d.m.Y");
        //return array($start);
        $result = array();
        $st = explode(".", $start);
        $end = explode(".", $finish);
        for ($i = $st[0]; $i <= $end[0]; $i += 1) {
            if ($i < 10 && $i != 1) $i = "0$i";
            $treaties = Iblock::getResForTable("treaties", "$i.$st[1].$st[2]", "$salon");
            $deal = Iblock::getResForTable("deal", "$i.$st[1].$st[2]", "$salon");
            $result["dates"][] = [
                "date" => "$i.$st[1].$st[2]",
                "treaties" => "$treaties",
                "deal" => "$deal"
            ];
            $result["total"] = [
                "treaties" => $result["total"]["treaties"] + $treaties,
                "deal" => $result["total"]["deal"] + $deal
            ];
        }
        return $result;
    }

    public static function addConvers(string $date, array $plans): array
    {
        $arIsAuth = \Wss\User::isAuth($_REQUEST);
        if (!$arIsAuth['billing']['active']) return \Wss\Billing::$arErrMessBilling;

        $dict = [
            "addPrimaryDeal" => self::$iblock_from_primary_to_deal,
            "addSecondaryDeal" => self::$iblock_from_secondary_to_deal,
            "addPrimaryKEV" => self::$iblock_from_primary_to_KEV,
            "addKEVDeal" => self::$iblock_from_KEV_to_deal,
        ];
        $num = null;
        foreach ($dict as $key => $value) {
            if ($key == $_REQUEST['helper']) {
                $num = $value;
            }
        }
        $tempDate = explode('.', $date);
        $tempDate[0] = '01';
        $date = implode('.', $tempDate);
        $unixTime = strtotime($date);
        $elemsName = 'Конверсия на ' . FormatDate('f', $unixTime);
        return self::addOrUpdateElems($num, $date, $plans, $elemsName);
    }

    public static function addFactWithMounth(string $date, array $plans): array
    {
        $arIsAuth = \Wss\User::isAuth($_REQUEST);
        if (!$arIsAuth['billing']['active']) return \Wss\Billing::$arErrMessBilling;
        $unixTime = strtotime($date);
        $elemsName = 'Факт на ' . FormatDate("d.m.Y", $unixTime);
        return self::addOrUpdateElems(self::$iblockFact, $date, $plans, $elemsName);
    }

    public static function addTreatiesWithMounth(string $date, array $plans): array
    {
        $arIsAuth = \Wss\User::isAuth($_REQUEST);
        if (!$arIsAuth['billing']['active']) return \Wss\Billing::$arErrMessBilling;
        $unixTime = strtotime($date);
        $elemsName = 'Договор от ' . FormatDate("d.m.Y", $unixTime);
        return self::addOrUpdateElems(self::$iblockTreaty, $date, $plans, $elemsName);
    }

    public static function getInfoByDate(int $blockId, string $date, array $sort = []): array
    {
        $unixTime = strtotime($date);

        $res = \CIBlockElement::GetList(
            $sort,
            [
                'IBLOCK_ID' => $blockId,
                'DATE_ACTIVE_FROM' => ConvertTimeStamp($unixTime, "SHORT"),
                'PROPERTY_department' => Department::getIds(),
            ],
            false,
            false,
            ['ID', 'NAME', 'DATE_ACTIVE_FROM', 'PROPERTY_department', 'PROPERTY_summ']
        );
        $data = [];
        while ($obRes = $res->Fetch()) {
            $data[$obRes["PROPERTY_DEPARTMENT_VALUE"]] = [
                'department' => $obRes['PROPERTY_DEPARTMENT_VALUE'],
                'price' => $obRes['PROPERTY_SUMM_VALUE'],
            ];
        }
        $deps = Iblock::getDepartments();
        if (!$data) {
            $sorted = [];
            foreach ($deps["list"] as $key => $value) {
                $sorted[$value["id"]]["department"] = $value["id"];
                $sorted[$value["id"]]["label"] = $value["label"];
                $sorted[$value["id"]]["price"] = "";
            }
            $result['status'] = 'success';
            $result['message'] = 'Данные за ' . FormatDate("d.m.Y", $unixTime) . ' не были найдены';
            $result['data'] = $sorted;
        } else {
            $sorted = [];
            $access_deps = [];
            $deps["list"] = User::getSalons(CUser::GetId());

            foreach ($deps["list"] as $key => $value) {
                if ($value["status"] === true)
                    $access_deps[] = $value;
            }
            foreach ($data as $key2 => $value2) {
                if (!in_array($value2["department"], array_column($access_deps, "id"))) {
                    unset($data[$key2]);
                } else {
                    foreach ($access_deps as $key3 => $value3) {
                        $data[$value3["id"]]["department"] = $value3["id"];
                        $data[$value3["id"]]["label"] = $value3["label"];
                        if (!$data[$value3["id"]]["price"]) {
                            $data[$value3["id"]]["price"] = "";
                        }
                    }
                }
            }

            $result['status'] = 'success';
            $result['message'] = 'Получены данные за ' . FormatDate("d.m.Y", $unixTime);

            foreach ($data as $key => $value) {
                $res = CIBlockElement::GetByID($value["department"]);
                if ($ar_res = $res->GetNext()) {
                    $data[$key]["label"] = $ar_res["NAME"];
                }
            }
            $result['data'] = $data;
            $result['date'] = FormatDate("d.m.Y", $unixTime);
        }
        return $result;
    }

    public static function getPlanByDate(string $date, $helper = false): array
    {
        if($helper){
           $_REQUEST['helper'] = $helper;
        }
        $dict = [
            "getPlanByDate" => self::$iblockPlans,
            "getPlanByDateForDeals" => self::$iblockPlansByDeal,
            "getPrimaryDeal" => self::$iblock_from_primary_to_deal,
            "getSecondaryDeal" => self::$iblock_from_secondary_to_deal,
            "getPrimaryKEV" => self::$iblock_from_primary_to_KEV,
            "getKEVDeal" => self::$iblock_from_KEV_to_deal,
        ];
        $num = null;
        foreach ($dict as $key => $value) {
            if ($key == $_REQUEST['helper']) {
                $num = $value;
            }
        }
        $tempDate = explode('.', $date);
        $tempDate[0] = '01';
        $date = implode('.', $tempDate);
        return self::getInfoByDate($num, $date);
    }

    public static function getFactInfo($request): array
    {
        $date = $request["date"];
        $sum_on_deals = self::getInfoByDate(self::$iblockTreaty, $date, ['PROPERTY_department' => 'ASC']);
        $sum_postuplenie = self::getInfoByDate(self::$iblockFact, $date, ['PROPERTY_department' => 'ASC']);
        $result = [];
        foreach($sum_on_deals["data"] as $key => $value){
            $result[] = [
                "departament_id" => $sum_on_deals["data"][$key]["department"],
                "name" => $sum_on_deals["data"][$key]["label"],
                "Fact_Deal" => $sum_on_deals["data"][$key]["price"],
                "Fact_Receipt" => $sum_postuplenie["data"][$key]["price"],
                ];
        }
        return $result;
    }

    public static function getPlanInfo($request): array{
        $date = $request["date"];
        $plan_receipt = self::getPlanByDate($date, "getPlanByDate");
        $plan_deals = self::getPlanByDate($date, "getPlanByDateForDeals");
        $result = [];
        foreach($plan_receipt["data"] as $key => $value){
            $result[] = [
                "departament_id" => $plan_deals["data"][$key]["department"],
                "name" => $plan_deals["data"][$key]["label"],
                "Plan_Deal" => $plan_deals["data"][$key]["price"],
                "Plan_Receipt" => $plan_receipt["data"][$key]["price"],
            ];
        }
        return $result;
    }

    public static function getConversation($request):array
    {
        $date = $request["date"];
        $getPrimaryDeal = self::getPlanByDate($_REQUEST['date'], "getPrimaryDeal");
        $getSecondaryDeal = self::getPlanByDate($_REQUEST['date'], "getSecondaryDeal");
        $getPrimaryKEV = self::getPlanByDate($_REQUEST['date'], "getPrimaryKEV");
        $getKEVDeal = self::getPlanByDate($_REQUEST['date'], "getKEVDeal");
        $result = [];
        foreach($getPrimaryDeal["data"] as $key => $value){
            $result[] = [
                "departament_id" => $getPrimaryDeal["data"][$key]["department"],
                "name" => $getPrimaryDeal["data"][$key]["label"],
                "Primary_To_Deal" => $getPrimaryDeal["data"][$key]["price"],
                "Secondary_To_Deal" => $getSecondaryDeal["data"][$key]["price"],
                "Primary_To_KEV" => $getPrimaryKEV["data"][$key]["price"],
                "KEV_To_Deal" => $getKEVDeal["data"][$key]["price"],
            ];
        }
        return $result;
    }

    public static function saveDataPlanFact(array $request): array {
        $date = $request["date"];
        $plans = $request["plans"];
        $arIsAuth = \Wss\User::isAuth($_REQUEST);
        if (!$arIsAuth['billing']['active']) return \Wss\Billing::$arErrMessBilling;

        $unixTime = strtotime($date);
        $dict = [
            "Fact_Deal" => ["id" => self::$iblockTreaty, "save_name" => 'Договор от ' . FormatDate("d.m.Y", $unixTime)],
            "Fact_Receipt" => ["id" => self::$iblockFact, "save_name" => 'Факт на ' . FormatDate("d.m.Y", $unixTime)],
            "Plan_Deal" => ["id" => self::$iblockPlansByDeal, "save_name" => 'План на ' . FormatDate('f', $unixTime)],
            "Plan_Receipt" => ["id" => self::$iblockPlans, "save_name" => 'План на ' . FormatDate('f', $unixTime)],
            "Primary_To_Deal" => ["id" => self::$iblock_from_primary_to_deal, "save_name" => 'Конверсия на ' . FormatDate('f', $unixTime)],
            "Secondary_To_Deal" => ["id" => self::$iblock_from_secondary_to_deal, "save_name" => 'Конверсия на ' . FormatDate('f', $unixTime)],
            "Primary_To_KEV" => ["id" => self::$iblock_from_primary_to_KEV, "save_name" => 'Конверсия на ' . FormatDate('f', $unixTime)],
            "KEV_To_Deal" => ["id" => self::$iblock_from_KEV_to_deal, "save_name" => 'Конверсия на ' . FormatDate('f', $unixTime)],
        ];
            foreach($plans as $key => $item) {
                if ($item["flag"] != "Fact_Deal" && $item["flag"] != "Fact_Receipt") {
                    $tempDate = explode('.', $date);
                    $tempDate[0] = '01';
                    $date = implode('.', $tempDate);
                }

                $num = $dict[$item["flag"]]["id"];
                $elemsName = $dict[$item["flag"]]["save_name"];
                unset($plans[$key]["flag"]);
                $element[] = $item;
                $result = self::addOrUpdateElems($num, $date, $element, $elemsName);
            }

        return $result;
    }

    public static function getFactByDate(array $request): array
    {
        $date = $request["date"];
        return self::getInfoByDate(self::$iblockFact, $date);
    }

    public static function getTreatiesByDate(array $request): array
    {
        $date = $request["date"];
        return self::getInfoByDate(self::$iblockTreaty, $date, ['PROPERTY_department' => 'ASC']);
    }

    // данные инфоблока с фильтрацией по департаментам текущей компании
    public static function getDataByDepartment(array $request): array
    {
        $departments = self::getDepartments()['list'];

        $result = [];

        $dateFrom = date('Y-m-d 00:00:00');
        $dateTo = date('Y-m-d 23:59:59');
        if ($request['date_from'] && $request['date_to']) {
            $dateFrom = $request['date_from'];
            $dateTo = $request['date_to'];
        }

        $dateFromFilter = trim(\CDatabase::CharToDateFunction(\ConvertTimeStamp(strtotime($dateFrom), 'FULL')), "\'");
        $dateToFilter = trim(\CDatabase::CharToDateFunction(\ConvertTimeStamp(strtotime($dateTo), 'FULL')), "\'");

        $dateFromFilter = \ConvertTimeStamp(\MakeTimeStamp($dateFrom, 'YYYY-MM-DD'));
        $dateToFilter = \ConvertTimeStamp(\MakeTimeStamp($dateTo, 'YYYY-MM-DD'));

        $dateFromFilter = (new \DateTimeImmutable($dateFrom))->format('01.m.Y H:i:s');
        $dateToFilter = (new \DateTimeImmutable($dateTo))->format('d.m.Y H:i:s');

        //$dateFromFilter = \ConvertTimeStamp(strtotime($dateFrom), "SHORT");
        //$dateToFilter = \ConvertTimeStamp(strtotime($dateTo), "SHORT");

        $cache = Cache::createInstance();
        $cacheId = 'getData' . md5(serialize($request));
        //return [$dateFromFilter];
        if ($cache->initCache(self::$cache_time_short, $cacheId, 'custom_cache')) {
            $result = $cache->getVars();
        } elseif ($cache->startDataCache()) {
            $sortField = $request['sort_field'] ?: 'SORT';
            $sortOrder = $request['sort_order'] ?: 'ASC';
            $res = CIBlockElement::GetList(
                [$sortField => $sortOrder],
                [
                    'IBLOCK_ID' => $request['iblock_id'],
                    'PROPERTY_department' => array_column($departments, 'id'),
                    '>=DATE_ACTIVE_FROM' => $dateFromFilter,
                    '<=DATE_ACTIVE_FROM' => $dateToFilter,
                ],
                false,
                false,
                ['*', 'PROPERTY_department']
            );
//return [$dateFromFilter];
            while ($ob = $res->GetNextElement()) {
                $fields = $ob->GetFields();
                //$result[$fields['PROPERTY_DEPARTMENT_VALUE']][0]["PROPERTIES"]["summ"]["VALUE"] = 0;
                $result[$fields['PROPERTY_DEPARTMENT_VALUE']][] = [
                    'FIELDS' => $ob->GetFields(),
                    'PROPERTIES' => $ob->GetProperties(),
                ];

                if ($result[$fields['PROPERTY_DEPARTMENT_VALUE']][0]["PROPERTIES"]["summ"]["VALUE"] != $ob->GetProperties()["summ"]["VALUE"]
                    || $result[$fields['PROPERTY_DEPARTMENT_VALUE']][0]["FIELDS"]["ID"] != $fields["ID"]
                    || $result[$fields['PROPERTY_DEPARTMENT_VALUE']][0]["FIELDS"]["DATE_CREATE"] != $fields["DATE_CREATE"]) {
                    $result[$fields['PROPERTY_DEPARTMENT_VALUE']][0]["PROPERTIES"]["summ"]["VALUE"] += $ob->GetProperties()["summ"]["VALUE"];
                }
            }

            $cache->endDataCache($result);
        }


        return $result;
    }

    // данные с инфоблока "Планы по салонам"
    public static function getPlans(array $request): array
    {
        $date = new \DateTimeImmutable($request['date'] ?: date('d-m-Y'));
        $request['date_from'] = $date->modify('first day of this month')->format('d.m.Y');
        $request['date_to'] = $date->modify('last day of this month')->format('d.m.Y');

        $result['date_from'] = $request['date_from'];
        $result['date_to'] = $request['date_to'];

        $result['list'] = self::getDataByDepartment(array_merge($request, ['iblock_id' => self::$iblockPlansByDeal]));

        $result['name'] = $date->modify('last day of this month')->format('j') . ' '
            . self::$arMonthRusFullRP[$date->modify('last day of this month')->format('m')];

        return $result;
    }

    // данные с инфоблока "Факты по салонам"
    public static function getFacts(array $request): array
    {
        if ($request['date_from'] && $request['date_to']) {
            $dateFrom = new \DateTimeImmutable($request['date_from']);
            $dateTo = new \DateTimeImmutable($request['date_to']);

            $result['date_from'] = $request['date_from'] = $dateFrom->format('d.m.Y');
            $result['date_to'] = $request['date_to'] = $dateTo->format('d.m.Y');
        } elseif ($request['date']) {
            $date = new \DateTimeImmutable($request['date'] ?: date('d-m-Y'));

            $result['date_from'] = $request['date_from'] = $date->format('01.m.Y');
            $result['date_to'] = $request['date_to'] = $date->format('d.m.Y');
        }

        $result['list'] = self::getDataByDepartment(array_merge($request, ['iblock_id' => self::$iblockFact]));

        return $result;
    }

    // данные с инфоблока "Факты по салонам" за прошлый год
    public static function getFactsLastYear(array $request)
    {
        if (!$request['date']) return false;
        $date = new \DateTimeImmutable($request['date'] ?: date('d-m-Y'));

        $monthLastYear = $date->modify('last day of this month last year');

        $request['date_from'] = $monthLastYear->modify('first day of this month')->format('d.m.Y');
        $request['date_to'] = $monthLastYear->format('d.m.Y');

        $request['sort_field'] = 'DATE_ACTIVE_FROM';
        $request['sort_order'] = 'DESC';

        return self::getFacts($request);
    }

    // данные с инфоблока "Договоры по салонам"
    public static function getContracts(array $request): array
    {
        $date = new \DateTimeImmutable($request['date'] ?: date('d-m-Y'));

        $request['date_from'] = $date->format('01.m.Y 00:00:00');
        $request['date_to'] = $date->format('d.m.Y 23:59:59');

        $result['date_from'] = $request['date_from'];
        $result['date_to'] = $request['date_to'];

        $result['list'] = self::getDataByDepartment(array_merge($request, ['iblock_id' => self::$iblockTreaty]));

        return $result;
    }

    public static function calcDynamic(float $lastVal, float $thisVal): float
    {
        $dynamic = 0;
        if ($lastVal == 0 && $lastVal < $thisVal) {
            $dynamic = 100;
        } elseif ($lastVal !== $thisVal) {
            $dynamic = ($thisVal / $lastVal - 1) * 100;
        }
        if ($lastVal > $thisVal) {
            $dynamic .= '-';
        }
        return round($dynamic, 2);
    }

    public static function getForecastByDepartments(array $request): array
    {
        $result = array();

        if (!$request['date']) return array('status' => 'error', 'message' => 'Не указана дата');

        self::$timeoutDebug = microtime(true);

        $departments = self::getDepartments()['list'];
        //return $departments;
        $plansThisMonth = self::getPlans($request);

        $contractsThisMonth = self::getContracts($request);

        //
        $factsThisMonth = self::getFacts($request);
        //return $factsThisMonth;

        $factsThisMonthLastYear = self::getFactsLastYear($request);

        $thisMonthName = self::$arMonthRusFull[(new \DateTimeImmutable($request['date']))->format('m')];

        $thisDate = new \DateTimeImmutable($request['date']);

        $eventsContractsThisMonth = self::getEventsCompanyCounts(
            [
                'type' => 'contract',
                'group_by' => 'department',
                'date_from' => $thisDate->modify('first day of this month')->format('d.m.Y'),
                'date_to' => $request['date'],
                'simple' => true,
            ]
        );

        $eventsPrimaryContractsThisMonth = self::getEventsCompanyCounts(
            [
                'type' => 'primary_contract',
                'group_by' => 'department',
                'date_from' => $thisDate->modify('first day of this month')->format('d.m.Y'),
                'date_to' => $request['date'],
                'simple' => true,
            ]
        );
        $eventsSecondaryContractsThisMonth = self::getEventsCompanyCounts(
            [
                'type' => 'secondary_contract',
                'group_by' => 'department',
                'date_from' => $thisDate->modify('first day of this month')->format('d.m.Y'),
                'date_to' => $request['date'],
                'simple' => true,
            ]
        );
        $eventsThisMonth = self::getEventsCompanyCounts(
            [
                'key' => 'traffic',
                'group_by' => 'department',
                'date_from' => $thisDate->modify('first day of this month')->format('d.m.Y'),
                'date_to' => $request['date'],
                'simple' => true,
            ]
        );

        //return $eventsThisMonth;

        //        if ($thisDate->format('j') > 28) {
        //            $lastDate = (new \DateTimeImmutable($request['date']))->modify('last day of last month');
        //            if ($lastDate->format('j') <= $thisDate->format('j')) {
        //                $request['date'] = $lastDate->format('d.m.Y');
        //            } else {
        //                for ($cLastDate = $lastDate->format('j');;$cLastDate--)
        //            }
        //        }
        if ($thisDate->format('t') == $thisDate->format('j')) {
            $request['date'] = $thisDate->modify('last day of last month')->format('d.m.Y');
        } else {
            $request['date'] = $thisDate->modify('last month')->format('d.m.Y');
        }

        $eventsContractsLastMonth = self::getEventsCompanyCounts(
            [
                'type' => 'contract',
                'group_by' => 'department',
                'date_from' => (new \DateTimeImmutable($request['date']))
                    ->modify('first day of this month')
                    ->format('d.m.Y'),
                'date_to' => $request['date'],
                'simple' => true,
            ]
        );
        //return $eventsContractsLastMonth;
        //        $result['$eventsContractsLastMonth'] = $eventsContractsLastMonth;

        $eventsLastMonth = self::getEventsCompanyCounts(
            [
                'group_by' => 'department',
                'date_from' => (new \DateTimeImmutable($request['date']))
                    ->modify('first day of this month')
                    ->format('d.m.Y'),
                'date_to' => $request['date'],
                'simple' => true,
            ]
        );
        //return array($eventsLastMonth);

        $lastMonthName = self::$arMonthRusFull[(new \DateTimeImmutable($request['date']))->format('m')];

        $contractsLastMonth = self::getContracts($request);
        //return self::getContracts($request);
        $factsLastMonth = self::getFacts($request);


        //        $plansLastMonth = self::getPlans($request);

        //        $result['departments'] = $departments;
        //        $result['$plansThisMonth'] = $plansThisMonth;
        //        $result['$contractsThisMonth'] = $contractsThisMonth;
        //        $result['$factsThisMonth'] = $factsThisMonth;
        //        $result['$contractsLastMonth'] = $contractsLastMonth;
        //        $result['$factsLastMonth'] = $factsLastMonth;


        foreach ($departments as $d) {
            $result[$d['id']] = [];

            $result[$d['id']]['departmentId'] = $d['id'];
            $result[$d['id']]['departmentName'] = $d['name'];
            $result[$d['id']]['planName'] = $plansThisMonth['name'];
            $result[$d['id']]['planSumm'] = $plansThisMonth['list'][$d['id']][0]['PROPERTIES']['summ']['VALUE'] ?: 0;
            $result[$d['id']]['lastMonthName'] = $lastMonthName;
            $result[$d['id']]['thisMonthName'] = $thisMonthName;

            $factThisMonthLastYearSumm = 0;
            if ($factsThisMonthLastYear['list'][$d['id']]) {
                foreach ($factsThisMonthLastYear['list'][$d['id']] as $fact) {
                    if ($fact['PROPERTIES']['summ']['VALUE'] > 0) {
                        $factThisMonthLastYearSumm = $fact['PROPERTIES']['summ']['VALUE'];
                        break;
                    }
                }
            }

            $result[$d['id']]['factsMonthLastYear'] = $factThisMonthLastYearSumm;

            $contractsEventsLastMonth = current(
                array_filter(
                    $eventsContractsLastMonth['groups'],
                    fn($value) => $value['score']['name'] == $d['name']
                )
            )['score']['value'];

            $contractsEventsThisMonth = current(
                array_filter(
                    $eventsContractsThisMonth['groups'],
                    fn($value) => $value['score']['name'] == $d['name']
                )
            )['score']['value'];
            $primaryContractsEventsThisMonth = current(
                array_filter(
                    $eventsPrimaryContractsThisMonth['groups'],
                    fn($value) => $value['score']['name'] == $d['name']
                )
            )['score']['value'];

            $secondaryContractsEventsThisMonth = current(
                array_filter(
                    $eventsSecondaryContractsThisMonth['groups'],
                    fn($value) => $value['score']['name'] == $d['name']
                )
            )['score']['value'];
            $eventsLastMonthD = current(
                array_filter(
                    $eventsLastMonth['groups'],
                    fn($value) => $value['score']['name'] == $d['name']
                )
            )['score']['value'];
            $eventsThisMonthD = current(
                array_filter(
                    $eventsThisMonth['groups'],
                    fn($value) => $value['score']['name'] == $d['name']
                )
            )['score']['value'];


            $countContactsLastMonth = $contractsLastMonth['list'][$d['id']][0]['PROPERTIES']['summ']['VALUE'] ?: 0;
            $countContactsThisMonth = $contractsThisMonth['list'][$d['id']][0]['PROPERTIES']['summ']['VALUE'] ?: 0;
            $result[$d['id']]['result'][] = [
                'name' => 'Договор',
                'lastMonth' => $countContactsLastMonth,
                'thisMonth' => $countContactsThisMonth,
                'dynamic' => self::calcDynamic((float)$countContactsLastMonth, (float)$countContactsThisMonth),
            ];

            $countFactsLastMonth = $factsLastMonth['list'][$d['id']][0]['PROPERTIES']['summ']['VALUE'] ?: 0;
            $countFactsThisMonth = $factsThisMonth['list'][$d['id']][0]['PROPERTIES']['summ']['VALUE'] ?: 0;

            $result[$d['id']]['result'][] = [
                'name' => 'Сумма денежных средств',
                'lastMonth' => $countFactsLastMonth,
                'thisMonth' => $countFactsThisMonth,
                'dynamic' => self::calcDynamic((float)$countFactsLastMonth, (float)$countFactsThisMonth),
            ];


            $averageCheckLastMonth = 0;
            $averageCheckThisMonth = 0;
            if ($contractsEventsLastMonth != 0) {
                $averageCheckLastMonth = round($countContactsLastMonth / $contractsEventsLastMonth, 2);
            }
            if ($contractsEventsThisMonth != 0) {
                $averageCheckThisMonth = round($countContactsThisMonth / $contractsEventsThisMonth, 2);
            }


            $result[$d['id']]['result'][] = [
                'name' => 'Средний чек',
                'lastMonth' => $averageCheckLastMonth,
                'thisMonth' => $averageCheckThisMonth,
                'dynamic' => self::calcDynamic((float)$averageCheckLastMonth, (float)$averageCheckThisMonth),
            ];
//return [$eventsThisMonthD];
            $result[$d['id']]['result'][] = [
                'name' => 'Трафик',
                'lastMonth' => $eventsLastMonthD,
                'thisMonth' => $eventsThisMonthD,
                'dynamic' => self::calcDynamic((float)$eventsLastMonthD, (float)$eventsThisMonthD),
            ];

            $result[$d['id']]['result'][] = [
                'name' => 'Количество продаж',
                'lastMonth' => $contractsEventsLastMonth,
                'thisMonth' => $contractsEventsThisMonth,
                'dynamic' => self::calcDynamic((float)$contractsEventsLastMonth, (float)$contractsEventsThisMonth),
            ];
            //return $result[$d['id']]['result'];
            $result[$d['id']]['result'][] = [
                'name' => 'Количество первичных продаж',
                'thisMonth' => $primaryContractsEventsThisMonth,
            ];

            $result[$d['id']]['result'][] = [
                'name' => 'Количество вторичных продаж',
                'thisMonth' => $secondaryContractsEventsThisMonth,
            ];

            $result[$d['id']]['result'][] = [
                'name' => 'Конверсия (%)',
                'lastMonth' => $eventsLastMonthD ? round($contractsEventsLastMonth / $eventsLastMonthD * 100, 2) : 0,
                'thisMonth' => $eventsThisMonthD ? round($contractsEventsThisMonth / $eventsThisMonthD * 100, 2) : 0,
                'dynamic' => self::calcDynamic(
                    $eventsLastMonthD ? (float)($contractsEventsLastMonth / $eventsLastMonthD) : 0,
                    $eventsThisMonthD ? (float)($contractsEventsThisMonth / $eventsThisMonthD) : 0
                ),
            ];
        }

        //        return $departments;

        return $result;
    }

    public static function getFullForecast(array $request): array
    {
        $result = array();
        // return $result;
        if (!$request['date']) return array('status' => 'error', 'message' => 'Не указана дата');

        $thisDate = new \DateTimeImmutable($request['date']);

        $departments = self::getDepartments()['list'];
//
        //return $access_deps;
        $square = array_reduce($departments, fn($sumSquare, $item) => $sumSquare + $item['prop_square']['values']);
        //return [$square];
        $plansThisMonth = self::getPlans($request);

        $result['$factsThisMonth'] = $factsThisMonth = self::getContracts($request);
        //return [$result['$factsThisMonth']];
        /*
        $result['$factsThisMonth'] = $factsThisMonth = self::getFacts([
            'date_from' => $thisDate->modify('first day of this month')->format('d.m.Y'),
            'date_to' => $request['date']
        ]);
        */

        $factsThisMonthLastYear = self::getFactsLastYear($request);


        $contractsEventsThisMonth = self::getEventsCompanyCounts(
            [
                'type' => 'contract',
                'date_from' => $thisDate->modify('first day of this month')->format('d.m.Y'),
                'date_to' => $request['date'],
                'simple' => true,
            ]
        )['score']['value'];


        $result['result']['sales'] = [
            'name' => 'Количество продаж',
            'thisMonth' => $contractsEventsThisMonth
        ];

        $result['result']['primary_seles'] = [
            'name' => 'Первичные продажи',
            'thisMonth' => self::getEventsCompanyCounts(
                [
                    'type' => 'primary_contract',
                    'date_from' => $thisDate->modify('first day of this month')->format('d.m.Y'),
                    'date_to' => $request['date'],
                    'simple' => true,
                ]
            )['score']['value']
        ];

        $result['result']['secondary_seles'] = [
            'name' => 'Вторичные продажи',
            'thisMonth' => self::getEventsCompanyCounts(
                [
                    'type' => 'secondary_contract',
                    'date_from' => $thisDate->modify('first day of this month')->format('d.m.Y'),
                    'date_to' => $request['date'],
                    'simple' => true,
                ]
            )['score']['value']
        ];


        $eventsThisMonth = self::getEventsCompanyCounts(
            [
                'group_by' => 'department',
                'date_from' => $thisDate->modify('first day of this month')->format('d.m.Y'),
                'date_to' => $request['date'],
                'simple' => true,
            ]
        );

        if ($thisDate->format('t') == $thisDate->format('j')) {
            $request['date'] = $thisDate->modify('last day of last month')->format('d.m.Y');
        } else {
            $request['date'] = $thisDate->modify('last month')->format('d.m.Y');
        }

        $eventsContractsLastMonth = self::getEventsCompanyCounts(
            [
                'type' => 'contract',
                'group_by' => 'department',
                'date_from' => (new \DateTimeImmutable($request['date']))
                    ->modify('first day of this month')
                    ->format('d.m.Y'),
                'date_to' => $request['date'],
                'simple' => true,
            ]
        );
//return $eventsContractsLastMonth;
        $eventsLastMonth = self::getEventsCompanyCounts(
            [
                'group_by' => 'department',
                'date_from' => (new \DateTimeImmutable($request['date']))
                    ->modify('first day of this month')
                    ->format('d.m.Y'),
                'date_to' => $request['date'],
                'simple' => true,
            ]
        );


        $contractsLastMonth = self::getContracts($request);
        $factsLastMonth = self::getContracts($request);


        $fullPlan = 0;
        $factThisMonthLastYearSumm = 0;
        $contractsEventsLastMonth = 0;
        $eventsLastMonthD = 0;
        $eventsThisMonthD = 0;
        $countFactsLastMonth = 0;
        $countFactsThisMonth = 0;


        foreach ($departments as $d) {

            $fullPlan += $plansThisMonth['list'][$d['id']][0]['PROPERTIES']['summ']['VALUE'] ?: 0;

            if ($factsThisMonthLastYear['list'][$d['id']]) {
                foreach ($factsThisMonthLastYear['list'][$d['id']] as $fact) {
                    if ($fact['PROPERTIES']['summ']['VALUE'] > 0) {
                        $factThisMonthLastYearSumm += $fact['PROPERTIES']['summ']['VALUE'];
                        break;
                    }
                }
            }


            $contractsEventsLastMonth += current(
                array_filter(
                    $eventsContractsLastMonth['groups'],
                    fn($value) => $value['score']['name'] == $d['name']
                )
            )['score']['value'];

//return [$contractsEventsLastMonth];
            $eventsLastMonthD += current(
                array_filter(
                    $eventsLastMonth['groups'],
                    fn($value) => $value['score']['name'] == $d['name']
                )
            )['score']['value'];
            $eventsThisMonthD += current(
                array_filter(
                    $eventsThisMonth['groups'],
                    fn($value) => $value['score']['name'] == $d['name']
                )
            )['score']['value'];

            $countFactsLastMonth += array_reduce($factsLastMonth['list'][$d['id']], fn($sum, $item) => $sum += $item['PROPERTIES']['summ']['~VALUE']);
            $countFactsThisMonth += array_reduce($factsThisMonth['list'][$d['id']], fn($sum, $item) => $sum += $item['PROPERTIES']['summ']['~VALUE']);

            $result['testing_array'][] = $factsThisMonth['list'][$d['id']][0]['PROPERTIES']['summ']['VALUE'];
        }
        $countFactsThisMonth = array_sum($result['testing_array']);
        $result['planSumm'] = $fullPlan;


        $result['result']['money'] = [
            'name' => 'Факт продаж',
            'lastMonth' => $countFactsLastMonth,
            'thisMonth' => $countFactsThisMonth,
            'dynamic' => self::calcDynamic((float)$countFactsLastMonth, (float)$countFactsThisMonth),
        ];

        //return [$countFactsLastMonth];
        $averageCheckLastMonth = 0;
        $averageCheckThisMonth = 0;
        if ($contractsEventsLastMonth != 0) {
            $averageCheckLastMonth = round($countFactsLastMonth / $contractsEventsLastMonth, 2);
        }
        if ($contractsEventsThisMonth != 0) {
            $averageCheckThisMonth = round($countFactsThisMonth / $contractsEventsThisMonth, 2);
        }

        $result['result']['cheque'] = [
            'name' => 'Средний чек',
            'lastMonth' => $averageCheckLastMonth,
            'thisMonth' => $averageCheckThisMonth,
            'dynamic' => self::calcDynamic((float)$averageCheckLastMonth, (float)$averageCheckThisMonth),
        ];

        $result['result']['traffic'] = [
            'name' => 'Трафик',
            'lastMonth' => $eventsLastMonthD,
            'thisMonth' => $eventsThisMonthD,
            'dynamic' => self::calcDynamic((float)$eventsLastMonthD, (float)$eventsThisMonthD),
        ];


        $result['result']['conversion'] = [
            'name' => 'Конверсия (%)',
            'lastMonth' => $eventsLastMonthD ? round($contractsEventsLastMonth / $eventsLastMonthD * 100, 2) : 0,
            'thisMonth' => $eventsThisMonthD ? round($contractsEventsThisMonth / $eventsThisMonthD * 100, 2) : 0,
            'dynamic' => self::calcDynamic(
                $eventsLastMonthD ? (float)($contractsEventsLastMonth / $eventsLastMonthD) : 0,
                $eventsThisMonthD ? (float)($contractsEventsThisMonth / $eventsThisMonthD) : 0
            ),
        ];

        if ($square)
            $result['result']['sum_to_square'] = [
                'name' => 'Продажа с 1 кв. метра',
                'value' => $countFactsThisMonth / $square
            ];

        return $result;
    }

    public static function getForecastForDepartment(array $request): array
    {
        $result = array();

        if (!$request['date']) return array('status' => 'error', 'message' => 'Не указана дата');
        if (!$request['department']) return array('status' => 'error', 'message' => 'Не указан салон');

        self::$timeoutDebug = microtime(true);

        $departments = self::getDepartments()['list'];
        $square = current(array_filter($departments, fn($value) => $value['id'] == $request['department']))['prop_square']['values'];

        $plansThisMonth = self::getPlans($request);
        $contractsThisMonth = self::getContracts($request);
        $factsThisMonth = self::getContracts($request);

        $factsThisMonthLastYear = self::getFactsLastYear($request);

        $thisMonthName = self::$arMonthRusFull[(new \DateTimeImmutable($request['date']))->format('m')];

        $thisDate = new \DateTimeImmutable($request['date']);
        $eventsContractsThisMonth = self::getEventsCompanyCounts(
            [
                'type' => 'contract',
                'group_by' => 'department',
                'date_from' => $thisDate->modify('first day of this month')->format('d.m.Y'),
                'date_to' => $request['date'],
                'simple' => true,
            ]
        );
        $eventsPrimaryContractsThisMonth = self::getEventsCompanyCounts(
            [
                'type' => 'primary_contract',
                'group_by' => 'department',
                'date_from' => $thisDate->modify('first day of this month')->format('d.m.Y'),
                'date_to' => $request['date'],
                'simple' => true,
            ]
        );
        $eventsSecondaryContractsThisMonth = self::getEventsCompanyCounts(
            [
                'type' => 'secondary_contract',
                'group_by' => 'department',
                'date_from' => $thisDate->modify('first day of this month')->format('d.m.Y'),
                'date_to' => $request['date'],
                'simple' => true,
            ]
        );
        $eventsThisMonth = self::getEventsCompanyCounts(
            [
                'group_by' => 'department',
                'date_from' => $thisDate->modify('first day of this month')->format('d.m.Y'),
                'date_to' => $request['date'],
                'simple' => true,
            ]
        );

        if ($thisDate->format('t') == $thisDate->format('j')) {
            $request['date'] = $thisDate->modify('last day of last month')->format('d.m.Y');
        } else {
            $request['date'] = $thisDate->modify('last month')->format('d.m.Y');
        }

        $eventsContractsLastMonth = self::getEventsCompanyCounts(
            [
                'type' => 'contract',
                'group_by' => 'department',
                'date_from' => (new \DateTimeImmutable($request['date']))
                    ->modify('first day of this month')
                    ->format('d.m.Y'),
                'date_to' => $request['date'],
                'simple' => true,
            ]
        );

        $eventsLastMonth = self::getEventsCompanyCounts(
            [
                'group_by' => 'department',
                'date_from' => (new \DateTimeImmutable($request['date']))
                    ->modify('first day of this month')
                    ->format('d.m.Y'),
                'date_to' => $request['date'],
                'simple' => true,
            ]
        );

        $lastMonthName = self::$arMonthRusFull[(new \DateTimeImmutable($request['date']))->format('m')];

        $contractsLastMonth = self::getContracts($request);
        $factsLastMonth = self::getContracts($request);

        $d = current(
            array_filter(
                $departments,
                fn($value) => $value['id'] == $request['department']
            )
        );

        $result = [];

        $result['planSumm'] = $plansThisMonth['list'][$d['id']][0]['PROPERTIES']['summ']['VALUE'] ?: 0;
        $result['lastMonthName'] = $lastMonthName;
        $result['thisMonthName'] = $thisMonthName;

        $factThisMonthLastYearSumm = 0;
        if ($factsThisMonthLastYear['list'][$d['id']]) {
            foreach ($factsThisMonthLastYear['list'][$d['id']] as $fact) {
                if ($fact['PROPERTIES']['summ']['VALUE'] > 0) {
                    $factThisMonthLastYearSumm = $fact['PROPERTIES']['summ']['VALUE'];
                    break;
                }
            }
        }

        $result['factsMonthLastYear'] = $factThisMonthLastYearSumm;

        $contractsEventsLastMonth = current(
            array_filter(
                $eventsContractsLastMonth['groups'],
                fn($value) => $value['score']['name'] == $d['name']
            )
        )['score']['value'];
        $primaryContractsEventsThisMonth = current(
            array_filter(
                $eventsPrimaryContractsThisMonth['groups'],
                fn($value) => $value['score']['name'] == $d['name']
            )
        )['score']['value'];

        $secondaryContractsEventsThisMonth = current(
            array_filter(
                $eventsSecondaryContractsThisMonth['groups'],
                fn($value) => $value['score']['name'] == $d['name']
            )
        )['score']['value'];
        $contractsEventsThisMonth = current(
            array_filter(
                $eventsContractsThisMonth['groups'],
                fn($value) => $value['score']['name'] == $d['name']
            )
        )['score']['value'];

        $eventsLastMonthD = current(
            array_filter(
                $eventsLastMonth['groups'],
                fn($value) => $value['score']['name'] == $d['name']
            )
        )['score']['value'];
        $eventsThisMonthD = current(
            array_filter(
                $eventsThisMonth['groups'],
                fn($value) => $value['score']['name'] == $d['name']
            )
        )['score']['value'];


        $countContactsLastMonth = $contractsLastMonth['list'][$d['id']][0]['PROPERTIES']['summ']['VALUE'] ?: 0;
        $countContactsThisMonth = $contractsThisMonth['list'][$d['id']][0]['PROPERTIES']['summ']['VALUE'] ?: 0;
        $result['result']['contracts'] = [
            'name' => 'Договор',
            'lastMonth' => $countContactsLastMonth,
            'thisMonth' => $countContactsThisMonth,
            'dynamic' => self::calcDynamic((float)$countContactsLastMonth, (float)$countContactsThisMonth),
        ];

        $countFactsLastMonth = $factsLastMonth['list'][$d['id']][0]['PROPERTIES']['summ']['VALUE'] ?: 0;
        $countFactsThisMonth = $factsThisMonth['list'][$d['id']][0]['PROPERTIES']['summ']['VALUE'] ?: 0;

        $result['result']['money'] = [
            'name' => 'Факт продаж',
            'lastMonth' => $countFactsLastMonth,
            'thisMonth' => $countFactsThisMonth,
            'dynamic' => self::calcDynamic((float)$countFactsLastMonth, (float)$countFactsThisMonth),
        ];

        $averageCheckLastMonth = 0;
        $averageCheckThisMonth = 0;
        if ($contractsEventsLastMonth != 0) {
            $averageCheckLastMonth = round($countFactsLastMonth / $contractsEventsLastMonth, 2);
        }
        if ($contractsEventsThisMonth != 0) {
            $averageCheckThisMonth = round($countFactsThisMonth / $contractsEventsThisMonth, 2);
        }


        $result['result']['cheque'] = [
            'name' => 'Средний чек',
            'lastMonth' => $averageCheckLastMonth,
            'thisMonth' => $averageCheckThisMonth,
            'dynamic' => self::calcDynamic((float)$averageCheckLastMonth, (float)$averageCheckThisMonth),
        ];
        $result["result"]["primary_seles"] = [
            'name' => 'Количество первичных продаж',
            'thisMonth' => $primaryContractsEventsThisMonth,
        ];

        $result['result']["secondary_seles"] = [
            'name' => 'Количество вторичных продаж',
            'thisMonth' => $secondaryContractsEventsThisMonth,
        ];
        $result['result']['traffic'] = [
            'name' => 'Трафик',
            'lastMonth' => $eventsLastMonthD,
            'thisMonth' => $eventsThisMonthD,
            'dynamic' => self::calcDynamic((float)$eventsLastMonthD, (float)$eventsThisMonthD),
        ];

        $result['result']['sales'] = [
            'name' => 'Количество продаж',
            'lastMonth' => $contractsEventsLastMonth,
            'thisMonth' => $contractsEventsThisMonth,
            'dynamic' => self::calcDynamic((float)$contractsEventsLastMonth, (float)$contractsEventsThisMonth),
        ];

        $result['result']['conversion'] = [
            'name' => 'Конверсия (%)',
            'lastMonth' => $eventsLastMonthD ? round($contractsEventsLastMonth / $eventsLastMonthD * 100, 2) : 0,
            'thisMonth' => $eventsThisMonthD ? round($contractsEventsThisMonth / $eventsThisMonthD * 100, 2) : 0,
            'dynamic' => self::calcDynamic(
                $eventsLastMonthD ? (float)($contractsEventsLastMonth / $eventsLastMonthD) : 0,
                $eventsThisMonthD ? (float)($contractsEventsThisMonth / $eventsThisMonthD) : 0
            ),
        ];

        if ($square)
            $result['result']['sum_to_square'] = [
                'name' => 'Продажа с 1 кв. метра',
                'value' => $countFactsThisMonth / $square
            ];

        return $result;
    }


    public static function getCheckingEventsInCrm(array $request): array
    {
        $result = [];
        $request['date_check_crm'] = true;
        $request['check_crm'] = '!Да';
        $events = self::getEventsCompany($request);
        //return self::getEventsCompany($request);
        if ($events['list']) {
            $result['all'] = [];
            $result['tomorrow'] = [];
            $result['ignore'] = [];
            $dateTomorrow = (new \DateTimeImmutable())->modify('+1 day')->format('dmY');
            foreach ($events['list'] as $event) {
                $dateCheck = (new \DateTimeImmutable($event['date_check_crm']['value']))->format('dmY');
                if ($event['check_number']['value'] > 2) {
                    $result['ignore'][] = $event;
                } elseif ($dateCheck == $dateTomorrow) {
                    $result['tomorrow'][] = $event;
                } else {
                    $result['all'][] = $event;
                }
            }
        }

        if ($events['status'] == 'error') return $events;

        //        $result['events'] = $events;

        return $result;
    }

    public static function addCheckingInCrmEvent(array $request): array
    {
        $arIsAuth = \Wss\User::isAuth($_REQUEST);
        if (!$arIsAuth['isAuth']) return self::$arErrMesAuth;
        if (!$arIsAuth['billing']['active']) return \Wss\Billing::$arErrMessBilling;
        if (!$arIsAuth['roles']['admins'] && !$arIsAuth['roles']['controllers']) {
            return array('status' => 'error', 'message' => 'У Вас нет прав на изменение событий');
        }

        $event_iblock = self::getCurUserEventIblockCompany();
        $arProps = self::getPropsByEventIblockCompany();

        if (!$request['event_id']) {
            return array('status' => 'error', 'message' => 'Не указан id события');
        }

        if (!self::checkEventIdByCurrentCompany($request['event_id'])) {
            return array('status' => 'error', 'message' => 'Указанное событие не найдено');
        }

        $props = [];
        if ($request['date_check_crm']) {
            $props['date_check_crm'] = \ConvertTimeStamp(strtotime($request['date_check_crm']), 'SHORT');
        } else {
            $props['date_check_crm'] = \ConvertTimeStamp(time(), 'SHORT');
        }
        if ($request['check_crm']) {
            if ($arProps['check_crm']['values']) {
                $checkValues = array_column($arProps['check_crm']['values'], 'id', 'xml_id');
                if ($checkValues[$request['check_crm']]) {
                    $props['check_crm'] = $checkValues[$request['check_crm']];
                } else {
                    return [
                        'status' => 'error',
                        'message' => 'Указанное XML_ID значение check_crm не найдено',
                    ];
                }
            }
        }
        if ($request['check_number']) {
            if (!is_numeric($request['check_number'])) {
                return [
                    'status' => 'error',
                    'message' => 'Значение check_number должно быть числом',
                ];
            }
            $props['check_number'] = $request['check_number'];
        }

        CIBlockElement::SetPropertyValuesEx($request['event_id'], $event_iblock['ID'], $props);

        $event = self::getEventsCompany(['event_id' => $request['event_id']]);
        $departments = self::getDepartments();
        if (isset($event['list'][0])) {
            $userEmail = '';
            $userName = '';
            $ropEmail = '';
            $ropName = '';
            $event = $event['list'][0];
            if ($event['user']) {
                $arUser = \Bitrix\Main\UserTable::getList(
                    [
                        "order" => ['NAME' => 'ASC'],
                        "select" => ['ID', 'EMAIL', 'NAME', 'LAST_NAME'],
                        "filter" => ['ID' => $event['user']['value_id']],
                    ]
                )->Fetch();
                $userEmail = $arUser['EMAIL'] ?: '';
                if ($arUser['NAME']) $userName = $arUser['NAME'];
                if ($arUser['LAST_NAME']) {
                    if ($arUser['NAME']) $userName .= ' ' . $arUser['LAST_NAME'];
                    else $userName .= $arUser['LAST_NAME'];
                }
                if (!$arUser['NAME'] && !$arUser['LAST_NAME'])
                    $userName = 'ID ' . $arUser['ID'] . ' (имя не указано)';
            }
        }
        $userid["id"] = $event['user']['value_id'];
        $user = User::getUser($userid);
        $VALUES = array();
        foreach ($user["salons"] as $key => $salon) {
            if ($salon["status"] === true) {
                $res = CIBlockElement::GetByID($salon["id"]);
                if ($obRes = $res->GetNextElement()) {
                    $VALUES[$salon["id"]] = $obRes->GetProperty("rops");
                }
            }
        }
        foreach ($VALUES as $key => $salon) {
            foreach ($salon["VALUE"] as $id) {
                $rsUser = CUser::GetByID($id);
                $arUser = $rsUser->Fetch();
                $rop_emails[] = $arUser["EMAIL"];
            }
        }
        //return $rop_emails;
        if ($request["check_crm"] != "y") {

            $telegram_request = [
                "event_id" => $request["event_id"],
                "user_id" => $event['user']['value_id'],
                "date_time" => "",
                "date_time" => "",
                "department" => "",
                "manager" => "",
                "event_type" => "",
                "result" => "",
                "check_count" => $request['check_number'],
            ];
            $telegram_request = Methods\Crm::prepare($telegram_request);
            //return [$telegram_request];
            if ((!$request['check_crm'] || $request['check_crm'] == 'n') && $request['check_number'] >= 2) {
                $message_for = 'manager'; // юзеру и роп
                if ($request['check_number'] == 3) {
                    $message_for = "rops"; // только юзеру
                }
                if ($message_for == "manager") {
                    Event::sendImmediate(
                        [
                            "EVENT_NAME" => "EVENT_CHECK_CRM",
                            "LID" => "s1",
                            "MESSAGE_ID" => 12,
                            "C_FIELDS" => [
                                "USER_EMAIL" => $userEmail,
                                "TEXT" => $telegram_request,
                            ],
                        ]
                    );
                } elseif ($message_for == "rops") {
                    foreach ($rop_emails as $email) {
                        Event::sendImmediate(
                            [
                                "EVENT_NAME" => "EVENT_CHECK_CRM",
                                "LID" => "s1",
                                "MESSAGE_ID" => 13,
                                "C_FIELDS" => [
                                    "USER_EMAIL" => $userEmail,
                                    "ROP_EMAIL" => $email,
                                    "TEXT" => $telegram_request,
                                ],
                            ]
                        );
                    }
                }
            }
        }
        return [
            'status' => 'success',
            'message' => "Информация добавлена",
        ];
    }

    public static function getMainTrafficStatistic(array $request): array
    {
        $result = [
            "primary_traffic" => [
                "current_value" => 0,
                "to_last_month" => 0,
                "to_last_year" => 0,
            ],
            "target_traffic" => 0,
            "missed_traffic" => 0,
            "secondary_traffic" => 0
        ];

        $result = array();
        $result['$request'] = $requestOriginal = $request;

        $primary_traffic = self::getEventsCompanyCounts(array_merge($request, array('type' => 'primary', 'get_list' => true)));

        $result['primary_traffic']['source'] = $primary_traffic;

        $result['primary_traffic']['current_value'] = $primary_traffic['score']['value'];
        $result['primary_traffic']['in_last_month'] = self::getEventsCompanyCounts(array_merge(
            $request,
            array(
                'type' => 'primary',
                'get_list' => false,
                'date_from' => date("d.m.Y 00:00:00", strtotime("-1 months", strtotime($request['date_from']))),
                'date_to' => date("d.m.Y 23:59:59", strtotime("-1 months", strtotime($request['date_to']))),
            )
        ))['score']['value'];

        if ($result['primary_traffic']['in_last_month'] == $result['primary_traffic']['current_value'])
            $result['primary_traffic']['to_last_month'] = 0;
        elseif ($result['primary_traffic']['in_last_month'] != 0)
            $result['primary_traffic']['to_last_month'] = (($result['primary_traffic']['current_value'] / $result['primary_traffic']['in_last_month']) - 1);
        else
            $result['primary_traffic']['to_last_month'] = 1;

        $result['primary_traffic']['in_last_year'] = self::getEventsCompanyCounts(array_merge(
            $request,
            array(
                'type' => 'primary',
                'get_list' => false,
                'date_from' => date("d.m.Y 00:00:00", strtotime("-1 years", strtotime($request['date_from']))),
                'date_to' => date("d.m.Y 23:59:59", strtotime("-1 years", strtotime($request['date_to']))),
            )
        ))['score']['value'];

        if ($result['primary_traffic']['in_last_year'] == $result['primary_traffic']['current_value'])
            $result['primary_traffic']['to_last_year'] = 0;
        elseif ($result['primary_traffic']['in_last_year'] != 0)
            $result['primary_traffic']['to_last_year'] = (($result['primary_traffic']['current_value'] / $result['primary_traffic']['in_last_year']) - 1);
        else
            $result['primary_traffic']['to_last_year'] = 1;


        $result['targeted_traffic']['current_value'] = self::getEventsCompanyCounts(array_merge($request, array('type' => 'targeted', 'get_list' => false)))['score']['value'];
        $result['missed_traffic']['current_value'] = self::getEventsCompanyCounts(array_merge($request, array('type' => 'missed', 'get_list' => false)))['score']['value'];
        //$result['secondary_traffic']['current_value'] = self::getEventsCompanyCounts(array_merge($request, array('type' => 'secondary', 'get_list' => false)))['score']['value'];
        //return [self::getEventsCompanyCounts(array_merge($request, array('type' => 'missed', 'get_list' => false)))['score']['value']];
        //return [1];
        return $result;
    }

    public function getConversion(array $request): array
    {
        $date = date($request['date'] ?: date('d-m-Y'));
        $Primary_to_deal = Iblock::getPlanByDate($_REQUEST['date'], $_REQUEST["helper"] = "getPrimaryDeal");
        $Secondary_deal = Iblock::getPlanByDate($_REQUEST['date'], $_REQUEST["helper"] = "getSecondaryDeal");
        $Primary_to_kev = Iblock::getPlanByDate($_REQUEST['date'], $_REQUEST["helper"] = "getPrimaryKEV");
        $Kev_deal = Iblock::getPlanByDate($_REQUEST['date'], $_REQUEST["helper"] = "getKEVDeal");
        $result["Primary_to_deal"] = self::sortConvers($Primary_to_deal);
        $result["Secondary_deal"] = self::sortConvers($Secondary_deal);
        $result["Primary_to_kev"] = self::sortConvers($Primary_to_kev);
        $result["Kev_deal"] = self::sortConvers($Kev_deal);

        return $result;
    }

    public function sortConvers($array)
    {
        $middle_percent = 0;
        $slash = count($array["data"]);
        foreach ($array["data"] as $key => $value) {
            $middle_percent += (int)$value["price"];
        }
        return round($middle_percent / $slash, 2);
    }
}
