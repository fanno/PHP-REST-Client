<?php
/**
 *
 *
 * @author Roman Piták <roman@pitak.net>
 *
 */

namespace RestClient;

class Exception extends \Exception {
}

class RequestException extends Exception {
}



class Request {

	const BASE_URL_KEY = 'base_url';
	const CURL_OPTIONS_KEY = 'curl_options';
	const HEADERS_KEY = 'headers';
	const METHOD_KEY = 'method';
	const PASSWORD_KEY = 'password';
	const DATA_KEY = 'data';
	const USER_AGENT_KEY = 'user_agent';
	const USERNAME_KEY = 'username';

	/** @var array Configuration */
	private $config = array();

	/** @var array Default configuration */
	private $defaultConfig = array(
		self::HEADERS_KEY => array(),
		self::CURL_OPTIONS_KEY => array(),
		self::USER_AGENT_KEY => 'Rest Client http://pitak.net',
		self::BASE_URL_KEY => null,
		self::METHOD_KEY => 'GET',
		self::USERNAME_KEY => null,
		self::PASSWORD_KEY => null
	);

	/** @var Response */
	private $response = null;

	/** @var resource Curl resource */
	private $curlResource = null;


	public function __construct($config = array()) {
		$this->setConfig(self::configArrayMergeRecursive($this->defaultConfig, $config));
	}


	/*
	 * ========== Execution ==========
	 */

	private function invalidateResponse() {
		$this->response = null;
	}

	private function responseIsValid() {
		return ($this->response instanceof Response);
	}

	private function curlResourceIsValid() {
		return (!is_null($this->curlResource));
	}

	/**
	 * @param bool $forceNew
	 * @return resource
	 */
	private function getCurlResource($forceNew = false) {

		if ((true === $forceNew) || (!$this->curlResourceIsValid())) {
			$this->curlResource = curl_init();
		}

		return $this->curlResource;

	}

	private function buildResponse() {

		// basic cURL options
		$curlOptions = $this->getOption(self::CURL_OPTIONS_KEY, array());
		$curlOptions[CURLOPT_HEADER] = true;
		$curlOptions[CURLOPT_RETURNTRANSFER] = true;
		$curlOptions[CURLOPT_USERAGENT] = $this->getOption(self::USER_AGENT_KEY);
		$curlOptions[CURLOPT_URL] = $this->getOption(self::BASE_URL_KEY);

		// cURL authentication
		$username = $this->getOption(self::USERNAME_KEY);
		$password = $this->getOption(self::PASSWORD_KEY);
		if ((!is_null($username)) && (!is_null($password))) {
			$curlOptions[CURLOPT_USERPWD] = sprintf("%s:%s", $username, $password);
		}

		// cURL HTTP headers
		$headers = $this->getOption(self::HEADERS_KEY, array());
		if (0 < count($headers)) {
			$curlOptions[CURLOPT_HTTPHEADER] = array();
			foreach ($headers as $key => $value) {
				$curlOptions[CURLOPT_HTTPHEADER][] = sprintf("%s:%s", $key, $value);
			}
		}

		// method setup
		$method = strtoupper($this->getOption(self::METHOD_KEY, 'GET'));
		switch ($method) {
			case 'GET':
				break;
			case 'POST':
				$curlOptions[CURLOPT_POST] = true;
				$curlOptions[CURLOPT_POSTFIELDS] = $this->getOption(self::DATA_KEY, array()); // todo data pre-processing if it's an array
				break;
			default:
				$curlOptions[CURLOPT_CUSTOMREQUEST] = $method;
				$curlOptions[CURLOPT_POSTFIELDS] = $this->getOption(self::DATA_KEY, array()); // todo data pre-processing
		}

		$curlResource = $this->getCurlResource();
		if (!curl_setopt_array($curlResource, $curlOptions)) {
			throw new RequestException('Invalid cURL options');
		}

		$response = new Response($curlResource);

		return $response;
	}

	/*
	 * ========== Getters ==========
	 */

	/**
	 * @param string $key Configuration key to be looked up and returned
	 * @param mixed $default Return this if key does not exist
	 * @return mixed
	 */
	public function getOption($key, $default = null) {
		return (isset($this->config[$key]) ? $this->config[$key] : $default);
	}

	/**
	 * @return Response
	 */
	public function getResponse() {

		if (!$this->responseIsValid()) {
			$this->response = $this->buildResponse();
		}

		return $this->response;
	}

	/*
	 * ========== Setters ==========
	 */

	/**
	 * @param array $config Configuration array to be merged with the current configuration.
	 * @return array Current configuration array after the merge.
	 */
	public function setConfig($config) {
		$this->config = self::configArrayMergeRecursive($this->config, $config);
		$this->invalidateResponse();
		return $this->config;
	}

	/**
	 * Set configuration parameter.
	 *
	 * @param string $key Configuration key
	 * @param mixed $value Value
	 */
	public function setOption($key, $value) {
		$this->invalidateResponse();
		$this->config[$key] = $value;
	}

	/*
	 * ========== Helpers ==========
	 */

	private static function configArrayMergeRecursive($array1, $array2) {
		if (is_array($array1) && is_array($array2)) {
			foreach ($array2 as $key => $value) {
				if (isset($array1[$key])) {
					$array1[$key] = self::configArrayMergeRecursive($array1[$key], $array2[$key]);
				} else {
					$array1[$key] = $value;
				}
			}
		} else {
			$array1 = $array2;
		}
		return $array1;
	}

}