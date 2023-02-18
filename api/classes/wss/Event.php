<?php

namespace Wss;

use Bitrix\Iblock\ElementTable;
use Bitrix\Main\Data\Cache;
use CIBlock;
use CIBlockElement;

class Event
{
    static public string $iblockEventsType = 'companies'; // тип инфоблока компаний

    // текущий инфоблок событий компании
    public static function getIblock()
    {
        $result = [];
        $cache = Cache::createInstance();
        $cacheId = 'getCurUserEventIblockCompany' . md5(serialize($_REQUEST));
        if ($cache->initCache(Helpers::$cache_time_long, $cacheId, 'custom_cache')) {
            $result = $cache->getVars();
        } elseif ($cache->startDataCache()) {
            $company_id = Company::getId();
            $res = CIBlock::GetList([], ['TYPE' => self::$iblockEventsType, "NAME" => '%_' . $company_id], true);
            if ($ar_res = $res->Fetch()) {
                $result = $ar_res;
            }
            $cache->endDataCache($result);
        }
        return $result;
    }

    // проверка события к принадлежности компании
    private static function checkIdByCurrentCompany(int $eventId): bool
    {
        $event_iblock = self::getIblock();
        $res = ElementTable::query()
            ->addSelect('ID')
            ->where('ID', $eventId)
            ->where('IBLOCK_ID', $event_iblock['ID'])
            ->fetch();
        if ($res) {
            return true;
        }
        return false;
    }

    // удаление события
    public static function delete($request): array
    {
        $arIsAuth = User::isAuth($_REQUEST);
        if (!$arIsAuth['isAuth']) {
            return Helpers::getError(User::$errMesAuth);
        }
        if (!$arIsAuth['billing']['active']) {
            return Helpers::getError(Billing::$errorSubscription);
        }
        if (!$arIsAuth['roles']['admins'] && !$arIsAuth['roles']['controllers']) {
            return Helpers::getError('У Вас нет прав на удаление событий');
        }
        if (!self::checkIdByCurrentCompany($request['event_id'])) {
            return Helpers::getError('Событие с указанным ID не найдено');
        }

//        ElementTable::delete($request['event_id']);
        CIBlockElement::Delete($request['event_id']);

        return Helpers::getSuccess('Событие успешно удалено');
    }
}
