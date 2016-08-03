<?php
	require_once(kPEXDirAWS.'aws-autoloader.php');
				
	use Aws\Common\Credentials\Credentials;
	
	class PexMailSES {
		
		var $client;
		
		function __construct() {
			

			$credentials = new Credentials(kPEXAWSId, kPEXAWSSecret);
			$aws = Aws\Common\Aws::factory(dirname(__FILE__).'/aws_config_v2.php');
			// Get the client from the builder by namespace
			$this->client = $aws->get('Ses');
		}
		
		function send($source, $destinations, $subject, $isHtml=false, $message, $return, &$error) {
			$msg = array();
			$msg['Source'] = $source;
			foreach ($destinations as $d) {
				$msg['Destination']['ToAddresses'][] = $d;
			}
			$msg['Message']['Subject']['Data'] = $subject;
			$msg['Message']['Subject']['Charset'] = "UTF-8";
			if($isHtml) {
				$msg['Message']['Body']['Html']['Data'] = $message;
				$msg['Message']['Body']['Html']['Charset'] = "UTF-8";
			} else {
				$msg['Message']['Body']['Text']['Data'] = $message;
				$msg['Message']['Body']['Text']['Charset'] = "UTF-8";
			}
			$msg['ReturnPath'] = $return;
			$error = '';
			try {
				$this->client->sendEmail($msg);
				$result = true;
			} catch  (Exception $e) {
				$error = $e->getMessage();
				$result = false;
			}
			return $result;
		}
	}



?>