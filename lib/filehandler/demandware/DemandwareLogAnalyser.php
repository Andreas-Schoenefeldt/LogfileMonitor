<?php

require_once(str_replace('//','/',dirname(__FILE__).'/') .'../FileAnalyser.php');

define("LOGFILE_ERROR",     "Logfile Error");

class DemandwareLogAnalyser extends FileAnalyser {
	
	var $cartridgePath = array(); // the cartridgepath in order of inclusion
	
	var $EXCEPTION_HANDLING_DEV_NAMESPACE = 'errorParsing';
	var $NAMESPACE_ERROR = 'error';
	var $NAMESPACE_CUSTOMERROR = 'customerror';
	var $NAMESPACE_CUSTOMWARN = 'customwarn';
	var $NAMESPACE_QUOTA = 'quota';
	
	var $errorExceptions = array();
	var $customErrorExceptions = array();
	
	function __construct($file, $layout, $settings, $alertConfiguration)  {
		
		parent::__construct($file, 'demandware', $layout, $settings, $alertConfiguration);
		
		// adding site setting
		$this->settings['site'] = $settings['site'];
		
		// error handling
		$this->errorExceptions = $this->mergeExceptionHandling(array(
			array(
				  'start' => 'SEOParsingException Unable to parse SEO url - no match found - {'
				, 'type' => 'SEO URL mismatch'
				, 'weight'	=> 1
				, 'solve' => function($definition, $alyStatus){
					
					$urlPlusReferer = substr($alyStatus['entry'], strlen($definition['start']));
					$parts = explode('} - Referer: ', $urlPlusReferer);
					
					if(count($parts) == 1)  $parts[0] = substr($parts[0], 0 , -2);
					
					$alyStatus['data']['urls'][$parts[0]] = true;
					if(count($parts) > 1) $alyStatus['data']['referers'][$parts[1]] = true;
					$alyStatus['entry'] = 'Unable to parse SEO url - no match found';
					return $alyStatus;
				}
			),
			
			array(
				  'start' => 'Invalid order status change from COMPLETED to OPEN for order '
				, 'type' => 'Invalid order status change'
				, 'weight'	=> 0
				, 'solve' => function($definition, $alyStatus){
					$alyStatus['data']['orders']['#' . substr($alyStatus['entry'], strlen($definition['start']))] = true;
					$alyStatus['entry'] = 'Invalid order status change from COMPLETED to OPEN';
					return $alyStatus;
				}
			),
			
			array(
				  'start' => 'Wrapped java.lang.IllegalArgumentException: Virtual path ['
				, 'type' => 'Virtual path invalid'
				, 'weight'	=> 9
				, 'solve' => function($definition, $alyStatus){
					preg_match('/Wrapped java\.lang\.IllegalArgumentException: Virtual path \[(?P<file>.*?)\] (?P<explanation>.*?) \(\[Template:(?P<template>.*?)\)$/', $alyStatus['entry'], $matches);
					// $alyStatus['data']['file'][$matches['file']] = true; // taking out the file for now, because it more spam then usefull
					$alyStatus['entry'] = 'Virtual path ' . $matches['explanation'];
					return $alyStatus;
				}
			),
		
			array(
				  'start' => 'An infinite loop was detected within the Redirect URL configuration. Caused by request URL'
				, 'type' => 'Redirect URL configuration'
				, 'weight'	=> 9
				, 'solve' => function($definition, $alyStatus){
					$alyStatus['data']['url'][substr($alyStatus['entry'], strlen($definition['start']))] = true;
					$alyStatus['entry'] = 'An infinite loop was detected within the Redirect URL configuration.';
					return $alyStatus;
				}
			),
			
			
			array(
				  'start' => 'Unexpected error: JDBC/SQL error: '
				, 'type' => 'ORMSQLException'
				, 'weight'	=> 9
			),
			
			array(
				  'start' => 'No start node'
				, 'type' => 'No start node'
				, 'weight'	=> 0
				, 'solve' => function($definition, $alyStatus){
					$alyStatus['data']['pipelines'][substr($alyStatus['entry'], 38, -7)] = true; // getting the pipeline
					$alyStatus['entry'] = 'No start node specified for pipeline call.';
					return $alyStatus;
				}
			),
			
			array(
				  'start' => 'Customer password could not be updated.'
				, 'type' => 'Invalid Customer password update'
				, 'weight'	=> 0
				, 'solve' => function($definition, $alyStatus){
					$alyStatus['data']['passwords'][substr($alyStatus['entry'], 80, -1)] = true;
					$alyStatus['entry'] = substr($alyStatus['entry'], 0, 78);
					return $alyStatus;
				}
			),
			
			array(
				  'start' => 'Maximum number of sku(s) exceeds limit'
				, 'type' => 'Maximum limit exceed'
				, 'weight'	=> 0
				, 'solve' => function($definition, $alyStatus){
					$alyStatus['data']['limits'][substr($alyStatus['entry'], 39)] = true;
					$alyStatus['entry'] = 'Maximum number of sku(s) exceeds limit.';
					return $alyStatus;
				}
			),
			
			// CORE debug : <RedirectUrlMgrImpl> getUrlByUri, (referrer url=http://www.moteur-shopping.com/redirect/4021420158832_r3321745/sg/4/s_id/8?origin=xxx&s_evar6=cebqhvgf-zbfnvdhr&s_evar7=znedhrf:%2Sznedhr%2Suhtb-obff_o2589%2Subzzr_p6%2Spunhffher-ubzzr_p176%2Sonfxrg-graqnapr-ubzzr_p181)java.lang.IllegalArgumentException: URLDecoder: Illegal hex characters in escape (%) pattern - For input string: "2S""
			array(
				  'start' => 'CORE debug : <RedirectUrlMgrImpl> getUrlByUri, '
				, 'type' => 'URLDecoder'
				, 'weight'	=> 0
				, 'solve' => function($definition, $alyStatus){
					
					preg_match('/^.*?getUrlByUri, \(referrer url=(?P<referrer>.*?)\)java.lang.IllegalArgumentException: URLDecoder: Illegal hex characters in escape \(%\) pattern - For input string: "(?P<string>.*?)".*?$/', $alyStatus['entry'], $matches);
					if(! count($matches)) {
						preg_match('/^.*?getUrlByUri, \(referrer url=(?P<referrer>.*?)\)java.lang.IllegalArgumentException: URLDecoder: (?P<exception>.*?)$/', $alyStatus['entry'], $matches);
					}
					
					if( array_key_exists('string',  $matches)) $alyStatus['data']['errorString'][$matches['string']] = true;
					
					if (array_key_exists('referrer',  $matches) && startsWith($matches['referrer'], 'referrer url=')) {
						$alyStatus['data']['referrer'][substr($matches['referrer'], 13)] = true;
						$alyStatus['entry'] = 'Illegal hex characters in escape (%) pattern from external URL.';
					} else if (array_key_exists('referrer',  $matches)){
						$alyStatus['data']['URI'][substr($matches['referrer'], 4)] = true;
						$alyStatus['entry'] = 'Illegal hex characters in escape (%) pattern from URI.';
					}
					
					if( array_key_exists('exception',  $matches)) $alyStatus['entry'] = $matches['exception'];
	
					return $alyStatus;
				}
			),
			
			
			array(
				  'start' => 'CORE debug     : <RedirectUrlMgrImpl> getUrlByUri, '
				, 'type' => 'URLDecoder'
				, 'weight'	=> 0
				, 'solve' => function($definition, $alyStatus){
					
					preg_match('/^.*?getUrlByUri, \((?P<referrer>.*?)\)java.lang.IllegalArgumentException: URLDecoder: Illegal hex characters in escape \(%\) pattern - For input string: "(?P<string>.*?)".*?$/', $alyStatus['entry'], $matches);
					if(! count($matches)) {
						preg_match('/^.*?getUrlByUri, \((?P<referrer>.*?)\)java.lang.IllegalArgumentException: URLDecoder: (?P<exception>.*?)$/', $alyStatus['entry'], $matches);
					}
					
					if( array_key_exists('string',  $matches)) $alyStatus['data']['errorString'][$matches['string']] = true;
					
					if (array_key_exists('referrer',  $matches) && startsWith($matches['referrer'], 'referrer url=')) {
						$alyStatus['data']['referrer'][substr($matches['referrer'], 13)] = true;
						$alyStatus['entry'] = 'Illegal hex characters in escape (%) pattern from external URL.';
					} else if (array_key_exists('referrer',  $matches)){
						$alyStatus['data']['URI'][substr($matches['referrer'], 4)] = true;
						$alyStatus['entry'] = 'Illegal hex characters in escape (%) pattern from URI.';
					}
					
					if( array_key_exists('exception',  $matches)) $alyStatus['entry'] = $matches['exception'];
	
					return $alyStatus;
				}
			),
			
			array(
				  'start' => 'Java constructor for'
				, 'type' => 'Java constructor'
				, 'weight'	=> 3
			),
						
			array(
				  'start' => 'The basket is null'
				, 'type' => 'Missing Basket'
				, 'weight' => 1
			),
			
			array(
				  'start' => 'Invalid order status change from COMPLETED to CANCELLED for order '
				, 'type' => 'Invalid order status change'
				, 'weight'	=> 0
				, 'solve' => function($definition, $alyStatus){
					$alyStatus['data']['orders']['#' . substr($alyStatus['entry'], strlen($definition['start']))] = true;
					$alyStatus['entry'] = 'Invalid order status change from COMPLETED to CANCELLED';
					return $alyStatus;
				}
			),
		), $this->NAMESPACE_ERROR);
		
		
		// custom error handling and custom warn handling
		$this->customErrorExceptions = $this->mergeExceptionHandling(array(
			array(
				  'start' => 'Error executing script'
				, 'type' => 'Error executing script'
				, 'weight'	=> 1
				, 'solve' => function($definition, $alyStatus){
					$alyStatus['entry'] = substr($alyStatus['entry'], 23);
					return $alyStatus;
				}
			),
			
			array(
				  'start' => 'Timeout while executing script'
				, 'type' => 'Script execution timeout'
				, 'weight' => 1
				, 'solve' => function($definition, $alyStatus){
					$alyStatus['entry'] = substr($alyStatus['entry'], 23);
					return $alyStatus;
				}
			),
			
			array(
				  'start' => 'Unknown category ID'
				, 'type' => 'Unknown category ID'
				, 'weight' => 1
				, 'solve' => function($definition, $alyStatus){
					$entry = explode('Unknown category ID ', $alyStatus['entry'], 2);
					$entry = explode(' for implicit search filters given.', $entry[1], 2);
					$alyStatus['data']['Category IDs'][trim($entry[0])] = true;
					$alyStatus['entry'] = 'Unknown category ID for implicit search filters given.';
					return $alyStatus;
				}
			),
			
			array(
				  'start' => 'Timeout while executing script'
				, 'type' => 'Script execution timeout'
				, 'weight' => 1
			)
			
		), $this->NAMESPACE_CUSTOMERROR);
		$this->customErrorExceptions = $this->mergeExceptionHandling($this->customErrorExceptions, $this->NAMESPACE_CUSTOMWARN); // both namespaces are handled by one function, so we also combine both error files
		
		// quota handling (was not necessary so far)
	}
	
	function mergeExceptionHandling($target, $namespace){
		if (array_key_exists($this->EXCEPTION_HANDLING_DEV_NAMESPACE, $this->alertConfiguration)){
			if (array_key_exists($namespace, $this->alertConfiguration[$this->EXCEPTION_HANDLING_DEV_NAMESPACE])){
				return array_merge($target, $this->alertConfiguration[$this->EXCEPTION_HANDLING_DEV_NAMESPACE][$namespace]);
			}
		}
		
		return $target;
	}
	
	function analyse($fileIdent){
		
		// init the analysation status
		$this->initAlyStatus($fileIdent, 0);
		
		// check file thresholds for whole logfile
		$alertMail = $this->checkAlert(0, '');
		if (!empty($alertMail)) {
			$this->alertMails[$this->layout." logfile threshold exceeded"] = $alertMail;
		}
		
		$firstError = true;
		
		while ($line = $this->getNextLineOfCurrentFile()) {	
			if($this->startsWithTimestamp($line)) {
				if (! $firstError) {
					$this->addError($fileIdent, $line);
				} else {
					$this->initAlyStatus($fileIdent, $this->alyStatus['lineNumber'], $line);
					$firstError = false;
				}
			} else {
				$this->alyStatus['stacktrace'] .= $line . "\n";
			}
			
			try {
				$line = $this->analyseLine($line);
			} catch(Exception $e) {
				d('EXCEPTION PARSING LINE:' . $line);
				d($e->getMessage());
			}
			
			
		}
		$this->addError($fileIdent, '');
	}
	
	function addError($fileIdent, $line){
	
		// additional dw specific checks against the site
		if (! $this->settings['site'] || $this->settings['site'] == 'ALL' ||  array_key_exists($this->settings['site'], $this->alyStatus['data']['sites'])) {
			$errorCount = $this->addEntry($this->alyStatus['timestamp'], $this->alyStatus['errorType'], $this->alyStatus['entry'], $this->alyStatus['entryNumber'], $this->alyStatus['fileIdent'], $this->alyStatus['data'], $this->alyStatus['stacktrace']);
			$alertMail = $this->checkAlert($errorCount, $this->alyStatus['stacktrace']);
			
			if (!empty($alertMail)) {
				$this->alertMails[$this->alyStatus['entry']] = $alertMail;
			}
		}
		
		$this->initAlyStatus($fileIdent, $this->alyStatus['lineNumber'], $line);
	}
	
	// analyse a single line
	function analyseLine($line) {
		switch($this->layout) {
			default:
				throw new Exception('Don\'t know how to handel ' . $this->layout . ' files.');
				break;
			case 'error':
				$line = $this->analyse_error_line($line);
				break;
			case 'customwarn':
			case 'customerror':
				$line = $this->analyse_customerror_line($line);
				break;
			case 'quota':
				$line = $this->analyse_quota_line($line);
				break;
		}
		return $line;
	}
	
	// init the ally status
	function initAlyStatus($fileIdent, $currentLineNumber, $line = false){
		// get the basic status from the abstract parent class
		parent::initAlyStatus($fileIdent, $currentLineNumber);
		$this->alyStatus['errorType'] = '-';
		$this->alyStatus['enter'] = true;
		$this->alyStatus['add'] = false;
		
		if ($line) $this->alyStatus['stacktrace'] = $line . "\n";
	}
	
	function startsWithTimestamp($line) {
		return substr($line, 0, 1) == '[' && substr($line, 25, 4) == 'GMT]';
	}
	
	function analyse_quota_line($line) {
		if ($this->alyStatus['enter']) { // every line is a error
			$this->alyStatus['enter'] = false;
			$this->alyStatus['entryNumber'] = $this->alyStatus['lineNumber'];
			$this->alyStatus['data'] = array('sites' => array(), 'dates' => array(), 'GMT timestamps' => array(), 'max actual' => array(), 'pipeline' => array());
			$this->alyStatus['add'] = true;
			
			if ($this->startsWithTimestamp($line)) {
				$errorLineLayout = 'extended';
				$parts = explode(']', substr($line, 29), 2);
				$this->alyStatus['data']['dates'][substr($line, 1, 10)] = true;
				$this->alyStatus['timestamp'] = strtotime(substr($line, 1, 27));
				$this->alyStatus['data']['GMT timestamps'][substr($line, 11, 6)] = true;
			} else {
				$errorLineLayout = 'core_extract';
				$parts = explode(':', $line, 2);
			}
			
			if (count($parts) > 1) {
				
				$this->alyStatus['entry'] = trim($parts[1]);
				
				$messageParts = explode('|', trim(substr($parts[0], 2))); // 0 => basic description, 1 => timestamp?, 2 => Site, 3 => Pipeline, 4 => another description? 5 => Cryptic stuff
				
				$description = explode(':', $this->alyStatus['entry'], 2);
				
				if (count($description) > 1) {
					$errorType = explode(' ', trim($description[0]), 2); // remove the quota or what else
					
					if (count($errorType) > 1) {
						$this->alyStatus['errorType'] = trim($errorType[1]);
					} else {
						$errorType = explode(':', trim($description[1]), 2);
						$this->alyStatus['errorType'] = trim($errorType[0]);
						$description[1] = $errorType[1];
					}
				} else {
					d($this->alyStatus['entry']);
				}
				
				// d($this->alyStatus['errorType']);
				
				switch($this->alyStatus['errorType']){
					default:
						
						preg_match('/^(.*)(\(.*?,.*?,.*?\))$/', $this->alyStatus['errorType'], $matchesHead);
						if (count($matchesHead) > 2) {
							$this->alyStatus['errorType'] = $matchesHead[1];
							$this->alyStatus['entry'] = $matchesHead[1] . ' ' . $matchesHead[2];
						}
						
						$message = $description[1];
						
						preg_match('/(, max actual was [0-9]*?),/', $message, $matches);
						
						if (count($matches) > 1) {
							$message = str_replace($matches[1], '', $message);
							$maxExceeds = explode(' ', $matches[1]);
							$this->alyStatus['data']['max actual']['#' . $maxExceeds[count($maxExceeds) - 1]] = true;
						} 
					
						// $this->alyStatus['entry'] = $this->alyStatus['errorType'] . ': ' . $message;
						if ($errorLineLayout == 'extended' && count($messageParts) > 2) {
							$this->alyStatus['data']['sites'][$this->extractSiteID(trim($messageParts[2]))] = true;
						}
						
						break;
					case 'api.dw.catalog.Category.getOnlineProducts()@SF (internal, limit 0)':
						$this->alyStatus['entry'] = "getOnlineProducts Quota";
						break;
					case 'api.dw.catalog.Category.getOnlineSubCategories()@SF (internal, limit 0)':
						$this->alyStatus['entry'] = "getOnlineSubCategories Quota";
						break;
					case 'api.queryObjects@JOB (internal, limit 0)':
						$this->alyStatus['entry'] = "queryObjects Quota";
						break;
				}
				
			} else {
				d($this->alyStatus);
				d($parts);
				$this->displayError($line);
			}
			
			
		}
	}
	
	function analyse_customerror_line($line) {
		
		if ($this->alyStatus['enter']) { // every line is a error
			$this->alyStatus['enter'] = false;
			$this->alyStatus['entryNumber'] = $this->alyStatus['lineNumber'];
			$this->alyStatus['data'] = array('sites' => array(), 'order numbers' => array(), 'dates' => array(), 'GMT timestamps' => array());
			$this->alyStatus['add'] = true;
			
			$parseSecondLine = false;
			
			if ($this->startsWithTimestamp($line)) {
				$errorLineLayout = 'extended';
				$parts = explode('== custom', $line, 2);
				
				$parts = (count($parts) > 1) ? $parts : explode(' custom  ', $line); // this is a message comming form Logger.error
				$this->alyStatus['timestamp'] = strtotime(substr($line, 1, 27));
				$this->alyStatus['data']['dates'][substr($line, 1, 10)] = true;
				$this->alyStatus['data']['GMT timestamps'][substr($line, 11, 6)] = true; // We only need a granularity by minute
				
				$techData = explode('|', $parts[0]);
				
				switch(count($techData)) {
					default:
						d('unknown techdata format');
						d($techData);
						break;
					case 4:
					case 6:
						$this->alyStatus['data']['pipelines'][$techData[3]] = true;
						break;
				}
				
			} else {
				$errorLineLayout = 'core_extract';
				$parts = explode(':', $line, 2);
			}
			
			if (count($parts) > 1) {
				
				$this->alyStatus['entry'] = trim($parts[1]);
				$messageParts = explode('|', trim(substr($parts[0], 29))); // 0 => basic description, 1 => timestamp?, 2 => Site, 3 => Pipeline, 4 => another description? 5 => Cryptic stuff
				
				$this->extractMeaningfullCustomData();
				
				switch($this->alyStatus['errorType']){
					default:
						break;
					case 'soapNews.ds':
					case 'sopaVideos.ds':
						
						$params = explode('; Url: ', $this->alyStatus['entry'], 2);
						
						if (count($params) > 1) {
							$this->alyStatus['data']['Urls'][trim($params[1])] = true;
							$this->alyStatus['entry'] = $params[0];
						}
						
						$params = explode(', SearchPhrase:', $this->alyStatus['entry'], 2);
						if (count($params) > 1) {
							$this->alyStatus['data']['SearchPhrases'][trim($params[1])] = true;
							$this->alyStatus['entry'] = $params[0];
						}
						
						
						break;
					case 'COPlaceOrder-Start':
					case 'COPlaceOrder-HandleAsyncPaymentEntry':
						
						$params = explode(', OrderNo: ', $this->alyStatus['entry'], 2);
						if (count($params) > 1) {
							$this->alyStatus['data']['Order Numbers']['#' . trim($params[1])] = true;
							$this->alyStatus['entry'] = $params[0];
						}
						
						break;
					case 'Ogone-Declined':
						
						$params = explode('CustomerNo: ', $this->alyStatus['entry'], 2);
						if (count($params) > 1) {
							$this->alyStatus['data']['Customer Numbers']['#' . trim($params[1])] = true;
							$this->alyStatus['entry'] = $params[0];
						}
						
						break;
				}
				
				if ($errorLineLayout == 'extended') $this->alyStatus['data']['sites'][$this->extractSiteID(trim($messageParts[2]))] = true;
				
				$errorsWithAdditionalLineToParse = array('Error executing script', 'Script execution timeout', 'AbstractBaseService.ds');
				
				if (in_array($this->alyStatus['errorType'], $errorsWithAdditionalLineToParse) || $parseSecondLine) {
					$newLine = $this->getNextLineOfCurrentFile($this->alyStatus);
					$this->alyStatus['stacktrace'] .= $newLine . "\n";
					
					// get aditional information from the next line
					switch ($this->alyStatus['errorType']) {
						case 'Error executing script':
							$this->alyStatus['entry'] .= ' ' . $newLine;
							break;
					}
				}
				
			} else {
				d($parts);
				$this->displayError($line);
			}
			
			
		}
	}

	// parsinfg of the error logs
	function analyse_error_line($line){
		if ($this->alyStatus['enter']) {  // && substr($line, 0, 1) == '[' && substr($line, 25, 4) == 'GMT]' [2012-05-22 00:11:56.785 GMT]
			// initial error definition
			$this->alyStatus['enter'] = false;
			$this->alyStatus['entryNumber'] = $this->alyStatus['lineNumber'];
			$this->alyStatus['data'] = array('sites' => array(), 'customers' => array(), 'dates' => array(), 'GMT timestamps' => array(), 'pipelines' => array(), 'urls' => array());
			
			$isExtended = $this->startsWithTimestamp($line);
            
			if ($isExtended) {
				$this->alyStatus['timestamp'] = strtotime(substr($line, 1, 27));
				$this->alyStatus['data']['dates'][substr($line, 1, 10)] = true;
				$this->alyStatus['data']['GMT timestamps'][substr($line, 11, 6)] = true;
				$line = substr($line, 30);
				$parts = explode('== ', $line);
				$errorLineLayout = 'extended';
			} else {
				$errorLineLayout = 'core_extract';
				$parts = explode(':', $line, 2);
			}
            
			if (count($parts) > 1) {

			    $entry = trim($parts[count($parts) - 1]);

			    // let's see, if we have a log id, which is not an id - so it needs to be cleaned out
                $entry = preg_replace('/[\w-]{16}-\d-\d{2} \d{19} - /', '', $entry);


                $this->alyStatus['entry'] = $entry;
				$messageParts = ($isExtended) ? explode('|', trim(str_replace('ERROR', '', $parts[0]))): array(); // 0 => basic description, 1 => timestamp?, 2 => Site, 3 => Pipeline, 4 => another description? 5 => Session Id
				
				// d($line);
				$this->extractMeaningfullData();
				
				$interestingLine = 7101;
				if($this->alyStatus['entryNumber'] == $interestingLine) {
					d($this->alyStatus);
					//$this->io->read();
				}
				
				switch($this->alyStatus['errorType']){
					default:
						if ($errorLineLayout == 'extended' && count($messageParts) > 2) $this->alyStatus['data']['sites'][$this->extractSiteID(trim($messageParts[2]))] = true;
						break;
					case 'TypeError':
					case 'com.demandware.beehive.core.capi.pipeline.PipeletExecutionException':
					case 'Wrapped com.demandware.beehive.core.capi.common.NullArgumentException':
					case 'Exception occurred during request processing':
					case 'ISH-CORE-2351':
					case 'ISH-CORE-2354':
						
						
						$this->alyStatus['entry'] = preg_replace("/ \[TemplateNamespace id=.*?\]/", "", $this->alyStatus['entry']); // taking out the not usefull TemplateNamespace id
						
						if ($errorLineLayout == 'extended') {
							switch ($messageParts[0]) {
								default:
									$pipeline = $messageParts[3];
									$siteID = $this->extractSiteID(trim($messageParts[2]));
									break;
								case 'JobThread':
									
									$partlets = explode(' ', $messageParts[3]);
									$pipeline = trim($partlets[0]);
									$siteID = $this->extractSiteID(trim($partlets[4]));
									
									break;
							}
							
							
							
							$this->alyStatus['entry'] = $pipeline . ' > ' . $this->alyStatus['entry'];
							$this->alyStatus['data']['sites'][$siteID] = true;
						} else {
							$this->alyStatus['entry'] = $this->alyStatus['entry'];
						}
						break;
					
					// errors with pipeline, but second line has the real error message
					case 'ISH-CORE-2368':
					case 'ISH-CORE-2355':
						$this->alyStatus['data']['systemError'][$this->alyStatus['errorType']] = true;
						if ($errorLineLayout == 'extended') $this->alyStatus['data']['sites'][$this->extractSiteID(trim($messageParts[2]))] = true;
						break;
					
					// Job errors
					case 'ISH-CORE-2652':
						
						$infosBefore = explode('[', $this->alyStatus['entry'], 2);
						$infosAfter = explode(']', $infosBefore[1], 2);
						
						$partlets = explode(':', $infosAfter[1]);
						
						$params = explode(', ', $infosAfter[0]);
						
						$this->alyStatus['entry'] = $infosBefore[0] . " " . $params[0] . " " . $partlets[0] . " " . $partlets[count($partlets) - 1];
						if (count($params) > 2) $this->alyStatus['data']['sites'][$this->extractSiteID(trim($params[2]))] = true;
						
						break;
					
					case 'ISH-CORE-2688':
						$parts = explode(' , ',$this->alyStatus['entry']);
						
						if ( count($parts) > 1 && startsWith($parts[0], 'Uncaught exception in Job Thread')) {
							
							preg_match('/, ID=(?P<id>.*?),.*?description=(?P<description>.*?),.*?pipelineName=(?P<pipeline>.*?),.*?startNodeName=(?P<node>.*?),/', $parts[1], $treffer);
							
							if(count($treffer)) {
								if( array_key_exists('id', $treffer) ) $this->alyStatus['data']['Job ID'][$treffer['id']] = true;
								if( array_key_exists('description', $treffer) ) $this->alyStatus['data']['Job Description'][$treffer['description']] = true;
								if( array_key_exists('pipeline', $treffer) ) $this->alyStatus['data']['Startnode'][$treffer['pipeline'] . '-' . $treffer['node']] = true;							
							}
							
							$parts = explode(' [', $parts[1]);
							$parts = explode(' ', $parts[0], 2);
							$this->alyStatus['entry'] = $parts[1];
						} else {
							$this->displayError($this->alyStatus['entry']);
						}
						break;
					// internal errors
					case 'ISH-CORE-2482':
						break;
					case '[bc_search] error':
						
						$parts = explode("'", $this->alyStatus['entry'], 3);
						
						if (count($parts) > 2) {
							$this->alyStatus['entry'] = $parts[0] . ' {- different items -} ' . $parts[2];
						} else {
							d($parts);
						}
						break;
					
				}
				
				$errorsWithAdditionalLineToParse = array(
					  'ISH-CORE-2482'
					, 'ISH-CORE-2351'
					, 'ISH-CORE-2354'
					, 'ISH-CORE-2368'
					, 'ISH-CORE-2355'
					, 'com.demandware.beehive.core.capi.pipeline.PipeletExecutionException'
					, 'Error while processing request'
					, 'ORMSQLException'
					, 'ABTestDataCollectionMgrImpl'
					, 'Error executing query'
				);
				
				if (in_array($this->alyStatus['errorType'], $errorsWithAdditionalLineToParse)) {
					$newLine = $this->getNextLineOfCurrentFile();
					$this->alyStatus['stacktrace'] .= $newLine . "\n";
				
				
					// get aditional information from the next line
					switch ($this->alyStatus['errorType']) {
						default:
							$this->alyStatus['entry'] .= ' ' . $newLine;
							break;
						case 'ISH-CORE-2355':
							$this->alyStatus['entry'] = 'Secure connection required for this request.';
							if ($errorLineLayout == 'extended') $this->alyStatus['data']['pipeline'][$messageParts[3]] = true;
							$this->alyStatus['errorType'] = 'Pipeline Execution Exception';
							break;
						case 'ISH-CORE-2368':
							
							if (startsWith($newLine, 'com.demandware.beehive.core.capi.pipeline.PipelineExecutionException: Start node not found') 
								|| startsWith($newLine, 'com.demandware.beehive.core.capi.pipeline.PipelineExecutionException: Start node is not public') 
							) {
								preg_match('/ \\((?P<node>.*?)\\) for pipeline \\((?P<pipeline>.*?)\\)/', $newLine, $hits);
								$pipe = $hits['pipeline'] . '-' . $hits['node'];
							} else if (startsWith($newLine, 'com.demandware.beehive.core.capi.pipeline.PipelineExecutionException: Pipeline not found')) {
								preg_match('/Pipeline not found \\((?P<pipeline>.*?)\\) for current domain \\(.*?\\)/', $newLine, $hits);
								$pipe = $hits['pipeline'];
							} 
							
							if (! isset($pipe) || ! $pipe) {
								d($newLine);
								d($hits);
							}
							
							$this->alyStatus['entry'] = 'Pipeline not found Error';
							$this->alyStatus['data']['pipeline'][$pipe] = true;
							$this->alyStatus['errorType'] = 'Pipeline Execution Exception';
							break;
						case 'ISH-CORE-2482':
							$this->alyStatus['entry'] = $newLine;
							$this->extractMeaningfullData();
							break;
						case 'Error executing query':
						case 'ABTestDataCollectionMgrImpl':
						case 'ORMSQLException':
						case 'Error while processing request':
						case 'ISH-CORE-2354':
							
							// try to find the real error
							$lines = 1;
							while ($lines < 5 && ! $this->startsWithTimestamp($newLine)){
								if (
									   startsWith($newLine, 'org.mozilla.javascript.EcmaError:')
									|| startsWith($newLine, 'com.demandware.beehive.core.capi.pipeline.PipelineExecutionException:')
									|| startsWith($newLine, 'com.demandware.beehive.orm.capi.common.ORMSQLException:')
								   ) $this->alyStatus['entry'] .= ' ' . $newLine;
								
								$newLine = $this->getNextLineOfCurrentFile();
								$this->alyStatus['stacktrace'] .= $newLine . "\n";
								$lines++;
							}
							return $newLine;
							
							break;
						case 'com.demandware.beehive.core.capi.pipeline.PipeletExecutionException':
							if (endsWith($this->alyStatus['entry'], 'Script execution stopped with exception:')) {
								$this->alyStatus['entry'] .= ' ' . $newLine;
							}
							break;
					}
				}
				
				if($this->alyStatus['entryNumber'] == $interestingLine) {
					d($this->alyStatus);
					// $this->io->read();
				}
				
			} else {
				
				// we probably have an error like this
				
				
				$parts = explode('|', $line);
                
                if (count($parts) == 6) {
                    // ERROR PipelineCallServlet|1240173582|Sites-RX-BE-Site|Product-Detail|PipelineCall|MzMbhhzUFQ5hXBwTh0eax41f6IvMpYXuDk-dI9v07DkaEpeO879byK9BkR21oPupB5UcUhOk_xkszcY51A0ZDw== system.core  Sites-RX-BE-Site STOREFRONT MzMbhhzUFQ5hXBwTh0eax41f6IvMpYXuDk-dI9v07DkaEpeO879byK9BkR21oPupB5UcUhOk_xkszcY51A0ZDw== uBEqVFlS8YSrAgAK-1-63 7762035536076453888 The following message was generated more than 10 times within the last 180 seconds. It will be suppressed for 180 seconds: Loading script file 'int_tealium:helpers.ds' from cartridge 'int_tealium'. Cartridge not assigned to current site 'Sites-RX-BE-Site'.
                    // ERROR PipelineCallServlet|1107565453|Sites-RX-IE-Site|Search-Show|PipelineCall|7bmw1u15_T_lKi4ZVQk-MXr16l1XgOsoZZ3BPQoXsF7Y84SKBBVZBD8QlsVCWxXMd0e0rKsRDZxuwtA3I6cbww== system.core  Sites-RX-IE-Site STOREFRONT 7bmw1u15_T_lKi4ZVQk-MXr16l1XgOsoZZ3BPQoXsF7Y84SKBBVZBD8QlsVCWxXMd0e0rKsRDZxuwtA3I6cbww== NpoDQ1lS8aqrAgAK-0-00 391139902702621696  Loading script file 'int_tealium:helpers.ds' from cartridge 'int_tealium'. Cartridge not assigned to current site 'Sites-RX-IE-Site'.

                    $this->alyStatus['data']['pipelines'][$parts[3]] = true;
                    $this->alyStatus['data']['sites'][$this->extractSiteID(trim($parts[2]))] = true;
                    
                    preg_match('/.*== [a-zA-Z0-9\-_]*? [0-9]*? (?P<message>.*)/', $parts[5], $hits);
                    
                    if( array_key_exists('message', $hits) && $hits['message']) {
                        $pos = strpos($hits['message'], 'Cartridge not assigned to current site');
                        
                        if ($pos > -1) {
                            $this->alyStatus['entry'] = trim(str_replace('The following message was generated more than 10 times within the last 180 seconds. It will be suppressed for 180 seconds: ', '', substr($hits['message'], 0, $pos)));
                        } else {
                            $this->alyStatus['entry'] = trim($hits['message']);
                        }
                    } else {
                        $this->alyStatus['entry'] = $parts[5];
                        d($parts);
                        $this->displayError($line);
                    }
                    
                    $errorId = 'Error executing pipeline: ';
                    if ( startsWith($this->alyStatus['entry'], $errorId)){
                        
                        // COCustomer Sub-Pipeline: Login NodeID: Process.1/b3.2/b2.1/b3.1:DPipeletNode:LoginCustomer.1
                        
                        preg_match('/\\w*? Sub-Pipeline: (?P<pipeline>\\w*?) NodeID: (?P<node>\\w*?)\\.[0-9]/', $this->alyStatus['entry'], $hits);
                        
                        if (count($hits)) {
                            $pipe = $parts[3] . ':' . $hits['pipeline'] . '-' . $hits['node'];
                            $this->alyStatus['entry'] = 'pipeline execution error: ' . $pipe;
                        } else {
                            $pipe = substr($this->alyStatus['entry'], strlen($errorId));
                            $this->alyStatus['entry'] = 'Error executing pipeline';
                            $this->alyStatus['data']['pipe'][$pipe] = true;
                        }
                    } 
                    
                } else if (count($parts) == 4) {
					// ERROR JobThread|14911671|BazaarProductCatalogExport|JobExecutor-Start system.dw.net.SFTPClient  {0}
                    
					$look = 'ERROR';
					
					if ( startsWith($look, trim($parts[0]) )) $this->alyStatus['errorType'] = $look . ' ';
					$this->alyStatus['errorType'] .= trim($parts[2]);
					
					$partlets = explode(' ', trim($parts[3]));
					$this->alyStatus['data']['pipelines'][$partlets[0]] = true;
					$this->alyStatus['entry'] = trim($parts[0]) . ' ' . trim($parts[2]) . ' ';
					
				} else {
					
					// now we catch a few frequent core errors, that also land here
					// ERROR localhost-startStop-2 org.apache.catalina.loader.WebappClassLoader  The web application [] appears to have started a thread named [HystrixTimer-1] but has failed to stop it. This is very likely to create a memory leak.
					// ERROR OnChangeIndexer-thread-1 com.demandware.component.search3.index.SearchSvcRequestFactory  - - - - 8679935854433576960  Outdated order found. Loaded OCA 301104337, Index Request OCA 301104338 for order (41935546, bc6tYiaajfl06aaadcwYcHb5V1)
					// ERROR background-executor-3 com.demandware.beehive.bts.internal.orderprocess.basket.datagrid.FetchBasketConfigFromMoceTask  - - - - 2081329827211234304  Failed executing basket config poll task
					if (startsWith($line, 'ERROR localhost-startStop-')) {
						
						preg_match('/The web application \\[(?P<application>.*?)\\] appears to have started a thread named \\[(?P<thread>.*?)\\] but has failed to stop it\\. This is very likely to create a memory leak\\./', $line, $hits);
						
						$this->alyStatus['errorType'] = "Thread Stop Failure";
						$this->alyStatus['entry'] = 'A web application was not able to stop a thread - this is likely to create a memory leak';
						$this->alyStatus['data']['application'][$hits['application']] = true;
						$this->alyStatus['data']['threadName'][$hits['thread']] = true;
						
					} else if (startsWith($line, 'ERROR OnChangeIndexer-thread-')) {
						
						preg_match('/Outdated order found. Loaded OCA [0-9]*?, Index Request OCA [0-9]*? for order \((?P<orderNo>.*?),/', $line, $hits);
						
						$this->alyStatus['errorType'] = "DW Internal Order Indexer";	
						$this->alyStatus['entry'] = 'Outdated order found';
						$this->alyStatus['data']['order No']['#' . $hits['orderNo']] = true;
					} else if (startsWith($line, 'ERROR background-executor-')) {
						$this->alyStatus['errorType'] = "DW Internal Background Executor";	
						$this->alyStatus['entry'] = 'Failed executing basket config poll task';
					} else if (startsWith($line, 'ERROR ShopAPIServlet|')) {
                        
                        preg_match('/File has no content: \'(?P<file>.*?\\/wapi_shop_config\\.json)\'/', $line, $hits);
                        
                        if( array_key_exists('file', $hits) && $hits['file']) {
                            
                            $this->alyStatus['entry'] = 'wapi_shop_config.json has no content';
                            $this->alyStatus['data']['file'][$hits['file']] = true;
                        
                        } else {
                            
                            $this->alyStatus['entry'] = $line;
                            d($parts);
                            $this->displayError($line);
                            
                        }
                        
                    } else if (startsWith($line, 'ERROR http-bio-')) {
                        $parts = explode('  ', $line);
                        $this->alyStatus['entry'] = $parts[count($parts) - 1];
                    } else {
                        
                        // we check the  File has no content: '/remote/f_aahh/aahh/aahh_prd/sharedata/sites/Sites-RX-DE-Site/2/config/wapi/wapi_shop_config.json'
                        
                        
						$this->alyStatus['entry'] = $line;
						d($parts);
						$this->displayError($line);
					}
				}
			}
		} else if ($this->startsWithTimestamp($line)) { // a log entry is unfortuatly only finished after we found the next entry or end of file 
			$this->alyStatus['add'] = true;
			return $line;
        } else if ($this->alyStatus['entry'] == 'Error in template script.' && startsWith($line, 'Stack trace <')){
            $this->alyStatus['entry'] .= ' Trace: ' . substr($line, strlen($line) - 33, 32);
		} else { // now we try to find general error information like QueryString:, PathInfo: etc.
			$generalInformations = array(); // 'QueryString', 'SessionID'
			
			foreach($generalInformations as $index => $searchString){
				if (startsWith($line, $searchString))	{
					$value = substr($line, strlen($searchString) + 2);
					$this->alyStatus['data'][$searchString][$value] = true;
				}
			}
		}
	}
	
	function getErrorType_1($entry){
		$errorType = explode(' ', $entry, 3);
		array_shift($errorType);
		return $errorType;
	}
	function getErrorType_2($entry){ return array($entry); }
	
	function extractMeaningfullData(){
		
		$exceptionStarts = array(
			array(
				'starts' => array('Wrapped '),
				'errorType' => 'getErrorType_1'
			),
			array(
				'starts' => array('No start node specified for pipeline', 'Customer password could not be updated.', 'Java constructor for'),
				'errorType' => 'getErrorType_2'
			)
		);
		
		$continue = true;

		// loop over the custom error functions
		for ($i = 0; $i < count($this->errorExceptions); $i++) {
			if (startsWith($this->alyStatus['entry'], $this->errorExceptions[$i]['start'])) {
				$this->alyStatus['errorType'] = $this->errorExceptions[$i]['type'];
				if (array_key_exists('solve', $this->errorExceptions[$i])) $this->alyStatus = $this->errorExceptions[$i]['solve']($this->errorExceptions[$i], $this->alyStatus, $this);
				$continue = false;
				break;
			}
		}
		
		if ($continue) {
		
			if (startsWith($this->alyStatus['entry'], 'Wrapped ')){
				$errorType = explode(' ', $this->alyStatus['entry'], 3);
				array_shift($errorType);
			} else {
				$errorType = explode(':', $this->alyStatus['entry'], 2);
			}
			
			if (count($errorType) > 1) {
					$dots = explode('.', trim(str_replace(':', '', $errorType[0])));
					
					$this->alyStatus['errorType'] = trim(array_pop($dots));
					$this->alyStatus['entry'] = trim($errorType[1]);
				
			} else {
				$this->displayError($this->alyStatus['entry']);
				$this->alyStatus['errorType'] = $this->alyStatus['entry'];
			};
		}
	}
	
	function extractMeaningfullCustomData(){
		
		$continue = true;
		
		for ($i = 0; $i < count($this->customErrorExceptions); $i++) {
			if (startsWith($this->alyStatus['entry'], $this->customErrorExceptions[$i]['start'])) {
				$this->alyStatus['errorType'] = $this->customErrorExceptions[$i]['type'];
				if (array_key_exists('solve', $this->customErrorExceptions[$i])) $this->alyStatus = $this->customErrorExceptions[$i]['solve']($this->customErrorExceptions[$i], $this->alyStatus, $this);
				$continue = false;
				break;
			}
		}
		
		if ($continue) {
			
			$errorType = explode(':', $this->alyStatus['entry'], 2);
			$errorType[0] = trim($errorType[0]);
			
			if (count($errorType) > 1 && trim($errorType[1]) != '') {
				
				$errorLine = $errorType[1];
				
				if (strpos($errorLine, '[[|') > -1 && strpos($errorLine, '|]]') === false) {
					// the JSON has here a multiline error - getting the rest and adding it with " | " for the parser
					while(strpos($errorLine, '|]]') === false){	
						$newLine = $this->getNextLineOfCurrentFile();
						$this->alyStatus['stacktrace'] .= $newLine . "\n";
						$errorLine .= " | " . htmlentities($newLine, ENT_COMPAT);
					}
					
					$errorLine = str_replace('&quot;}', '"}', $errorLine);
				}

				// try to find out, weather we have a JSON standardized error message here
				preg_match('/^(.*?)\[\[\|(.*)\|\]\]$/s', $errorLine, $matchesJSON);
				
				if(count($matchesJSON) && $matchesJSON[2]) {
					
					$this->alyStatus['errorType'] = $errorType[0];
					$this->alyStatus['entry'] = $matchesJSON[1];
					
					$json = json_decode($matchesJSON[2], true);
										
					if($json) { // is case we have to deal with invalid json
						foreach($json as $key => $value) {
							
							$value = html_entity_decode($value); // decoding from above
							if (is_numeric($key)) $key = '#' . $key;
							if (is_numeric($value)) $value = '#' . $value;
							
							$this->alyStatus['data'][$key][$value] = true;
							
						}
					} else {
						d('invalid JSON part');
						d($matchesJSON[2]);
					}
						
				} else if (startsWith($errorType[0], 'Exception while evaluating script expression')){
					$this->alyStatus['errorType'] = 'Script Exception';
					$this->alyStatus['entry'] = $errorLine; // substr($errorType[0], 45) . 
				} else if (startsWith($errorType[0], 'Error executing script')) {
					$this->alyStatus['errorType'] = 'Error executing script';
					$this->alyStatus['entry'] = substr($this->alyStatus['entry'], 23) . ' ';
				} else {
					$this->alyStatus['entry'] = trim($errorLine);
					$errorType = (startsWith($errorType[0], 'org.')) ? explode('.', $errorType[0]) : explode(' ', $errorType[0]) ;
					$this->alyStatus['errorType'] = array_pop($errorType);
				}
			} else {
				$this->displayError($this->alyStatus['entry']);
			}
		}
	}
	
	function extractSiteID($siteString) {
		if (startsWith($siteString, 'Sites-') && endsWith($siteString, '-Site')) {
		
			$result = substr($siteString, 6);
			$result = substr($result, 0, -5);
			return ($result) ? $result : $siteString;
		}
		
		return $siteString;
	}
	
	
	// check if alert has to be thrown and return mail object for notification
	function checkAlert($errorCount, $stacktrace) {
		$filename = $this->currentFile;
		$filesize = round(filesize($filename) / 1024, 2);
		// get configuration
		$thresholds = $this->alertConfiguration['thresholds'];
		
		// preset mail variables
		$message = ($errorCount>0 ? "Error Count: $errorCount\n\n" : "")."Last logfile impacted: ".substr (strrchr($filename,'/'), 1)."\n\nLogfile size: $filesize KB\n\n".$stacktrace; 
		$mail = array();
		//$mail[$errorType] = array();
		
		// check for ignore pattern
		if (isset($thresholds['ignorepattern'])) {
			$ignorePattern = $this->checkSimplePatternThreshold($thresholds['ignorepattern'], $stacktrace);
			if (!empty($ignorePattern)) {
				return null;
			}
		}
		
		// check for count pattern - if a specific error has a certain count override
		if (isset($thresholds['countpattern'])) {
			$countPattern = $this->checkSimplePatternThreshold($thresholds['countpattern'], $stacktrace);
			if (!empty($countPattern)) {
				// check for pattern error count
				if (isset($thresholds['patterncount'])) {
					$threshold = 'patterncount';
					$maxPatternCount = $this->checkSimpleValueThreshold($thresholds['patterncount'], $errorCount);
					if (!empty($maxPatternCount)) {
						$mail[$threshold] = array(
							'message' => "Threshold: Pattern Error Count $maxPatternCount for pattern $countPattern exceeded.\n\n".$message,
							'subject' => "Pattern Error Count $maxPatternCount for pattern $countPattern exceeded"
						);
						return $mail;
					}
				}
				return null;
			}
		}
		
		
		
		// check for any other threshold - default fallback
		foreach ($thresholds as $threshold => $expression) {
			
			switch($threshold) {
				default:
					throw new Exception('Don\'t know how to handle ' . $threshold . ' threshold.');
					break;
				case 'errorcount':
					$maxErrorCount = $this->checkSimpleValueThreshold($expression, $errorCount);
					// only send an email, if the threshold is over the edge
					if (!empty($maxErrorCount) && $errorCount > $maxErrorCount) {
						$mail[$threshold] = array(
							'message' => "Threshold: Error Count $maxErrorCount exceeded.\n\n".$message,
							'subject' => "Error Count $maxErrorCount exceeded"
						);
					}
					break;
				case 'matchpattern':
					$matchedPattern = $this->checkSimplePatternThreshold($expression, $stacktrace);
					if (!empty($matchedPattern)) {
						$mail[$threshold] = array(
							'message' => "Threshold: Pattern '".$matchedPattern."' matched.\n\n".$message,
							'subject' => "Pattern '".$matchedPattern."' matched"
						);
					}
					break;
				case 'filesize':
					// only check when no log file entry is provided (only check once per file)
					if($errorCount<=0 && empty($stacktrace)) {
						$maxFilesize = $this->checkSimpleValueThreshold($expression, $filesize);
						if (!empty($maxFilesize)) {
							$mail[$threshold] = array(
								'message' => "Threshold: Logfile size '".$maxFilesize."' KB exceeded.\n\n".$message,
								'subject' => "Logfile size $maxFilesize KB exceeded"
							);
						}
					}
					break;
				case 'ignorepattern':
				case 'countpattern':
				case 'patterncount':
					// this has been already handled above
					break;
			}
		}
				
		return $mail;
	}
	
	// checks simple threshold value for given threshold expression
	function checkSimpleValueThreshold($expression, $checkvalue) {		
		$exceeded_value = null;
		if (is_array($expression)) {
			foreach ($expression as $filelayout => $value) {
				if ($this->layout == $filelayout) {
					$exceeded_value = $value;
				}
			}
			
			if ($exceeded_value === null && array_key_exists('default', $expression)) {
				$exceeded_value = $expression['default'];
			}
		}
		return $exceeded_value;
	}
	
	// checks simple threshold pattern for fiven threshold expression
	function checkSimplePatternThreshold($expression, $checkpattern) {
		$exceeded_value = null;
		foreach ($expression as $pattern) {
			if (preg_match("/$pattern/", $checkpattern)) {
				$exceeded_value = $pattern;
			}
		}
		return $exceeded_value;
	}
	
}

?>