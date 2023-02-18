<?
namespace Wss;

use CUser;

class Billing
{
    static public $iblockBilling = 3; // инфоблок подписок
    static public $arErrMesAuth = array('status' => 'error', 'message' => 'Ошибка проверки авторизации');
    static public $arErrMessBilling = array('status'=>'error','message'=>'Активных подписок не найдено');
    static public string $errorSubscription = 'Активных подписок не найдено';
    static private $checkAuth = true;

    static public function check($company_id = false, $active_date = true) // проверка подписки текущей компании
    {
        $result = array();
        if(!$company_id) {
            $company_id = \Wss\Iblock::getCompanyIdByCurUser(Cuser::GetID());
            $result['active'] = false;
        }

        $arFilter = array(
            "IBLOCK_ID" => self::$iblockBilling,
            "ACTIVE" => "Y",
            "PROPERTY_account_id" => $company_id,
        );

        if($active_date === true){
            $arFilter['ACTIVE_DATE'] = 'Y';
        }

        \CModule::IncludeModule("iblock");

        $rs = \CIBlockElement::GetList(
            array('active_to' => 'desc'),
            $arFilter,
            false,
            false,
            array('ID', 'NAME', 'IBLOCK_ID', 'ACTIVE_TO')
        );
        $pass_mass = [];
        while ($ob = $rs->GetNextElement()) {
            $arFields = $ob->GetFields();
            $pass_mass["ACTIVE_TO"] = $arFields["ACTIVE_TO"];
            $salons = $ob->GetProperty("count_salons");
            $pass_mass["salons"] = $salons["VALUE"] ;
//            $arProps = $ob->GetProperties();
            //if(!$result['active_to']) $result['active_to'] = date('c',strtotime($arFields['ACTIVE_TO']));
            $result['list'][] = $pass_mass;
//            break;
        }
        //$end_date = strtotime($result['active_to']);

            foreach ($result['list'] as $key => $value){
                if(strtotime(date("d.m.Y H:i:s")) <= strtotime($value['ACTIVE_TO'])) {
                    $result["active_to"] = $value['ACTIVE_TO'];
                    $result["available_salon"] += $value["salons"];
                }
            }
        $difference = strtotime($result["active_to"]) - strtotime(date("d.m.Y H:i:s"));
        if($difference >= 604800) {
            $result["notify"] = "";
        }else{
            $result["notify"] = "У вас заканчивается тариф, убедительная просьба, подумать о продлении тарифа заранее, спасибо!";
        }
        if (!$result['active_to']) {
            $result['status'] = 'error';
            $result['message'] = self::$arErrMessBilling['message'];
        } else {
            $result['status'] = 'success';
            $result['active'] = true;
        }
    $result["active_to"] = date('c',strtotime($result["active_to"]));

        return $result;
    }

    static public function checkByCompanyId($company_id, $active_date = true){ // проверка подписки по id компании
        return self::check($company_id,$active_date);
    }

    static public function add($request) // проверка подписки текущей компании
    {
        global $USER;
        $result = array();
        if (self::$checkAuth) {
            $arIsAuth = \Wss\User::isAuth($_REQUEST);
            if (!$arIsAuth['isAuth']) {
                return self::$arErrMesAuth;
            }

            if (!$arIsAuth['roles']['super_admin']) {
                return ['status' => 'error', 'message' => 'У Вас нет прав на добавление подписок'];
            }
        }
        if(!$request['company_id']) return array('status'=>'error','message'=>'Не указан id компании');
        if(!$request['date_from'] && !$request['date_to'] && !$request['period']) return array('status'=>'error','message'=>'Не указан диапазон дат или период');

        $el = new \CIBlockElement;

        $PROP = array();
        $PROP[1] = $request['company_id']; // account_id
        $PROP[2] = $request['sum']; // summ

        if(!$request['period']) {
            $date_from = date('d-m-Y 00:00:00', strtotime($request['date_from']));
            $date_to = date('d-m-Y 23:59:59', strtotime($request['date_to']));
        }

        $date_to_active = self::checkByCompanyId($request['company_id'], false)['active_to'];

//        $result['$date_to_active'] = $date_to_active;

        if($date_to_active){
            if($request['period'] > 0) { // если указан период подписки в днях
                $date_from = date('d-m-Y 00:00:01',strtotime('+1 day',strtotime($date_to_active)));
                $date_to = date('d-m-Y 23:59:59',strtotime('+'.($request['period'] + 1).' day',strtotime($date_to_active)));
            }elseif(strtotime(date('d-m-Y',strtotime($date_to_active))) >= strtotime(date('d-m-Y',strtotime($date_from)))){ // если активная подписка больше либо равна, чем указана дата начала подписки, то сместим на разницу их дней
                $days_count_until_active_day = count(\Wss\Iblock::getDatesFromRange($date_to_active,$date_from));// кол-во дней до конца подписки
//                $result['$days_count_until_active_day'] = $days_count_until_active_day;
                $days_count_between_dates = count(\Wss\Iblock::getDatesFromRange($date_from,$date_to));// кол-во дней до конца подписки
//                $result['$days_count_between_dates'] = $days_count_between_dates;

                $date_from = date('d-m-Y 00:00:01',strtotime('+'.(($days_count_until_active_day === 0) ? '1' : $days_count_until_active_day).' day',strtotime($date_from)));
                $date_to = date('d-m-Y 23:59:59',strtotime('+'.($days_count_between_dates + 1).' day',strtotime($date_from)));

                $result['message'] = 'Указанная дата начала подписки попадала на действующую, поэтому она была заменена на следующую после окончания действующей подписки, а именно с '.$date_from.' по '.$date_to;
                $date_from = ''; // удалим т.к.активируется сразу
            }
        }else{
            $date_from = date('d-m-Y 00:00:01');
            $date_to = date('d-m-Y 23:59:59',strtotime('+'.$request['period'].' day',strtotime(date('d-m-Y 23:59:59'))));
        }

//        $result['$date_from'] = $date_from;
//        $result['$date_to'] = $date_to;

        $arLoadProductArray = Array(
            "MODIFIED_BY"    => $USER->GetID(), // элемент изменен текущим пользователем
            "IBLOCK_SECTION_ID" => false,          // элемент лежит в корне раздела
            "IBLOCK_ID"      => self::$iblockBilling,
            "ACTIVE_FROM"      => ConvertTimeStamp(strtotime($date_from), "FULL"),
//            "DATE_ACTIVE_FROM"      => \ConvertTimeStamp(strtotime($date_from), 'FULL'),
            "ACTIVE_TO"      => \ConvertTimeStamp(strtotime($date_to),'FULL'),
//            "DATE_ACTIVE_TO"      => \ConvertTimeStamp(strtotime($date_to),'FULL'),
            "PROPERTY_VALUES"=> $PROP,
            "NAME"           => "Оплата ".date('Y-m-d')." ".$request['sum'],
            "ACTIVE"         => "Y",            // активен
        );

        if($PRODUCT_ID = $el->Add($arLoadProductArray)) {
            $result['status'] = 'success';
            if(!$result['message']) $result['message'] = 'Подписка успешно добавлена с указанным периодом с '.$date_from.' по '.$date_to;
        }else {
            $result['status'] = 'error';
            $result['message'] = $el->LAST_ERROR;
        }



        return $result;
    }

    static public function addDemo ($companyId)
    {
        if ($companyId) {
            self::$checkAuth = false;
            self::add(['company_id' => $companyId, 'period' => 14, 'sum' => 0]);
        }
    }

}