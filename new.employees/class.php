<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;

Loc::loadLanguageFile(dirname(__FILE__) . '/.parameters.php');

class NewEmployees extends CBitrixComponent
{
    public function onPrepareComponentParams($arParams)
    {
        if (empty($arParams['NAME_TEMPLATE'])) {
            $arParams['NAME_TEMPLATE'] = '#LAST_NAME# #NAME#';
        }

        if (empty($arParams['HEADER_LABEL'])) {
            $arParams['HEADER_LABEL'] = Loc::getMessage('HEADER_LABEL');
        }

        if ($arParams['NEW_EMPLOYEE_PERIOD'] <= 0) {
            $arParams['NEW_EMPLOYEE_PERIOD'] = '7';
        }
        $arParams['NEW_EMPLOYEE_PERIOD'] = trim($arParams['NEW_EMPLOYEE_PERIOD']);

        if ($arParams['DIVISION_FILTER'] <= 0) {
            $arParams['DIVISION_FILTER'] = '';
        }
        $arParams['DIVISION_FILTER'] = trim($arParams['DIVISION_FILTER']);

        return $arParams;
    }

    public function executeComponent()
    {
        $today = new \Bitrix\Main\Type\Date();

        if ($this->startResultCache($this->arParams['CACHE_TIME'], $today->toString())) {
            $startDate = new \Bitrix\Main\Type\Date();
            $startDate->add('-'.$this->arParams['NEW_EMPLOYEE_PERIOD'].'d');

            $query = \Bitrix\Main\UserTable::query()
                ->registerRuntimeField(
                    new Bitrix\Main\Entity\ExpressionField('DAY', 'DAY(DATE_REGISTER)')
                )
                ->setSelect(['ID', 'NAME', 'LAST_NAME', 'DATE_REGISTER', 'SECOND_NAME', 'LOGIN', 'WORK_POSITION', 'PERSONAL_PHOTO']);

            if ($this->arParams['DIVISION_FILTER'] > 0) {
                $query->addFilter('UF_DEPARTMENT', $this->arParams['DIVISION_FILTER']);
            }

            $query->addFilter('>DATE_REGISTER', $startDate);
//            $query->addFilter('<=DATE_REGISTER', $today);
            $query->addOrder('DATE_REGISTER', 'DESC');

            $res = $query->fetchAll();

            foreach ($res as $arUser) {
                $userName = array(
                    "NAME" => $arUser["NAME"],
                    "LAST_NAME" => $arUser["LAST_NAME"],
                    "SECOND_NAME" => $arUser["SECOND_NAME"],
                    "LOGIN" => $arUser["LOGIN"],
                    "WORK_POSITION" => $arUser["WORK_POSITION"]);

                if (isset($arUser['PERSONAL_PHOTO'])) {
                    $arSize = ["width" => 40, "height" => 40];
                    $resizeType = BX_RESIZE_IMAGE_PROPORTIONAL_ALT;
                    $arFileTmp = CFile::ResizeImageGet(
                        $arUser['PERSONAL_PHOTO'],
                        $arSize,
                        $resizeType,
                        true
                    );
                    $arUser['PHOTO_URL'] = $arFileTmp['src'];
                } else {
                    $currentTheme = CUserOptions::GetOption("intranet", "theme_type_preset_".SITE_ID) == 'dark' ? '-dark' : '';
                    $arUser['PHOTO_URL'] = $this->__path."/images/person-no-image$currentTheme.svg";
                }

                $arUser['FORMAT_NAME'] = CUser::FormatName($this->arParams["NAME_TEMPLATE"], $userName);
                $this->arResult['USERS'][] = $arUser;
            }

            $this->includeComponentTemplate();
        }
    }
}
