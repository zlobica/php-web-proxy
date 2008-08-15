<?php

require_once(dirname(__FILE__) . '/Logger.class.php');
require_once(dirname(__FILE__) . '/CurlConnector.class.php');


define('INDEX_FILE_NAME', 'index.php');
define('URL_PARAM_NAME', 'proxy_url');

/**
 * PHP Proxy class.
 */
class PHPProxy {
	
	// Internal array to store cookie name/value pairs.
	// Same as $_COOKIE superglobal except this will be a buffer between remote page and browser.
	var $cookies = array();
	
	// Internal array to store POST data
	var $post = array();
	
	// An array of files to cleanup once cURL has completed
	var $cleanup_files = array();
	
	// Default script options - overrideable by values in session
	// accept_cookies: whether to accept cookies on the client side
	// cookies_session_only: whether to force all cookies to be session only
	// include_navbar: whether to include the HTML navbar in pages
	// navbar_sticky: whether the nav bar should be 'sticky' by default
	// strip_script: whether to strip out <script> tags or not
	var $opts = array(
		'accept_cookies' => true,
		'cookies_session_only' => true,
		'include_navbar' => true,
		'navbar_sticky' => true,
		'strip_script' => true
	);
	
	// The headers the script is NOT allowed to pass through from the remote server.
	// note: content length and content type are set by this script so should be included
	// here.
	var $disallowed_headers = array(
		'set-cookie', 'content-length', 'content-type', 'transfer-encoding', 'location',
		'expires', 'pragma', 'cache-control'
	);
	
	// An array of protocols that this script supports.
	var $supported_protocols = array(
		'http', 'https'
	);

	function __construct($url, $username = NULL, $password = NULL) {
		session_start();
		
		foreach($this->opts as $key => $value) {
			if (array_key_exists('pref_' . $key, $_SESSION)) {
				$this->opts[$key] = $_SESSION['pref_' . $key];
			}
		}
	
		$this->log = new Logger();
		$this->url = $url;
		
		if (strlen($url) % 4 == 0) {
			$decoded = base64_decode($url);
			if ($decoded !== FALSE) {
				$this->url = trim($decoded);
			}
		}
		
		$this->appendQueryString();
		
		$this->local_url = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
		
		$this->log->info('Connecting to: ' . $this->url);
		
		if ($username !== NULL) {
			$this->username = $username;
			$this->password = $password;
			
			$this->storeUsernamePasswordInfo();
		}
		else {
			$this->loadUsernamePasswordInfo();
		}
		
		$this->connector = new CurlConnector($this->url);
	}
	
	/** 
	 * Entry point for the script. Handles the current request
	 * by decoding request parameters, cookies etc and writes
	 * the output out to the current page.
	 */
	function handleRequest() {
		if ($this->username) {
			$connector->setLogin($this->username, $this->password);
		}
		
		$this->setReferer();
		$this->setPostParams();
		$this->setFiles();
		$this->setCookies();
		
		if (!empty($this->post)) {
			$this->log->debug(sprintf('Sending POST request; %d post vars', sizeof($this->post)));
			$this->connector->setPostInfo($this->post);
		}
		
		$this->connector->connect();
		$result = $this->connector->getOutput();
		
		$httpCode = $this->connector->getHttpCode();
		$headers = $this->connector->getHeaders();
		$contentType = $headers['content-type'];
		
		// Set cookies first in case location header redirects us and we lose cookie info
		if ($this->opts['accept_cookies'] === TRUE) {
			$this->convertAndSetCookies($headers);
		}
		
		if (array_key_exists('location', $headers)) {
			// Convert the URL because some scripts don't send the full URL (in violation of the RFC, I believe...)
			$location = $headers['location'];
			if (strpos($location, INDEX_FILE_NAME . '?' . URL_PARAM_NAME) === FALSE) {
				$location = $this->convertUrl($location);
			}
			else {
				$location = $this->local_url . '?' . URL_PARAM_NAME . '=' . base64_encode($location);
			}
			$this->log->info('Redirecting to: ' . $location);
			header('Location: ' . $location);
			die();
		}
		
		if (array_key_exists('www-authenticate', $headers)) {
			$this->log->info('Site requires authentication!');
			$matches = array();
			preg_match('/realm="(.*?)"/', $headers['www-authenticate'], $matches);
			$realm = '';
			if (sizeof($matches) > 1) {
				$realm = $matches[1];
			}
			header('Location: http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/basic-auth.php?' . URL_PARAM_NAME . '=' . base64_encode($this->url) . '&realm=' . urlencode($realm));
			die();
		}
		
		foreach($this->cleanup_files as $file) {
			$this->log->debug("Removing '$file'");
			unlink($file);
		}
		
		header('HTTP/1.1 ' . $httpCode);
		header('Content-Type: ' . $contentType);
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('Cache-Control: post-check=0, pre-check=0', FALSE);
		header('Pragma: no-cache');
		
		foreach ($headers as $key => $val) {
			if (in_array($key, $this->disallowed_headers)) continue;

			//$this->log->debug('Setting header: ' . $key . ': ' . $val);
			
			header($key . ': ' . $val);
		}
		
		$this->log->debug(sprintf('HTTP code is: [%s]', $httpCode));
		$this->log->debug(sprintf('Content type is: [%s]', $contentType));
		
		// Note: binary content should be output directly by connector
		if (strstr($contentType, 'html') !== FALSE) {
			$html = $this->parseHtml($result);
			
			//$this->log->debug(sprintf('HTML size is %d', sizeof($html)));
			
			echo $html;
		}
		elseif (strstr($contentType, 'text/css') !== FALSE) {
			$css = $this->parseCss($result);
			
			echo $css;
		}
		else {
			echo $result;
		}
		
		$this->connector->disconnect();
	}
	
	/**
	 * Sets a user preference with the specified name and value.
	 */
	function setPref($name, $value) {
		// Convert to boolean if value is "true" or "false"
		if ($value == 'true') {
			$value = TRUE;
		}
		elseif ($value == 'false') {
			$value = FALSE;
		}
		
		$this->log->debug("Setting pref [$name] = [$value]");
		$_SESSION['pref_' . $name] = $value;
	}
	
	/**
	 * This function converts cookies for the proxy, and sends them to the user.
	 */
	private function convertAndSetCookies($headers) {
		foreach ($headers as $key => $val) {
			if (strtolower($key) == 'set-cookie') {
				$cookies = $val;
				break;
			}
		}
		
		if ($cookies == NULL) {
			$this->log->debug('No cookies sent with request');
			return;
		}
		
		if (! is_array($cookies)) {
			$cookies = array($cookies);
		}
		
		// Opts shouldn't be different for all cookies set for a domain, so just
		// maintain a separate array.
		$opts = array('expires' => 0, 'path' => '', 'domain' => '', 'secure' => '', 'httponly' => '');
		
		foreach ($cookies as $cookie) {
			$this->log->debug('Extracting cookie: ' . $cookie);
			
			$extracted = explode(';', $cookie);
			
			foreach ($extracted as $val) {
				$val = trim($val);
				if (strpos($val, '=') === FALSE) {
					$opts[] = $val;
					continue;
				}
				$name = trim(substr($val, 0, strpos($val, '=')));
				
				if (in_array($name, array('expires', 'domain', 'path'))) {
					$opts[$name] = $val;
				}
				else {
					$this->cookies[$name] = trim(substr($val, strpos($val, '=') + 1));
				}
			}
		}
		
		$flattened = '';
		foreach($this->cookies as $name=>$val) {
			$flattened .= '; ' . $name . '=' . $val;
		}
		$flattened = substr($flattened, 2);
		$cookieName = $this->sanitize($this->getBaseUrl()) . '_cookie';
		
		$this->log->debug(sprintf('Setting cookie name [%s], value [%s]', $cookieName, $flattened));
		
		$expires = $this->opts['cookies_session_only'] === TRUE ? 0 : ($opts['expires'] == '') ? 0 : strtotime($opts['expires']);
		
		setcookie($cookieName, base64_encode($flattened), $expires);
	}
	
	/**
	 * Retrieve the Base URL (i.e. the domain name)
	 */
	private function getBaseUrl() {
		$baseUrl = $this->url;
		
		// Strip protocol
		if (strpos($baseUrl, '://') !== FALSE) {
			$baseUrl = substr($baseUrl, strpos($baseUrl, '://') + 3);
		}
		
		if (strpos($baseUrl, '/') !== FALSE) {
			$baseUrl = substr($baseUrl, 0, strpos($baseUrl, '/'));
		}
		
		//$this->log->debug('Base URL is: ' . $baseUrl);
		
		return $baseUrl;
	}
	
	/**
	 * Sets the cURL referer to be the referer of the current page provided that 
	 */
	private function setReferer() {
		$referer = $_SERVER['HTTP_REFERER'];
		$matches = array();
		preg_match('/' . URL_PARAM_NAME . '=(.*?)(&|$)/', $referer, $matches);
		
		if (sizeof($matches) > 0) {
			$url = base64_decode($matches[1]);
		}
		else {
			$url = $referer;
		}
		
		if (!empty($url)) {
			$this->log->debug('Setting referer to: ' . $url);
			$this->connector->setReferer($url);
		}
		else {
			$this->log->debug('Referer is empty.');
		}
	}
	
	/**
	 * Loads all cookies from the user's browser and sets them in the current
	 * curl request. Also stores them in the cookies member variable.
	 */
	private function setCookies() {
		$this->log->debug(sprintf('%d cookies sent by user', sizeof($_COOKIE)));
		
		$cookieName = $this->sanitize($this->getBaseUrl()) . '_cookie';
		
		foreach ($_COOKIE as $name => $cookie) {
			if ($cookieName != $name) {
				continue;
			}
			
			$decoded = base64_decode($cookie);
			
			$this->log->debug('Sending cookie: ' . $name . ', value: ' . $decoded);
			
			$this->connector->setCookie($decoded);
			
			$arr = explode('; ', $decoded);
			foreach ($arr as $vals) {
				$key = substr($vals, 0, strpos($vals, '='));
				$val = substr($vals, strpos($vals, '=') + 1);
				
				$this->cookies[$key] = $val;
			}
		}
	}
	
	/**
	 * Sets the POST parameters for the request. This basically extracts all
	 * POST parameters from the current request and passes them through 
	 * to CURL.
	 */
	private function setPostParams() {
		if (sizeof($_POST) == 0) {
			return;
		}
		
		foreach($_POST as $key => $val) {
			if (get_magic_quotes_gpc() == 1) {
				$val = stripslashes($val);
			}
			$this->post[$key] = $val;
		}
	}
	
	/**
	 * Similar to the post params function, except this processes any uploaded
	 * files and sends them via cURL.
	 */
	private function setFiles() {
		if (sizeof($_FILES) == 0) {
			return;
		}
		
		foreach($_FILES as $key => $file) {
			$this->log->debug('Handling uploaded file: ' . $file['name']);
			
			if ($file['size'] == 0) {
				$this->log->debug('Size is zero -- ignoring');
				continue;
			}
			
			$path = $file['tmp_name'];
			
			if (is_uploaded_file($path)) {
				$newpath = dirname($path) . '/' . $file['name'];
				$this->log->debug('Moving file to: ' . $newpath);
				move_uploaded_file($path, $newpath);
				
				$this->post[$key] = '@' . $newpath;
				
				$this->cleanup_files[] = $newpath;
			}
		}
	}
	
	/** 
	 * Appends any GET parameters to the current URL. Ignores the
	 * 'url' parameters as this is passed by the proxy script.
	 */
	private function appendQueryString() {
		if (sizeof($_GET) <= 1) {
			return;
		}
	
		if (strpos($this->url, '?') === FALSE) {
			$this->url .= '?';
		}
		
		foreach($_GET as $key => $value) {
			if (in_array($key, array(URL_PARAM_NAME, 'proxy_username', 'proxy_password'))) {
				continue;
			}
			
			$this->url .= urlencode($key) . '=' . urlencode($value) . '&';
		}
		
		$this->log->debug(sprintf('Built query string [%s]', $this->url));
	}
	
	/**
	 * Parses HTML for links etc and returns the updated version.
	 */
	private function parseHtml($html) {
		$matches = array();
		
		if ($this->opts['strip_script'] === TRUE) {
			preg_match_all('/<script.*?>.*?<\/script>|on(?:load|click|mouseover|mouseout|change)=".*?"/si', $html, $matches);
			foreach($matches[0] as $match) {
				$html = str_replace($match, '', $html);
			}
		}
		
		// Try to match href="link" and src="link"
		$matches = array();
		preg_match_all('/(action|href|src)=["\']?(.*?)["\']?(?:[\n ]|\/?>)/si', $html, $matches);
		
		for ($i=0; $i < sizeof($matches[0]); $i++) {
			$orig = $matches[0][$i];
			$url = trim($matches[2][$i]);
			
			$new = str_replace($url, $this->convertUrl($url), $orig);
			
			$html = str_replace($orig, $new, $html);
		}
		
		$matches = array();
		
		// Adds hidden input to all GET forms
		preg_match_all('/<form (?![^>]*?method=[\'"]?post[\'"]).*?>/si', $html, $matches);
		
		foreach ($matches[0] as $match) {
			$m = array();
			preg_match('/action=[\'"]?(.*?)[\'"]?[ >]/', $match, $m);
			$url = $m[1];
			
			if (strpos($url, INDEX_FILE_NAME . '?' . URL_PARAM_NAME) !== FALSE) {
				$url = substr($url, strrpos($url, '=') + 1);
				$this->log->debug('Converted URL to: ' . $url);
			}
			
			if ($url != '') {
				$new = $match . '<input type="hidden" name="' . URL_PARAM_NAME . '" value="' . $url . '" />';
				$html = str_replace($match, $new, $html);
			}
		}
		
		// Extract and parse all CSS.
		// It may be more effective to just run the entire HTML through the parseCss routine,
		// but for now extract each CSS element and parse it separately.
		$matches = array();
		preg_match_all('/<style.*?>(.*?)<\/style>/is', $html, $matches);
		
		foreach($matches[1] as $match) {
			$orig = $match;
			$css = $this->parseCss($orig);
			
			$html = str_replace($orig, $css, $html);
		}
		
		if ($this->opts['include_navbar'] === TRUE) {
			$this->includeNavbar($html);
		}
		
		return $html;
	}
	
	/**
	 * Parses css for url links.
	 */ 
	private function parseCss($css) {
		$matches = array();
		
		preg_match_all('/url\([\'"]?(.*?)[\'"]?\)|@import (?!url)["\']?(.*?)[\'"]?;/i', $css, $matches);
		
		for ($i=0; $i < sizeof($matches[0]); $i++) {
			$orig = $matches[0][$i];
			$url = trim($matches[1][$i]);
			if (empty($url)) {
				$url = trim($matches[2][$i]);
			}
			
			$new = str_replace($url, $this->convertUrl($url), $orig);
			$css = str_replace($orig, $new, $css);
		}
		
		return $css;
	}
	
	/**
	 * Include the nav bar in an HTML document.
	 */
	private function includeNavbar(& $html) {
		$this->log->debug('Including navbar: '. $this->url );
		// include() the file so that it doesn't have to be pure PHP
		// use the output buffer to prevent it being written to the page
		// in the wrong place.
		ob_start();
		include_once(dirname(__FILE__) . '/../navbar.inc.php');
		$navbar = ob_get_contents();
		ob_end_clean();
		
		if (preg_match('/<body.*?>/i', $html) !== FALSE) {
			$html = preg_replace('/<body(.*?)>/i', '<body$1>' . $navbar, $html);
		}
		else {
			// HTML Documents should have a <body> tag. However, if they don't,
			// prepend the navbar to the beginning of the document.
			$html = $navbar . $html;
		}
	}
	
	/**
	 * Converts a URL to one which will be handled by the proxy.
	 */
	private function convertUrl($url) {
		$new = '';
		$original = $url;
		
		// Ignore email links -- we cannot convert them
		if (substr($url, 0, 7) == 'mailto:') {
			return $url;
		}
		
		$base = $this->url;
		$protocol = 'http';
		
		if (strpos($base, '://') !== FALSE) {
			$protocol = substr($base, 0, strpos($base, '://'));
			$base = substr($base, strpos($base, '://') + 3);
		}
		
		if (substr($url, 0, 1) == '/') {
			// URL is relative to server root -- append to base URL
			
			// Strip off server path
			if (strpos($base, '/') !== FALSE) {
				$base = substr($base, 0, strpos($base, '/'));
			}
			
			$new = $protocol . '://' . $base . $url;
		}
		elseif (substr($url, 0, 4) == 'http') {
			// URL is an absolute URL
			$new = $url;
		}
		else {
			// URL is relative to current URL, so check whether URL is currently a directory.
			
			if (strpos($base, '/') === FALSE) { // URLs such as example.com
				$new = $base . '/' . $url;
			}
			elseif (substr($base, -1) == '/') { // directory, such as example.com/dir/
				$new = $base . $url;
			}
			else { // file, such as example.com/dir/file.html
				$base = substr($base, 0, strrpos($base, '/'));
				$new = $base . '/' . $url;
			}
			
			$new = $protocol . '://' . $new;
		}
		
		if ($new != $url) {
			//$this->log->debug(sprintf('Converted [%s] to [%s]', $original, $new));
		}
		
		// Decode HTML Entities as characters are sometimes sent to the browser encoded -- particularly &amp;s
		return $this->local_url . '?' . URL_PARAM_NAME . '=' . base64_encode(html_entity_decode($new));
	}
	
	/** 
	 * Saves the username / password info (i.e. for Basic Authentication) to the user's cookie.
	 */
	private function storeUsernamePasswordInfo() {
		$cookieName = $this->sanitize($this->getBaseUrl()) . '_auth';
		
		$this->log->debug('Saving auth data');
		
		setcookie($cookieName, $this->username . ':' . $this->password);
	}
	
	/**
	 * Loads the username / password info for a site from the user's cookie.
	 */
	private function loadUsernamePasswordInfo() {
		$cookieName = $this->sanitize($this->getBaseUrl()) . '_auth';
		
		if (array_key_exists($cookieName, $_COOKIE)) {
			$value = $_COOKIE[$cookieName];
			$this->username = substr($value, 0, strpos($value, ':'));
			$this->password = substr($value, strpos($value, ':') + 1);
			
			$this->log->debug(sprintf('Loaded auth data for [%s]', $this->username));
		}
		else {
			$this->log->debug('No auth data');
		}
	}
	
	/**
	 * Returns a sanitized version of a piece of text, i.e. lowercased and converts all non-alphanumeric
	 * characters to underscores.
	 */
	private function sanitize($text) {
		$orig = $text;
		
		$text = strtolower($text);
		$text = preg_replace('/[^a-zA-Z0-9]+/', '_', $text);
		$text = preg_replace('/^_|_$/', '', $text);
		
		//$this->log->debug("Sanitized '$orig' to '$text'");
		
		return $text;
	}
}

?>