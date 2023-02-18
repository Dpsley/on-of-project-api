<?php

namespace Wss;

use Bitrix\Main\Data\Cache;
use Bitrix\Main\UserTable;
use CIBlockElement;
use CIBlockResult;

class Department
{
//    static public int $iblockDepartments = 1; // инфоблок департаментов
    static public string $iblockDepartmentsType = 'general'; // тип инфоблока департаментов
    static public string $iblockDepartmentsCode = 'departments'; // код инфоблока департаментов

    public static function getIblockId(): int
    {
        return Helpers::getIblockIdByCode(self::$iblockDepartmentsCode, self::$iblockDepartmentsType);
    }

    // проверка названия департамента (подразделения) компании
    public static function checkName($name)
    {
        $arIsAuth = User::isAuth($_REQUEST);
        if (!$arIsAuth['isAuth']) {
            return Helpers::getError(Helpers::$errorAuth);
        }
        $company_id = Company::getId();
        $rs = CIBlockElement::GetList(
            [],
            [
                "IBLOCK_ID" => self::getIblockId(),
                "ACTIVE" => "Y",
                "=NAME" => $name,
                "PROPERTY_company" => $company_id
            ],
            false,
            false,
            ["ID", "NAME"]
        );
        if ($ar = $rs->GetNext()) {
            return $ar['NAME'];
        }

        return false;
    }

    // добавление департамента
    public static function add($request)
    {
        $result = [];
        $curUser = User::isAuth($_REQUEST);
        if (!$curUser['isAuth']) {
            return Helpers::getError(Helpers::$errorAuth);
        }
        if (!$curUser['roles']['admins']) {
            return Helpers::getError('У Ваc нет прав на добавление департамента');
        }
        if (empty($request['dep_name'])) {
            return Helpers::getError('Не указано название департамента');
        }
        if (self::checkName($request['dep_name'])) {
            return Helpers::getError('Указанное название подразделения уже существует - (' . $request['dep_name'] . ')');
        }

        $companyId = Company::getId();

        $PROP = Helpers::getPropsByIblock(self::getIblockId());
        $el = new CIBlockElement;

        $arLoadProductArray = array(
            "IBLOCK_SECTION_ID" => false,
            "IBLOCK_ID" => self::getIblockId(),
            "NAME" => $request['dep_name'],
            "ACTIVE" => "Y",
        );

        foreach ($PROP as $prop) {
            $arLoadProductArray['PROPERTY_VALUES'][$prop['ID']] =
                $request['dep_prop_' . mb_strtolower($prop['CODE'])] ?? $prop['VALUE'];

            if ($prop['CODE'] == 'company') {
                $arLoadProductArray['PROPERTY_VALUES'][$prop['ID']] = $companyId;
            }
        }

        if ($ID = $el->Add($arLoadProductArray)) {
            $result['status'] = 'success';
            $result['message'] = 'Создан департамент с ID ' . $ID;
        } else {
            $result['status'] = 'error';
            $result['message'] = $el->LAST_ERROR;
        }

        return $result;
    }

    // изменение департамента
    public static function update($request): array
    {
        $result = [];
        $curUser = User::isAuth($_REQUEST);
        if (!$curUser['isAuth']) {
            return Helpers::getError(Helpers::$errorAuth);
        }
        if (!$curUser['roles']['admins']) {
            return Helpers::getError('У Ваc нет прав на изменение департамента');
        }

        if ($request['dep_id'] > 0) {
            $departments = self::getList()['list'];

            if (array_filter($departments, static fn ($value) => $value['id'] == $request['dep_id'])) {
                $PROP = Helpers::getPropsByElement($request['dep_id'], true);
                $el = new CIBlockElement;
                $arLoadProductArray = [];

                if (!empty($request['dep_name'])) {
                    $arLoadProductArray['NAME'] = $request['dep_name'];
                }

                foreach ($PROP as $prop) {
                    $arLoadProductArray['PROPERTY_VALUES'][$prop['ID']] =
                        $request['dep_prop_' . mb_strtolower($prop['CODE'])] ?? $prop['VALUE'];
                }

                if ($el->Update($request['dep_id'], $arLoadProductArray)) {
                    $result['status'] = 'success';
                    $result['message'] = 'Сохранено';
                } else {
                    $result['status'] = 'error';
                    $result['message'] = $el->LAST_ERROR;
                }
            } else {
                return Helpers::getError('Департамент с указанным id не найден');
            }
        } else {
            return Helpers::getError('Не указан id департамента');
        }
        return $result;
    }

    // удаление департамента
    public static function delete($request)
    {
        $result = [];
        $curUser = User::isAuth($_REQUEST);
        if (!$curUser['isAuth']) {
            return Helpers::getError(Helpers::$errorAuth);
        }
        if (!$curUser['roles']['admins']) {
            return Helpers::getError('У Ваc нет прав на добавление департамента');
        }
        if ($request['dep_id'] > 0) {
            $departments = self::getList()['list'];
            if (array_filter($departments, static fn ($value) => $value['id'] == $request['dep_id'])) {
                $el = new CIBlockElement;

                $arLoadProductArray['ACTIVE'] = 'N';

                if ($el->Update($request['dep_id'], $arLoadProductArray)) {
                    $result['status'] = 'success';
                    $result['message'] = 'Удален департамент с ID ' . $request['dep_id'];
                } else {
                    $result['status'] = 'error';
                    $result['message'] = $el->LAST_ERROR;
                }
            } else {
                return Helpers::getError('Департамент с указанным id не найден');
            }
        } else {
            return Helpers::getError('Не указан id департамента');
        }
        return $result;
    }

    // список департаментов, к которым привязан юзер (привязка к компании не считается)
    public static function getListAttachByUserId($userId = 0): array
    {
        if (!is_numeric($userId) || $userId === 0) {
            $arIsAuth = User::isAuth($_REQUEST);
            if (!$arIsAuth['isAuth']) {
                return Helpers::$arErrMesAuth;
            }
            $userId = $arIsAuth['user_id'];
        }
        $result = [];
        $rs = CIBlockElement::GetList(
            [],
            [
                "IBLOCK_ID" => self::getIblockId(),
                "ACTIVE" => "Y",
                ["LOGIC" => "OR", ["PROPERTY_users" => $userId], ["PROPERTY_rops" => $userId], ["PROPERTY_controllers" => $userId], ["PROPERTY_moneys" => $userId]]
            ],
            false,
            false,
            ["ID", "IBLOCK_ID", "PROPERTY_company"]
        );
        while ($ob = $rs->GetNextElement()) {
            $result[] = [
                'FIELDS' => $ob->GetFields(),
                'PROPERTIES' => $ob->GetProperties(),
            ];
        }
        return $result;
    }

    // получение списка департаментов текущего пользователя
    public static function getList(): array
    {
        $arIsAuth = User::isAuth($_REQUEST);
        if (!$arIsAuth['isAuth']) {
            return Helpers::getError(Helpers::$errorAuth);
        }
        $result = [];

        $cache = Cache::createInstance();
        $cacheId = 'getDepartments' . md5(serialize($_REQUEST)) . md5(serialize($arIsAuth));
        if ($cache->initCache(Helpers::$cache_time_long, $cacheId, 'custom_cache')) {
            $result = $cache->getVars();
        } elseif ($cache->startDataCache()) {
            $company_id = Company::getId();
            if ($company_id) {
                $rs = CIBlockElement::GetList(
                    [],
                    [
                        "IBLOCK_ID" => self::getIblockId(),
                        "ACTIVE" => "Y",
                        "PROPERTY_company" => $company_id,
                        "ID" => $arIsAuth['department']
                        // отфильтруем департаменты для обычных сотрудников и роп, для других значение пустое
                    ],
                    false,
                    false,
                    ['ID', 'NAME', 'IBLOCK_ID']
                );
                while ($ob = $rs->GetNextElement()) {
                    $arFields = $ob->GetFields();
                    $arFields['value'] = (int)$arFields['ID'];
                    $arFields['label'] = $arFields['NAME']; //для Quasar Select
                    $arProps = $ob->GetProperties();
                    $arProps2 = [];
                    foreach ($arProps as $pv) {
                        $arProps2['prop_' . $pv['CODE']]['name'] = $pv['NAME'];
                        $arProps2['prop_' . $pv['CODE']]['code'] = $pv['CODE'];
                        if (($pv['CODE'] == 'users' || $pv['CODE'] == 'rop') && $pv['VALUE']) {
                            $rsUsers = UserTable::getList(
                                [
                                    "order" => ['NAME' => 'ASC'],
                                    "select" => [
                                        'ID',
                                        'NAME',
                                        'LAST_NAME',
                                        'SECOND_NAME',
                                        'EMAIL'
                                    ],
                                    "filter" => ['ID' => $pv['VALUE']],
                                ]
                            );
                            unset($pv['VALUE']);
                            while ($arUser = $rsUsers->Fetch()) {
                                //для Quasar Select
                                $arUser['value'] = (int)$arUser['ID'];
                                $arUser['label'] = $arUser['NAME'] ?? $arUser['LAST_NAME'] ?? '';
                                if ($arUser['LAST_NAME'] && $arUser['NAME']) {
                                    $arUser['label'] .= ' ' . $arUser['LAST_NAME'];
                                }
                                if (!$arUser['NAME'] && !$arUser['LAST_NAME']) {
                                    $arUser['label'] = 'ID ' . $arUser['ID'] . ' (имя не указано)';
                                }

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

    // получение ID департаментов текущего пользователя
    public static function getIds()
    {
        $result = self::getList();
        $ids = [];
        foreach ($result['list'] as $item) {
            $ids[] = $item['id'];
        }
        return $ids;
    }
}
