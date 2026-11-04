<?php
/*
	Ringotel Integration for FusionPBX - SMS providers

	The providers are the ringotel_sms_<name>.php files next to this one (see ringotel_sms_interface).
	Their credentials are ringotel default settings, so a domain setting can give a client its own account.
*/

require_once dirname(__DIR__) . '/interfaces/ringotel_sms_interface.php';

abstract class ringotel_sms implements ringotel_sms_interface {

	/** @var settings */
	protected $settings;

	public function __construct($settings) {
		$this->settings = $settings;
	}

	/**
	 * All providers, by Ringotel service id
	 * @param settings $settings
	 * @return ringotel_sms[]
	 */
	public static function providers($settings): array {
		$providers = [];
		foreach (glob(__DIR__ . '/ringotel_sms_*.php') ?: [] as $file) {
			require_once $file;
			$class = basename($file, '.php');
			if (class_exists($class) && is_subclass_of($class, 'ringotel_sms')) {
				$provider = new $class($settings);
				$providers[$provider->id()] = $provider;
			}
		}
		ksort($providers);
		return $providers;
	}

	/**
	 * One provider by Ringotel service id, null when there is none
	 * @param settings $settings
	 * @param string $id
	 * @return ringotel_sms|null
	 */
	public static function provider($settings, string $id): ?ringotel_sms {
		return self::providers($settings)[$id] ?? null;
	}

	/**
	 * The credentials for Ringotel: Ringotel field name => value
	 * @return array
	 */
	public function credentials(): array {
		$credentials = [];
		foreach ($this->properties() as $field => $subcategory) {
			$credentials[$field] = trim((string)$this->settings->get('ringotel', $subcategory, ''));
		}
		return $credentials;
	}

	/**
	 * True when every credential is set
	 * @return bool
	 */
	public function is_configured(): bool {
		return !in_array('', $this->credentials(), true);
	}

	/**
	 * The numbers on the account. Providers without a number API return null (the page then offers the domain's destinations).
	 * @return array|null
	 */
	public function numbers(): ?array {
		return null;
	}

	/**
	 * A 10 digit number as Ringotel stores it: E.164 unless the provider overrides it
	 * @param string $number
	 * @return string
	 */
	public function format_number(string $number): string {
		return '+1' . $number;
	}

	/**
	 * A number as 10 digits (US / Canada), or '' when it is not one
	 * @param string $number
	 * @return string
	 */
	public static function normalize(string $number): string {
		$digits = preg_replace('/\D/', '', $number);
		if (strlen($digits) == 11 && $digits[0] == '1') {
			$digits = substr($digits, 1);
		}
		return strlen($digits) == 10 ? $digits : '';
	}

	/**
	 * GET a JSON API
	 * @param string $url
	 * @param array $headers
	 * @return array the decoded body
	 * @throws Exception when the call fails
	 */
	protected function get_json(string $url, array $headers): array {
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 20,
			CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
		]);
		$body = curl_exec($ch);
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);
		if ($body === false) {
			throw new Exception($this->name() . ': ' . $error);
		}
		if ($status < 200 || $status >= 300) {
			throw new Exception($this->name() . ' answered HTTP ' . $status);
		}
		$data = json_decode($body, true);
		if (!is_array($data)) {
			throw new Exception($this->name() . ' did not answer with JSON');
		}
		return $data;
	}
}
