<?php

# Arvin Castro, arvin@sudocode.net
# 19 June 2011
# http://sudocode.net/sources/includes/class-xhttp-php/

/*
	Changelog
	03-03-2011 Added CURLOPT_TIMEOUT = 50 and CURLOPT_CONNECTTIMEOUT = 20 as default setting
	16-04-2011 Added hook: before-curl-execution (curl handle, request data)
	           Moved response parsing to function after_curl_execution
	           * Changes made to support xhttp_multi plugin
	           Added $response['json'] in output if the content-type is application/json
	23-05-2011 Added $response['json'] in output if the content-type is text/javascript
	24-05-2011 Fixed before-curl-execution hook, changed $responseData to $requestData
	26-05-2011 Used http_build_query for the function toQueryString
	19-06-2011 Added hook to pass GET and POST parameters to utf8_encode
*/

class xhttp {

	private static $first_run = true;

	public static $hooks   = array();
	public static $plugins = array();

	public static function init() {
		if(self::$first_run) self::$first_run = false; else return;
		self::$hooks['data-preparation'][8][] = array('xhttp', 'utf8_encode');
	}

	public static function fetch($url, $requestData = array()) {

		$requestData['curl'][CURLOPT_HEADER] = true;
		$requestData['curl'][CURLOPT_RETURNTRANSFER] = true;
		self::addHookToRequest($requestData, 'return-response', array(__CLASS__, 'response_decode'), 2);

		$method = (isset($requestData['method'])) ? $requestData['method']: ((isset($requestData['post'])) ? 'post': 'get');
		return self::request($url, $method, $requestData);
	}

	public static function request($url, $method = 'get', $requestData = array()) {

		self::init();

		# Hook here: xhttp-initialization
		# array(&$urlparts, &$requestData);
		$output = self::runHook('xhttp-initialization', $requestData['hooks'], array(&$url, &$method, &$requestData));
		if($output !== null) return $output;

		# Set URL and Method
		$requestData['method'] = $method;
		$requestData['url'] = $url;

		# Dissect URL
		$urlparts = array_merge(
			array('scheme'=>'http','user'=>'','pass'=>'','host'=>'','port'=>'','path'=>'','query'=>'','fragment'=>''),
			parse_url($url));

		# Set Default Ports
		if(!$urlparts['port']) switch($urlparts['scheme']) {
			case 'http' : $urlparts['port'] =  80; break;
			case 'https': $urlparts['port'] = 443; break;
		}

		# Collect GET
		if($urlparts['query']) {
			if(!isset($requestData['get'])) $requestData['get'] = array();
			$requestData['get'] = array_merge($requestData['get'], self::toQueryArray($urlparts['query']));
			$urlparts['query']	= '';
		}

		# Default Settings
		if(!isset($requestData['curl'][CURLOPT_SSL_VERIFYPEER]))
			$requestData['curl'][CURLOPT_SSL_VERIFYPEER] = false;
		if(!isset($requestData['headers']['Expect']))
			$requestData['headers']['Expect'] = '';
		if(!isset($requestData['curl'][CURLOPT_CONNECTTIMEOUT]))
			$requestData['curl'][CURLOPT_CONNECTTIMEOUT] = 20;
		if(!isset($requestData['curl'][CURLOPT_TIMEOUT]))
			$requestData['curl'][CURLOPT_TIMEOUT] = 50;

		# Hook here: data-preparation
		# array(&$urlparts, &$requestData);
		$output = self::runHook('data-preparation', $requestData['hooks'], array(&$urlparts, &$requestData));
		if($output !== null) return $output;

		# Set GET, POST, COOKIES, HEADERS
		if(isset($requestData['get']))	   $urlparts['query'] = self::toQueryString($requestData['get']);
		if(isset($requestData['post']))    $requestData['curl'][CURLOPT_POSTFIELDS] = $requestData['post'];
		if(isset($requestData['headers'])) $requestData['curl'][CURLOPT_HTTPHEADER] = self::toCurlHeaders($requestData['headers']);
		if(isset($requestData['cookies'])) $requestData['curl'][CURLOPT_COOKIE]     = self::toCookieString($requestData['cookies']);

		# Set username and password
		if($urlparts['user']) {
			if($urlparts['pass']) $urlparts['user'] .= ':'.$urlparts['pass'];
			$requestData['curl'][CURLOPT_USERPWD] = $urlparts['user'];
			$urlparts['pass'] = $urlparts['user'] = '';
		}

		if($requestData['method'] == 'head') {
			$requestData['curl'][CURLOPT_HEADER] = true;
			$requestData['curl'][CURLOPT_NOBODY] = true;
		}

		# Build URL
		$url = $requestData['url'] = self::unparse_url($urlparts);

		# Hook here: curl-initialization
		# array(&$url, &$requestData);
		$output = self::runHook('curl-initialization', $requestData['hooks'], array(&$url, &$requestData));
		if($output !== null) return $output;

		if(!isset($requestData['headers']['User-Agent']))
			$requestData['headers']['User-Agent'] = "sudocode.net xhttp class";

		# Create cURL instance
		$ch = curl_init();

		# Set file uploads
		if(isset($requestData['files'])) {
			$requestData['headers']['Content-Type'] = 'multipart/form-data';
			if(!is_array($requestData['curl'][CURLOPT_POSTFIELDS])) $requestData['curl'][CURLOPT_POSTFIELDS] = array();
			foreach($requestData['files'] as $key => $file) {
				# Check if file is a URL, download and save to a file
				if(strpos($file, 'http') === 0) {
					$requestData['tmpfile'][$key] = tempnam('.', 'xhttp-tmp-');
					file_put_contents($requestData['tmpfile'][$key], file_get_contents($file));
					$file = $requestData['tmpfile'][$key];

				# Check if file is not a file, then dump it to a file first
				} elseif(!file_exists($file)) {
					$requestData['tmpfile'][$key] = tempnam('.', 'xhttp-tmp-');
					file_put_contents($requestData['tmpfile'][$key], $file);
					$file = $requestData['tmpfile'][$key];
				}

				$requestData['curl'][CURLOPT_POSTFIELDS][$key] = (strpos($file, '@') === 0) ? $file: '@'.$file;
			}
		}

		# Format POST fields according to Content-Type
		if(isset($requestData['curl'][CURLOPT_POSTFIELDS]) or isset($requestData['headers']['Content-Type'])) {
			if(!isset($requestData['headers']['Content-Type'])) $requestData['headers']['Content-Type'] = 'application/x-www-form-urlencoded';

			if(strpos($requestData['headers']['Content-Type'], 'application/json') !== false) {
				if(is_array($requestData['curl'][CURLOPT_POSTFIELDS]))
					$requestData['curl'][CURLOPT_POSTFIELDS] = json_encode($requestData['curl'][CURLOPT_POSTFIELDS]);
				elseif(!isset($requestData['curl'][CURLOPT_POSTFIELDS]))
					$requestData['curl'][CURLOPT_POSTFIELDS] = '{}';
			}

			if(strpos($requestData['headers']['Content-Type'], 'application/x-www-form-urlencoded') !== false) {
				if(is_array($requestData['curl'][CURLOPT_POSTFIELDS]))
					$requestData['curl'][CURLOPT_POSTFIELDS] = self::toQueryString($requestData['curl'][CURLOPT_POSTFIELDS]);
			}
		}

		# Set all cURL options
		$optionsSet = curl_setopt_array($ch, $requestData['curl']);

		if($optionsSet) {
			# Set URL and Request Method
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

			# hook here: before-curl-execution
			# array(&$ch, &$responseData)
			$output = self::runHook('before-curl-execution', $requestData['hooks'], array(&$ch, &$requestData));
			if($output !== null) return $output;

			# Execute cURL Request
			$response = curl_exec($ch);
		}

		return self::after_curl_execution($ch, $response, $requestData);
	}

	public static function after_curl_execution(&$ch, &$response, &$requestData) {

		if(!$ch) {
			trigger_error($requestData['url'], E_USER_WARNING);
		}

		# Initialize response data
		$responseData = array();
		$responseData['url'] = $requestData['url'];
		$responseData['request'] = $requestData;
		$responseData['body'] = $response;

		# Get cURL errors if any
		$responseData['error'] = array('code'=> curl_errno($ch), 'description' => curl_error($ch));
		$responseData['status'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$responseData['successful'] = ($responseData['status'] >= 200 and $responseData['status'] < 300) ? true: false;

		# hook here: after-curl-execution
		# array(&$ch, &$response, &$responseData)
		$output = self::runHook('after-curl-execution', $requestData['hooks'], array(&$ch, &$response, &$responseData));
		if($output !== null) { curl_close($ch); return $output; }

		# Close cURL handle
		curl_close($ch);

		# Cleanup tempfile
		if(isset($requestData['tmpfile'])) {
			foreach($requestData['tmpfile'] as $key => $file) unlink($file);
		}

		# hook here: return-response
		# array(&$response, &$responseData)
		$output = self::runHook('return-response', $requestData['hooks'], array(&$response, &$responseData));
		if($output !== null) return $output;

		return $responseData;
	}

	public static function utf8_encode(&$urlparts, &$requestData) {
		if(isset($requestData['get'])  and is_array($requestData['get']))  array_walk($requestData['get'],  array(__CLASS__, 'array_utf8_encode'));
		if(isset($requestData['post']) and is_array($requestData['post'])) array_walk($requestData['post'], array(__CLASS__, 'array_utf8_encode'));
	}
	public static function array_utf8_encode(&$item, $key) {
		if('UTF-8' != mb_detect_encoding($item)) $item = utf8_encode($item);
	}

	public static function response_decode(&$response, &$responseData) {

		if($responseData['error']['code'] == 0
			AND isset($responseData['request']['curl'][CURLOPT_RETURNTRANSFER])
			AND $responseData['request']['curl'][CURLOPT_RETURNTRANSFER]
			AND isset($responseData['request']['curl'][CURLOPT_HEADER])
			AND $responseData['request']['curl'][CURLOPT_HEADER]) {

			# Check for HTTP/1.1 100 Continue, skip if it exists
			$continue = stripos($response, 'HTTP/1.1 100 Continue');
			$offset = ($continue === 0) ? strpos($response, "\r\n\r\n")+4: 0;

			$blankline = strpos($response, "\r\n\r\n", $offset);
			$responseData['headers'] = self::toHeaderArray(substr($response, $offset, $blankline - $offset));
			$responseData['body']	 = substr($response, $blankline + 4);

			# Reformat cookies or $responseData['headers']['cookies']
			if(isset($responseData['headers']['set-cookie'])) {
				$responseData['headers']['cookies'] = self::cookie_decode(self::array_flatten_bfs($responseData['headers']['set-cookie']));

				if(!isset($responseData['headers']['cookies']['domain'])) {
					$domain = parse_url($responseData['url'], PHP_URL_HOST);
					if(stripos($domain, 'www.') === 0) $domain = substr($domain, 4);
					$responseData['headers']['cookies']['domain'] = strtolower($domain);
				}
				if(!isset($responseData['headers']['cookies']['path']))
					$responseData['headers']['cookies']['path'] = '/';
				if(isset($responseData['headers']['cookies']['expires']))
					$responseData['headers']['cookies']['expires'] = strtotime($responseData['headers']['cookies']['expires']);
				if(isset($responseData['headers']['cookies']['max-age']))
					$responseData['headers']['cookies']['expires'] = time() + $responseData['headers']['cookies']['max-age'];
			}

			# Reformat content-type and charset info
			if(isset($responseData['headers']['content-type'])) {
				$info = explode(';', $responseData['headers']['content-type'], 2);
				$responseData['headers']['content-type'] = $info[0];
				if(isset($info[1])) {
					preg_match('/charset= *([^; ]+)/i', $info[1], $matches);
					$responseData['headers']['charset'] = $matches[1];
				}
			}

			# Check for special content-types
			if(in_array($responseData['headers']['content-type'], array('application/json', 'text/javascript'))) {
				$responseData['json'] = json_decode($responseData['body'], true);
			}

		} else {
			$responseData['body'] = $response;
		}
	}

	public static function cookie_decode($cookies, $urldecode = false) {
		if(!is_array($cookies)) $cookies = array($cookies);
		$array = array();
		foreach($cookies as $cookie) {
			$crumbles = explode(';', $cookie);
			foreach($crumbles as $crumble) {
				$name_value = explode('=', $crumble, 2);
				if(!isset($name_value[1])) $name_value[1] = true;
				list($name, $value) = $name_value;
				$array[strtolower(trim($name))] = ($urldecode) ? urldecode($value): $value;
			}
		}
		return $array;
	}

	# Helper functions

	public static function unparse_url($urlparts) {
		if($urlparts['port'] == 80	and $urlparts['scheme'] == 'http' ) $urlparts['port'] = '';
		if($urlparts['port'] == 443 and $urlparts['scheme'] == 'https') $urlparts['port'] = '';

		if($urlparts['fragment']) $urlparts['fragment'] = '#'.$urlparts['fragment'];
		if($urlparts['query'])	  $urlparts['query']	= '?'.$urlparts['query'];
		if($urlparts['port'])	  $urlparts['port'] 	= ':'.$urlparts['port'];
		if($urlparts['user']) {
			if($urlparts['pass']) $urlparts['user'] .= ':'.$urlparts['pass'];
			$urlparts['user'] .= '@';
		}
		return $urlparts['scheme'].'://'.$urlparts['user'].$urlparts['host'].$urlparts['port'].$urlparts['path'].$urlparts['query'].$urlparts['fragment'];
	}

	public static function toQueryString($array) {
		$query = http_build_query($array);
		return $query;
	}

	public static function toQueryArray($string, $urldecode = true) {
		$pairs = explode('&', $string);
		$array = array();
		foreach($pairs as $pair) if(trim($pair)) {
			$keyvalue = explode('=', $pair, 2);
			$key   = trim($keyvalue[0]);
			$value = (isset($keyvalue[1])) ? ($urldecode) ? urldecode($keyvalue[1]): $keyvalue[1]: '';
			if($key) $array[$key] = $value;
		}
		return $array;
	}

	function toCookieString($array) {
		$string = '';
		foreach($array as $key => $value)
			$string .= $key.'='.$value.'; ';
		return $string;
	}

	# http://www.php.net/manual/en/function.http-parse-headers.php#77241
	public static function toHeaderArray($string) {
		$retVal = array();
		$fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $string));
		foreach( $fields as $field ) {
			if( preg_match('/([^:]+): (.+)/m', $field, $match) ) {
				$match[1] = strtolower(preg_replace('/(?<=^|[\x09\x20\x2D])./e', 'strtoupper("\0")', trim($match[1])));
				if( isset($retVal[$match[1]]) ) {
					$retVal[$match[1]] = array($retVal[$match[1]], $match[2]);
				} else {
					$retVal[$match[1]] = trim($match[2]);
				}
			}
		}
		return $retVal;
	}

	public static function toCurlHeaders(&$headers) {
		if(is_string($headers)) {
			return explode("\r\n", $headers);
		} else if(is_array($headers)) {
			if(isset($headers[0])) {
				return $headers;
			} else {
				$array = array();
				foreach($headers as $key => $value)
					if($value !== null) $array[] = "$key: $value";
				return $array;
			}
		}
	}

	public function array_flatten_bfs($array) {
		$flat_array = (is_string($array)) ? array($array): array();
		while(is_array($array) and null !== ($item = array_shift($array))) {
			if(is_array($item)) foreach($item as $i) $array[] = $i;
			else $flat_array[] = $item;
		}
		return $flat_array;
	}

	public static function close_connection($message = '', $headers = array()) {
		ob_end_clean();
		if(function_exists('ini_set'))
			ini_set('zlib.output_compression', 'Off');
		if(!isset($headers['content-type']))
			$headers['content-type'] = 'text/plain';
		if(isset($headers['location'])) {
			if(!isset($headers['status'])) $headers['status'] = 303;
			header('Location: '.$headers['location'], true, $headers['status']);
			unset($headers['location']);
			$message = '';
		}
		header('Connection: close');
		foreach($headers as $name => $value)
			header($name.': '.$value);

		ignore_user_abort(true);
		ob_start();
		echo $message;
		$size = ob_get_length();
		header('Content-Length: '.$size);
		ob_end_flush();
		ob_flush();
		flush();
		ob_end_clean();
		session_write_close();
		sleep(2);
	}

	# plugin system

	public static function runHook($name, &$hooks, $arguments) {

		if(isset($hooks[$name])) $functions =& $hooks[$name]; else $functions = array();

		foreach(range(0, 9) as $priority) {
			# Run global hooks
			if(isset(self::$hooks[$name][$priority])) foreach(self::$hooks[$name][$priority] as $function) {
				$output = call_user_func_array($function, $arguments);
				if($output !== null) return $output;
			}
			# Run hooks included in requestData
			if(isset($functions[$priority])) foreach($functions[$priority] as $function) {
				$output = call_user_func_array($function, $arguments);
				if($output !== null) return $output;
			}
		}
		return null;
	}

	public static function addHook($hook, $function, $priority = 5) {
		self::$hooks[$hook][$priority][] = $function;
		return true;
	}

	public static function addHookToRequest(&$request, $hook, $function, $priority = 5) {
		$request['hooks'][$hook][$priority][] = $function;
		return true;
	}

	public static function abort() {
		return false;
	}

	# Plugin class name: xhttp_NAME (dots are converted to underscores)
	# Plugin class file: plugin.xhttp.NAME.php (underscores are converted to dots)
	public static function load($plugins) {
		$plugins = explode(',', $plugins);
		foreach($plugins as $name) if(false === array_search($name, self::$plugins)) {
			$class	 = str_replace('.', '_', "xhttp_{$name}");
			$filename= str_replace('_', '.', "plugin.xhttp.{$name}.php");

			# Load class file if not loaded
			if(!class_exists($class, false)) require_once $filename;

			# Load plugin
			if(is_callable(array($class, 'load'))) {
				if(call_user_func(array($class, 'load'))) {
					self::$plugins[] = $name;
				}
			}
		}
	}
}

?>