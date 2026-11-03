<?php

//menu title and address: the ringotel > display_name and url_alias default settings, so the app can be white labeled
	$ringotel_display_name = 'Ringotel';
	$ringotel_url_alias = '';
	try {
		$ringotel_settings = new settings();
		$ringotel_display_name = trim((string)$ringotel_settings->get('ringotel', 'display_name', 'Ringotel')) ?: 'Ringotel';
		$ringotel_url_alias = strtolower(trim((string)$ringotel_settings->get('ringotel', 'url_alias', '')));
		unset($ringotel_settings);
	}
	catch (Throwable $e) {
		//no database yet (fresh install), keep the default name
	}

$apps[$x]['menu'][0]['title']['en-us'] = $ringotel_display_name;
$apps[$x]['menu'][0]['title']['es-cl'] = "";
$apps[$x]['menu'][0]['title']['es-mx'] = "";
$apps[$x]['menu'][0]['title']['fr-fr'] = "";
$apps[$x]['menu'][0]['title']['fr-ca'] = " ";
$apps[$x]['menu'][0]['title']['pt-pt'] = "";
$apps[$x]['menu'][0]['title']['pt-br'] = "";
$apps[$x]['menu'][0]['title']['pl'] = " ";
$apps[$x]['menu'][0]['title']['uk'] = "";
$apps[$x]['menu'][0]['title']['sv-se'] = "";
$apps[$x]['menu'][0]['title']['ro'] = "";
$apps[$x]['menu'][0]['title']['de-at'] = "";
$apps[$x]['menu'][0]['title']['ar-eg'] = "";
$apps[$x]['menu'][0]['title']['he'] = "";
$apps[$x]['menu'][0]['uuid'] = "dfc8340a-188b-4780-9c7a-19294ec55aef";
$apps[$x]['menu'][0]['parent_uuid'] = "fd29e39c-c936-f5fc-8e2b-611681b266b5";
$apps[$x]['menu'][0]['category'] = "internal";
$apps[$x]['menu'][0]['path'] = "/app/" . (preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $ringotel_url_alias) ? $ringotel_url_alias : "ringotel") . "/index.php";
$apps[$x]['menu'][0]['groups'][] = "superadmin";
// $apps[$x]['menu'][0]['groups'][] = "admin";

?>
