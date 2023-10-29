<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();
/** @var CBitrixComponent $this */
/** @var array $arParams */
/** @var array $arResult */
/** @var string $componentPath */
/** @var string $componentName */
/** @var string $componentTemplate */
/** @global CDatabase $DB */
/** @global CUser $USER */
/** @global CMain $APPLICATION */

/** @global CIntranetToolbar $INTRANET_TOOLBAR */

use Bitrix\Main\Context;
use Bitrix\Main\Type\DateTime;

use Bitrix\Main\Loader; 
use Bitrix\Disk\Internals\ObjectTable;
use Bitrix\Main\Localization\Loc;
use Bitrix\Disk\BaseObject;
use Bitrix\Disk\Ui;
use Bitrix\Disk\Driver;
use \Bitrix\Disk\Storage;

Loc::loadMessages(__FILE__);

CPageOption::SetOptionString("main", "nav_page_in_session", "N");

if(is_array($arParams["STORAGE_ID"]) && count($arParams["STORAGE_ID"])<=0)
	$arParams["STORAGE_ID"] = array(14);

$arParams["FILES_COUNT"] = intval($arParams["FILES_COUNT"]);
if($arParams["FILES_COUNT"]<=0)
	$arParams["FILES_COUNT"] = 5;

$arParams["DISPLAY_TOP_PAGER"] = $arParams["DISPLAY_TOP_PAGER"]=="Y";
$arParams["DISPLAY_BOTTOM_PAGER"] = $arParams["DISPLAY_BOTTOM_PAGER"]!="N";
$arParams["PAGER_TITLE"] = trim($arParams["PAGER_TITLE"]);
$arParams["PAGER_SHOW_ALWAYS"] = $arParams["PAGER_SHOW_ALWAYS"]=="Y";
$arParams["PAGER_TEMPLATE"] = trim($arParams["PAGER_TEMPLATE"]);
$arParams["PAGER_DESC_NUMBERING"] = $arParams["PAGER_DESC_NUMBERING"]=="Y";
$arParams["PAGER_DESC_NUMBERING_CACHE_TIME"] = intval($arParams["PAGER_DESC_NUMBERING_CACHE_TIME"]);
$arParams["PAGER_SHOW_ALL"] = $arParams["PAGER_SHOW_ALL"]=="Y";

if($arParams["DISPLAY_TOP_PAGER"] || $arParams["DISPLAY_BOTTOM_PAGER"])
{
	$arNavParams = array(
		"nPageSize" => $arParams["FILES_COUNT"],
		"bDescPageNumbering" => $arParams["PAGER_DESC_NUMBERING"],
		"bShowAll" => $arParams["PAGER_SHOW_ALL"],
	);
	$arNavigation = CDBResult::GetNavParams($arNavParams);
	if($arNavigation["PAGEN"]==0 && $arParams["PAGER_DESC_NUMBERING_CACHE_TIME"]>0)
		$arParams["CACHE_TIME"] = $arParams["PAGER_DESC_NUMBERING_CACHE_TIME"];
}
else
{
	$arNavParams = array(
		"nTopCount" => $arParams["FILES_COUNT"],
		"bDescPageNumbering" => $arParams["PAGER_DESC_NUMBERING"],
	);
	$arNavigation = false;
}

if (empty($arParams["PAGER_PARAMS_NAME"]) || !preg_match("/^[A-Za-z_][A-Za-z01-9_]*$/", $arParams["PAGER_PARAMS_NAME"]))
{
	$pagerParameters = array();
}
else
{
	$pagerParameters = $GLOBALS[$arParams["PAGER_PARAMS_NAME"]];
	if (!is_array($pagerParameters))
		$pagerParameters = array();
}

if(!CModule::IncludeModule("disk"))
{
	ShowError(GetMessage("DISK_MODULE_NOT_INSTALLED"));
	return;
}
	
$arResult["USER_HAVE_ACCESS"] = $bUSER_HAVE_ACCESS;
//SELECT
$arSelect = array(
	'ID',
	'STORAGE_ID',
	'NAME',
	'UPDATE_TIME',
	'CREATE_TIME',
	'TYPE',
	'FILE_CONTENT_TYPE' => 'FILE_CONTENT.CONTENT_TYPE'
);
		
//WHERE
$arFilter = array (
	'STORAGE_ID'    => $arParams["STORAGE_ID"],
	'TYPE'          => ObjectTable::TYPE_FILE,
	'DELETED_TYPE'  => 0
);

//ORDER BY
$arSort["ID"] = "DESC";
		
$urlManager = Driver::getInstance()->getUrlManager();
$arComponentVariables = array('FOLDER_ID', 'FILE_ID', 'PATH');
$arVariableAliases = array();
//$rows = array();

$arResult["ITEMS"] = array();

$resObjects = ObjectTable::getList([
	 'select' => $arSelect,
	 'filter' => $arFilter,
	 'order' => $arSort,
	 'limit' => $arParams["FILES_COUNT"]
]);
	
while( $arObject = $resObjects->fetch() ) 
{
	$object = BaseObject::buildFromArray($arObject);
	/** @var File|Folder $object */
	$name = $object->getName();
	$objectId = $object->getId();
	$exportData = array(
		'TYPE' => $object->getType(),
		'NAME' => $name,
		'ID' => $objectId,
	); 
	$folder = \Bitrix\Disk\Folder::loadById($arResult['VARIABLES']['FOLDER_ID']);
	$strorage = Storage::loadById((int)$arObject['STORAGE_ID'], array('ROOT_OBJECT'));
	$arVariables = array(
		'STORAGE' => $strorage,
		'FOLDER_ID' => $strorage->getRootObjectId(),
		'RELATIVE_PATH' => '/',
		'RELATIVE_ITEMS' => array(),
	);
	CComponentEngine::InitComponentVariables('file_list', $arComponentVariables, $arVariableAliases, $arVariables);
	
	$relativePath = trim($arVariables['RELATIVE_PATH'], '/');
	$detailPageFile = CComponentEngine::makePathFromTemplate('', array(
			'FILE_ID' => $objectId,
			'FILE_PATH' => ltrim($relativePath . '/' . $name, '/'),
		));
	$actions = $tileActions = $columns = array();
	if(1)
	{
		$exportData['OPEN_URL'] = $urlManager->encodeUrn($detailPageFile);

		$sourceUri = new \Bitrix\Main\Web\Uri($urlManager->getUrlForDownloadFile($object));
		$fileData = [
			'ID' => $object->getId(),
			'CONTENT_TYPE' => $arObject['FILE_CONTENT_TYPE'],
			'ORIGINAL_NAME' => $object->getName(),
			'FILE_SIZE' => $object->getSize(),
		];
		
		$attr = (Bitrix\Disk\Ui\FileAttributes::buildByFileData($fileData, $sourceUri)
			->setObjectId($object->getId()))->toDataSet();
			

		$attrHtml = '';
		
		if(array_key_exists('viewer',$attr)) $attrHtml .= ' data-viewer';
		if(isset($attr['viewerType'])) $attrHtml .= ' data-viewer-type="'.$attr['viewerType'].'"';
		if(isset($attr['src'])) $attrHtml .= ' data-src="'.$attr['src']->getPath().'?'.$attr['src']->getQuery().'"';
		if(isset($attr['objectId'])) $attrHtml .= ' data-object-id="'.$attr['objectId'].'"';
		
		$actions[] = array(
			"PSEUDO_NAME" => "download",
			"ICONCLASS" => "ui-btn-icon-download",
			"TEXT" => Loc::getMessage('DISK_FOLDER_LIST_ACT_DOWNLOAD'),
			"ONCLICK" => "jsUtils.Redirect(arguments, '" . $urlManager->getUrlForDownloadFile($object) . "')",
			"HREF" => $urlManager->getUrlForDownloadFile($object),
		);
		
		$exportData['IS_LINK'] = $object->isLink();
		$tildaExportData = array();
		foreach($exportData as $exportName => $exportValue)
		{
			$tildaExportData['~' . $exportName] = $exportValue;
		}
		unset($exportRow);
		$arResult["ITEMS"][] = array(
			'data' => array_merge($exportData, $tildaExportData),
			'columns' => $columns,
			'actions' => $actions,
			'tileActions' => $tileActions,
			//for sortByColumn
			'TYPE' => $exportData['TYPE'],
			'NAME' => $exportData['NAME'],
			'CREATE_TIME' => $object->getCreateTime()->getTimestamp(),
			'UPDATE_TIME' => $object->getUpdateTime()->getTimestamp(),
			'SIZE' => $object->getSize(),
			"ATTR" => $attrHtml,
			"HREF" => $urlManager->getUrlForDownloadFile($object)
		);
	}

}
		
$this->IncludeComponentTemplate();
	
/*
if(isset($arResult["ID"]))
{
	$arTitleOptions = null;
	
	$this->SetTemplateCachedData($arResult["NAV_CACHED_DATA"]);

	return $arResult["ELEMENTS"];
}
*/