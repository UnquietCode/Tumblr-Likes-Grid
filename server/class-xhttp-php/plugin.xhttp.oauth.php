<?php

# Arvin Castro
# December 17, 2010
# http://sudocode.net/sources/includes/class-xhttp-php/plugin-xhttp-oauth-php/

/* Changelog
 *
 * 19-06-2011 Removed utf8_encode in encode function. Moved the encoding fix in xhttp
 *
 *
 * */

class xhttp_oauth {

	public static $datastore = array();
	public static $last_basestring;
	public static $last_secretkey;

	public function oauth($profile, $consumer_key, $consumer_secret = null, $token = null, $token_secret = null) {
		$profile = ($profile instanceof xhttp_profile) ? $profile->name: ($profile) ? $profile: 'default';

		self::$datastore[$profile] = array(
			'consumer_key' => $consumer_key,
			'consumer_secret' => $consumer_secret,
			'token' => $token,
			'token_secret' => $token_secret,
		);
	}

	public function set_token($profile, $token, $token_secret = '') {
		$profile = ($profile instanceof xhttp_profile) ? $profile->name: ($profile) ? $profile: 'default';
		self::$datastore[$profile]['token'] = $token;
		self::$datastore[$profile]['token_secret'] = $token_secret;
	}

	public static function set_realm($profile, $realm) {
		$profile = ($profile instanceof xhttp_profile) ? $profile->name: ($profile) ? $profile: 'default';
		self::$datastore[$profile]['realm'] = $realm;
	}

	public static function get_oauth_header($profile, $url, $requestData = array()) {
		$profile = ($profile instanceof xhttp_profile) ? $profile->name: ($profile) ? $profile: 'default';

		# Abort request before executing curl
		xhttp::addHookToRequest($requestData, 'curl-initialization', array(__CLASS__, 'return_authorization_header'), 9);
		$method = (isset($requestData['method'])) ? $requestData['method']: (isset($requestData['post'])) ? 'post': 'get';
		$requestData['profile']['name'] = $profile;
		return xhttp::request($url, $method, $requestData);
	}

	# hook: curl-initialization
	public static function return_authorization_header(&$urlparts, &$requestData) {
		return (isset($requestData['headers']['Authorization'])) ? $requestData['headers']['Authorization']: '';
	}

	public static function oauth_method($profile, $method = 'header') {
		$profile = ($profile instanceof xhttp_profile) ? $profile->name: ($profile) ? $profile: 'default';
		self::$datastore[$profile]['method'] = strtolower($method);
	}

	public static function load() {
		xhttp::load('profile');
		xhttp::addHook('data-preparation', array(__CLASS__, 'oauth_sign_request'), 9);
		xhttp_profile::addFunction('oauth', array(__CLASS__, 'oauth'));
		xhttp_profile::addFunction('set_token', array(__CLASS__, 'set_token'));
		xhttp_profile::addFunction('set_realm', array(__CLASS__, 'set_realm'));
		xhttp_profile::addFunction('get_oauth_header', array(__CLASS__, 'get_oauth_header'));
		xhttp_profile::addFunction('oauth_method', array(__CLASS__, 'oauth_method'));
		return true;
	}

	# hook: data-preparation
	public static function oauth_sign_request(&$urlparts, &$requestData) {
		$profile = (isset($requestData['profile']['name'])) ? $requestData['profile']['name']: 'default';

		# Do nothing, if no OAuth options
		if(!isset(self::$datastore[$profile])) return;

		# Required values
		$oauth_data = array(
			'oauth_consumer_key'     => self::$datastore[$profile]['consumer_key'],
			'oauth_nonce'            => md5(mt_rand()),
			'oauth_timestamp'        => time(),
			'oauth_version'          => '1.0',
			'oauth_signature_method' => 'HMAC-SHA1',
			);
		if(isset(self::$datastore[$profile]['token']))
			$oauth_data['oauth_token'] = self::$datastore[$profile]['token'];

		# ADD GET and POST variables
		if(isset($requestData['get'])) $oauth_data = array_merge($oauth_data, $requestData['get']);
		if(isset($requestData['post']) and is_array($requestData['post']))
			$oauth_data = array_merge($oauth_data, $requestData['post']);

		# Convert array
		$parameters = array();
		foreach($oauth_data as $key => $value)
			$parameters[] = array(self::encode($key), self::encode($value));

		# Sort parameters by key, then by value
		usort($parameters, array(__CLASS__, 'parameter_sort_callback'));

		# Create base string
		$array = array();
		foreach($parameters as $index => $pair) if($pair[1] !== null) $array[] = $pair[0].'='.$pair[1];
		#foreach($parameters as $index => $pair) $array[] = $pair[0].'='.$pair[1];
		$base_string = implode('&', $array);

		# Generate URL and base string
		$url = xhttp::unparse_url($urlparts);
		$base_string = self::encode(strtoupper($requestData['method']))
			.'&'.self::encode($url).'&'.self::encode($base_string);

		# Combine Consumer Secret and Token Secret
		$secret_key = self::encode(self::$datastore[$profile]['consumer_secret'])
			.'&'.self::encode(self::$datastore[$profile]['token_secret']);

		self::$last_basestring = $base_string;
		self::$last_secretkey  = $secret_key;

		# Generate Signature
		$oauth_data['oauth_signature'] = base64_encode(hash_hmac('sha1', $base_string, $secret_key, true));

		if(self::$datastore[$profile]['method'] == 'get' or self::$datastore[$profile]['method'] == 'post') {
			$method = self::$datastore[$profile]['method'];
			foreach($oauth_data as $key => $value)
				if(substr($key, 0, 6) == 'oauth_' and $value !== null) $requestData[$method][$key] = $value;

		} else {
			# Set Host header
			$requestData['headers']['Host'] = $urlparts['host'].':'.$urlparts['port'];

			# Set Authorization Header
			$authorization = 'OAuth ';
			if(isset(self::$datastore[$profile]['realm'])) {
				if(self::$datastore[$profile]['realm'])
					$authorization .= 'realm="'.self::$datastore[$profile]['realm'].'", ';
			} else $authorization .= 'realm="'.$url.'", ';

			foreach($oauth_data as $key => $value)
				if(substr($key, 0, 6) == 'oauth_' and $value !== null) $authorization .= $key.'="'.self::encode($value).'", ';
			$requestData['headers']['Authorization'] = rtrim($authorization, ', ');
		}

		# Other Headers
		if(!isset($requestData['headers']['User-Agent']))
			$requestData['headers']['User-Agent']  = 'sudocode.net xhttp oauth plugin';

		# Clean GET data
		if(isset($requestData['get']) and is_array($requestData['get'])) {
			$vars = array();
			foreach($requestData['get'] as $key => $value) if(substr($key,0,5) != 'oauth' or self::$datastore[$profile]['method'] == 'get') $vars[$key] = $value;
			$requestData['get'] = $vars;
		}

		# Clean POST data
		if(isset($requestData['post']) and is_array($requestData['post'])) {
			$vars = array();
			foreach($requestData['post'] as $key => $value) if(substr($key,0,5) != 'oauth' or self::$datastore[$profile]['method'] == 'post') $vars[$key] = $value;
			$requestData['post'] = $vars;
		}
	}

	public static function encode($string) {
		return str_replace('%7E', '~', rawurlencode($string));
	}

	public static function parameter_sort_callback($item1, $item2) {
		$result = strcmp($item1[0], $item2[0]);
		return $result != 0 ? $result: strcmp($item1[1], $item2[1]);
	}
}

?>