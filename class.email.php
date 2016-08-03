<?php
if (!class_exists("PHPMailer")) {
	require_once(kPEXDirController."class.phpmailer.php");
}
if (!class_exists("PexMailSES")) {
	require_once(kPEXDirController."MailSES.php");
}

class PexSendEmail{
	private $To;
	private $Subject;
	private $Message;
	private $VideoLink;
	private $UserName;
	function SetTo($var){
		$this->To = $var;
	}
	function SetSubject($var){
		$this->Subject = $var;
	}
	function SetMessage($var){
		$this->Message = $var;
	}
	function SetVideoLink($var){
		$this->VideoLink = $var;
	}
	function SetUserName($var){
		$this->UserName = ucfirst($var);
	}
	function NewVideoEmail($userID, $movieID, $albumID=null, $movieTitle=null, $subject=null, $sender=null, $client=null){
	    if($userID===null || empty($userID))  { return "You need userID"; }
	    if($movieID===null || empty($movieID)) { return "You need movieID"; }
		if(empty($this->VideoLink))            { return "You need set the Video Link first"; }
		if(empty($this->UserName))             { return "You need set the UserName first"; }
		if(empty($this->To))                   { return "You need set The Receiver (To)"; }
		
		if (file_exists(dirname(__FILE__).'/../../PSO/modules/ID/ID.SO.Factory.php') ) {
		    require_once(dirname(__FILE__).'/../../PSO/modules/ID/ID.SO.Factory.php');
		    $idFactory = new ID_SO_Factory(); $isMovieIDValid=false;
		    $checkIDResult = $idFactory->simpleValidateItemS3IDFormat($movieID, $isMovieIDValid);
		    if (!$checkIDResult || $isMovieIDValid!==true) { return "Error with movieID"; }
		}
		
		if (file_exists(dirname(__FILE__).'/../../PSO/modules/DB/DB.SO.Databases.dynamoDB.php')) {                                  
		    require_once (dirname(__FILE__).'/../../PSO/modules/DB/DB.SO.Databases.dynamoDB.php');
		    $db = new DB_SO_Databases(); $returnV=true; $rangeID=null; $keyExists = -1; $itemValue=null; $count=null; $oldCreateDate=-1;
		    $returnV = $returnV && $db->_keyExistsAndGetDynamoDB(kPEXDynamoDBNewVideoEmail, $db->_shiftPostID($movieID), $rangeID, $keyExists, $itemValue, $count, $oldCreateDate);    // $movieID if email was sent before
		    if (!$returnV || $keyExists!==false) { return 'The email for this movie ID may have already been sent | $userID = >'.$userID.'< | $movieID = >'.$movieID.'<'; }
		    $createDate = date('YmdHis');                           // Prepare Creation Date Format in string: YYYYmmDDHHiiss
		    $returnV = $returnV && $db->_insertItemDynamoDB(kPEXDynamoDBNewVideoEmail, $db->_shiftPostID($movieID), $userID, null, null, $albumID, $createDate, null, null, null, null, null, null, null, null, $movieTitle, $client, true);
		    if (!$returnV) { return "Error with DynamoDB"; }
		}
		
		if ($subject!==null) { $this->SetSubject($subject); } else { $this->SetSubject("Video is ready!"); }
		$sender = ($sender!==null)? $sender : 'info@pepblast.com';
		
		if ($movieTitle!==null) {
		    $msg = "<h2>Hello {$this->UserName}, </h2>";
		    $msg .= "<p><strong>$movieTitle</strong></p>";
		    $msg .= "<a href='{$this->VideoLink}'>{$this->VideoLink}</a>";
		    $msg .= "<br /><br /><br /><p> ".kPEXMakeMovieUrlShort."</p>";
		} else {
		    $msg = "<h2>Hello {$this->UserName}, </h2>";
		    $msg .= "<a href='{$this->VideoLink}'>{$this->VideoLink}</a>";
		    $msg .= "<br /><br /><p>".kPEXMakeMovieUrlShort."</p>";
		}
		
		$this->SetMessage($msg);
		
		$messageSender = new PexMailSES();
		$error='';
		$res = $messageSender->send($sender, array($this->To), $this->Subject, true, $this->Message, $sender, $error);
		
		if($res==true) {
			return "Ok";
		} else {
			return "fail: $error";
		}
	}
	function SendBySmtp()
	{
		
		
		$mail = new PHPMailer();
		
		//	Setting the Charset
		$mail->CharSet = "UTF-8";
		
		//	User and Connection settings
		$mail->Username = EmailUser;
		$mail->Password = EmailPass;
		$mail->Host = EmailHost;
		$mail->Port = EmailPort;
		$mail->SMTPAuth = true;
		$mail->SMTPSecure = EmailSMTPSecure;
		$mail->IsSMTP();
		$mail->From = EmailUser;
		
		//	Reply to a custom Email
		$mail->AddReplyTo(EmailReplyTo,EmailReplyToName);

		//	Add Address only one per call of the function AddAddress
		$dests = explode(";", $this->To);
		for ($d=0; $d < count($dests); $d++) {
			$mail->AddAddress($dests[$d]);
		}
		
		
		
		//	the message is in HTML Code
		$mail->IsHTML(true);
		//	Subject
		$mail->Subject = $this->Subject;
		//	Body
		$mail->Body = $this->Message;
		
		$errors = array();
		
		//	Send Email and Check Return, if email not send return the errors otherwise return true
		if (!$mail->Send()) {
			for ($e=0; $e < count($errors); $e++) {
				return "\tErro: ".$errors[$e]."\n<br>\t";
			}
		}
		else {
			return true;
			
		}
	}
}

?>