<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
require("./get_function.php");
require("./get_const.php");

//Если передана команда на скачивания
if($_REQUEST['action']=='dwl'){
	//Возвращает 1 если все прошло успешно
	//возвращает -1 если файл уже присутствует
	//возвращает 0 если произошла ошибка
	$request = array(
		'id' => $_REQUEST['id'],
		'ver' => $_REQUEST['ver'],
		'prevver' => $_REQUEST['prevver'],
		'type' => $_REQUEST['type'],
		);
	$result = downloadModule($request);

	if($request['ver']){
		$id = str_replace('.', '-',$_REQUEST['id']);
		$id .= '-'.str_replace('.', '-',$_REQUEST['ver']);
	}
	else $id = str_replace('.', '-',$_REQUEST['id']);
	
	if(is_string($result)){
		$result = str_replace('control-', 'new-control-', $result);
		$result = str_replace('infoAJAX', '1', $result);
		echo $result;
	}
	elseif($result === FALSE) echo '<div id="'.$id.'-inf" class="0"></div>';
	elseif($result === TRUE) echo '<div id="'.$id.'-inf" class="-1"></div>';
}

//Если передана команда на обновление
if($_REQUEST['action']=='upd'){
	$request = array(
		'file' => $_REQUEST['file'],
		'id' => $_REQUEST['id'],
		'ver' => $_REQUEST['ver'],
		'prevver' => $_REQUEST['prevver'],
		'type' => $_REQUEST['type'],
	);
	
	//расспаковываем файл для установки
	$unarch = unarch($request['file']);
	$updatesDir = "update_".$request['file'];
	$id = str_replace('.', '-',$_REQUEST['id']);
	
	$uptd = CUpdateClientPartner::UpdateStepModules($updatesDir, $strError = '');
		
	if (!$uptd || !$unarch) {echo '<div id="'.$id.'-inf" class="0"></div>';}
	else {
		rebuild();
		if($request['type'] == 'mod'){
			$result = infForModule($_SESSION['KeyInfo']['MODULES'][$request['id']]);
			$result = str_replace('control-', 'new-control-', $result);
			$result = str_replace('infoAJAX', '1', $result);
			echo $result;
		}elseif($request['type'] == 'delta'){
			$result = infForDelta($_SESSION['KeyInfo']['MODULES'][$request['id']],$_SESSION['KeyInfo']['MODULES'][$request['id']]['VERSIONS'][$request['ver']],$request['prevver']);
			$result = str_replace('control-', 'new-control-', $result);
			$result = str_replace('infoAJAX', '1', $result);
			echo $result;

		}
		
	}

}


?>