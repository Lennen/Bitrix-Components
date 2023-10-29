<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)
{
	die();
}

/**
 * Bitrix vars
 *
 * @var array $arParams
 * @var array $arResult
 * @var CBitrixComponent $this
 * @global CMain $APPLICATION
 * @global CUser $USER
 */
if(!CModule::IncludeModule('rest'))
{
	return;
}

$query = \CRestUtil::getRequestData();

$arDefaultUrlTemplates404 = array(
	"method" => "#method#",
	"method1" => "#method#/",
	"webhook" => "#aplogin#/#ap#/#method#",
	"webhook1" => "#aplogin#/#ap#/#method#/",
);

$arDefaultVariableAliases404 = array();
$arDefaultVariableAliases = array();

$arComponentVariables = array(
	"method", "aplogin", "ap"
);

$arVariables = array();

if($arParams["SEF_MODE"] == "Y")
{
	$arUrlTemplates = CComponentEngine::MakeComponentUrlTemplates($arDefaultUrlTemplates404, $arParams["SEF_URL_TEMPLATES"]);
	$arVariableAliases = CComponentEngine::MakeComponentVariableAliases($arDefaultVariableAliases404, $arParams["VARIABLE_ALIASES"]);

	$componentPage = CComponentEngine::ParseComponentPath(
		$arParams["SEF_FOLDER"],
		$arUrlTemplates,
		$arVariables
	);

	CComponentEngine::InitComponentVariables($componentPage, $arComponentVariables, $arVariableAliases, $arVariables);

	$query = array_merge($query, $arVariables);
	unset($query['method']);
}
else
{
	ShowError('Non-SEF mode is not supported by bitrix:rest.server component');
}

$transport = 'json';
$methods = [ToLower($arVariables['method']), $arVariables['method']];

// try lowercase first, then original
foreach ($methods as $method)
{
	$point = mb_strrpos($method, '.');

	if($point > 0)
	{
		$check = mb_substr($method, $point + 1);
		if(CRestServer::transportSupported($check))
		{
			$transport = $check;
			$method = mb_substr($method, 0, $point);
		}
	}

	$server = new CRestServer(array(
		"CLASS" => $arParams["CLASS"],
		"METHOD" => $method,
		"TRANSPORT" => $transport,
		"QUERY" => $query,
	), false);

	$result = $server->process();
	/* Кое чье день рожд. не показываю */
        $fl_not_show_dr = 0;
		$uid_show = 0;
		global $USER;
		if( 
		isset($result["result"]["result"]["formData"][0]["ID"]) &&  
		isset($result["result"]["result"]["formData"][0]["LAST_NAME"]) && 
		$USER->IsAdmin()===false
		){
			 if( strlen($result["result"]["result"]["formData"][0]["ID"])>0 ){
				$uid_show = $result["result"]["result"]["formData"][0]["ID"];
				$filter = array('ID' => $uid_show);
				$rsUser = CUser::GetList(($by="ID"), ($order="DESC"), $filter,array("SELECT"=>array("UF_HIDD_DR")));
				$ms_user = $rsUser->Fetch();
				if(isset($ms_user['UF_HIDD_DR'])) {
					if((int)$ms_user['UF_HIDD_DR'] === 1) {
						$fl_not_show_dr = 1;
					}
				}
				$result_0 = \Bitrix\Main\GroupTable::getList(array('select' => array('ID'),'filter' => array('STRING_ID' => 'NO_SHOW_BD')));
				$arGroup = $result_0->fetch();
				if (isset($arGroup['ID'])) {
					$arGroups = CUser::GetUserGroup($uid_show);
					if(in_array($arGroup['ID'], $arGroups)) {
						$fl_not_show_dr = 1;
					}
				}
				/* */
				if($fl_not_show_dr === 1) {
					unset($result["result"]["result"]["formData"][0]["PERSONAL_BIRTHDAY"]);
				}else{
					$str_ms = explode(" ", $result["result"]["result"]["formData"][0]["PERSONAL_BIRTHDAY"]);
					if( isset($str_ms[0]) && isset($str_ms[1]) && count($str_ms)>2){
						$result["result"]["result"]["formData"][0]["PERSONAL_BIRTHDAY"] = trim($str_ms[0]." ".$str_ms[1]);
					}
				}
				 
			 }
		}
		
        
	
	if( isset($result["result"]["result"]["formData"][0]["PERSONAL_BIRTHDAY"]) ){
		
		//file_put_contents("/home/bitrix/www/2mob.txt", "71---- ".$result["result"]["result"]["formData"][0]["PERSONAL_BIRTHDAY"]."\r\n",  FILE_APPEND);
		$str_ms = explode(" ", $result["result"]["result"]["formData"][0]["PERSONAL_BIRTHDAY"]);
		if( isset($str_ms[0]) && isset($str_ms[1]) ){
			//file_put_contents("/home/bitrix/www/2mob.txt", "72---- ".$result["result"]["result"]["formData"][0]["PERSONAL_BIRTHDAY"]."\r\n",  FILE_APPEND);
			$result["result"]["result"]["formData"][0]["PERSONAL_BIRTHDAY"] = trim($str_ms[0]." ".$str_ms[1]);
		}
		
	}
	
	// try original controller name if lower is not found
	if (is_array($result) && !empty($result['error']) && $result['error'] === 'ERROR_METHOD_NOT_FOUND')
	{
		continue;
	}

	// output result
	break;
}

$APPLICATION->RestartBuffer();

$output = $server->output($result);
if (is_object($output) && $output instanceof \Bitrix\Main\HttpResponse)
{
	$server->sendHeadersAdditional();
	$output->send();
}
else
{
	$server->sendHeaders();
	echo $output;
}

CMain::FinalActions();
die();
