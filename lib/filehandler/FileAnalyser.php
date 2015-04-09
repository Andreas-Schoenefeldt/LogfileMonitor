<?php

$pathToPHPShellHelpers = str_replace('//','/',dirname(__FILE__).'/') .'../../../PHP-Shell-Helpers/';

require_once($pathToPHPShellHelpers .'CmdIO.php');
require_once($pathToPHPShellHelpers .'Debug/libDebug.php');


/* -----------------------------------------------------------------------
 * A function to open a file and parse the content into keymaps
 * ----------------------------------------------------------------------- */
class FileAnalyser {

	var $io = null;
	var $files = array(); // the files which will be analysed
	var $filePointer = null;
	var $environment = null;
	var $layout = null;
	
	var $workingDir = null;
	var $timestamp = null;
	
	var $entrys = array(); // an entry of a file array( 'entry text' => array( count => Int, line => line, fileIdent => fileIndex))
	var $errorcount = 0;
	var $allerrorscount = 0;
	
	var $settings = array('from' => null, 'to'=> null, 'timestamp' => null, 'timezoneOffset' => 2, 'from_human', 'to_human');
	
	// State Variables of the Fileanalyser
	var $currentFile = null;
	var $alyStatus = array(); // the current case analysation object
	
	var $alertMails = array();
	
	var $resultFileName = '';
	var $alertConfiguration = array();

	function __construct($files, $environment, $layout, $settings, $alertConfiguration = array())  {
		$this->io = new CmdIO();
		$this->files = $files;
		$this->environment = $environment;
		$this->layout = $layout;
		$this->alertConfiguration = $alertConfiguration;
		
		// calculate the timeframes
		$this->settings['from_human'] = $settings['from'];
		$this->settings['to_human'] = $settings['to'];
		if (array_key_exists('timezoneOffset', $settings)) $this->settings['timezoneOffset'] = $settings['timezoneOffset'];
		$this->settings['timestamp'] = $settings['timestamp'];
		$this->settings['from'] = $settings['timestamp'] + $this->getSecondsFromHoure($settings['from']);
		$this->settings['to'] = $settings['timestamp'] + $this->getSecondsFromHoure($settings['to']);
	}
	
	function setResultFileName($name){ $this->resultFileName = $name; }
	function getResultFileName()     { return $this->resultFileName; }
	
	// function to convert "h:mm" to seconds
	function getSecondsFromHoure($houreString){
		$parts = explode(':', $houreString, 2);
		$hours = intval(trim($parts[0])) - $this->settings['timezoneOffset']; // two is the MEZ timezone offset
		$minutes = intval(trim($parts[1]));
		
		// return the second value
		return $hours * 3600 + $minutes * 60;
	}
	
	function getAllErrorCount() {		return $this->allerrorscount;	}
	function getErrorCount() { 			return $this->errorcount; 		}
	function setTime($timestamp) { 		$this->timestamp = $timestamp;	}
	
	/**
	 * Sets and creates the working dir.
	 */
	function setWorkingDir($dir) {
		if (! file_exists($dir)) {
			mkdir($dir);
		}
		
		$this->workingDir = $dir;
	}
	
	/**
	 *	Main Function, which will start the actual analysation process
	 */
	function parse(){
		// get the file
		for ($i = 0; $i < count($this->files); $i++) {
			
			$filename = $this->files[$i]; 
			if (file_exists($filename)){
				$this->currentFile = $filename;
				$this->filePointer = fopen($filename, 'r');
				
				// analyse the file
				$this->analyse($i);
				
				fclose($this->filePointer);
				
			} else {
				$this->io->error("File $filename does not exist");
			}	
		}
	}
	
	/**
	 *	the analyse function of the File Analyser. Should be implemented in concrete environment based childclasses
	 */
	function analyse($fileIdent) {
		throw new Exception("Not Implemented.");
	}
	
	/**
	 *	The basic get line function. Will add a line to the currents case stack trace and increment the internal line number counter
	 *
	 *	@return String		The next line of the file
	 */
	function getNextLineOfCurrentFile(){
		$this->alyStatus['lineNumber']++;
		$line = fgets($this->filePointer, 4096);
		
		$trimmedLine = trim($line);
		return ($line && !$trimmedLine) ? true : $trimmedLine;
	}
	
	/**
	 * function which will do a basic initialisation of the aly status.
	 * It is recommended to call this function in your own implementation first, because other functions of this class are reffering to the basic aly propertys
	 *
	 */
	function initAlyStatus($fileIdent, $currentLineNumber){
		$this->alyStatus = array(
			  'timestamp' => $this->settings['timestamp']
			, 'stacktrace' => ''
			, 'entry' => '-'
			, 'lineNumber' => $currentLineNumber
			, 'fileIdent' => $fileIdent
			, 'data' => array() // a map of data entrys
		);
	}
	
	/**
	 *	The basic add Entry function. Will add a entry to the local list of entries.
	 *
	 *	@return Integer		The amount of this entry
	 */
	function addEntry($timestamp, $type, $key, $lineNumber, $fileIdent, $data, $stacktrace) {
		if ($timestamp >= $this->settings['from'] && $timestamp <= $this->settings['to']) {
			$key = str_replace(array("\n", "\r"), '' , $key);
			
			if (! array_key_exists($key, $this->entrys)){
				$this->entrys[$key] = array('count' => 0, 'type' => $type, 'line' => $lineNumber, 'fileIdent' => $fileIdent, 'data' => array(), 'stacktrace' => $stacktrace);
				$this->errorcount++; // errors +1
			}
			
			if ($lineNumber < $this->entrys[$key]['line']) {
				$this->entrys[$key]['line'] = $lineNumber;
				$this->entrys[$key]['fileIdent'] = $fileIdent;
				$this->entrys[$key]['stacktrace'] = $stacktrace;
			}
			
			$this->entrys[$key]['count']++;
			$this->entrys[$key]['data'] = $this->mergeAndUpcCuntArray($this->entrys[$key]['data'], $data);
			
			$this->allerrorscount++;
			
			return $this->entrys[$key]['count'];
		}
	}
	
	/**
	 *	A function that weill actualy count the occurences of the array. true is counted as 1
	 */
	function mergeAndUpcCuntArray($mergTo, $takeFrom) {
		
		foreach ($takeFrom as $key => $valueArr) {
			foreach ($valueArr as $data => $count) {
				$count = ($count === true) ? 1 : $count;
				if (array_key_exists($key, $mergTo) && array_key_exists($data, $mergTo[$key])){
					$mergTo[$key][$data] += $count;
				} else {
					$mergTo[$key][$data] = $count;
				}
			}
		}
		
		return $mergTo;
	}
	
	function printResults($format = 'cmd'){
		
		
		uasort($this->entrys, function($a, $b){
			if ($a['count'] == $b['count']) {
				
				// sort for the first line number afterwards
				if ($a['line'] == $b['line']) {
					return 0;
				}
				return ($a['line'] < $b['line']) ? -1 : 1;
			}
			return ($a['count'] < $b['count']) ? 1 : -1;
		});
		
		switch ($format) {
			default:
				$this->io->out('Results:', true, 1);
				
				foreach ($this->entrys as $message => $stats) {
					$countString = $stats['count'] . '';
					$countString = str_repeat(' ', 4 - strlen($countString)) . $countString;
					$this->io->out($countString . ' times since line ' . $stats['line']. ' in file ' . $stats['fileIdent'] . ' | ' . $message);
					
					$siteString = str_repeat(' ', 5 ) . 'Sites:';
					foreach ($stats['data']['sites'] as $siteId => $val) {
						$siteString .= ', ' . $siteId;
					}
					$this->io->out($siteString);
					
					$this->io->out();
				}
				break;
			case 'html':
				
				$this->writeCss();
				$this->writeJs();
				
				$filename = $this->writeErrorList();
				
				break;
		}
		
		return $filename;
	}
	
	function writeCss(){
		$filepath = $this->workingDir . '/style.css';
		copy(str_replace('//','/',dirname(__FILE__).'/') .'../../templates/analyse/style.css', $filepath);
	}
	
	function writeErrorList(){
		global $projectTechName; // gettingthe techname from the config
		
		$filename = date('Y-m-d', $this->timestamp) . '_' . $this->layout .'.html';
		$filepath = $this->workingDir . '/' . $filename;
		
		$file = fopen($filepath, 'w');
		
		$title = $projectTechName . ' ' . $this->layout . ' overview - ' . date('d.m.Y', $this->timestamp) .' from ' . $this->settings['from_human'] . ' to ' . $this->settings['to_human'] . ' GMT + ' . $this->settings['timezoneOffset'];
		
		$navigation = '<div class="navigation"><a href="index.html">back to overview</a></div>';
		
		$fileString = '
<!DOCTYPE html>
<html>
<head>
	<meta content="text/html; charset=utf-8" http-equiv="Content-Type">

	<title>'.$title.'</title>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.min.js" type="text/javascript"></script>
	<script src="app.js" type="text/javascript"></script>
	<link href="style.css" type="text/css" rel="stylesheet">
</head>
<body>
	<div class="page">'. $navigation .'
		<h1>'.$title.'</h1>
		
		<div class="error_table">' . "\n";
		
		foreach ($this->entrys as $message => $stats) {
			
			$pathexplodes = explode('/', $this->files[$stats['fileIdent']]);
			
			$fileString .= '<div class="error_row widget_showAditionals" id="' . $this->getIdHash($message) . '">' . "\n"; // note that we id the actual message with a md4 hash
			$fileString .= '	<div class="entry number">' . $stats['count'] . ' x</div>' . "\n";
			$fileString .= '	<div class="entry type">' . htmlentities($stats['type']) . '</div>' . "\n";
			$fileString .= '	<div class="entry actions"><a class="widget_traceoverlay minibutton" title="show the raw stacktrace of this error">raw<span class="hidden overlay"><span class="headline">First occurence in line ' . $stats['line'] . ', logfile ' . $pathexplodes[count($pathexplodes) - 1] . '</span><pre class="preformated">' . htmlentities($stats['stacktrace']) . '</pre></span></a></div>' . "\n";
			$fileString .= '
	<div class="entry message">
		<div>' . htmlentities($message) . '</div>
		<div class="aditionals">' . "\n";
			
			foreach ($stats['data'] as $headline => $data){
				$first = true;
				$valString = '';
				
				if (count($data)) {
					
					if ($headline != 'GMT timestamps') {
						// sort the values and keep the key => value associations
						asort($data);
						$data= array_reverse($data);
					
				
						foreach ($data as $value => $count) {
							if (! $first) $valString .= ', ';
							if ($count > 1) $valString .= '[' . $count . 'x] ';
							$valString .= $value;
							$first = false;
						}
						
						$fileString .=  '<div><strong>' . htmlentities($headline) . ':</strong> ' . htmlentities($valString) . '</div>'. "\n";
					} else {
						$maxCount = 0;
						foreach ($data as $value => $count) {
							$maxCount = ($count > $maxCount) ? $count : $maxCount;
						}
						
						$fileString .=  '<div><strong>' . htmlentities($headline) . ':</strong><div class="occ-frame"><div class="sideLabels"><label class="topLbl">' . $maxCount . '</label><label class="bottomLbl">0</label></div><div class="timing">';
						
						$bottomLbl = '<div class="bottomLabels">';
						$lastHoure = -1;
						
						$firstI = 0;
						
						for ($i = 0; $i < 1440; $i++){
							
							if ($firstI) $firstI++;
							
							$minute = $i % 60;
							$houre = intval( $i / 60);
							
							$val = ' ' . ($houre < 10 ? '0' : '') . $houre . ':' . ($minute < 10 ? '0' : '') . $minute;
							
							if (array_key_exists($val, $data) && $data[$val]) {
								
								if (! $firstI) $firstI = 1;
								
								if ($lastHoure != $houre) {
									$lastHoure = $houre;
									$bottomLbl .= '<label class="btm-lbl" style="left: '. ($firstI * 5 + 1 ) .'px" >' . $val . '</label>';
									
								}
								
								$height = $data[$val] / $maxCount * 100;
								$fileString .=  '<div class="occ-line" style="left: '. ($firstI * 5 ) .'px"><div class="occurence" style="height: '.$height.'%;"></div><label class="occLbl">' . $val . ' - ' . $data[$val] . ' time(s)</label></div>';
							}
							
						}
						
						$bottomLbl .= '</div>';
						
						$fileString .= $bottomLbl . '</div></div><div class="clear"></div></div>';
					}
				}
			}
			$fileString .= '
		</div>
	</div>
			' . "\n";
			$fileString .= '	<div class="clear"><!-- Karmapa Tchenno --></div>' . "\n";
			$fileString .= '</div>' . "\n";
		}
		
		
		$fileString .= '	
		</div>'. $navigation;
		$fileString .= '<div id="footer">Generated at ' . date('d. F Y, H:i') . '</div>';
		$fileString .= '</div></body></html>';
		
		
		fwrite ($file, utf8_encode($fileString));
		
		
		fclose($file);
		
		return $filename;
	}
	
	function displayError($line){
		d('SOMETHING STRANGE: ' . $line . "\n" . 'line ' .  $this->alyStatus['lineNumber'] . ' in File: ' . $this->currentFile);
	}
	
	function writeJs(){
		copy(str_replace('//','/',dirname(__FILE__).'/') .'../../templates/analyse/app.js', $this->workingDir . '/app.js');
	}
	
	function getIdHash($identifyer) {
		return hash('md4', $identifyer); // we are using md4, because it is the fastest hash of all of them
	}

}

?>

