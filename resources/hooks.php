<?php
/*
	Ringotel Integration for FusionPBX - files the app keeps outside its own folder

	1. The extensions list. FusionPBX has no hook for it, so the Ringotel column and
	   the "Extension, VM & Ringotel" delete button are loaded by a few lines in
	   app/extensions/extensions.php. A FusionPBX update replaces that file.
	2. The white label address. With the ringotel > url_alias setting (for example
	   my-app) the page is also served at /app/my-app/index.php, a one line stub
	   that loads app/ringotel/index.php, so the address bar shows that name.

	app_defaults.php runs ringotel_hooks() on every App Defaults upgrade, which puts
	both back. Command line (same thing, without an upgrade):
		php /var/www/fusionpbx/app/ringotel/resources/hooks.php [install|remove|status]
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

	/**
	 * Install, remove or check the white label address app/<alias>/index.php.
	 * Only folders holding nothing but a stub made here are ever changed or removed.
	 *
	 * @param string $action install, remove or status
	 * @param string $alias the ringotel > url_alias setting, empty for none
	 * @return string what happened
	 */
	function ringotel_url_alias(string $action = 'install', string $alias = ''): string {
		$app_path = dirname(__DIR__, 2);
		$marker = '//ringotel: white label address for app/ringotel (made from the ringotel > url_alias setting, safe to remove)';
		$stub = "<?php\n" . $marker . "\nrequire dirname(__DIR__) . '/ringotel/index.php';\n";
		$alias = strtolower(trim($alias));

		//stubs made by this function, other than the wanted one
		$is_stub_folder = function (string $folder) use ($marker): bool {
			$files = array_diff(scandir($folder) ?: [], ['.', '..']);
			return array_values($files) == ['index.php'] && strpos((string)file_get_contents($folder . '/index.php'), $marker) !== false;
		};
		$stubs = [];
		foreach (glob($app_path . '/*/index.php') ?: [] as $file) {
			if ($is_stub_folder(dirname($file))) {
				$stubs[] = basename(dirname($file));
			}
		}

		if ($action == 'status') {
			return empty($stubs) ? 'no address' : '/app/' . implode('/index.php, /app/', $stubs) . '/index.php';
		}
		if ($action != 'install' && $action != 'remove') {
			return 'error: unknown action ' . $action;
		}

		//check the new name first, so a bad one leaves the current address in place
		$wanted = $action == 'install' && $alias != '' && $alias != 'ringotel';
		if ($wanted && !preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $alias)) {
			return 'error: url_alias may only use a-z, 0-9, - and _';
		}
		if ($wanted && !in_array($alias, $stubs) && file_exists($app_path . '/' . $alias)) {
			return 'error: app/' . $alias . ' already exists and is not an address made by this app';
		}

		//remove old stubs (all of them on remove)
		$removed = [];
		foreach ($stubs as $name) {
			if ($action == 'remove' || $name != $alias) {
				if (!unlink($app_path . '/' . $name . '/index.php') || !rmdir($app_path . '/' . $name)) {
					return 'error: could not remove app/' . $name;
				}
				$removed[] = $name;
			}
		}
		$result = empty($removed) ? '' : 'removed /app/' . implode(', /app/', $removed) . '; ';

		if (!$wanted) {
			return $result . ($action == 'remove' ? 'done' : 'no address set');
		}
		if (in_array($alias, $stubs)) {
			return $result . 'already installed';
		}
		$folder = $app_path . '/' . $alias;
		if (!mkdir($folder, 0755) || file_put_contents($folder . '/index.php', $stub) === false) {
			return $result . 'error: could not create app/' . $alias;
		}
		//same owner as the app folder (www-data when this runs as root)
		$owner = fileowner(dirname(__DIR__));
		$group = filegroup(dirname(__DIR__));
		@chown($folder, $owner); @chgrp($folder, $group);
		@chown($folder . '/index.php', $owner); @chgrp($folder . '/index.php', $group);
		return $result . 'installed /app/' . $alias . '/index.php';
	}

	/**
	 * Both of the above.
	 *
	 * @param string $action install, remove or status
	 * @param string $alias the ringotel > url_alias setting
	 * @return array result per hook
	 */
	function ringotel_hooks(string $action = 'install', string $alias = ''): array {
		return [
			'extensions list' => ringotel_extensions_hook($action),
			'address' => ringotel_url_alias($action, $alias),
		];
	}

}

//command line
if (PHP_SAPI == 'cli' && isset($argv[0]) && realpath($argv[0]) == __FILE__) {
	$action = $argv[1] ?? 'install';
	//read the url_alias setting from the database
	$alias = '';
	if ($action == 'install') {
		require_once dirname(__DIR__, 3) . '/resources/require.php';
		$alias = (string)(new settings())->get('ringotel', 'url_alias', '');
	}
	$error = false;
	foreach (ringotel_hooks($action, $alias) as $name => $result) {
		echo $name . ': ' . $result . "\n";
		$error = $error || strpos($result, 'error') !== false;
	}
	exit($error ? 1 : 0);
}
