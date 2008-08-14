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
	}
	
	function disconnect() {
		curl_close($this->curl);
	}
	
	function getHeaders() {
		
	}
	
	function getOutput() {
		return $this->output;
	}
}