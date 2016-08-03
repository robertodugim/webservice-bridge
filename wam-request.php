<?php
	require_once(dirname(__FILE__)."/config.php");
	require_once(kPEXDirController."class.wam.php");
	$PEX_WAM = new PEX_WAM($_POST,$_GET, false);    
	// $PEX_WAM = new PEX_WAM($_POST,$_GET);               // un-comment this to enable checkhash (using cookie 'PexUserHash')
	if(array_key_exists('jsonp', $_POST)){
		if($_POST['jsonp'] == '1'){
			$return = $PEX_WAM->Request();
			parse_str($return,$return);
			echo $_GET['jsonpCall'] . "(".json_encode($return).")";
		}elseif(kPEXInPZServer === false){
			echo $PEX_WAM->Request();
		} else {
			$PEX_WAM->Request();                               // should not do echo for wam require
		}
	}elseif(kPEXInPZServer === false){
		echo $PEX_WAM->Request();
	} else {
	    $PEX_WAM->Request();                               // should not do echo for wam require
	}
?>