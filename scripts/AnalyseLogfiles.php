#!/usr/local/bin/php -q
<?php
	date_default_timezone_set("Europe/Berlin");
	
	$pathToPHPShellHelpers = str_replace('//','/',dirname(__FILE__).'/') .'../../PHP-Shell-Helpers/';
	
	require_once($pathToPHPShellHelpers . 'CmdIO.php');
	require_once($pathToPHPShellHelpers . 'Filehandler/staticFunctions.php');
	require_once($pathToPHPShellHelpers . 'ComandLineTools/CmdParameterReader.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib/Mail/Mail.php');
	
	define("LOCAL_LISTING_FILENAME", '_listing.html' );
	
	$params = new CmdParameterReader(
		$argv,
		array(
			'e' => array(
				'name' => 'environment',
				'datatype' => 'Enum',
				'default' => 'demandware',
				'values' => array(
					'demandware' => array('name' => 'dw')
				),
				
				'description' => 'Defines the software environment of the logfile.'
			),
			'r' => array(
				'name' => 'remote',
				'datatype' => 'Boolean',
				'description' => 'if set, the files will be grabbed from a remote location'
			),
			'l' => array(
				'name' => 'local',
				'datatype' => 'Boolean',
				'description' => 'process localy only',
			),
			'd' => array(
				'name' => 'date',
				'datatype' => 'String',
				'description' => 'use this to put a direct date for the log processing use 6.7.2012'
			),
			'ts' => array(
				'name' => 'timeframeStart',
				'datatype' => 'String',
				'default' => '00:00',
				'description' => 'a time before this the logs are ignored. Write as h:mm in 24h format'
			),
			'te' => array(
				'name' => 'timeframeEnd',
				'datatype' => 'String',
				'default' => '24:00',
				'description' => 'a time after this the logs are ignored. Write as h:mm in 24h format'
			),
			'site' => array(
				  'name' => 'site'
				, 'datatype' => 'String'
				, 'default' => 'ALL'
				, 'description' => 'Allows to filter log messages only for a specific site. eg US'
			)
		),
		'A script to parse a logfile and return the results in a readable form.'
	);
	
	
	$files = $params->getFiles();
	
	if(! $params->getVal('r') && count($files) == 0 && ! $params->getVal('l')){
		$params->print_usage();
	} else {
		
		$env = $params->getVal('e');
		$class = capitalise($env) .'LogAnalyser';
		
		// dynamically including the Demandware class
		require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib/filehandler/' . $env . '/' . $class . '.php');
		
		// this is a remote connection, start the remote process
		if ($params->getVal('r') || $params->getVal('l')) {
			
			if (count($files) > 0) {
				for ($i = 0; $i  < count($files); $i++) {
					processLogFiles(getcwd() . '/' . $files[$i]);
				}
			} else {
				// the default, if no explicit config is given
				$configBaseDir = (str_replace('//','/',dirname(__FILE__).'/') .'../config/');
				forEachFile($configBaseDir, '.*config.*\.php', true, 'processLogFiles');
			}
			
		} else {
			$analyser = new $class($files, 'error');
			$analyser->printResults();
		}
	}
	
	function getTimeStamp($date) {
		$splits = ($date) ? explode('.', $date) : array();
		$day = (count($splits) == 3) ?   $splits[0] : date("d");
		$month = (count($splits) == 3) ? $splits[1] : date("m");
		$year = (count($splits) == 3) ?  $splits[2] : date("Y");
		
		return strtotime("$year-$month-$day 00:00:00.000 GMT");
	}
	
	function downloadRelevantFiles($configFile, $date, $download){
		global $io;
		
		require($configFile);
		$searchExpressions = array();
		$results = array();
		
		$timestamp = getTimeStamp($date);
		
		// listing of the logfolder
		if($download) download($webdavUser, $webdavPswd, $webdavUrl, '', $targetWorkingFolder, $alertConfiguration);
		
		// preparing the search expressions
		foreach ($logConfiguration as $layout => $config) {
			
			if (! $date) $timestamp += $config['dayoffset'] * 86400; // change the date by the number of days
			$time = date($config['timestampformat'], $timestamp);
		
			$searchExpressions[$layout] = str_replace('${timestamp}', $time, $config['regexTemplate']);
			$results[$layout] = array(); // to keep the order as defined
		}
		
		// reading the listing file line by line and downloading the files, if we have a match
		if ($download) {
			$io->out('> parsing ' . LOCAL_LISTING_FILENAME);
			$fp = fopen( $targetWorkingFolder . '/' . LOCAL_LISTING_FILENAME , 'r');
			$linecount = 1;
			while(($line = fgets($fp)) !== false){
			
				$linecount++;
			
				foreach($searchExpressions as $layout => $searchExpression){
					preg_match_all('/<tt>(' . $searchExpression . ')<\\/tt>/', $line, $match);

					if(count($match[0])) {
						for ($i = 0; $i < count($match[1]); $i++) {
							$file = $match[1][$i];
							$io->out("\n".'> line ' . $linecount . " \t found file " . $file );
							$target = $targetWorkingFolder . '/' . $file;
							download($webdavUser, $webdavPswd, $webdavUrl, $file, $targetWorkingFolder, $alertConfiguration);
							$results[$layout][] = $target;
						}
					}
				}
			}
		} else {
			// locale file listings
			$io->out('> listing ' . $targetWorkingFolder);
			foreach(scandir($targetWorkingFolder) as $index => $filename){
				foreach($searchExpressions as $layout => $searchExpression){
					preg_match_all('/(' . $searchExpression . ')/', $filename, $match);
					if( count($match[0]) ){
						for ($i = 0; $i < count($match[1]); $i++) {
							$file = $match[1][$i];
							$io->out("> found file " . $file );
							$target = $targetWorkingFolder . '/' . $file;
							$results[$layout][] = $target;
						}
					}
				}
			}
		}
		
		return $results;
	}

	function processLogFiles($configFile) {
		global $params, $io, $class, $configBaseDir;
		
		$timestamp = getTimeStamp($params->getVal('d'));
		$configPath = str_replace($configBaseDir, '', $configFile);
		$io->out('> Config File: '.$configPath, true, 1);
		
		require($configFile);
		
		set_error_handler('custom_error_handler', E_ALL);
		
		try {
		
			$alertConfiguration['configPath'] = $configPath;

			$download = ! $params->getVal('l');	
			
			if ($download) { // Variable to test the html generation quikly
				$io->out('> Preparing ' . $targetWorkingFolder);
				
				if (! file_exists($targetWorkingFolder)) mkdir($targetWorkingFolder, 0744, true);
				if ($clearWorkingFolderOnStart) emptyFolder($targetWorkingFolder, array('/\.sdb$/'));
				
				if (! file_exists($targetWorkingFolder)) mkdir($targetWorkingFolder, 0744, true);
			}
			
			$io->out('> Coppying Files...');
			
			$results = downloadRelevantFiles($configFile, $params->getVal('d'), $download, $timestamp);
			
			$htmlWorkingDir = $targetWorkingFolder . '/html';
			if (! file_exists($htmlWorkingDir)) mkdir($htmlWorkingDir);
			
			// now lets print a index.html
			$io->out('> Writing index.html');
			
			$filepath = $htmlWorkingDir . '/index.html';
			$file = fopen($filepath, 'w');
			
			$title = date('d.m.Y', $timestamp);
			
			fwrite($file, '<!DOCTYPE html><html><head>
					<meta content="text/html; charset=utf-8" http-equiv="Content-Type">
					<title>'.$title.'</title>
					<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.min.js" type="text/javascript"></script>
					<script src="app.js" type="text/javascript"></script><link href="style.css" type="text/css" rel="stylesheet">
					<meta http-equiv="refresh" content="900">
				</head><body><div class="page"><p><b>' . strtoupper($projectTechName) . ' Logfiles</b></p><div class="today-entry entry"><h1>'.$title.'</h1>');
			
			$settings = array(
				  'from' => $params->getVal('ts')
				, 'to' => $params->getVal('te')
				, 'site' => $params->getVal('site')
				, 'timestamp' => $timestamp
				, 'timezoneOffset' => 0
			);
			
			foreach ($results as $layout => $files) {
				$analyser = new $class($files, $layout, $settings, $alertConfiguration);
				$analyser->parse();
				$analyser->setWorkingDir($htmlWorkingDir);
				$analyser->setTime($timestamp);
				
				$io->out('> Writing result for ' . $layout . ' files.');
				$filename = $analyser->printResults('html');
				fwrite($file, '<p><a href="'. $filename .'"><strong>'. $analyser->layout . ' logs</strong> ('.$analyser->getErrorCount().' different errors, '. $analyser->getAllErrorCount() .' total)</a></p>');
				
				$analyser->setResultFileName($webdavUploadURL . '/' . $filename);
				
				if ($download) {
					Mail::sendDWAlertMails($analyser, $targetWorkingFolder, $alertConfiguration, $layout, $emailConfiguration);
					
					if (! isset($webdavUploadURL)) $io->fatal('$webdavUploadURL is not defined - please add it to ' . $configFile);
					upload($webdavUser, $webdavPswd, $htmlWorkingDir, $webdavUploadURL, $filename);
				}
				
				
			}
			
			fwrite($file, '</div>');
			
			$daysToThePast = 7;
			for ($i = 1; $i <= $daysToThePast; $i++) {
				
				$pastDate = $timestamp - $i * 86400;
				
				$filestring = '<div class="past-entry entry">';
				$filestring .= '<h2>' . date('d.m.Y', $pastDate) . '</h2>';
				
				
				
				foreach ($results as $layout => $files) {
					$filename = date('Y-m-d', $pastDate) . '_' . $layout .'.html';
					$filestring .= '<p><a href="' . $filename . '">' . $layout . ' logs</a></p>';
				}
				
				$filestring .= '</div>';
				
				fwrite($file, $filestring);
			}
			
			fwrite($file, '<div class="clear"><!-- K.T. --></div><div id="footer">Generated at ' . date('d. F Y, H:i') . '</div>');
			fwrite($file, '</div></body></html>');
			fclose($file);
			
			if ($download) {
				if (! isset($webdavUploadURL)) $io->fatal('$webdavUploadURL is not defined - please add it to ' . $configFile);
				upload($webdavUser, $webdavPswd, $htmlWorkingDir, $webdavUploadURL, 'index.html');
				upload($webdavUser, $webdavPswd, $htmlWorkingDir, $webdavUploadURL, 'app.js');
				upload($webdavUser, $webdavPswd, $htmlWorkingDir, $webdavUploadURL, 'style.css');
			}
		
		} catch (Exception $e) {
			$io->error("Error occurred during processing log files. Maybe invalid config file ($configFile) provided: ".$e->getMessage());
		}
	}
	
	function download($webdavUser, $webdavPswd, $webdavUrl, $filename, $localWorkingDir, $alertConfiguration) {
		global $io;
		
		$localeFileName = $filename ? $filename : LOCAL_LISTING_FILENAME;
		
		// check if the file exists before
		$commandBody = "curl -k -I -L --user \"$webdavUser:$webdavPswd\" ";
		$command = $commandBody . '"' . $webdavUrl . '/' . $filename . '"';
		$io->out($command);
		$output = shell_exec($command);
		$lines = explode("\n", trim($output));
		
		// check the response header status code
		for ($i = 0; $i < count($lines); $i++) {
			if (startsWith($lines[$i], 'HTTP')) {
				$codes = explode(' ', trim($lines[$i]));
				$statuscode = $codes[1];
				break;
			}
		}
		
		if (isset($statuscode) && $statuscode == '200') {
		
			$io->out('> ----------------------------------');
			$io->out('> Downloading ' . $filename);
			$commandBody = "curl -k --user \"$webdavUser:$webdavPswd\" ";
			$command =  $commandBody . '"' . $webdavUrl . '/' . $filename . '" -o "' . $localWorkingDir . '/' . $localeFileName . '"' ;
			
			$lastline = system($command, $retval);
			// retry logic
			if($retval > 0) {
				$io->error('Failed download ' . $filename);
				return false;
			}
			
			return true;
		} else {
			$errorMessage = "File $filename could not be downloaded from the server. Message: $output. Http Status Code: " . (isset($statuscode) ? $statuscode : 'undefined');
			$io->error($errorMessage);
			if (!isset($statuscode) || $statuscode != "404") {
				Mail::sendDWAlertMails(array('Server Alert' => array('Connection failed' => array('subject' => "Failed to connect to $webdavUrl.", 'message' => "Alert: Failed to connect to $webdavUrl. $errorMessage"))), $localWorkingDir, $alertConfiguration, '');
			}
		}
		
		return false;
	}
	
	
	function upload($webdavUser, $webdavPswd, $htmlWorkingDir, $webdavUrl, $filename) {
		global $io;
		
		$uploadPath = $webdavUrl . '/' . $filename;
		$localFile = $htmlWorkingDir . '/' . $filename;
		$commandBody = "curl -k --user \"$webdavUser:$webdavPswd\" ";
		$command =  $commandBody . '-T "' . $localFile . '" "' . $uploadPath . '"';
		$io->out();
		$io->out('> ' . $command );
		$io->out('> ----------------------------------');
		$io->out('> Uploading ' . $filename); 
		$io->out('>' . $localFile . ' to ' . $uploadPath);
		$lastline = system($command, $retval);
		// retry logic
		if($retval > 0) {
			$io->error('Failed upload ' . $filename);
			return false;
		}
		
		return true;
	}
	
	function custom_error_handler($errno, $errstr, $errfile, $errline, $errcontext) {
		$constants = get_defined_constants(1);

		$eName = 'Unknown error type';
		foreach ($constants['Core'] as $key => $value) {
			if (substr($key, 0, 2) == 'E_' && $errno == $value) {
				$eName = $key;
				break;
			}
		}

		$msg = $eName . ': ' . $errstr . ' in ' . $errfile . ', line ' . $errline;

		throw new Exception($msg);
	}
	
?>
