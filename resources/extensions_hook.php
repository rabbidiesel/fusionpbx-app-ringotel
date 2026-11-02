<?php
/*
	Ringotel Integration for FusionPBX - extensions list hook

	FusionPBX has no hook for the extensions list, so the Ringotel column and the
	"Extension, VM & Ringotel" delete button are loaded by a few lines in
	app/extensions/extensions.php. A FusionPBX update replaces that file, so
	app_defaults.php calls ringotel_extensions_hook() on every App Defaults
	upgrade to put the lines back.

	Command line (no FusionPBX bootstrap needed):
		php /var/www/fusionpbx/app/ringotel/resources/extensions_hook.php [install|remove|status]
*/

if (!function_exists('ringotel_extensions_hook')) {

	/**
	 * Install, remove or check the include in app/extensions/extensions.php.
	 *
	 * @param string $action install, remove or status
	 * @return string what happened: installed, already installed, removed, not installed, or "error: ..."
	 */
	function ringotel_extensions_hook(string $action = 'install'): string {
		$file = dirname(__DIR__, 3) . '/app/extensions/extensions.php';
		$include = 'app/ringotel/ringotel_extension_list.php';
		$marker = '//ringotel: column and delete button on the extensions list (added by app/ringotel, safe to remove)';
		$block = "\t" . $marker . "\n"
			. "\tif (file_exists(dirname(__DIR__, 2).\"/" . $include . "\")) {\n"
			. "\t\trequire_once dirname(__DIR__, 2).\"/" . $include . "\";\n"
			. "\t}\n\n";

		if (!is_file($file)) {
			return 'error: ' . $file . ' not found';
		}
		$content = file_get_contents($file);
		if ($content === false) {
			return 'error: ' . $file . ' could not be read';
		}
		$installed = strpos($content, $include) !== false;

		switch ($action) {
			case 'status':
				return $installed ? 'installed' : 'not installed';

			case 'install':
				if ($installed) {
					return 'already installed';
				}
				//insert in front of the footer
				$position = strrpos($content, '//show the footer');
				if ($position === false) {
					$position = strrpos($content, 'require_once "resources/footer.php";');
				}
				if ($position === false) {
					return 'error: footer not found in ' . $file;
				}
				//start of that line, so the block keeps its own indentation
				$position = strrpos(substr($content, 0, $position), "\n") + 1;
				$content = substr($content, 0, $position) . $block . substr($content, $position);
				break;

			case 'remove':
				if (strpos($content, $marker) === false) {
					return 'not installed';
				}
				$content = str_replace($block, '', $content);
				if (strpos($content, $marker) !== false) {
					return 'error: the lines in ' . $file . ' were changed, remove them by hand';
				}
				break;

			default:
				return 'error: unknown action ' . $action;
		}

		//write in place so the owner and mode of the file stay the same
		if (!is_writable($file) || file_put_contents($file, $content, LOCK_EX) === false) {
			return 'error: ' . $file . ' is not writable';
		}
		return $action == 'install' ? 'installed' : 'removed';
	}

}

//command line
if (PHP_SAPI == 'cli' && isset($argv[0]) && realpath($argv[0]) == __FILE__) {
	$result = ringotel_extensions_hook($argv[1] ?? 'install');
	echo $result . "\n";
	exit(strpos($result, 'error') === 0 ? 1 : 0);
}
