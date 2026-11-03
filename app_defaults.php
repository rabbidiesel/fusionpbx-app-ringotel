<?php

//put back what the app keeps outside its folder (the extensions list lines and the white label address), for example after a FusionPBX update replaced extensions.php
	if ($domains_processed == 1) {
		require_once __DIR__ . '/resources/hooks.php';
		$ringotel_default_settings = new settings(['database' => $database]);
		$ringotel_hook_results = ringotel_hooks('install', (string)$ringotel_default_settings->get('ringotel', 'url_alias', ''));
		if (PHP_SAPI == 'cli') {
			foreach ($ringotel_hook_results as $name => $result) {
				if ($result != 'already installed' && $result != 'no address set') {
					echo "Ringotel " . $name . ": " . $result . "\n";
				}
			}
		}
		unset($ringotel_default_settings, $ringotel_hook_results);
	}

?>
