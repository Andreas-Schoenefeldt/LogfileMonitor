<?php

/* -----------------------------------------------------------------------
 * Class encapsulates mail functions
 * ----------------------------------------------------------------------- */
class Mail {
	
	// sends mail regarding given parameters
	public static function sendMail($recipient, $subject, $messge, $sender) {
		if ($recipient) {
			$headers  = "MIME-Version: 1.0\r\n";
			$headers .= "Content-type: text/html; charset=utf-8\r\n";
			$headers .= "From: " . strip_tags($sender) . "\r\n";
			
			return mail($recipient, $subject, $messge, $headers);
		}
	}
	
	// sends all DW specific mails in given alertMails object
	public static function sendDWAlertMails($analyser, $currentFolder, $alertConfiguration, $currentLayout, $emailConfiguration) {
		
		$alertMails = $analyser->alertMails;
		
		$emailadresses = $emailConfiguration['emailadresses'];
		$senderemailaddress = $emailConfiguration['senderemailaddress'];		
		
		if (!empty($alertMails) && count($emailadresses) > 0) {
			
			$subject = !empty($emailConfiguration['subject']) ? "{$emailConfiguration['subject']} ": "LOG ALERT: ";
			$storagePath = $currentFolder . '/sendalertmails-'.$currentLayout.'-'.str_replace(array('\\','/','.php'), array('-','-',''), $alertConfiguration['configPath']).'.sdb';
			$tmpStoragePath = $currentFolder . '/sendalertmails-'.$currentLayout.'-'.str_replace(array('\\','/','.php'), array('-','-',''), $alertConfiguration['configPath']).'.tmp';
			$mailStorage=array();
			
			// check for already sent mails
			if (file_exists($storagePath)) {
				$mailStorage=file($storagePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
				$timestamp=array_pop($mailStorage);
				// if storage is older than one day delete it and make it possible to send mails again
				if ($timestamp!=date("mdy")) {
					$mailStorage=array();;
				}
			}
			// ensure to delete any tmp mail storage
			if (file_exists($tmpStoragePath)) {
				unlink($tmpStoragePath);
			}
			
			// send mail based on collected alertMails object
			foreach ($alertMails as $errorType => $errorTypeMail) {
				foreach ($errorTypeMail as $threshold => $thresholdMail) {
					$success=true;
					//only send when not already sent before
					if (!in_array($errorType.$threshold, $mailStorage)) {
						d("mail [$errorType]");
						
						$success = Mail::sendMail(	join(',',$emailadresses), 
									"$subject [$errorType] - ".$thresholdMail['subject'], 
									"<html><head></head><body><p>Error Type: $errorType</p><pre style=\"font:inherit;\">".$thresholdMail['message'] . '</pre><p><a href="' . $analyser->getResultFileName() . '" style="font-weight: bold;font-size: 1.2em;display:inline-block; background-color: #84c7f6; text-decoration:none;padding: 4px 8px;border: 1px solid #35688b; border-radius: 3px;">View Online</a></p></body></html>', 
									$senderemailaddress
								);
					} 
					// fill mail storage
					if ($success) {
						file_put_contents($tmpStoragePath,$errorType.$threshold."\n", FILE_APPEND);
					}
				}
			}
			
			if (file_exists($tmpStoragePath)) {
				// rename tmp file when it exists
				file_put_contents($tmpStoragePath,date("mdy")."\n", FILE_APPEND);
				rename($tmpStoragePath, $storagePath);
			} else if (file_exists($storagePath)) {
				// otherwise delete mail storage when nothing new was sent
				unlink($storagePath);
			}
		}
	
	}

}

?>

