<?php
session_start();
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
require("./get_function.php");
require("./get_const.php");

//Патчим файлы для правильной работы скрипта скрытия модулей
echo not_mine();

//Сбрасываем кэш
$cache = new CPHPCache();
$cache->AbortDataCache();


//Устанавливаем заголовок страницы
$APPLICATION->SetTitle("Get Update Modules ver 2.3.0 (beta)");

/////////////////////
//Начинаем

//Если ключ меньшей длинны или нажата кнопка Сбросить
if(strlen($_POST["k"])<23 || $_POST["command"]=="reset" || $_SESSION['k'] != $_POST["k"]){
	clear();	
}
if($_SESSION['k'] != $_POST["k"]) getKeyInfo(TRUE);

//строим основной массив с данными
rebuild();


//Если нажата кнопка получить инфу о модулях
if($_POST["command"]=="module"){
	
	//Если ключ не изменился
	if($_SESSION['k'] == $_POST["k"]){
		echo KEYNOTCHANGE;
	}
	//Если ключ короче чем должен быть
	elseif(strlen($_POST["k"])<23){
		echo SHORTKEY;
	}
	//Если передан новый ключ и нужной длинны
	else{
		unset($_SESSION['KeyInfo']['ERROR']);
		//Сохраняем ключ в сессии
		$_SESSION['k'] = $_POST["k"];
		echo MADEREQUEST;
		//Получаем информацию о ключе и сохраняем в сессию
		getKeyInfo();
		
	}

}

//Сортируем массив с модулями
sortArray($_SESSION['KeyInfo']['MODULES']);

// Выводим ошибку если есть
echo "<p>".$_SESSION['KeyInfo']['ERROR']."</p>";

//ссылки для уменьшения кода
if(is_array($_SESSION['KeyInfo']['CLIENT'])){
	$cName = &$_SESSION['KeyInfo']['CLIENT']['NAME'];
	$dateFrom = &$_SESSION['KeyInfo']['CLIENT']['DATE_FROM'];
	$dateTo = &$_SESSION['KeyInfo']['CLIENT']['DATE_TO'];
}


$modules = &$_SESSION['KeyInfo']['MODULES'];

//Сортируем список обновлений
foreach($modules as $modId => $infMod){
	sortArray($_SESSION['KeyInfo']['MODULES'][$modId]['VERSIONS']);
}

?>
<br>
<form id="infkey" action="get_update.php" method="post"></form>
<form id="reset" action="get_update.php" method="post"></form>

<p>Ключ: <input id="key" name="k" form="infkey" value="<?=$_POST['k']?>" size="28" maxlength="23"></p>


<p><input name="command" form="infkey" value="module" hidden="true">
<input type="submit" form="infkey" value="Получить информацию о модулях">

<input name="command" form="reset" value="reset" hidden="true">
<input type="submit" form="reset" value="Сбросить"></p> 

<?//Строим вывод исходя из полученной информации?>

<form id="downmod" action="get_update.php" method="post"></form>
<div id="updater">
<?//Получена ли информация о ключе
	

	if(is_array($_SESSION['KeyInfo']['CLIENT'])){
		echo "<div id='keyinfo'><p>Зарегистрировано на имя \"".$cName."\", обновления доступны с ".$dateFrom." по ".$dateTo."</p>";
		if(count($modules)>0) echo '<br><p>Список модулей:</p>';
		echo "</div>";

	}

	//Пробегаемся по всем модулям и выводим строки
	foreach($modules as $modId => $infMod){
		//Проверяем если есть установленный модуль или доступно обновление
		if($modules[$modId]['INST_VERSION'] != '' || $modules[$modId]['UPDATE_VERSION'] != '' || $modules[$modId]['KEY'] == 'Y'){ 
			//Выводим строку
			echo strModule($infMod);
		}
	}
	
//ОТЛАДКА
//__($_SESSION['KeyInfo']);
//$temp_upd = 'update_archive';
//CUpdateClientPartner::UnGzipArchive($temp_upd, $strError='', false);
//unarch('aspro.mshop.1.1.2.delta');
//<div id="result">Результат</div>
	
?>
</div>


<script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.0/jquery.min.js"></script>


<script>
$(document).on('click', '.ajax-send-dwl',function(){

	 parents = $(this).parents();
	 controlId = $(parents[1]).attr('id');
	 controlInf = controlId.replace(/control-/gi, "");
	
	 id = $(this).attr('data-id');
	 type = $(this).attr('data-type');
	
	if(type == 'delta'){
		 prevver = $(this).attr('data-prevver');
		 ver = $(this).attr('data-ver');
	}else{
		prevver = false;
		ver = false;
	}

	var buff;
	buff = $("#"+controlId).html();
	
	$.ajax({
		url: 'get_upd.php',
		cache: false,
	    data:{
	        action: "dwl",
	        id:id,
	        prevver:prevver,
	        ver:ver,
	        type:type
    },
	    beforeSend: function(){
			    h = $("#"+controlId).outerHeight(true);
		        $("#"+controlId).replaceWith("<div id='"+controlId+"' class='control'> <?=$ajaxloading?> </div>");
		        $("#"+controlId).height(h);
		        $("#updater").addClass('disable');
	},
        complete: function(data){
	       
		   control = $("#new-"+controlId, data.responseText).html();
		   inf = $("#"+controlInf+"-inf", data.responseText).attr("class");

		   if(inf==1){
		   	    $("#"+controlInf+"-inf").append("<span class='green'>Успешно скачан файл!</span>");
	           	$("#"+controlInf+"-inf span").delay(2000).fadeOut(1000, function(){$(this).remove()});
		   		$("#"+controlId).replaceWith("<div id='"+controlId+"' class='control'>"+control+"</div>");
           }else if(inf==0){
	           	$("#"+controlInf+"-inf").append("<span class='red'>Произошла неизвестная Ошибка!</span>");
	           	$("#"+controlInf+"-inf span").delay(2000).fadeOut(1000, function(){$(this).remove()});
	           	$("#"+controlId).replaceWith("<div id='"+controlId+"' class='control'>"+buff+"</div>");
           }else if(inf<0){
	           	$("#"+controlInf+"-inf").append("<span class='blue'>Файл уже присутствует в папке!</span>");
	           	$("#"+controlInf+"-inf span").delay(2000).fadeOut(1000, function(){$(this).remove()});
			   	$("#"+controlId).replaceWith("<div id='"+controlId+"' class='control'>"+buff+"</div>");
           }
           $("#updater").removeClass('disable');
           //$('#result').html(data.responseText);

        }
	});
	return false;
});



$(document).on('click', '.ajax-send-upd',function(){

	parents = $(this).parents();
	controlId = $(parents[1]).attr('id');
	controlInf = controlId.replace(/control-/gi, "");
	
	file = $(this).attr('data-file');
	id = $(this).attr('data-id');
	type = $(this).attr('data-type');
	
	if(type == 'delta'){
		prevver = $(this).attr('data-prevver');
		ver = $(this).attr('data-ver');
	}else{
		prevver = false;
		ver = false;
	}


	var buff;
	buff = $("#"+controlId).html();
	
	$.ajax({
		url: 'get_upd.php',
		cache: false,
	    data:{
	        action: "upd",
	        file:file,
	        id:id,
	        prevver:prevver,
	        ver:ver,
	        type:type

    },
	    beforeSend: function(){
			h = $("#"+controlId).outerHeight(true);
		    $("#"+controlId).replaceWith("<div id='"+controlId+"' class='control'> <?=$ajaxloading?> </div>");
		    $("#"+controlId).height(h);
		    $("#updater").addClass('disable');
	},
        complete: function(data){
	       
		   control = $("#new-"+controlId, data.responseText).html();
		   inf = $("#"+controlInf+"-inf", data.responseText).attr("class");
		   
		   if(inf==1){
		   	    $("#"+controlInf+"-inf").append("<span class='green'>Успешно установлено!</span>");
	           	$("#"+controlInf+"-inf span").delay(2000).fadeOut(1000, function(){$(this).remove()});
		   		if(type =='mod'){
			   		ids = $(parents[3]).attr('id');
			   		$("#"+ids+" .install").removeClass('no');
			   	}
		   		$("#"+controlId).replaceWith("<div id='"+controlId+"' class='control'>"+control+"</div>");
           }else if(inf==0){
	           	$("#"+controlInf+"-inf").append("<span class='red'>Произошла неизвестная Ошибка!</span>");
	           	$("#"+controlInf+"-inf span").delay(2000).fadeOut(1000, function(){$(this).remove()});
	           	$("#"+controlId).replaceWith("<div id='"+controlId+"' class='control'>"+buff+"</div>");
           }
           $("#updater").removeClass('disable');
           //$('#result').html(data.responseText);

        }
	});
	return false;
});


</script>

<style>
.disable .control a{pointer-events: none;color:#999; text-decoration: none}
.module { font-size: 14px; padding-bottom: 24px; }
.module .strModule:hover, .module .strUpdate:hover { background-color: #e0ffed; }
.module .nameModule { padding: 7px 0; background-color: rgba(238, 238, 238, 0.46); }
.module .strUpdate .inf { padding-left: 15px; }
.module .control, .module .infoAJAX, .module .nameModule { text-align: center; }
.module .icon { min-width: 12px; min-height: 12px; padding: 0 3px; }
.module .icon.iconF { background-image: url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAABHUlEQVQokZVSPWrDUAwWjcFXKIn9LEF6g4zZM3UMgdQXKNRZu/oEgcTWu0IgS4e6GIohB8kNSlosvQt0aOzYJR36TUKffj8JoMH26JOVBFlLYi2ItUDWkqwksD360EWwPo2IXWWsxDDfD1oiPXjGSkzsqmB9Gl0qs6sCljH8gWAjd8Sugu3RB7KSGCtxNwAzmWEms67PWInJSgLIWvbGAIAok2WUybLXZr4fIGsJxFo0vjCrJ4Z1iuxSZJca1mmY1ZOGJ9ail4C5rNDqM+ayw1x2Z3vVS/j/SNeW5nqBXC+uLt3IOtx8mZZNDx6kB6+VlWXcyvpT8eN2yJ+hYb3/fbiI5YHYvV8O15LpDebu8fwSL2T1FVnfIpan7mt8AwzWpWaaVYYHAAAAAElFTkSuQmCC"); background-position: center; background-repeat: no-repeat; }
.module .icon.checkF { background-image: url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAABDklEQVQokYWSPU7DQBBGv4ZU7pFygkguEEpJsxIkUjyTneEMlKZ3S80NuACR/DPbQkHtVD4CjW8QV1BRGEeJFchI26zePO18s8BvxXk8SUxSNqnZtOMgOzb9cLmLMK7Fxk85SEOmLZlkXKijSu7Y+GpgVqW/3ps5SENB3k7aAFClD2z6tSzXMyQmKZm252Cu5L6/CLIlkwwA5i/zi39hAGDTjgt1t6/+koN8rip/8yd82AAAXOlTn448j2Eu1HGQ3dGThiYy/R6bySRjkxoc/ON46GW5nh3CLncRmbaJSXo2Vpe7iE3fOUgT5/EEwOnFcaGOTDIybTlIs9j46ZFp+BoUZMumXX+kTkzSvRnADyChnzV3+kMtAAAAAElFTkSuQmCC"); background-position: center; background-repeat: no-repeat; }
.module .icon.keyF { background-image: url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAA4ElEQVQokZXPMUoDURAG4F+sLSw8g4ewl7w3szMDlrlAwD4k2CmYxiukEW2W3Zk2VbocwDsIkkAaGwvBtTBbxKwkDrxuvvn/BwDolXJOoTNy/SDXVw4ZAzhC19AznZLrG4c120/GnSC7XnNYQ6GLVKYzdulzWENuq84Uru2Owxp2nQDAxVRO2hSUON4FleafBX2nkFtynW8OvHRWAgByffr9Bwr9YpfBnyhVWlDIQ3Yd5druD0JbVUMf/4XYbXlwPQCgWohcb7LrqEW5sqv9SbUNN+AzRXG5F6QoErmt2+vf6v+HtwWGu7EAAAAASUVORK5CYII="); background-position: center; background-repeat: no-repeat; }
.module .icon.keyA { background-image: url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAA40lEQVQokZWPMUoDYRCFn6S2SOEZUqWQ/WcaFfLPgGBCqpReIGAfDHYK2ngKsfIEVnYewDsIYkTI/jOQIuCm2A2EZCXJgyle8b33BgCQYmi50qsLz1z4M0UaAzhAnaan7aYLfblysXoVtCnTcOXKhQm929nxkXfosvKT2hYTunPlwoUeAODnpHW4bHkBGhtAHkO3TORkSrcm/Fb5j9pJ5Sx6Xv/Bhf5MePgvlIT6SegxCV270v1O0Kpc+GkvyJS/d54HAHnMeiZ8U06soBgGW5uShlEJ8Hwq2flWIJdwYcq/y/QFiJSQyUhKN38AAAAASUVORK5CYII="); background-position: center; background-repeat: no-repeat; }
.module .download, .module .install, .module .icon { display: inline-block; padding: 0 5px; }
.module .no {pointer-events: none;opacity: 0}

.module { -moz-box-sizing: border-box; -webkit-box-sizing: border-box; box-sizing: border-box; max-width: 100%; margin-left: auto; margin-right: auto; }
.module:after { content: " "; display: block; clear: both; }

.strModule, .strUpdate, .nameModule { -moz-box-sizing: border-box; -webkit-box-sizing: border-box; box-sizing: border-box; width: 100%; float: left; margin-left: 0; margin-right: 0; }

.inf { -moz-box-sizing: border-box; -webkit-box-sizing: border-box; box-sizing: border-box; width: 24.42748%; float: left; margin-right: 0.76336%; }

.control { -moz-box-sizing: border-box; -webkit-box-sizing: border-box; box-sizing: border-box; width: 41.22137%; float: left; margin-right: 0.76336%; display: flex;}

.infoAJAX { -moz-box-sizing: border-box; -webkit-box-sizing: border-box; box-sizing: border-box; width: 32.82443%; float: right; margin-right: 0; }

.module .green{
	color: #00b722;
}
.module .blue{
	color: #00a3c7;
}
.module .red{
	color: #ff9696;
}

.tooltip::after {
	background: rgba(20, 20, 20, 0.72);
	border-radius: 8px 8px 8px 0px;
	box-shadow: 1px 1px 10px rgba(0, 0, 0, 0.5);
	color: #fff;
	content: attr(data-tooltip); /* Главная часть кода, определяющая содержимое всплывающей подсказки */
	margin-top: -24px;
	opacity: 0; /* Наш элемент прозрачен... */
	padding: 3px 7px;
	position: absolute;
	visibility: hidden; /* ...и скрыт. */
			
	transition: all 0.2s ease-in-out; /* Добавить плавности по вкусу */
}
		
.tooltip:hover::after {
	opacity: 1; /* Показываем его */
	visibility: visible;
}

.cssload-container *,.cssload-container *:before,.cssload-container *:after{box-sizing:border-box;-o-box-sizing:border-box;-ms-box-sizing:border-box;-webkit-box-sizing:border-box;-moz-box-sizing:border-box}.cssload-container{margin:auto 0;width:58px; padding-left: 5px}.cssload-container >div{float:left;background:rgb(185,108,255);height:100%;width:5px;margin-right:1px;display:inline-block}.cssload-container .cssload-shaft1{animation-delay:0.06s;-o-animation-delay:0.06s;-ms-animation-delay:0.06s;-webkit-animation-delay:0.06s;-moz-animation-delay:0.06s}.cssload-container .cssload-shaft2{animation-delay:0.12s;-o-animation-delay:0.12s;-ms-animation-delay:0.12s;-webkit-animation-delay:0.12s;-moz-animation-delay:0.12s}.cssload-container .cssload-shaft3{animation-delay:0.17s;-o-animation-delay:0.17s;-ms-animation-delay:0.17s;-webkit-animation-delay:0.17s;-moz-animation-delay:0.17s}.cssload-container .cssload-shaft4{animation-delay:0.23s;-o-animation-delay:0.23s;-ms-animation-delay:0.23s;-webkit-animation-delay:0.23s;-moz-animation-delay:0.23s}.cssload-container .cssload-shaft5{animation-delay:0.29s;-o-animation-delay:0.29s;-ms-animation-delay:0.29s;-webkit-animation-delay:0.29s;-moz-animation-delay:0.29s}.cssload-container .cssload-shaft6{animation-delay:0.35s;-o-animation-delay:0.35s;-ms-animation-delay:0.35s;-webkit-animation-delay:0.35s;-moz-animation-delay:0.35s}.cssload-container .cssload-shaft7{animation-delay:0.4s;-o-animation-delay:0.4s;-ms-animation-delay:0.4s;-webkit-animation-delay:0.4s;-moz-animation-delay:0.4s}.cssload-container .cssload-shaft8{animation-delay:0.46s;-o-animation-delay:0.46s;-ms-animation-delay:0.46s;-webkit-animation-delay:0.46s;-moz-animation-delay:0.46s}.cssload-container .cssload-shaft9{animation-delay:0.52s;-o-animation-delay:0.52s;-ms-animation-delay:0.52s;-webkit-animation-delay:0.52s;-moz-animation-delay:0.52s}.cssload-container .cssload-shaft10{animation-delay:0.58s;-o-animation-delay:0.58s;-ms-animation-delay:0.58s;-webkit-animation-delay:0.58s;-moz-animation-delay:0.58s}.cssload-container{width:92px}.cssload-container >div{height:5px;margin-right:0;animation:cssload-wave 1.15s infinite ease-in-out;-o-animation:cssload-wave 1.15s infinite ease-in-out;-ms-animation:cssload-wave 1.15s infinite ease-in-out;-webkit-animation:cssload-wave 1.15s infinite ease-in-out;-moz-animation:cssload-wave 1.15s infinite ease-in-out}@keyframes cssload-wave{80%{background:rgb(86,215,198);margin-right:5px;transform:translateX"("-10px")"}}@-o-keyframes cssload-wave{80%{background:rgb(86,215,198);margin-right:5px;-o-transform:translateX"("-10px")"}}@-ms-keyframes cssload-wave{80%{background:rgb(86,215,198);margin-right:5px;-ms-transform:translateX"("-10px")"}}@-webkit-keyframes cssload-wave{80%{background:rgb(86,215,198);margin-right:5px;-webkit-transform:translateX"("-10px")"}}@-moz-keyframes cssload-wave{80%{background:rgb(86,215,198);margin-right:5px;-moz-transform:translateX"("-10px")"}}

</style>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>