<?php
	require_once(kPEXDirController."class.email.php");
	class PEX_WAM extends PexSendEmail{
		private $pexData = array();
		private $pexGet = array();
		private $pexFiles = array();
		private $pexUrl;
		private $pexReturn;
		private $pexSendByCurl;
		function __construct($pexData,$pexGet,$pexCheckHash = true){
			//Check User Hash in User Cookie
			if($pexCheckHash === true){
				$this->CheckUserHash();
			}
			//parse Post to pexData 
			$this->pexData = $pexData;
			
			$PEXWAMMethodByCurl = unserialize (kPEXWAMMethodByCurl);
			if(in_array($this->pexData['method'],$PEXWAMMethodByCurl)){
				$this->pexSendByCurl = true;
			}
			$this->pexGet = http_build_query($pexGet);
			
			$PexWAMMethod = unserialize (kPEXWAMMethod);
			if(!in_array($this->pexData['method'],$PexWAMMethod)){
				echo "result=error&error=MethodNotExists";
				exit;
			}
			
			//Check if the method exists
			if(method_exists($this,$this->pexData['method'])){
				$pexFunc = $this->pexData['method'];
				$this->$pexFunc();
			}
			$this->UrlConstruct();
		}
		function Request(){
			$this->CreateLog();
			if(kPEXInPZServer === true && $this->pexSendByCurl !== true){
				$this->require_wam();
			}else{
				$this->post_curl($this->pexData,$this->pexFiles);
			}
			$this->CreateLog("Return");
			$PexRealReturn = $this->pexReturn;
			$this->HandleReturn();
			if(kPEXInPZServer === true && $this->pexSendByCurl === true){
				echo $PexRealReturn;
			}
			return $PexRealReturn;
		}
		
		private function CheckUserHash(){
			$pexHashDate = md5(date('dmY'));
			$pexHashString = md5(kPEXSecretWord);
			$pexHashCookieDate = substr($_COOKIE['PexUserHash'],4,strlen($pexHashDate));
			$pexHashCookieString = substr($_COOKIE['PexUserHash'],8+strlen($pexHashDate),strlen($pexHashString));
			if($pexHashDate != $pexHashCookieDate || $pexHashString != $pexHashCookieString){
				echo "result=error&error=HashNotValid";
				exit();
			}
		}
		
		private function HandleReturn(){
			if(kPEXInPZServer !== true || $this->pexSendByCurl === true){
				parse_str($this->pexReturn,$this->pexReturn);
			}
			$this->SetUrlProtocol();
			$this->SetCookies();
			if(method_exists($this,"Return".$this->pexData['method'])){
				$pexFunc = "Return".$this->pexData['method'];
				$this->$pexFunc();
			}
		}
		private function UrlConstruct(){
			if(!empty($this->pexData['scripts_location'])){
				$this->pexUrl = $this->pexData['scripts_location']."/".$this->pexData['method'].".php?".$this->pexGet;
			}else{
				$this->pexUrl = kPEXWAMurl.$this->pexData['method'].".php?".$this->pexGet;
			}
		}
		private function require_wam(){
		    
		    $username=null; $authorized_login_id=null; $scripts_location=null; $albums_location=null; $outFields=array();
		    if ($this->pexData['method']==='WAMLogin' || $this->pexData['method']==='WAMLogout') {
		        require_once(kPEXDirWAMCentralDir.$this->pexData['method'].'.php');
		    } else {
		        require_once(kPEXDirWAM.$this->pexData['method'].'.php');
		    }
			if($result === kPEXLOGIN_SUCCESS){
				$this->pexReturn['result'] = $result;
				if(isset($username)){
					$this->pexReturn['username'] = $username;
				}
				if(isset($authorized_login_id)){
					$this->pexReturn['authorized_login_id'] = $authorized_login_id;
				}
				if(isset($scripts_location)){
					$this->pexReturn['scripts_location'] = $scripts_location;
				}
				if(isset($scripts_location)){
					$this->pexReturn['albums_location'] = $albums_location;
				}
				if(isset($outFields)){
					if(array_key_exists('movie_url', $outFields)){
						if(!empty($outFields['movie_url'])){
							if(array_key_exists('movie_created', $outFields)){
								$this->pexReturn['movie_created'] = $outFields['movie_created'];
							}
							if(array_key_exists('movie_url', $outFields)){
								$this->pexReturn['movie_url'] = $outFields['movie_url'];
							}
						}
					}
				}
			}
		}
		private function post_curl($pexPost=array(),$pexFiles=array()){
			$pexCh = curl_init($this->pexUrl);
			curl_setopt ($pexCh, CURLOPT_POST, 1);
			$this->curl_custom_postfields($pexCh,$pexPost,$pexFiles);
			//curl_setopt ($pexCh, CURLOPT_POSTFIELDS, $pexPost);
			curl_setopt ($pexCh, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($pexCh, CURLOPT_TIMEOUT, 86400);
			curl_setopt($pexCh, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($pexCh,CURLOPT_HEADER,false);
			
			$this->pexReturn = curl_exec ($pexCh);
		}
		function curl_custom_postfields($pexCh, array $pexAssoc = array(), array $pexFiles = array()) {
			
			// invalid characters for "name" and "filename"
			static $pexDisallow = array("\0", "\"", "\r", "\n");
			
			// build normal parameters
			foreach ($pexAssoc as $pexK => $pexV) {
				$pexK = str_replace($pexDisallow, "_", $pexK);
				$pexBody[] = implode("\r\n", array(
					"Content-Disposition: form-data; name=\"{$pexK}\"",
					"",
					filter_var($pexV), 
				));
			}
			
			// build file parameters
			foreach ($pexFiles as $pexK => $pexV) {
				
				$pexBody[] = implode("\r\n", array(
					"Content-Disposition: form-data; name=\"{$pexK}\"; filename=\"{$pexV['filename']}\"",
					"Content-Type: application/octet-stream",
					"",
					$pexV['content'], 
				));
			}
			
			// generate safe boundary 
			do {
				$pexBoundary = "---------------------" . md5(mt_rand() . microtime());
			} while (preg_grep("/{$pexBoundary}/", $pexBody));
			
			// add boundary for each parameters
			array_walk($pexBody, function (&$pexPart) use ($pexBoundary) {
				$pexPart = "--{$pexBoundary}\r\n{$pexPart}";
			});
			
			// add final boundary
			$pexBody[] = "--{$pexBoundary}--";
			$pexBody[] = "";
			
			// set options
			return @curl_setopt_array($pexCh, array(
				CURLOPT_POSTFIELDS => implode("\r\n", $pexBody),
				CURLOPT_HTTPHEADER => array(
					"Expect: 100-continue",
					"Content-Type: multipart/form-data; boundary={$pexBoundary}", // change Content-Type
				),
			));
		}
		private function SetUrlProtocol(){
			if(isset($this->pexReturn['scripts_location']) && !empty($this->pexReturn['scripts_location'])){
				$this->pexReturn['scripts_location'] = str_ireplace('http:', kPEXUrlProtocol.":" ,$this->pexReturn['scripts_location']);
			}
		}
		private function SetCookies(){
			if(empty($this->pexReturn['username']) && !empty($this->pexReturn['username_cc'])){
				$this->pexReturn['username'] = $this->pexReturn['username_cc'];
				$this->pexReturn['valid_user'] = $this->pexReturn['username'];
			}
			if(empty($this->pexReturn['auth_session_id']) && !empty($this->pexReturn['authorized_login_id'])){
				$this->pexReturn['auth_session_id'] = $this->pexReturn['authorized_login_id'];
			}
			$pexCookiesVars = unserialize (kPEXWAMCookieVariables);
			foreach($pexCookiesVars as $pexVars){
				if(isset($this->pexReturn[$pexVars]) && !empty($this->pexReturn[$pexVars])){
					setcookie($pexVars,$this->pexReturn[$pexVars],time()+31536000);
				}
			}
		}
		function WAMLogout(){
			unset($_COOKIE['auth_session_id']);
			unset($_COOKIE['username']);
			unset($_COOKIE['albums_location']);
			unset($_COOKIE['scripts_location']);
			setcookie('auth_session_id', null, time()-3600);
			setcookie('username', null, time()-3600);
			setcookie('albums_location', null, time()-3600);
			setcookie('scripts_location', null, time()-3600);
		}
		function WAMCreateAlbum(){
			$this->pexData['album_date'] = date('Y/m/d');
			$this->pexData['album_year'] = date('Y');
			$_POST['album_date'] = $this->pexData['album_date'];
			$_POST['album_year'] = $this->pexData['album_year'];
		}
		function WAMCreatePhoto(){
			unset($this->pexData['Filedata']);			
		}
		function WAMUploadPhoto(){
			$_FILES['Filedata']['name'] = $this->pexData['Filename'];
			
			if(kPEXInPZServer === false || $this->pexSendByCurl === true){
				$this->pexFiles = array();
				$this->pexFiles['Filedata']['content'] = file_get_contents($_FILES['Filedata']['tmp_name']);
				$this->pexFiles['Filedata']['filename'] = $this->pexData['Filename'];
			}
		}
		function WAMSetUserProfile(){
			$_FILES['Filedata1']['name'] = $this->pexData['Filename'];
			
			if(kPEXInPZServer === false || $this->pexSendByCurl === true){
				$this->pexFiles = array();
				$this->pexFiles['Filedata1']['content'] = file_get_contents($_FILES['Filedata1']['tmp_name']);
				$this->pexFiles['Filedata1']['filename'] = $this->pexData['Filename'];
			}
		}
		function ReturnWAMMovieStatus(){
			$this->pexReturn['sendEmail'] = $this->pexData['uemail'];
			$extraArray=array();
			$extraArray['PEXDATA']='pexData >'; foreach ($this->pexData as $key => $value) {$extraArray['pexData_'.$key]=$value; }
			$extraArray['PEXRETURN']='pexReturn >'; foreach ($this->pexReturn as $key => $value) {$extraArray['pexReturn_'.$key]=$value; }
			$extraArray['kPEXSendEmail']=kPEXSendEmail;
			$this->CreateLog("Start Send Email", $extraArray);
			if($this->pexReturn['movie_created'] == 1 && !empty($this->pexData['uemail']) && kPEXSendEmail == '1'){
				$this->SetTo($this->pexData['uemail']);
				$this->SetVideoLink($this->pexReturn['movie_url']);
				$this->SetUserName($this->pexData['username']); 
				
				$movieTitle=null;
				if (array_key_exists('album_name', $this->pexData) && $this->pexData['album_name']!==null && $this->pexData['album_name']!==''  && $this->pexData['album_name']!==' ') {
				    $albumName = trim($this->pexData['album_name']);
				    $movieTitle = (mb_strlen($albumName) > 36)? mb_substr($albumName,0,36).'...' : $albumName;
				    $subject = 'Video is ready! > '.$movieTitle;
				} else {
				    $subject = 'Video is ready!';
				}
				$userID =   (array_key_exists('uid', $_GET) && $_GET['uid']!==null)?        substr($_GET['uid'], 0, 64)     : null;
				$movieID =  (array_key_exists('mid', $_GET) && $_GET['mid']!==null)?        substr($_GET['mid'], 0, 64)     : null;
				$albumID =  (array_key_exists('aid', $_GET) && $_GET['aid']!==null)?        substr($_GET['aid'], 0, 64)     : null;
				$client =   (array_key_exists('client', $_GET) && $_GET['client']!==null)?  substr($_GET['client'], 0, 64)  : null;

				$this->pexReturn['sendEmail'] = $this->NewVideoEmail($userID, $movieID, $albumID, $movieTitle, $subject, null, $client);
			}
			$this->CreateLog("End Send Email return {$this->pexReturn['sendEmail']} ");
		}
		function CreateLog($pexAction = "Send", $extraArray=null){
			if(kPEXDebug === true){
				$pex_log_get = "";
				foreach($_GET as $pexK => $pexV){
					if($pexK != 'method'){
						$pex_log_get .= "  $pexK => $pexV,  ";
					}
				}
				if ($extraArray!==null) {
				    foreach($extraArray as $pexK => $pexV){
				        $pex_log_get .= "  $pexK => $pexV,  ";
				    }
				}
				$pexLog = date("m-d-Y H:i:s") . " {$this->pexData['method']} $pexAction $pex_log_get \n";
				file_put_contents(kPEXDirLog.'/pexLog_'.date("m_d_Y").'.txt', $pexLog, FILE_APPEND);
			}
		}
	}
?>