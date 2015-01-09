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
			
			$line = $this->analyseLine($line);
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
					case 'SendOgoneDeleteAuthorization.ds':
					case 'SendOgoneAuthorization.ds':
					case 'SendOgoneCapture.ds':
					case 'SendOgoneRefund.ds':
					case 'OgoneError':
						
						$this->parseOgoneError($this->alyStatus['entry']);
						
						if (endsWith($this->alyStatus['entry'], 'RequestUrl:')) $parseSecondLine = true;
						
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
						case 'SendOgoneDeleteAuthorization.ds':
						case 'SendOgoneAuthorization.ds':
						case 'SendOgoneCapture.ds':
						case 'SendOgoneRefund.ds':
						case 'OgoneError':
							$this->parseOgoneError($newLine);
							break;
					}
				}
				
			} else {
				d($parts);
				$this->displayError($line);
			}
			
			
		}
	}
	
	function parseOgoneError($line){
		
		$params = explode(' OrderNo:', $line, 2);
						
		// d($this->alyStatus['errorType']);
		// d($params);
		if (count($params) > 1) {
			$line = substr($params[0], 0, -1);
			$params = explode(',', $params[1]);
			
			// d($params);
			
			$this->alyStatus['data']['order numbers']['#' . trim($params[0])] = true;
			
			for ($i = 1; $i < count($params); $i++) {
				$parts = explode(':', $params[$i],2);
				$this->alyStatus['data'][trim($parts[0])][trim($parts[1])] = true;
			}
		}
		
		$params = explode(' Seconds since start:', $line, 2);
		
		if (count($params) > 1) {
			
			$line = substr($params[0], 0, -1);
			// $params = explode(',', $params[1], 2);
			// d($params);
			
			$this->alyStatus['data']['Seconds since start'][trim($params[1])] = true;
		}
		
		$startStr = 'Capture successfully for Order ';
		if (startsWith($line, $startStr)) {
			
			$this->alyStatus['data']['order numbers']['#' . trim(substr($line, strlen($startStr)))] = true;
			$line = trim($startStr);
		}
		
		// split the ogone Url
		$start = 'https://secure.ogone.com';
		if (startsWith($line, $start)) {
			
			$exceptions = array('java.net.SocketTimeoutException: Read timed out', 'Error connecting to Ogone Direct Link. Return Code: 404');
			$parseURL = true;
			
			for($i = 0; $i < count($exceptions); $i++){
				if (strrpos($this->alyStatus['entry'], $exceptions[$i]) > -1) {
					$line = $exceptions[$i]; // line is now the error message
					$parseURL = false;
					break;
				}	
			}
			
			if ($parseURL) {
				
				$parts = explode('; OgoneError: ', $line, 2);
				$url = explode('?', $parts[0]);
				$params = explode('&', $url[1]);
				
				$line = 'OgoneError: ' . $parts[1];
				
				for ($i = 0; $i < count($params); $i++) {
					$patlets = explode('=', $params[$i], 2);
					
					switch ($patlets[0]) {
						case 'PM':
						case 'OWNERTOWN':
						case 'OPERATION':
						case 'FLAG3D':
							$this->alyStatus['data'][$patlets[0]][trim($patlets[1])] = true;
							break;
						case 'AMOUNT':
							$this->alyStatus['data'][$patlets[0]][  substr(trim($patlets[1]), 0, -2) . '.' . substr(trim($patlets[1]), -2)] = true;
							break;
					}
				}
			}
		}
		
		$this->alyStatus['entry'] = $line;
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
				$parts = explode(' "', $line, 2);
				$errorLineLayout = 'extended';
			} else {
				$errorLineLayout = 'core_extract';
				$parts = explode(':', $line, 2);
			}
			
			if (count($parts) > 1) {
				
				$this->alyStatus['entry'] = trim($parts[1]);
				$messageParts = ($isExtended) ? explode('|', trim(str_replace('ERROR', '', $parts[0]))): array(); // 0 => basic description, 1 => timestamp?, 2 => Site, 3 => Pipeline, 4 => another description? 5 => Cryptic stuff
				
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
						$this->alyStatus['entry'] = ($errorLineLayout == 'extended') ? $messageParts[3] . ' > ' : '';
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
				// ERROR JobThread|14911671|BazaarProductCatalogExport|JobExecutor-Start system.dw.net.SFTPClient  {0}
				
				$parts = explode('|', $line);
				
				if (count($parts) > 3) {
					
					$look = 'ERROR';
					
					if ( startsWith($look, trim($parts[0]) )) $this->alyStatus['errorType'] = $look . ' ';
					$this->alyStatus['errorType'] .= trim($parts[2]);
					
					$partlets = explode(' ', trim($parts[3]));
					$this->alyStatus['data']['pipelines'][$partlets[0]] = true;
					$this->alyStatus['entry'] = trim($parts[0]) . ' ' . trim($parts[2]) . ' ';
					
				} else {
					
					$this->alyStatus['entry'] = $line;
					d($parts);
					$this->displayError($line);
				}
			}
		} else if ($this->startsWithTimestamp($line)) { // a log entry is unfortuatly only finished after we found the next entry or end of file 
			$this->alyStatus['add'] = true;
			return $line;
		} else { // now we try to find general error information like QueryString:, PathInfo: etc.
			$generalInformations = array('QueryString');
			
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
		
		for ($i = 0; $i < count($this->errorExceptions); $i++) {
			if (startsWith($this->alyStatus['entry'], $this->errorExceptions[$i]['start'])) {
				$this->alyStatus['errorType'] = $this->errorExceptions[$i]['type'];
				if (array_key_exists('solve', $this->errorExceptions[$i])) $this->alyStatus = $this->errorExceptions[$i]['solve']($this->errorExceptions[$i], $this->alyStatus);
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
				if (array_key_exists('solve', $this->customErrorExceptions[$i])) $this->alyStatus = $this->customErrorExceptions[$i]['solve']($this->customErrorExceptions[$i], $this->alyStatus);
				$continue = false;
				break;
			}
		}
		
		if ($continue) {
			
			
			//custom  infoscore/pipelets/RiskCheck.ds: Risk Check Result [[| {"status" : "OK", "communicationToken" : "46318476045772"} |]]
		
			// 'Unknown category ID'
			
			$errorType = explode(':', $this->alyStatus['entry'], 2);
			$errorType[0] = trim($errorType[0]);
			
			
			
			if (count($errorType) > 1 && trim($errorType[1]) != '') {
				
				
				
				// try to find out, weather we have a JSON standardized error message here
				preg_match('/^(.*?)\[\[\|(.*)\|\]\]$/', $errorType[1], $matchesJSON);
				
				if(count($matchesJSON) && $matchesJSON[2]) {
					
					$this->alyStatus['errorType'] = $errorType[0];
					$this->alyStatus['entry'] = $matchesJSON[1];
					
					$json = json_decode($matchesJSON[2], true);
					
					foreach($json as $key => $value) {
						
						if (is_numeric($key)) $key = '#' . $key;
						if (is_numeric($value)) $value = '#' . $value;
						
						$this->alyStatus['data'][$key][$value] = true;
						
					}
						
				} else if (startsWith($errorType[0], 'Exception while evaluating script expression')){
					$this->alyStatus['errorType'] = 'Script Exception';
					$this->alyStatus['entry'] = $errorType[1]; // substr($errorType[0], 45) . 
				} else if (startsWith($errorType[0], 'Error executing script')) {
					$this->alyStatus['errorType'] = 'Error executing script';
					$this->alyStatus['entry'] = substr($this->alyStatus['entry'], 23) . ' ';
				} else {
					$this->alyStatus['entry'] = trim($errorType[1]);
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
		$senderemailaddress = $this->alertConfiguration['senderemailaddress'];
		$emailadresses = $this->alertConfiguration['emailadresses'];
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
		
		// check for count pattern
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
		
		// check for any other threshold
		foreach ($thresholds as $threshold => $expression) {
			switch($threshold) {
				default:
					throw new Exception('Don\'t know how to handel ' . $threshold . ' threshold.');
					break;
				case 'errorcount':
					$maxErrorCount = $this->checkSimpleValueThreshold($expression, $errorCount);
					if (!empty($maxErrorCount)) {
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
					break;
			}
		}
		
		return $mail;
	}
	
	// checks simple threshold value for fiven threshold expression
	function checkSimpleValueThreshold($expression, $checkvalue) {
		$exceeded_value = null;
		$filelayoutExists = false;
		if (is_array($expression)) {
			foreach ($expression as $filelayout => $value) {
				if ($this->layout == $filelayout) {
					if ($filelayout!==0) {
						$filelayoutExists = true;
					}
					// allow default value when no threshold for current layout exists
					if (($checkvalue > $value || $checkvalue < 0) && ($filelayout!==0 || !$filelayoutExists)) {	
						$exceeded_value = $value;
					}
				}
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