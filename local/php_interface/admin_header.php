<?php

/**
 * global @var $APPLICATION
 */

if ($_REQUEST['type'] == 'general' && $_REQUEST['IBLOCK_ID'] == 48 && $APPLICATION->GetCurPage() == '/bitrix/admin/iblock_element_edit.php') { ?>
    <style>
        #tr_PROPERTY_505, #tr_PROPERTY_504 {
            display: none;
        }
    </style>
<?php }
