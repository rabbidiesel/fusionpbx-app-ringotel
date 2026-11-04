<?php
/*
	Ringotel Integration for FusionPBX - Bandwidth SMS
*/

class ringotel_sms_bandwidth extends ringotel_sms {

	public function id(): string {
		return 'Bandwidth';
	}

	public function name(): string {
		return 'Bandwidth';
	}

	public function properties(): array {
		return [
			'Username' => 'ringotel_bandwidth_integration_username',
			'Password' => 'ringotel_bandwidth_integration_password',
			'Account ID' => 'ringotel_bandwidth_integration_account_id',
			'Application ID' => 'ringotel_bandwidth_integration_application_id',
		];
	}
}
