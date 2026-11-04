<?php
/*
	Ringotel Integration for FusionPBX - BulkVS SMS
	Numbers: https://portal.bulkvs.com/api/v1.0/documentation (tnRecord). Basic auth with the API username and password.
*/

class ringotel_sms_bulkvs extends ringotel_sms {

	public function id(): string {
		return 'BulkVS';
	}

	public function name(): string {
		return 'BulkVS';
	}

	public function properties(): array {
		return [
			'Username' => 'ringotel_bulkvs_username',
			'Password' => 'ringotel_bulkvs_password',
		];
	}

	//Ringotel keeps BulkVS numbers as 11 digits without the +
	public function format_number(string $number): string {
		return '1' . $number;
	}

	public function numbers(): ?array {
		$credentials = $this->credentials();
		$headers = ['Authorization: Basic ' . base64_encode($credentials['Username'] . ':' . $credentials['Password'])];
		$numbers = [];
		foreach ($this->get_json('https://portal.bulkvs.com/api/v1.0/tnRecord', $headers) as $row) {
			$number = self::normalize((string)(is_array($row) ? ($row['TN'] ?? $row['tn'] ?? '') : $row));
			if ($number != '') {
				$numbers[] = $number;
			}
		}
		return $numbers;
	}
}
