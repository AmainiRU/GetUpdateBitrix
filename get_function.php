<?php
//Подключаем битриксовые классы УДАЛИТЬ ПОТОМ ИХ!
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/classes/general/update_class.php");
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/classes/general/update_client_partner.php");

//Указываем путь до директории куда будет происходить скачивание
//Изменить на свой в будующем
$dir = $_SERVER["DOCUMENT_ROOT"]."/bitrix/updates/";

//Инфа по иконкам	
$pic['iconF'] = '<div class="icon iconF tooltip" data-tooltip="Присутствует файл в папке обновлений!"></div>';
$pic['keyF'] = '<div class="icon keyF tooltip" data-tooltip="Модуль достуен на ключе!"></div>';
$pic['keyA'] = '<div class="icon keyA tooltip" data-tooltip="Модуль доступен на ключе, но закончился срок получения обновлений!"></div>';
$pic['checkF'] = '<div class="icon checkF tooltip" data-tooltip="Модуль установлен!"></div>';
$pic['empty'] = '<div class="icon"></div>';
$ajaxloading = "<div class='cssload-container'><div class='cssload-shaft1'></div><div class='cssload-shaft2'></div><div class='cssload-shaft3'></div><div class='cssload-shaft4'></div><div class='cssload-shaft5'></div><div class='cssload-shaft6'></div><div class='cssload-shaft7'></div><div class='cssload-shaft8'></div><div class='cssload-shaft9'></div><div class='cssload-shaft10'></div></div>";



//Все функции
function clear(){unset($_SESSION['KeyInfo']);unset($_SESSION['k']);unset($_SESSION['Key']);}


//Формирует запрос на сервера битрикса $gzip, $step, $type, $module=NULL, $data=NULL, $ver=NUL
	/*
		array(
			0 utf - поддержка utf
			1 gzip - поддержка сжатия
			2 step - вид запроса
			3 type - тип страницы запроса STEPM|LIST
			4 idmodule - идентификатор модуля
			5 ver - текущая  установленная версия модуля
			6 data - остальные данные
			7 fname - имя файла
		);
		
	*/
function response($p) {
	
	$key = $_SESSION['k'];

	$str =   "utf=".$p['utf'].
           		"&lang=ru".
		   		"&stable=Y".
		   		"&CANGZIP=".$p['gzip'].
		   		"&SUPD_DBS=MYSQL".
		   		"&XE=N".
		   		"&LICENSE_KEY=".md5($key).
		   		"&SUPD_STS=1".
		   		"&SUPD_URS=0".
		   		"&SUPD_URSA=1".
		   		"&TYPENC=E".
		   		"&CLIENT_PHPVER=5.5.14".
		   		"&dbv=5.5.41";
		   		
	switch ($p['step']) {
    	case "info_key":
        	$str .= "&fullmoduleinfo=Y&SUPD_SRS=RU&SUPD_CMP=N&product=BSM&verfix=2";
			break;
		case "module_info":
			$str .= "&reqm=".$p['idmodule']."&lim=Y&SUPD_SRS=RU&SUPD_CMP=N&product=BSM&verfix=2";
			break;
		case "download":
			$str .= "&reqm=".$p['idmodule']."&lim=Y&SUPD_SRS=RU&SUPD_CMP=N&UFILE=".$p['data']."&USTART=0&product=BSM&verfix=2";
			break;
		case "updates":
			$str .= "&instm=".$p['data']."&reqm=".$p['idmodule']."&lim=Y&SUPD_SRS=RU&SUPD_CMP=N&product=BSM&verfix=2";
			break;
	}

	if(GetHTTPBitrix($p['fname'], $p['type'], $str)) return TRUE;
	else return FALSE;
}




////////////////////////////////////////////////
// РАБОТАЕМ ИСКЛЮЧИТЕЛЬНО С ИНФОРМАЦИЕЙ О КЛЮЧЕ
//Получает информацию о ключе	
function getKeyInfo($q=FALSE){
	if($q) {$result = &$_SESSION['Key']; $key = $_SESSION['k']; $_SESSION['k'] = $_POST["k"];}
	else $result = &$_SESSION['KeyInfo'];
	
	//формируем массив запроса
	$query = array(
		'utf' => 'Y',
		'gzip' => 'N',
		'step' => 'info_key',
		'type' => 'LIST',
		'fname' => 'keyinfo');
	//Если запрос к серверу вернул ИСТИНУ
	if(response($query)){
			$file = get_tmp_file($query['fname']);
			$infoKey = file_get_contents($file);
			unlink($file);
			$arrInfoKey = Array(); 
			CUpdateClientPartner::__ParseServerData($infoKey, $arrInfoKey, $strError_tmp);
			//Проверяем на наличие ошибок
			if(isset($arrInfoKey['DATA']['#']['ERROR'])) $result['ERROR'] = $arrInfoKey['DATA']['#']['ERROR']['0']['#'];
			else{
				//Формируем массив с ответом 
				$result['CLIENT']['NAME'] = $arrInfoKey['DATA']['#']['CLIENT']['0']['@']['NAME'];
				$result['CLIENT']['DATE_FROM'] = $arrInfoKey['DATA']['#']['CLIENT']['0']['@']['DATE_FROM'];
				$result['CLIENT']['DATE_TO'] = $arrInfoKey['DATA']['#']['CLIENT']['0']['@']['DATE_TO'];
				//пробегаемся по модулям если они есть и дергаем информацию
				foreach($arrInfoKey['DATA']['#']['MODULE'] as $value){
					$moduleId = $value['@']['ID'];
					$result['MODULES'][$moduleId]['ID'] = $moduleId;
					$result['MODULES'][$moduleId]['NAME'] = $value['@']['NAME'];
					$result['MODULES'][$moduleId]['DATE_FROM'] = $value['@']['DATE_FROM'];
					$result['MODULES'][$moduleId]['DATE_TO'] = $value['@']['DATE_TO'];
					$result['MODULES'][$moduleId]['UPDATE_END'] = $value['@']['UPDATE_END'];
					$result['MODULES'][$moduleId]['KEY'] = 'Y';
					foreach($value['#']['VERSION'] as $val){
						$ver = $val['@']['ID'];
						$result['MODULES'][$moduleId]['VERSIONS'][$ver]['ID'] = $moduleId;
						$result['MODULES'][$moduleId]['VERSIONS'][$ver]['UPDATE_VERSION'] = $ver;
						$result['MODULES'][$moduleId]['VERSIONS'][$ver]['DESC'] = $val['#']['DESCRIPTION']['0']['#'];
					}
					
				}
			}
			if($q) {unset($_SESSION['k']); $_SESSION['k'] = $key;}
			return $result;
	}
	//Иначе ошибка
	else return $result['ERROR'] = UNKNOWNERROR;

}

//Добавляет информацию о файлах модуля в сессию	
function getModuleInfo($moduleId){
	//Проверяем на доступность обновлений и информацию хранящююся в сессии
	$module = &$_SESSION['KeyInfo']['MODULES'][$moduleId];
	
	if(	(!$module['UFILE'] || !$module['FSIZE']) && $module['UPDATE_END'] != 'Y'){
		$query = array(
			'utf' => 'Y',
			'gzip' => 'N',
			'step' => 'module_info',
			'type' => 'STEPM',
			'idmodule' => $moduleId,
			'fname' => 'info.'.$moduleId);
		
	
		if(response($query)){
			$file = get_tmp_file($query['fname']);
			$infoModule = file_get_contents($file);
			unlink($file);
			$arrInfoModule = Array(); 
			CUpdateClientPartner::__ParseServerData($infoModule, $arrInfoModule, $strError_tmp);
			$module['UFILE'] = $arrInfoModule['DATA']['#']['FILE']['0']['@']['NAME'];
			$module['FSIZE'] = $arrInfoModule['DATA']['#']['FILE']['0']['@']['SIZE'];
		}else return $_SESSION['KeyInfo']['ERROR'] = UNKNOWNERROR;
	}
	
}
////////////////////////////////////////////////



////////////////////////////////////////////////
//РАБОТАЕМ ИСКЛЮЧИТЕЛЬНО С ИНФОРМАЦИЕЙ ИЗ ПАПКИ ОБНОВЛЕНИЙ
//Добавляет информацию о файлах обновлений в сессию	
function getMyUpdates(){
	//Разбираем файлы в папке
	global $dir;
	$result = &$_SESSION['KeyInfo']['MODULES'];
 	$arrDir = scandir($dir);
 	foreach($arrDir as $val){
	 	$arr = infUPD($val);

	 	//!!!!!!!!!!!!!!
	 	//Добавить поиск имени модуля в файле обновления
	 	
	 	//////

	 	//Заносим информацию в сессию о доступных обновлениях
	 	if($arr['updtype'] == 'mod'){
		 	$result[$arr['mid']]['UPDATE_VERSION'] = $arr['mver'];
		 	$result[$arr['mid']]['UPDATE_FILE'] = $arr['fname'];
		 	$result[$arr['mid']]['ID'] = $arr['mid'];
	 	}
	 	elseif($arr['updtype'] == 'delta'){	
		 	$result[$arr['mid']]['VERSIONS'][$arr['mver']]['UPDATE_VERSION'] = $arr['mver'];
		 	$result[$arr['mid']]['VERSIONS'][$arr['mver']]['UPDATE_FILE'] = $arr['fname'];
		 	$result[$arr['mid']]['VERSIONS'][$arr['mver']]['ID'] = $arr['mid'];
	 	}
	 	
 	}

}

//разбирает имя файла на массив с данными
function infUPD($fupd){
	preg_match("/(\w+\.\w+)+\.(\d+\.\d+\.\d+)+\.(mod|delta)+.upd+/", $fupd, $arr);
	if(is_array($arr)){
		$result['fname'] = $arr['0'];
		$result['mid'] = $arr['1'];
		$result['mver'] = $arr['2'];
		$result['updtype'] = $arr['3'];
		return $result;
	}else return NULL;
}
////////////////////////////////////////////////



////////////////////////////////////////////////
//РАБОТАЕМ ИСКЛЮЧИТЕЛЬНО С ИНФОРМАЦИЕ О СИСТЕМЕ
//Получает список присутствующих в системе модулей
function myModules(){
	//Получаем список установленных модулей
	// Получаем массив установленных версий модулей с версиями
	$myModules = CUpdateClientPartner::GetCurrentModules($strError_tmp='');
	
	//Убираем пример для скрытия модулей
	unset($myModules['module.name1-not_mine']);
	unset($myModules['module.name2-not_mine']);
	unset($myModules['module.name3-not_mine']);
	
	//убираем пометку "Не мое" скрывавшую модуль от сервера битрикса
	foreach($myModules as $key => $value){
		$myModules[str_replace('-not_mine','',$key)] = $value;
		if (strpos($key,'-not_mine')) unset($myModules[$key]);
	}
	//Убираем все модули ядра
	$bitrixModules = array("abtest", "advertising", "bitrix.eshop", "bitrix.sitecommunity", "bitrix.sitecorporate", "bitrix.siteinfoportal", "bitrix.sitepersonal", "bitrixcloud", "bizproc", "bizprocdesigner", "blog", "calendar", "catalog", "clouds", "cluster", "compression", "conversion", "currency", "eshopapp", "fileman", "form", "forum", "highloadblock", "iblock", "idea", "im", "ldap", "learning", "lists", "mail", "main", "mobileapp", "perfmon", "photogallery", "pull", "report", "sale", "scale", "search", "security", "sender", "seo", "socialnetwork", "socialservices", "statistic", "storeassist", "subscribe", "support", "translate", "vote", "webservice", "wiki", "workflow");
	foreach($bitrixModules as $value){
		if(isset($myModules[$value])) unset($myModules[$value]);
	}
	//Получаем имена модулей
	foreach($myModules as $key => $value){
		if(file_exists($lang=$_SERVER["DOCUMENT_ROOT"].'/bitrix/modules/'.$key.'/lang/ru/install/index.php')) require($lang);
		foreach($MESS as $k => $v){
			if(strpos($k, 'MODULE_NAME') or strpos($k,'INSTALL_NAME') or strpos($k,'MOD_NAME')) {
				$myModules[$key]['NAME'] = $v;
			}
		}
		unset($MESS);
	}

	return $myModules;
}
//Помещает информацию в сессию
function getMyModules(){
	$myModules = myModules();
	foreach($myModules as $moduleId => $value){
		$_SESSION['KeyInfo']['MODULES'][$moduleId]['INST_VERSION'] = $value['VERSION'];
		$_SESSION['KeyInfo']['MODULES'][$moduleId]['IS_DEMO'] = $value['IS_DEMO'];
		$_SESSION['KeyInfo']['MODULES'][$moduleId]['NAME'] = $value['NAME'];
		$_SESSION['KeyInfo']['MODULES'][$moduleId]['ID'] = $moduleId;
	}
}	
////////////////////////////////////////////////

//Перестраивает массив без запроса данных с ключа.
function rebuild(){
	$_SESSION['KeyInfo'] = $_SESSION['Key'];
	getMyModules();
	getMyUpdates();
}


function GetHTTPBitrix($fname, $page, $str, $vfix=FALSE){
	
	$requestIP = COption::GetOptionString("main", "update_site", DEFAULT_UPDATE_SERVER);
	$requestPort = 80;
	
	if($page == "LIST")
		$page = "smp_updater_list.php";
	elseif($page == "STEPM")
		$page = "smp_updater_modules.php";

	$FP = fsockopen($requestIP, $requestPort, $errno, $errstr, 120);

	if ($FP)
	{
		$strRequest = "";
		$strRequest .= "POST /bitrix/updates/".$page." HTTP/1.0\r\n";
		$strRequest .= "User-Agent: BitrixSMUpdater\r\n";
		$strRequest .= "Accept: */*\r\n";
		$strRequest .= "Host: ".$requestIP."\r\n";
		$strRequest .= "Accept-Language: en\r\n";
		$strRequest .= "Content-type: application/x-www-form-urlencoded\r\n";
		$strRequest .= "Content-length: ".strlen($str)."\r\n\r\n";
		$strRequest .= "$str";
		$strRequest .= "\r\n";

		fputs($FP, $strRequest);

		$bChunked = False;
		while (!feof($FP)){
			$line = fgets($FP, 4096);
			if ($line != "\r\n"){
				if (preg_match("/Transfer-Encoding: +chunked/i", $line))
					$bChunked = True;
			}else break;
		}

		$content = "";
		if ($bChunked){
			$maxReadSize = 4096;

			$length = 0;
			$line = FGets($FP, $maxReadSize);
			$line = StrToLower($line);

			$strChunkSize = "";
			$i = 0;
			while ($i < StrLen($line) && in_array($line[$i], array("0", "1", "2", "3", "4", "5", "6", "7", "8", "9", "a", "b", "c", "d", "e", "f")))
			{
				$strChunkSize .= $line[$i];
				$i++;
			}

			$chunkSize = hexdec($strChunkSize);

			while ($chunkSize > 0){
				$processedSize = 0;
				$readSize = (($chunkSize > $maxReadSize) ? $maxReadSize : $chunkSize);

				while ($readSize > 0 && $line = fread($FP, $readSize)){
					$content .= $line;
					$processedSize += StrLen($line);
					$newSize = $chunkSize - $processedSize;
					$readSize = (($newSize > $maxReadSize) ? $maxReadSize : $newSize);
				}
				
				$length += $chunkSize;

				$line = FGets($FP, $maxReadSize);

				$line = FGets($FP, $maxReadSize);
				$line = StrToLower($line);

				$strChunkSize = "";
				$i = 0;
				while ($i < StrLen($line) && in_array($line[$i], array("0", "1", "2", "3", "4", "5", "6", "7", "8", "9", "a", "b", "c", "d", "e", "f")))
				{
					$strChunkSize .= $line[$i];
					$i++;
				}

				$chunkSize = hexdec($strChunkSize);
			}
		}else{
			$file = fopen($_SERVER["DOCUMENT_ROOT"]."/bitrix/updates/".$fname.".upd", "w");
			while ($line = fread($FP, 4096)){
				fwrite($file,$line);
			}
			fclose($file);
		}		
		fclose($FP);
	}else return FALSE;
	return TRUE;
}
	
	
//////////////////////////////////////////////

function get_tmp_file($file,$upd=TRUE){
	$str = $_SERVER["DOCUMENT_ROOT"]."/bitrix/updates/".$file;
	if($upd)$str .= ".upd";
	return $str;
	
}

//Убирает пометку "НЕ МОЕ" с ID модуля
function not_mine(){
    //Смотрим пропатчен ли апдейтер. Если нет — патчим его
    $path2 = $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/classes/general/update_client_partner.php";
    $path3 = $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/classes/general/update_client.php";
    $newstr = '/*PATCH*/if(file_exists($ff=$_SERVER["DOCUMENT_ROOT"]."/not_mine.php")) require($ff); /*END PATCH*/return $arClientModules;';
	$newstr2 = '/*PATCH*/if(file_exists($ff=$_SERVER["DOCUMENT_ROOT"]."/not_mine.php")) require($ff);';
		
    $oldstr = '/return\s\$arClientModules;/';
    $isPatch = str_search($path2, $newstr);
    $isPatchToo = str_search($path3, $newstr2);
	$itsNumber = '/GetCurrentModules\(\S+\s\S+\s\S+\s\S(\S+)=\sarray/';	//ну, как смог...


    if(!$isPatch){
        $arrfile = file($path2);
        foreach($arrfile as $key => $str){
            $arrfile[$key] = preg_replace($oldstr, $newstr, $str);
        }
        $fp = fopen($path2, "w+"); // перезаписываем независимо от длины новой строки
        fwrite($fp,implode("",$arrfile));
        fclose($fp);
        echo "<span style='color:red; font-size:16px;'>Внимание!!!<br><br>Файл /bitrix/modules/main/classes/general/update_client_partner.php пропатчен (искать по слову PATCH).</span><br><br>";
    }
	//пропатчен ли update_client.php 
   if(!$isPatchToo){
        $arrfile = file($path3);
		foreach($arrfile as $key => $str){
        preg_match($itsNumber, $str, $itsNumber);
		$itsNumber = $itsNumber[1];
		$oldstr = '/return\s\$'.$itsNumber.';/';
		$newstr2 = '/*PATCH*/if(file_exists($ff=$_SERVER["DOCUMENT_ROOT"]."/not_mine.php")) require($ff);foreach($arModules as $val){if(isset($'.$itsNumber.'[$val]))unset($'.$itsNumber.'[$val]);} /*END PATCH*/return $'.$itsNumber.';';
			$arrfile[$key] = preg_replace($oldstr, $newstr2, $str);
        }
        $fp = fopen($path3, "w+"); // перезаписываем независимо от длины новой строки
        fwrite($fp,implode("",$arrfile));
        fclose($fp);
        echo "<span style='color:red; font-size:16px;'>Внимание!!!<br><br>Файл /bitrix/modules/main/classes/general/update_client.php пропатчен (искать по слову PATCH).</span><br><br>";
    }
    // Проверяем есть ли файл, если да, то не меняем его
    if(!file_exists($path=$_SERVER["DOCUMENT_ROOT"]."/not_mine.php")){
        $fp = fopen($path,"w+");
        fwrite ($fp, "<?\r\n//Замените названия module.name1, module.name2, module.name3 и так далее на модули которые хотите скрыть от серверов битрикса\r\n".'$'."arModules = array(\r\n'module.name1',\r\n'module.name2',\r\n'module.name3'\r\n);\r\n\r\nforeach(".'$'."arModules as ".'$'."val){\r\n    if(isset(".'$'."arClientModules[".'$'."val])) unset(".'$'."arClientModules[".'$'."val]);\r\n}\r\n?>");
        fclose($fp);
        echo "<span style='color:red; font-size:16px;'>Внесите свои даные в файл /not_mine.php находящемся в корне вашего сайта</span><br><br>";
    }
}

// Поиск вхождения строки в файле
function str_search($file, $str)
{
    $isPatch = false;
    if(strpos(file_get_contents($file), $str)){
        $isPatch = true;
    }
    return $isPatch;
}

/** Сравнение двух версий в формате XX.XX.XX  **/
/** Возвращает 1, если $v1 > $v2  **/
/** Возвращает -1, если $v1 < $v2 **/
/** Возвращает 0, если $v1 == $v2 **/
function compareVer($v1, $v2)
{
	$v1 = Trim($v1);
	$v2 = Trim($v2);
	if ($v1 == $v2)	return 0;
	$arrV1 = explode(".", $v1);
	$arrV2 = explode(".", $v2);
	if (IntVal($arrV1[0]) > IntVal($arrV2[0])
		|| IntVal($arrV1[0]) == IntVal($arrV2[0]) && IntVal($arrV1[1]) > IntVal($arrV2[1])
		|| IntVal($arrV1[0]) == IntVal($arrV2[0]) && IntVal($arrV1[1]) == IntVal($arrV2[1]) && IntVal($arrV1[2]) > IntVal($arrV2[2]))
	{return 1;}

	if (IntVal($arrV1[0]) == IntVal($arrV2[0]) && IntVal($arrV1[1]) == IntVal($arrV2[1]) && IntVal($arrV1[2]) == IntVal($arrV2[2]))
	{return 0;}

	return -1;
}


//проверяет возможность установки для модуля
function canInstall($moduleId, $verUPD = NULL){
	
	$inf = $_SESSION['KeyInfo']['MODULES'][$moduleId];

	//Есть ли установленная версия в системе
	if(!isset($verUPD)){
		if($inf['UPDATE_FILE']){
			//Проверяем на установку модуля
			if($inf['INST_VERSION']){
				//сравниваем версии
				$v = compareVer($inf['INST_VERSION'],$inf['UPDATE_VERSION']);
				//Если установленная версия больше или равна той что содержится в папке обновлений запрещаем обновления
				if($v == '1' || $v == '0') return FALSE;
				else return TRUE;			
			} else return TRUE;
		} //else return FALSE;
	}else{
		//Проверяем наличие файла обновления
		if($inf['VERSIONS'][$verUPD]['UPDATE_FILE']){
			//Проверяем на установку модуля
			if($inf['INST_VERSION']){
				
				$v = compareVer($inf['INST_VERSION'],$verUPD);//править
				if($v == "-1" && $inf['VERSIONS'][$verUPD]['UPDATE_VERSION']) return TRUE;
				else return FALSE;
			} //else return FALSE;
		}
	}
	return FALSE;
}

//Проверяет возможность скачивания
function canDownload($moduleId){
	$inf = $_SESSION['KeyInfo']['MODULES'][$moduleId];
	
	//Проверям окончилась ли поддержка
	if($inf['UPDATE_END'] != 'Y' && $inf['KEY'] == 'Y'){
		//сравниваем версии
		return TRUE;
	}else return FALSE;
}

////////////////////////////////////
//Отображение информации

function sortArray(&$arr){
	ksort($arr, SORT_NATURAL);
}

//Отдаем строку модуля
function strModule($infMod){
	
	$str  = "<div id=".str_replace('.', '-',$infMod['ID'])." class='module'>";
	$str .= "<div class='nameModule'><a href='http://marketplace.1c-bitrix.ru/solutions/".$infMod['ID']."'>";
	$infMod['NAME'] ? $str .= $infMod['NAME'].' ' : $str .= "(".$infMod['ID'].")";
	//$str .= "(".$infMod['ID'].")";
	$infMod["DATE_TO"] ? $str .= " поддержка до - ".$infMod["DATE_TO"] : "";
	$str .= "</a></div>";
	$str .=  "<div class='strModule'>";
	$str .= "<div class='inf'>";
	$infMod['INST_VERSION'] ? $str .= $infMod['ID']." (".$infMod['INST_VERSION'].")</div>" : $str .= $infMod['ID']."</div>";
	$str .= infForModule($infMod);
	$str .= "</div>";
		//Есть ли версии и установлен ли модуль
		if($infMod['VERSIONS'] && $infMod['INST_VERSION'])
		$prevVer = '0.0.0';
		foreach($infMod['VERSIONS'] as $vModId => $infVer){
			
			if($infMod['UPDATE_END'] != 'Y' || $infVer['UPDATE_FILE']) $str .= strDelta($infMod,$infVer,$prevVer);
			$prevVer = $infVer['UPDATE_VERSION'];
		}

	$str .= "</div>";
	
	return $str;
}

//блок информации и действий для модуля
function infForModule($infMod){
	global $pic;
	
	//Проверяем на возможность скачивания
	if(canDownload($infMod['ID'])) $isDownloadMod = '<div class="download"><a class="isDownload ajax-send-dwl" data-id="'.$infMod['ID'].'" data-type="mod" href="">Скачать</a></div>';
	else $isDownloadMod = '<div class="download no">Скачать</div>';
	
	//Проверяем на возможность установки
	if(canInstall($infMod['ID'])) $isInstallMod = '<div class="install"><a class="isInstall ajax-send-upd"  data-file="'.$infMod['UPDATE_FILE'].'" data-id="'.$infMod['ID'].'" data-type="mod" href="">Установить</a></div>';
	else $isInstallMod = '<div class="install no">Установить</div>';
	
	$str = '<div id="control-'.str_replace('.', '-',$infMod['ID']).'"class="control">';
	//Установлен?
	$infMod["INST_VERSION"] ? $str .= $pic['checkF'] : $str .= $pic['empty'];
	//Есть файл обновления?
	$infMod["UPDATE_FILE"] ? $str .= $pic['iconF'] : $str .= $pic['empty'];
	//Инфа с ключа о поддержке
	if($infMod["KEY"] == 'Y'){
		$infMod["UPDATE_END"] == 'Y' ? $str .= $pic['keyA'] : $str .= $pic['keyF'];
	}else $str .= $pic['empty'];
	
	$str .= $isDownloadMod;
	$str .= $isInstallMod;
	$str .= "</div>";
	$str .= "<div id='".str_replace('.', '-',$infMod['ID'])."-inf' class='infoAJAX'></div>";
	return $str;
}


//Отдаем строку дельта обновления
function strDelta($infMod,$infVer,$prevVer){

	
	$str = "<div class='strUpdate'>";
	$str .= "<div class='inf'>";
	$str .= $infVer['UPDATE_VERSION'];
	$str .= "</div>";
	$str .= infForDelta($infMod,$infVer,$prevVer);
	$str .= "</div>";
	
	return $str;
}

//блок информации и действий для модуля
function infForDelta($infMod,$infVer,$prevVer){
	global $pic;
	
	//Проверяем на возможность скачивания
	//if(canDownload($infMod['ID'])) $isDownloadUpd = '<div class="download"><a class="isDownload ajax-send-dwl" data-id="'.$infVer['ID'].'" data-prevver="'.$prevVer.'" data-ver="'.$infVer['UPDATE_VERSION'].'" data-type="delta" href="">Скачать</a></div>';
	//else $isDownloadUpd = '<div class="download no">Скачать</div>';
	
	if(canDownload($infMod['ID'])) $noDown = NULL; else $noDown ='no';
	$isDownloadUpd = '<div class="download '.$noDown.'"><a class="isDownload ajax-send-dwl" data-id="'.$infVer['ID'].'" data-prevver="'.$prevVer.'" data-ver="'.$infVer['UPDATE_VERSION'].'" data-type="delta" href="">Скачать</a></div>';
	
	//Проверяем на возможность установки
	//if(canInstall($infMod['ID'], $infVer['UPDATE_VERSION'])) $isInstallUpd = '<div class="install"><a class="isInstall ajax-send-upd" data-file="'.$infVer['UPDATE_FILE'].'" data-id="'.$infVer['ID'].'" data-prevver="'.$prevVer.'" data-ver="'.$infVer['UPDATE_VERSION'].'" data-type="delta" href="">Установить</a></div>';
	//else $isInstallUpd = '<div class="install no">Установить</div>';
	
	if(canInstall($infMod['ID'], $infVer['UPDATE_VERSION'])) $noUpd = NULL; else $noUpd ='no';
	$isInstallUpd = '<div class="install '.$noUpd.'"><a class="isInstall ajax-send-upd" data-file="'.$infVer['UPDATE_FILE'].'" data-id="'.$infVer['ID'].'" data-prevver="'.$prevVer.'" data-ver="'.$infVer['UPDATE_VERSION'].'" data-type="delta" href="">Установить</a></div>';
	
	$str = '<div id="control-'.str_replace('.', '-',$infMod['ID'].'-'.$infVer['UPDATE_VERSION']).'"class="control">';
	//Установлено ли обновление?
	compareVer($infMod["INST_VERSION"],$infVer['UPDATE_VERSION']) != '-1' ? $str .= $pic['checkF'] : $str .= $pic['empty'];
	$infVer["UPDATE_FILE"] ? $str .= $pic['iconF'] : $str .= $pic['empty'];
	//Поправка для иконки ключа
	$str .= $pic['empty'];
	$str .= $isDownloadUpd;
	$str .= $isInstallUpd;
	$str .= "</div>";
	$str .= "<div id='".str_replace('.', '-',$infMod['ID'])."-".str_replace('.', '-',$infVer['UPDATE_VERSION'])."-inf' class='infoAJAX'></div>";
	return $str;

}
//////////////////////////////////////////



//////////////////////////////////////////
//Функции для Аякса

//Функция скачивания модулей
// возвращает строку html после скачивания
// возвращает TRUE если файл уже был
// возвращает FALSE в случае ошибки
function downloadModule($request){
	
	//формируем запрос на получение имени файла для скачивания
	if($request['type'] == 'mod'){
		$query = array(
		'utf' => 'Y',
		'gzip' => 'N',
		'step' => 'download',
		'type' => 'STEPM',
		'idmodule' => $request['id'],
		'fname' => 'dwl.'.$request['id']);
	}else{
		$query = array(
		'utf' => 'Y',
		'gzip' => 'N',
		'step' => 'updates',
		'type' => 'STEPM',
		'idmodule' => $request['id'],
		'data' => $request['id']."%2C".$request['prevver']."%2CN",
		'fname' => 'dwl.'.$request['id']);
	}

		
	//отправляем запрос
	response($query);
	$file = get_tmp_file($query['fname']);
	//считываем файл
	$infFile = file_get_contents($file);

	unlink($file);
	$arrInf = Array();
	//разбираем в массив
	CUpdateClientPartner::__ParseServerData($infFile, $arrInf, $strError_tmp);
	$ufile = $arrInf['DATA']['#']['FILE']['0']['@']['NAME'];
	$ufile_size = $arrInf['DATA']['#']['FILE']['0']['@']['SIZE'];	
	//ищем уже скаченный файл в папке с таким же размером
	$nofile = TRUE;


	global $dir;
	$arrDir = scandir($dir);

 	foreach($arrDir as $val){
	 	$arr = infUPD($val);
	 	$file = str_replace('.upd.upd', '.upd',get_tmp_file($arr['fname']));
	 	//Отладка
	 	//echo $arr['mid']." == ".$request['id']." && ".$arr['updtype']." == ".$request['type']." && ".$arr['mver']." == ".$request['ver']." -- ".$ufile_size." == ".filesize($file)."<br>";
	 	if($request['type'] == 'mod'){
		 	if($arr['mid'] == $request['id'] && $arr['updtype'] == $request['type'])
	 		if($ufile_size==filesize($file)) $nofile =FALSE;
	 	}else{
		 	if($arr['mid'] == $request['id'] && $arr['updtype'] == $request['type'] && $arr['mver'] == $request['ver'])
	 		if($ufile_size==filesize($file)) $nofile =FALSE;
	 	}
	 }
 
	if($nofile){
		//Если получено имя файла
		if($ufile){
			//Получаем файл модуля
			if($request['type'] == 'mod') {
				$query['data'] = $ufile;
				$query['fname'] = $request['id'].'.0.0.0.mod';
			}else{
				$query['data'] .= "&UFILE=".$ufile;
				$query['fname'] = $request['id'].'.'.$request['ver'].'.'.$request['type'];
			}	

			response($query);
			$file = get_tmp_file($query['fname']);
	
			//Если размер не совпадает то удаляем
			if($ufile_size!=filesize($file)){
				unlink($file);
				return FALSE;
			}
			//иначе ищим строку с указанием версии модуля
			elseif($request['type'] == 'mod'){
				$handle = fopen($file, "r");
				$versionMod = FALSE;
				while(!$versionMod  && ($line = fgets($handle, 4096)) !== FALSE ){
					preg_match("/VERSION.[^0-9]*=+>+.[^0-9]*(\d+\.\d+\.\d+)/", $line, $arr);
					//Если найдена то переименовываем файл и останавливаем цикл
					if($arr['1']) {
						rename($file,get_tmp_file($request['id'].'.'.$arr['1'].'.mod'));
						$versionMod = TRUE;}
					
				}
				
				//Изменяем данные в сессии в соответствии с полученными данными
				rebuild();
				return infForModule($_SESSION['KeyInfo']['MODULES'][$request['id']]);
			}elseif($request['type'] == 'delta'){
				//Изменяем данные в сессии в соответствии с полученными данными
				rebuild();
				return infForDelta($_SESSION['KeyInfo']['MODULES'][$request['id']],$_SESSION['KeyInfo']['MODULES'][$request['id']]['VERSIONS'][$request['ver']],$request['prevver']);
			}
		}else return FALSE;
	}else	return TRUE;
}


//Функция расспаковки архива обновлений
function unarch($file, $isDel=FALSE){
	
	$pathFile = get_tmp_file($file,FALSE);
	$updatesDir = "update_".$file;
	$updatesDirFull = $_SERVER["DOCUMENT_ROOT"]."/bitrix/updates/".$updatesDir;

	$f = fopen($pathFile, "r");
	$flabel = fread($f, strlen("BITRIX"));
	if($flabel != 'BITRIX') return FALSE;

	while (TRUE){

		$add_info_size = fread($f, 5);
		$add_info_size = Trim($add_info_size);

		if (IntVal($add_info_size) > 0 && IntVal($add_info_size)."!"==$add_info_size."!"){
			$add_info_size = IntVal($add_info_size);
		}else break;
		
		$add_info = fread($f, $add_info_size);
		$add_info_arr = explode("|", $add_info);

		//Проверяем полученный массив на кол-во элементов
		if (count($add_info_arr) != 3) break;

		$size = $add_info_arr[0];
		$curpath = $add_info_arr[1];
		$crc32 = $add_info_arr[2];

		$contents = "";

		if (IntVal($size) > 0) $contents = fread($f, $size);
		
		//Проверяем контент, контрольная сумма
		$crc32_new = dechex(crc32($contents));
		if ($crc32_new != $crc32) break;
						
		else
		{
			checkDir($updatesDirFull.$curpath, true);

			if (!($fp1 = fopen($updatesDirFull.$curpath, "wb")))break;

			if (strlen($contents) > 0 && !fwrite($fp1, $contents))
			{
				@fclose($fp1);
				break;
			}
			fclose($fp1);

			//Проверка записанного файла
			$crc32_new = dechex(crc32(file_get_contents($updatesDirFull.$curpath)));
			if ($crc32_new != $crc32) break;
		}
				
		fclose($zp);
		
		
		
	}
	if ($isDel)	@unlink($pathFile);
	return TRUE;
	
}


///////////////////ВЯЗТО ТУПО У БИТРИКСА//////////////////////////
/** Создание пути, если его нет, и установка прав писать **/
function checkDir($path, $bPermission = true)
{
	$badDirs = Array();
	$path = str_replace("\\", "/", $path);
	$path = str_replace("//", "/", $path);

	if ($path[strlen($path)-1] != "/") //отрежем имя файла
	{
		$p = strrposbx($path, "/");
		$path = substr($path, 0, $p);
	}

	while (strlen($path)>1 && $path[strlen($path)-1]=="/") //отрежем / в конце, если есть
		$path = substr($path, 0, strlen($path)-1);

	$p = strrposbx($path, "/");
	while ($p > 0)
	{
		if (file_exists($path) && is_dir($path))
		{
			if ($bPermission)
			{
				if (!is_writable($path))
					@chmod($path, BX_DIR_PERMISSIONS);
			}
			break;
		}
		$badDirs[] = substr($path, $p+1);
		$path = substr($path, 0, $p);
		$p = strrposbx($path, "/");
	}

	for ($i = count($badDirs)-1; $i>=0; $i--)
	{
		$path = $path."/".$badDirs[$i];
		@mkdir($path, BX_DIR_PERMISSIONS);
	}
}

function strrposbx($haystack, $needle)
{
	$index = strpos(strrev($haystack), strrev($needle));
	if($index === false)
		return false;
	$index = strlen($haystack) - strlen($needle) - $index;
	return $index;
}
////////////////////////////////////////////////////////////////
?>