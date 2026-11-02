<?php

//add the Ringotel column and delete button to the extensions list, and put them back after a FusionPBX update replaced extensions.php
	if ($domains_processed == 1) {
		require_once __DIR__ . '/resources/extensions_hook.php';
		$ringotel_hook_result = ringotel_extensions_hook('install');
		if (PHP_SAPI == 'cli' && $ringotel_hook_result != 'already installed') {
			echo "Ringotel extensions list: " . $ringotel_hook_result . "\n";
		}
		unset($ringotel_hook_result);
	}

?>
