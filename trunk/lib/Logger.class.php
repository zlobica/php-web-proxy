<?php

define('LOG_FILE', dirname(__FILE__) . '/../logs/proxy.log');
define('LINE_BREAK', "\n");
define('DATE_FORMAT', 'd-m-Y H:i:s');
define('LOG_LEVEL', 0);

class Logger {
	const SEVERITY_DEBUG = 0;
	const SEVERITY_INFO  = 1;
	const SEVERITY_WARN  = 2;
	const SEVERITY_ERROR = 3;
	const SEVERITY_FATAL = 4;
	
	var $severities = array(
		Logger::SEVERITY_DEBUG => 'DEBUG',
		Logger::SEVERITY_INFO => 'INFO',
		Logger::SEVERITY_WARN => 'WARN',
		Logger::SEVERITY_ERROR => 'ERROR',
		Logger::SEVERITY_FATAL => 'FATAL'
	);
	
	function debug($message) {
		$this->log(Logger::SEVERITY_DEBUG, $message);
	}
	
	function info($message) {
		$this->log(Logger::SEVERITY_INFO, $message);
	}
	
	function warn($message) {
		$this->log(Logger::SEVERITY_WARN, $message);
	}
	
	function error($message) {
		$this->log(Logger::SEVERITY_ERROR, $message);
	}
	
	function fatal($message) {
		$this->log(Logger::SEVERITY_FATAL, $message);
	}
	
	private function log($severity, $message) {
		if (LOG_LEVEL > $severity) {
			return;
		}
		
		$this->openLog();
		
		$date = date(DATE_FORMAT);
		
		$toWrite = sprintf('%s - %5s - %s%s', $date, $this->severities[$severity], $message, LINE_BREAK);
		
		fwrite($this->file, $toWrite);
		$this->closeLog();
	}
	
	private function openLog() {
		$this->file = fopen(LOG_FILE, 'ab');
	}
	
	private function closeLog() {
		fclose($this->file);
	}
}

?>