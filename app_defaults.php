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

//give the admin group the app: open it, see the extensions and the Ringotel column, and edit contacts
//adding, editing, deleting and managing Ringotel users stays superadmin only. A permission that already has a row
//for the group (assigned or not) is left alone, so a change made in Group Manager is kept
	if ($domains_processed == 1) {
		$sql = "select group_uuid from v_groups where group_name = 'admin' and domain_uuid is null ";
		$ringotel_admin_uuid = $database->select($sql, null, 'column');
		if (!empty($ringotel_admin_uuid)) {
			foreach (['ringotel', 'ringotel_extensions', 'extension_ringotel', 'ringotel_contacts'] as $ringotel_permission) {
				$sql = "select count(*) from v_group_permissions where group_uuid = :group_uuid and permission_name = :permission_name ";
				$parameters = ['group_uuid' => $ringotel_admin_uuid, 'permission_name' => $ringotel_permission];
				if ($database->select($sql, $parameters, 'column') == 0) {
					$sql = "insert into v_group_permissions (group_permission_uuid, permission_name, permission_protected, permission_assigned, group_name, group_uuid, insert_date) ";
					$sql .= "values (:group_permission_uuid, :permission_name, 'false', 'true', 'admin', :group_uuid, now()) ";
					$parameters['group_permission_uuid'] = uuid();
					$database->execute($sql, $parameters);
				}
			}

			//show the menu item to admins who can open the app
			$sql = "select count(*) from v_group_permissions where group_uuid = :group_uuid and permission_name = 'ringotel' and permission_assigned = 'true' ";
			if ($database->select($sql, ['group_uuid' => $ringotel_admin_uuid], 'column') > 0) {
				$sql = "select menu_item_uuid, menu_uuid from v_menu_items ";
				$sql .= "where menu_item_uuid = 'dfc8340a-188b-4780-9c7a-19294ec55aef' or menu_item_link = '/app/ringotel/index.php' ";
				$parameters = [];
				$ringotel_alias = strtolower(trim((string)(new settings(['database' => $database]))->get('ringotel', 'url_alias', '')));
				if (preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $ringotel_alias)) {
					$sql .= "or menu_item_link = :alias_link ";
					$parameters['alias_link'] = '/app/' . $ringotel_alias . '/index.php';
				}
				foreach ($database->select($sql, $parameters ?: null, 'all') ?: [] as $row) {
					$sql = "select count(*) from v_menu_item_groups where menu_item_uuid = :menu_item_uuid and group_uuid = :group_uuid ";
					if ($database->select($sql, ['menu_item_uuid' => $row['menu_item_uuid'], 'group_uuid' => $ringotel_admin_uuid], 'column') == 0) {
						$sql = "insert into v_menu_item_groups (menu_item_group_uuid, menu_uuid, menu_item_uuid, group_name, group_uuid, insert_date) ";
						$sql .= "values (:menu_item_group_uuid, :menu_uuid, :menu_item_uuid, 'admin', :group_uuid, now()) ";
						$database->execute($sql, ['menu_item_group_uuid' => uuid(), 'menu_uuid' => $row['menu_uuid'], 'menu_item_uuid' => $row['menu_item_uuid'], 'group_uuid' => $ringotel_admin_uuid]);
					}
				}
			}
		}
		unset($sql, $parameters, $row, $ringotel_admin_uuid, $ringotel_permission, $ringotel_alias);
	}

?>
