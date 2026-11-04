<?php
/*
	Ringotel Integration for FusionPBX - Telnyx SMS
	Numbers: https://developers.telnyx.com/api/numbers/list-phone-numbers
*/

class ringotel_sms_telnyx extends ringotel_sms {

	public function id(): string {
		return 'Telnyx';
	}

	public function name(): string {
		return 'Telnyx';
	}

	public function properties(): array {
		return [
			'API key' => 'ringotel_telnyx_api_key',
		];
	}

	public function numbers(): ?array {
		$headers = ['Authorization: Bearer ' . $this->credentials()['API key']];
		$numbers = [];
		$page = 1;
		do {
			$data = $this->get_json('https://api.telnyx.com/v2/phone_numbers?page[size]=250&page[number]=' . $page, $headers);
			foreach ($data['data'] ?? [] as $row) {
				$number = self::normalize((string)($row['phone_number'] ?? ''));
				if ($number != '') {
					$numbers[] = $number;
				}
			}
			$pages = (int)($data['meta']['total_pages'] ?? 1);
			$page++;
		} while ($page <= $pages && $page <= 40);
		return $numbers;
	}
}
