<?php
/*
	Ringotel Integration for FusionPBX - an SMS provider (Ringotel integration) the app can connect

	Each provider is one class in resources/classes/ringotel_sms_<name>.php that extends ringotel_sms.
	To add one: copy ringotel_sms_telnyx.php, set the Ringotel service id, the credential fields
	(Ringotel field name => default setting) and, if the provider has an API for it, list the numbers.
	Add the default settings to app_config.php.
*/

interface ringotel_sms_interface {
	/** The Ringotel service id, for example "Telnyx" */
	public function id(): string;

	/** The name shown on the page */
	public function name(): string;

	/** The Ringotel credential fields: Ringotel field name => ringotel default setting subcategory */
	public function properties(): array;

	/** The numbers on the provider account as 10 digit strings, or null when the provider cannot list them */
	public function numbers(): ?array;

	/** A 10 digit number the way Ringotel stores it for this provider, for example +12125551234 */
	public function format_number(string $number): string;
}
