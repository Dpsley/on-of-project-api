<?php

namespace Wss;

use Bitrix\Landing\Help;
use Bitrix\Main\Mail\Event;
use Bitrix\Main\UserTable;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Highloadblock\HighloadBlockTable as HLBT;
use CIBlockElement;
use CModule;
use CSite;
use CUser;

\CModule::IncludeModule("iblock");

function myRecursiveMerge($a, $b)
{ //$a will be result. $a will be edited. It's to avoid a lot of copying in recursion
    foreach ($b as $key => $value) {
        if (isset($a[$key])) {
            if (is_array($a[$key]) && is_array($value)) { //merge if they are both arrays
                $a[$key] = myRecursiveMerge($a[$key], $value);
            } else
                $a[$key] = $value;
        } else
            $a[$key] = $value; //add if not exists
    }

    return $a;
}

class Checklist
{
    public static $iblockChecklist = 100; // id инфоблока с опросниками
    public static $iblockCompletedForms = 102; // id инфоблока с результатами

    public static $HLblockQuestionsBlocks = 1; // id HL-блока с блоками вопросов
    public static $HLblockQuestions = 2; // id HL-блока с вопросами
    public static $HLblockOptions = 3; // id HL-блока с вариантами ответов
    public static $HLblockResponseFields = 4; // id HL-блока с полями для письменных ответов

    public static $question_types = array(1 => "TIME", 2 => "RADIO", 3 => "SLIDER", 4 => "YES_OR_NO");
    public static $checklist_fixe_types = array('QUESTION_FIXES' => 1342, 'TIME_QUESTION_FIXES' => 1343, 'OPTION_FIXES' => 1344);

    public static $arErrMesAuth = array('status' => 'error', 'message' => 'Ошибка проверки авторизации');

    private static function GetEntityDataClass($HlBlockId)
    {
        if (empty($HlBlockId) || $HlBlockId < 1) {
            return false;
        }
        $hlblock = HLBT::getById($HlBlockId)->fetch();
        $entity = HLBT::compileEntity($hlblock);
        $entity_data_class = $entity->getDataClass();
        return $entity_data_class;
    }

    private static function getOptionsByQuestion($question_id)
    {
        $result = array();

        if (CModule::IncludeModule('highloadblock')) {
            $strEntityDataClass = self::GetEntityDataClass(self::$HLblockOptions);

            $rsData = $strEntityDataClass::getList(
                array(
                    'select' => array('ID', 'UF_LABEL', 'UF_VALUE', 'UF_TO_QUESTION'),
                    'order' => array('ID' => 'ASC'),
                    'limit' => '20',
                    'filter' => array('UF_TO_QUESTION' => $question_id)
                )
            );

            $kosttil = 0.001;

            while ($arItem = $rsData->Fetch()) {
                $resultItem['value'] = intval($arItem['UF_VALUE']) + $kosttil;
                $resultItem['label'] = $arItem['UF_LABEL'];
                $result[$arItem['ID']] = $resultItem;

                $kosttil += 0.001;
            }
        }

        return $result;
    }

    private static function getFieldsByQuestion($question_id)
    {
        $result = array();

        if (CModule::IncludeModule('highloadblock')) {
            $strEntityDataClass = self::GetEntityDataClass(self::$HLblockResponseFields);

            $rsData = $strEntityDataClass::getList(
                array(
                    'select' => array('ID', 'UF_LABEL', 'UF_IS_REQUIRED', 'UF_QUESTION'),
                    'order' => array('ID' => 'ASC'),
                    'limit' => '20',
                    'filter' => array('UF_QUESTION' => $question_id)
                )
            );
            while ($arItem = $rsData->Fetch()) {
                $result[] = $arItem;
            }
        }

        return $result;
    }

    private static function getQuestionsByQuestionsBlock($questions_block_id)
    {
        $result = array();

        if (CModule::IncludeModule('highloadblock')) {
            $strEntityDataClass = self::GetEntityDataClass(self::$HLblockQuestions);

            $rsData = $strEntityDataClass::getList(
                array(
                    'select' => array('ID', 'UF_TITLE', 'UF_SUBTITLE', 'UF_TYPE', 'UF_EDITABLE', 'UF_SLIDER_MIN', 'UF_SLIDER_MAX', 'UF_TO_QUESTION_BLOCK', 'UF_LIMIT_TIME_VALUE'),
                    'order' => array('ID' => 'ASC'),
                    'limit' => '20',
                    'filter' => array('UF_TO_QUESTION_BLOCK' => $questions_block_id)
                )
            );
            while ($arItem = $rsData->Fetch()) {
                $arItem['UF_TYPE'] = self::$question_types[$arItem['UF_TYPE']];
                $arItem['UF_EDITABLE'] = $arItem['UF_EDITABLE'] === '1';

                $arItem['fields'] = self::getFieldsByQuestion($arItem['ID']);

                if ($arItem['UF_TYPE'] == 'RADIO')
                    $arItem['options'] = self::getOptionsByQuestion($arItem['ID']);

                if ($arItem['UF_EDITABLE']) {

                    $quesiton_fixes = array();

                    if ($arItem['UF_TYPE'] == 'TIME')
                        $quesiton_fixes = self::getFixChecklistProperty("TIME_QUESTION_FIXES", $arItem['ID']);
                    elseif ($arItem['UF_TYPE'] == 'RADIO')
                        $quesiton_fixes = self::getFixChecklistProperty("OPTION_FIXES", $arItem['ID']);

                    $quesiton_fixes = array_merge($quesiton_fixes, self::getFixChecklistProperty("QUESTION_FIXES", $arItem['ID']));

                    $arItem = myRecursiveMerge($arItem, $quesiton_fixes);
                    $arItem['fixes'] = $quesiton_fixes;
                }

                if ($arItem['UF_TYPE'] == 'RADIO') {
                    $arItem['options_by_id'] = $arItem['options'];
                    $arItem['options'] = [...$arItem['options']];
                }
                $result[] = $arItem;
            }
        }

        return $result;
    }

    private static function getQuestionsBlocks($checklist_id)
    {
        $result = array();

        if (CModule::IncludeModule('highloadblock')) {
            $strEntityDataClass = self::GetEntityDataClass(self::$HLblockQuestionsBlocks);

            $rsData = $strEntityDataClass::getList(
                array(
                    'select' => array('ID', 'UF_TITLE', 'UF_INFORM', 'UF_CHECKLIST'),
                    'order' => array('ID' => 'ASC'),
                    'limit' => '20',
                    'filter' => array('UF_CHECKLIST' => $checklist_id)
                )
            );
            while ($arItem = $rsData->Fetch()) {
                $arItem['questions'] = self::getQuestionsByQuestionsBlock($arItem['ID']);
                $result[] = $arItem;
            }
        }

        return $result;
    }

    private static function getChecklistIdForCompany($company_id)
    {
        $result = "Not faund";

        CModule::IncludeModule('iblock');

        $arSelect = array("ID", "IBLOCK_ID", "NAME", "PROPERTY_COMPANY"); //IBLOCK_ID и ID обязательно должны быть указаны, см. описание arSelectFields выше
        $arFilter = array("IBLOCK_ID" => self::$iblockChecklist, "ACTIVE" => "Y", "PROPERTY_COMPANY" => $company_id);
        $res = CIBlockElement::GetList(array(), $arFilter, false, array("nPageSize" => 1), $arSelect);
        if ($ob = $res->GetNextElement()) {
            $arFields = $ob->GetFields();
            $result = $arFields['ID'];
        }

        return 25963; //$result;
    }

    public static function getChecklist($request)
    {
        $result = array();

        $company_id = Iblock::getCompanyIdByCurUser();

        $result['checklist_id'] = $checklist_id = self::getChecklistIdForCompany($company_id);

        $result['arQuestionsBlocks'] = $arQuestionsBlocks = self::getQuestionsBlocks($checklist_id);


        return $result;
    }

    private static function getDatetimeInSiteFormat($datetime)
    {
        global $DB;

        $site_format = CSite::GetDateFormat("SHORT");

        // переведем формат сайта в формат PHP
        $php_format = $DB->DateFormatToPHP($site_format);

        return date($php_format, MakeTimeStamp($datetime, "DD.MM.YYYY HH:MI:SS"));
    }

    public static function postResult($request)
    {
        if (!$request['answers'])
            return array('status' => 'error', 'message' => 'Не выбран ни один ответ');
        if (!$request['department_id'])
            return array('status' => 'error', 'message' => 'Не указан салон');
        if (!$request['seller'])
            return array('status' => 'error', 'message' => 'Не указан продавец');
        if (!$request['datetime_from'] || !$request['datetime_to'])
            return array('status' => 'error', 'message' => 'Не указан временной промежуток');

        $user_id = User::isAuth($_REQUEST);
        if (!$user_id)
            return array('status' => 'error', 'message' => 'Пользователь не авторизован');

        $answers = $request['answers'];
        $inputs = $request['inputs'];

        $datetime_from = $request['datetime_from'];
        $datetime_to = $request['datetime_to'];
        $department_id = $request['department_id'];
        $seller = $request['seller'];
        $email = $request['email'];
        $comment = $request['comment'];
        $checklist_id = $request['checklist_id'];


        CModule::IncludeModule('iblock');

        $propertes = array(
            'INPUTS' => array(),
            'ANSWERS' => array(),
            'CONTROLLER' => $user_id,
            'SELLER' => $seller,
            'DEPARTMENT' => $department_id,
            'COMMENT' => $comment,
            'CHECKLIST' => $checklist_id,
        );

        foreach ($answers as $answer) {
            $question_id = $answer['question_id'];
            $score = $answer['score'];

            $propertes['ANSWERS'][] = array('VALUE' => $score, 'DESCRIPTION' => $question_id);
        }

        foreach ($inputs as $input) {
            $input_id = $input['input_id'];
            $value = $input['value'];

            $propertes['INPUTS'][] = array('VALUE' => $value, 'DESCRIPTION' => $input_id);
        }


        $arLoadProductArray = array(
            'CREATED_BY' => $user_id,
            'MODIFIED_BY' => $user_id,
            'IBLOCK_SECTION_ID' => false,
            'IBLOCK_ID' => self::$iblockCompletedForms,
            'NAME' => $datetime_from . ' - ' . $datetime_to,
            'ACTIVE' => 'Y',
            'ACTIVE_FROM' => self::getDatetimeInSiteFormat($datetime_from),
            'ACTIVE_TO' => self::getDatetimeInSiteFormat($datetime_to)
        );

        $el = new CIBlockElement;

        if ($ITEM_ID = $el->Add($arLoadProductArray)) {
            CIBlockElement::SetPropertyValuesEx($ITEM_ID, self::$iblockCompletedForms, $propertes);


            return ['id' => $ITEM_ID];
        } else {
            return ['status' => 'error', 'message' => $el->LAST_ERROR];
        }
    }

    private static function getFixChecklistProperty($propName, $question_id)
    {
        CModule::IncludeModule('iblock');

        $fixType_id = self::$checklist_fixe_types[$propName];

        $company_id = Iblock::getCompanyIdByCurUser();
        $checklist_id = self::getChecklistIdForCompany($company_id);

        $row = CIBlockElement::GetPropertyValues(self::$iblockChecklist, ['ID' => $checklist_id], true, ['ID' => [$fixType_id]])->Fetch();
        $prop = ['DESCRIPTION' => $row['DESCRIPTION'][$fixType_id], 'VALUE' => $row[$fixType_id]];

        $table = self::propsWhithDescriptionToHashTable($prop);

        if (isset($table[$company_id]) && $table[$company_id]) {
            $companyFixes = json_decode($table[$company_id], true);

            if (isset($companyFixes[$question_id]))
                return $companyFixes[$question_id];
            else
                return [];
        } else
            return [];
    }

    private static function updateChecklistProperty($propName, $question_id, $value)
    {
        CModule::IncludeModule('iblock');

        $company_id = Iblock::getCompanyIdByCurUser();
        $checklist_id = self::getChecklistIdForCompany($company_id);

        $prop = CIBlockElement::GetPropertyValues(self::$iblockChecklist, ['ID' => $checklist_id], true, ['ID' => [$propName]])->Fetch();
        $table = self::propsWhithDescriptionToHashTable($prop);

        $companyFixes = array();

        if ($table[$company_id])
            $companyFixes = json_decode($table[$company_id]);

        $companyFixes[$question_id] = $value;

        $table[$company_id] = json_encode($companyFixes);
        $newProp = self::HashTableToPropsWhithDescription($table);

        $propValues = [$propName => $newProp];

        CIBlockElement::SetPropertyValuesEx($checklist_id, self::$iblockChecklist, $propValues);

        return $propValues;
    }

    public static function editQuestion($request)
    {
        $result = array();

        $propName = "QUESTION_FIXES";
        $question_id = $request['question_id'];

        $title = $request['title'];
        $subtitle = $request['subtitle'];
        $value = ['UF_TITLE' => $title, 'UF_SUBTITLE' => $subtitle];

        self::updateChecklistProperty($propName, $question_id, $value);

        return $result;
    }

    public static function editTimeQuestion($request)
    {
        $result = array();

        $propName = "TIME_QUESTION_FIXES";
        $question_id = $request['question_id'];
        $limit_value = ['UF_LIMIT_TIME_VALUE' => $request['limit_value']];

        self::updateChecklistProperty($propName, $question_id, $limit_value);

        return $result;
    }

    public static function editQuestionOption($request)
    {
        $result = array();

        $propName = "OPTION_FIXES";
        $question_id = $request['question_id'];
        $option_id = $request['option_id'];
        $label = $request['label'];

        $value = ['options' => [$option_id => ['label' => $label]]];

        self::updateChecklistProperty($propName, $question_id, $value);

        return $result;
    }


    private static function propsWhithDescriptionToHashTable($props)
    {
        $result = array();

        foreach ($props['DESCRIPTION'] as $key => $desc) {
            $result[$desc] = $props['VALUE'][$key];
        }

        return $result;
    }

    private static function HashTableToPropsWhithDescription($table)
    {
        $result = array();

        foreach ($table as $desc => $value) {
            $result[] = ['VALUE' => $value, 'DESCRIPTION' => $desc];
        }

        return $result;
    }


    private static function getFormatAnswersForQuestionBlock($question_block, $completed_checklist_answers, $completed_checklist_inputs)
    {
        $result = array(
            'name' => $question_block['UF_TITLE'],
            'title' => $question_block['UF_TITLE'],
            'subtitle' => $question_block['UF_SUBTITLE'],
            'score' => '',
            'max_score' => '',
            'questions' => array()
        );

        $question_block_score = 0;
        $question_block_max_score = 0;


        foreach ($question_block['questions'] as $question) {
            $question_score = $completed_checklist_answers[$question['ID']];
            $question_max_score = 0;

            $question_type = $question['UF_TYPE'];

            if ($question_type == 'SLIDER' || $question_type == 'YES_OR_NO') {
                $question_max_score = round(($question['UF_SLIDER_MAX']) ? $question['UF_SLIDER_MAX'] : 1);
            } elseif ($question_type == 'RADIO') {
                $question_max_score = round(max(array_map(fn($option) => $option['value'], $question['options'])));
            } else {
                $question_max_score = 2;
            }


            $response_complited_question = array('name' => $question['UF_TITLE'], 'score' => $question_score, 'max_score' => $question_max_score, 'fields' => array(), 'type' => $question['UF_TYPE'], 'options' => $question['options']);


            foreach (self::getFieldsByQuestion($question['ID']) as $field) {
                $response_field = array("label" => $field['UF_LABEL'], 'value' => $completed_checklist_inputs[$field['ID']]);

                $response_complited_question['fields'][] = $response_field;
            }


            $question_block_score += $question_score;
            $question_block_max_score += $question_max_score;

            $result['questions'][] = $response_complited_question;
        }


        $result['score'] = $question_block_score;
        $result['max_score'] = $question_block_max_score;

        return $result;
    }


    private static function formatChecklistsStatistic($completed_checklists)
    {
        $result = array('checklists' => array(), 'average_score' => 0);


        foreach ($completed_checklists as $completed_checklist) {

            $checklist_score = 0;
            $checklist_max_score = 0;

            $checklist_id = $completed_checklist['checklist_id'];
            $completed_checklist_name = $completed_checklist['name'];

            $completed_checklist_answers = self::propsWhithDescriptionToHashTable($completed_checklist['answers']);
            $completed_checklist_inputs = self::propsWhithDescriptionToHashTable($completed_checklist['inputs']);

            $completed_checklist_comment = $completed_checklist['comment']['VALUE'];

            $response_checklist = array('name' => $completed_checklist_name, 'comment' => $completed_checklist_comment, 'score' => '', 'max_score' => '', 'question_blocks' => array());


            foreach (self::getQuestionsBlocks($checklist_id) as $question_block) {
                $response_question_block = self::getFormatAnswersForQuestionBlock($question_block, $completed_checklist_answers, $completed_checklist_inputs);

                $response_checklist['question_blocks'][] = $response_question_block;

                $checklist_score += $response_question_block['score'];
                $checklist_max_score += $response_question_block['max_score'];
            }


            $response_checklist['score'] = $checklist_score;
            $response_checklist['max_score'] = $checklist_max_score;

            $result['checklists'][] = $response_checklist;
        }


        if (count($result['checklists']))
            $result['average_score'] = array_sum(array_map(fn($checklist) => $checklist['score'], $result['checklists'])) / count($result['checklists']);

        return $result;
    }


    private static function filterChecklistStatisticBySeller($completed_checklists, $seller_id)
    {
        $result = array();

        $seller_obj = CUser::GetByID($seller_id)->Fetch();
        $seller_name = $seller_obj['LAST_NAME'] . ' ' . $seller_obj['NAME'];

        $seler_completed_checklists = array_filter($completed_checklists, function ($checklist) use ($seller_id) {
            return $checklist['seller'] == $seller_id;
        });

        $format_checklists = self::formatChecklistsStatistic($seler_completed_checklists);

        $result = array_merge(array('id' => $seller_id, 'name' => $seller_name), $format_checklists);

        return $result;
    }

    private static function groupChecklistsBySellers($completed_checklists)
    {
        $result = array();

        $sellers = array_unique(array_column($completed_checklists, 'seller'));

        foreach ($sellers as $seller_id) {
            $result[] = self::filterChecklistStatisticBySeller($completed_checklists, $seller_id);
        }

        return $result;
    }


    private static function groupChecklistsByDepartment($completed_checklists)
    {
        $result = array();

        $departments = array_unique(array_column($completed_checklists, 'department'));


        foreach ($departments as $department_id) {

            $name = CIBlockElement::GetByID($department_id)->GetNext()['NAME'];

            $result[] = array('name' => $name, 'sellers' => self::groupChecklistsBySellers($completed_checklists));
        }

        return $result;
    }


    public static function getCompletedChecklists($request)
    {
        $result = array();

        $completedChecklists = array();

        CModule::IncludeModule('iblock');

        $arSelect = array("ID", "IBLOCK_ID", "NAME", "PROPERTY_SELLER");
        $arFilter = array("IBLOCK_ID" => self::$iblockCompletedForms, "ACTIVE" => "Y");

        if (isset($request['date_from']) && isset($request['date_to']))
            $arFilter = array_merge($arFilter, array(">=DATE_ACTIVE_FROM" => self::getDatetimeInSiteFormat($request['date_from'] . ' 00:00:00'), "<=DATE_ACTIVE_TO" => self::getDatetimeInSiteFormat($request['date_to'] . ' 23:59:59')));

        if (!isset($request['department']))
            $request['department'] = array_column(Iblock::getDepartments()['list'], 'id');

        $arFilter = array_merge($arFilter, array("PROPERTY_DEPARTMENT" => $request['department']));


        if (isset($request['seller']))
            $arFilter = array_merge($arFilter, array("PROPERTY_SELLER" => $request['seller']));


        $res = CIBlockElement::GetList(array(), $arFilter, false, array("nPageSize" => 50), $arSelect);
        while ($ob = $res->GetNextElement()) {
            $arFields = $ob->GetFields();
            $arProperties = $ob->GetProperties();
            $completedChecklists[] = array('id' => $arFields['ID'], 'name' => $arFields['NAME'], 'seller' => $arProperties['SELLER']['VALUE'], 'department' => $arProperties['DEPARTMENT']['VALUE'], 'inputs' => $arProperties['INPUTS'], 'answers' => $arProperties['ANSWERS'], 'comment' => $arProperties['COMMENT'], 'checklist_id' => $arProperties['CHECKLIST']);
        }


        //$result['sellers'] = self::groupChecklistsBySellers($completedChecklists);

        $result['departments'] = self::groupChecklistsByDepartment($completedChecklists);

        return $result;
    }
}