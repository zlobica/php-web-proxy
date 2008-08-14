<?php

require_once(dirname(__FILE__) . '/Connector.class.php');
require_once(dirname(__FILE__) . '/Logger.class.php');

/**
 * Curl implementation of the Connector interface.
 */
class CurlConnector implements Connector {
	
	function __construct($url) {
		$this->log = new Logger();
		$this->url = $url;
		
		$this->curl = curl_init($this->url);
		
		curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, FALSE);
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($this->curl, CURLOPT_AUTOREFERER, TRUE);
		curl_setopt($this->curl, CURLOPT_HEADER, TRUE);
		curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, FALSE); // Disable SSL cert checking
		curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, FALSE); // cURL does not like some SSL certs apparently
		
		$this->log->debug('Curl Connector initialised with URL: ' . $this->url);
	}
	
	function setLogin($username, $password) {
		$this->username = $username;
		$this->password = $password;
		
		curl_setopt($this->curl, CURLOPT_USERPWD, $this->username . ':' . $this->password);
	}
	
	function setPostInfo($fields) {
		curl_setopt($this->curl, CURLOPT_POST, TRUE);
		curl_setopt($this->curl, CURLOPT_POSTFIELDS, $fields);
	}
	
	function setReferer($referer) {
		curl_setopt($this->curl, CURLOPT_REFERER, $url);
	}
	
	function setCookie($cookie) {
		curl_setopt($this->curl, CURLOPT_COOKIE, $cookie);
	}
	
	function connect() {
		$this->output = curl_exec($this->curl);
		
		$info = curl_getinfo($this->curl);
		$this->httpCode = $info['http_code'];
		
		$this->extractHeaders($this->output);
	}
	
	function disconnect() {
		curl_close($this->curl);
	}
	
	function getHeaders() {
		return $this->headers;
	}
	
	function getHttpCode() {
		return $this->httpCode;
	}
	
	function getOutput() {
		return $this->output;
	}
	
	/**
	 * Extract the headers from a result. This should also remove
	 * the headers from the result.
	 */
	private function extractHeaders(& $result) {
		$result = preg_replace('/HTTP\/1.1 100.*?\r\n\r\n/', '', $result);
		$headers = substr($result, 0, strpos($result, "\r\n\r\n"));
		
		$result = substr($result, strpos($result, "\r\n\r\n") + 4);
		
		$headers = explode("\r\n", $headers);
		
		$arr = array();
		
		foreach($headers as $header) {
			$pos = strpos($header, ':');
			if ($pos === FALSE) continue;
			$key = strtolower(trim(substr($header, 0, $pos)));
			$val = trim(substr($header, $pos + 1));
			
			if (is_array($arr[$key])) {
				$arr[$key][] = $val;
			}
			elseif (array_key_exists($key, $arr)) {
				$arr[$key] = array($arr[$key], $val);
			}
			else {
				$arr[$key] = $val;
			}
			
			//$this->log->debug("Header: [$key] = [$val]");
		}
		
		$this->log->debug(sprintf('Got %s headers', sizeof($arr)));
		
		$this->headers = $arr;
	}
}