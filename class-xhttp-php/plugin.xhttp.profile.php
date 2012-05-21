<?php

# Arvin Castro
# January 31, 2011
# http://sudocode.net/sources/includes/class-xhttp-php/plugin-xhttp-profile-php/

class xhttp_profile {

	public static $instanceCount = 0;
	public static $datastore = array();
	public static $functions = array();

	public $name;

	public function __construct($name = null, $requestData = array()) {
		$this->name = ($name) ? $name: self::generateName();
		self::$datastore[$this->name]['requestData'] = $requestData;

		# load this plugin
		xhttp::load('profile');
	}

	public static function generateName($length=5) {
		return 'profile'.self::$instanceCount++;
	}

	public function setRequestData($requestData) {
		self::$datastore[$this->name]['requestData'] = $requestData;
	}

	public function getRequestData($profile) {
		return (isset(self::$datastore[$profile]['requestData'])) ? self::$datastore[$profile]['requestData']: array();
	}

	public function getResponse($field = null) {
		if($field) {
			if(isset(self::$datastore[$this->name]['response'][$field]))
				return self::$datastore[$this->name]['response'][$field];
			return null;

		} else {
			return self::$datastore[$this->name]['response'];
		}
	}

	# Over-written xhttp functions

	public function fetch($url, $requestData = array()) {
		$requestData['profile']['name'] = $this->name;
		$response = xhttp::fetch($url, $requestData);
		self::$datastore[$this->name]['response'] = $response;
		return $response;
	}

	public function request($url, $method = 'get', $requestData = array()) {
		$requestData['profile']['name'] = $this->name;
		$response = xhttp::request($url, $method, $requestData);
		self::$datastore[$this->name]['response'] = $response;
		return $response;
	}

	public static function load() {
		xhttp::addHook('data-preparation', array(__CLASS__, 'apply_profile_requestdata'), $priority = 1);
		return true;
	}

	# hook: data-preparation
	public static function apply_profile_requestdata(&$urlparts, &$requestData) {
		$profile = (isset($requestData['profile']['name'])) ? $requestData['profile']['name']: 'default';
		$masterRequestData = self::getRequestData($profile);
		foreach($masterRequestData as $key => &$data) {
			if(!isset($requestData[$key])) $requestData[$key] = $data;
			else $requestData[$key] += $data;
		}
	}

	# Plugin system
	public static function addFunction($name, $function) {
		self::$functions[$name] = $function;
	}

	public function __call($name, $arguments) {
		if(isset(self::$functions[$name])) {
			if(is_array($arguments))
				array_unshift($arguments, $this->name);
			else
				$arguments = array($this->name);
			return call_user_func_array(self::$functions[$name], $arguments);
		} else {
			throw new Exception("Function [$name] is not defined.");
		}
	}
}

?>