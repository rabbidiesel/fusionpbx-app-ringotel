<?php
/*
	Ringotel column, welcome email for new extensions and delete button for the FusionPBX extensions list.
	Included at the end of app/extensions/extensions.php, which app_defaults.php
	(or resources/hooks.php) adds and puts back after FusionPBX updates.
*/

//only for users who may see it, and not on the all domains view (the organization is per domain)
	if (!permission_exists("extension_ringotel") || (!empty($_GET['show']) && $_GET['show'] == "all")) {
		return;
	}

//white label name
	$ringotel_display_name = 'Ringotel';
	if (isset($settings) && $settings instanceof settings) {
		$ringotel_display_name = trim((string)$settings->get('ringotel', 'display_name', 'Ringotel')) ?: 'Ringotel';
	}

//the delete button also removes the user from Ringotel, so it needs the Ringotel delete permission
	$ringotel_can_delete = permission_exists("ringotel_extension_delete") || if_group("superadmin");

//welcome email: creates a new extension in Ringotel and activates it, which sends the welcome email (managing existing users stays in the app)
	$ringotel_can_welcome = (permission_exists("ringotel_extension_add") && permission_exists("ringotel_extension_manage")) || permission_exists("ringotel_extension_welcome") || if_group("superadmin");

//voicemail email per extension, to prefill the welcome email address
	$ringotel_voicemail_emails = [];
	if ($ringotel_can_welcome) {
		$ringotel_rows = (new database)->select("select voicemail_id, voicemail_mail_to from v_voicemails where domain_uuid = :domain_uuid", ['domain_uuid' => $_SESSION['domain_uuid']], 'all');
		foreach ((is_array($ringotel_rows) ? $ringotel_rows : []) as $ringotel_row) {
			$ringotel_email = trim(explode(',', (string)$ringotel_row['voicemail_mail_to'])[0]);
			if ($ringotel_email != '') {
				$ringotel_voicemail_emails[$ringotel_row['voicemail_id']] = $ringotel_email;
			}
		}
		unset($ringotel_rows, $ringotel_row, $ringotel_email);

		//the dialog
		echo modal::create([
			'id' => 'modal-ringotel-welcome',
			'title' => 'Send welcome email',
			'message' =>
				"<div id='ringotel_welcome_text' style='margin-bottom: 15px;'></div>".
				"<input type='email' class='formfld' id='ringotel_welcome_email' placeholder='Email address' style='width: 100%; margin-bottom: 10px;'>".
				"<select class='formfld' id='ringotel_welcome_branch' style='width: 100%; display: none;'></select>".
				"<div id='ringotel_welcome_error' style='color: #c00; margin-top: 10px; display: none;'></div>",
			'actions' =>
				button::create(['type'=>'button','label'=>$text['button-cancel'] ?? 'Cancel','icon'=>$settings->get('theme', 'button_icon_cancel'),'collapse'=>'hide-xs','onclick'=>'modal_close();']).
				button::create(['type'=>'button','id'=>'ringotel_welcome_send','label'=>'Send','icon'=>'envelope','collapse'=>'never','style'=>'float: right;','onclick'=>"ringotelWelcomeSend();"])
		]);
	}

//the column head, moved into the table by the script below
	echo "<table style='display: none;'><tr><th id='extension_ringotel'>" . escape($ringotel_display_name) . "</th></tr></table>\n";

echo "<style>";
echo "    .ringotel_info {";
echo "        display: flex;";
echo "        flex-direction: row;";
echo "        align-items: center;";
echo "    }";
echo "    .spinner_sync {";
echo "		animation: spinnerSync 1s linear infinite;";
echo "	}";
echo "    @keyframes spinnerSync {";
echo "		0% {";
echo "			transform: rotate(0deg);";
echo "		}";
echo "";
echo "		25% {";
echo "			transform: rotate(90deg);";
echo "		}";
echo "";
echo "		50% {";
echo "			transform: rotate(180deg);";
echo "		}";
echo "";
echo "		75% {";
echo "			transform: rotate(270deg);";
echo "		}";
echo "";
echo "		100% {";
echo "			transform: rotate(360deg);";
echo "		}";
echo "	}";
echo "    .popover-div {";
echo "        cursor: pointer;";
echo "        position: relative;";
echo "    }";
echo "    .popover-content {";
echo "        display: none;";
echo "        position: absolute;";
echo "        margin-top: 0;";
echo "        padding: 5px 10px;";
echo "        background-color: #fff;";
echo "        border: 1px solid #dee2e6;";
echo "        border-radius: 5px;";
echo "        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);";
echo "        z-index: 1000;";
echo "        font-family: arial;";
echo "        flex-direction: column;";
echo "        gap: 0rem;";
echo "        width: max-content;";
echo "        color: black;";
echo "        transform: translateX(55px) translateY(5px);";
echo "        line-height: 13pt;";
echo "        font-weight: 800;";
echo "    }";
echo "    div.card {";
echo "        overflow-x: unset;";
echo "    }";
echo "    .indicator-green {";
echo "    	box-shadow: inset rgb(64 217 88) 0px 0px 4px 2px;";
echo "    	border-radius: 2rem;";
echo "    	margin: 0rem 0.2rem;";
echo "    	width: 10px;";
echo "    	height: 10px;";
echo "    	text-align: center;";
echo "    	display: inline-block;";
echo "    }";
echo "    .indicator-yellow {";
echo "    	box-shadow: inset rgb(202 203 8) 0px 0px 4px 2px;";
echo "    	border-radius: 2rem;";
echo "    	margin: 0rem 0.2rem;";
echo "    	width: 10px;";
echo "    	height: 10px;";
echo "    	text-align: center;";
echo "    	display: inline-block;";
echo "    }";
echo "    .indicator-red {";
echo "    	box-shadow: inset rgb(217 64 64 / 55%) 0px 0px 4px 2px;";
echo "    	border-radius: 2rem;";
echo "    	margin: 0rem 0.2rem;";
echo "    	width: 10px;";
echo "    	height: 10px;";
echo "    	text-align: center;";
echo "    	display: inline-block;";
echo "    }";
echo "</style>";

?>
<script>
(function () {
    const target_id = 'extension_ringotel';
    const service_url = '<?php echo PROJECT_PATH; ?>/app/ringotel/service.php?method=';
    const display_name = <?php echo json_encode($ringotel_display_name); ?>;
    const can_delete = <?php echo $ringotel_can_delete ? 'true' : 'false'; ?>;
    const can_welcome = <?php echo $ringotel_can_welcome ? 'true' : 'false'; ?>;
    const voicemail_emails = <?php echo json_encode((object)$ringotel_voicemail_emails); ?>;
    let organization = null; //{ id, domain } of the Ringotel organization of this domain

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    const getOrganization = async () => await $.ajax({
            url: service_url + 'get_organization',
            type: "get",
            cache: true
        }).then((response) => {
            const { result } = JSON.parse(response.replaceAll("\\", ""));
            return result?.id ? { id: result.id, domain: result.domain } : null;
        });

    const getRingotelUsers = async (orgid) => await $.ajax({
            url: service_url + 'get_users',
            type: "get",
            cache: true,
            data: { orgid }
        }).then((response) => {
            const { result } = JSON.parse(response.replaceAll("\\", ""));
            return result;
        });

    window.showExtPopover = function (event) {
        event.currentTarget.querySelector('.popover-content').style.display = 'flex';
    };

    window.hideExtPopover = function (event) {
        event.currentTarget.querySelector('.popover-content').style.display = 'none';
    };

    //find the column to insert in front of: "Enabled" is always shown (the "Context" column is superadmin only)
    const findAnchorHeading = () => {
        const enabled = document.querySelector("th a[href*='order_by=enabled']")?.closest('th');
        if (enabled) return enabled;
        const context = document.querySelector("th a[href*='order_by=user_context']")?.closest('th');
        if (context) return context;
        return null;
    };

    //one cell per extension row
    const fillCells = (rows, shift, render) => {
        rows.forEach((tr, k) => {
            if (k == 0) return;
            document.getElementById(target_id + '_' + k)?.parentNode.remove();
            tr.children[shift - 1]?.insertAdjacentHTML('afterend', render(tr, k));
        });
    };

    const indicator = (k, css) => '<td><div class="' + css + '" style="margin-left: 2rem;" id="' + target_id + '_' + k + '"></div></td>';

    //new extension (not in Ringotel yet): red indicator plus the welcome email button
    const newExtensionCell = (k, tr) => {
        const extension = tr?.querySelector('td a')?.innerText?.trim();
        const uuid = tr.querySelector("input[name$='[uuid]']")?.value || new URLSearchParams((tr.getAttribute('href') || '').split('?')[1] || '').get('id');
        if (!can_welcome || !organization || !extension || !uuid) return indicator(k, 'indicator-red');
        return '<td><div id="' + target_id + '_' + k + '" style="margin-left: 2rem; display: flex; align-items: center;">' +
            '<div class="indicator-red"></div>' +
            '<i class="fas fa-envelope ringotel_welcome" style="margin-left: 0.6rem; cursor: pointer; color: #2196f3;"' +
            ' title="Add to ' + escapeHtml(display_name) + ' and send the welcome email"' +
            ' data-extension="' + escapeHtml(extension) + '" data-uuid="' + escapeHtml(uuid) + '"' +
            ' onclick="event.stopPropagation(); ringotelWelcomeOpen(this);"></i>' +
            '</div></td>';
    };

    const userCell = (k, ext_data) => {
        const line = (label, value) =>
            '<div style="display: flex;flex-direction: row;align-items: center;">' +
            '<div style="padding-right: 0.25rem;">' + label + ':</div><div>' + escapeHtml(value) + '</div></div>';
        let popover = line('Username', ext_data.username || '-') + line('Type', ext_data.type);
        if (ext_data?.info?.email) popover += line('Email', ext_data.info.email);
        if (Array.isArray(ext_data?.devs)) {
            for (const device of ext_data.devs) {
                if (device?.ua) popover += line('Device', device.ua);
                if (device?.ip) popover += line('IP', device.ip);
            }
        }
        return '<td>' +
            '<div class="ringotel_info popover-div" onmouseover="showExtPopover(event)" onmouseout="hideExtPopover(event)" id="' + target_id + '_' + k + '">' +
            '<i class="fa fa-info-circle indicator-green" aria-hidden="true" style="color: rgb(64 217 88);box-shadow:none;"></i>' +
            '<div class="popover-content">' + popover + '</div>' +
            '<div style="text-align: center;margin-left: 0.2rem;" class="ringotel_app" data-id="' + escapeHtml(ext_data.id) + '" data-org-id="' + escapeHtml(ext_data.orgid) + '" data-branch-id="' + escapeHtml(ext_data.branchid) + '">' +
            escapeHtml(ext_data?.name || ext_data?.username) +
            '</div></div></td>';
    };

    const initRingotelExtensions = async () => {
        const column = document.getElementById(target_id);
        const anchor = findAnchorHeading();
        if (!column || !anchor) {
            console.warn(display_name + ': extensions list column not added, the table header was not found');
            return;
        }

        //insert the column head
        anchor.before(column);
        const shift = [...column.parentNode.children].indexOf(column);
        const rows = [...column.parentNode.parentNode.children];

        //loading spinners
        fillCells(rows, shift, (tr, k) => '<td><div id="' + target_id + '_' + k + '" style="margin-left:2rem;"><i class="fas fa-spinner spinner_sync"></i></div></td>');

        //get the users of the organization for this domain
        let extensions = [];
        try {
            organization = await getOrganization();
            const orgId = organization?.id;
            if (orgId) {
                const regexp = /[\+*]/; //parks
                const users = await getRingotelUsers(orgId);
                extensions = (Array.isArray(users) ? users : []).map((ext) => ({
                    type: ext.extension.match(regexp) ? 'park' : (ext.status === 1 ? 'user' : 'extension'),
                    orgid: orgId || null,
                    id: ext?.id || null,
                    branchid: ext?.branchid || null,
                    extension: ext?.extension || null,
                    name: ext?.name || null,
                    username: ext?.name || null,
                    devs: ext?.devs || null,
                    info: ext?.info || null
                }));
            }
        } catch (error) {
            console.error(display_name + ': could not load the users', error);
        }

        fillCells(rows, shift, (tr, k) => {
            const ext_ = tr?.querySelector('td a')?.innerText;
            const ext_data = extensions.find(({ extension }) => extension == ext_);
            if (ext_data?.type == 'user') return userCell(k, ext_data);
            if (!ext_data) return newExtensionCell(k, tr);
            return indicator(k, ext_data?.type == 'extension' ? 'indicator-yellow' : 'indicator-red');
        });
    };

    initRingotelExtensions();

    //welcome email: create the extension in Ringotel as an active user, Ringotel then emails the welcome / provisioning instructions
    let welcome_target = null;
    let branches = null;

    const getBranches = async () => {
        if (branches) return branches;
        const response = await $.ajax({ url: service_url + 'get_branches', type: "get", cache: true, data: { orgid: organization.id } });
        const { result } = JSON.parse(response.replaceAll("\\", ""));
        branches = Array.isArray(result) ? result : [];
        return branches;
    };

    const welcomeError = (message) => {
        const element = document.getElementById('ringotel_welcome_error');
        element.textContent = message || '';
        element.style.display = message ? 'block' : 'none';
    };

    window.ringotelWelcomeOpen = async function (icon) {
        welcome_target = { extension: icon.getAttribute('data-extension'), uuid: icon.getAttribute('data-uuid') };
        document.getElementById('ringotel_welcome_text').innerHTML =
            'Add extension <b>' + escapeHtml(welcome_target.extension) + '</b> to ' + escapeHtml(display_name) +
            ' and email the welcome instructions (app download and login) to:';
        document.getElementById('ringotel_welcome_email').value = voicemail_emails[welcome_target.extension] || '';
        document.getElementById('ringotel_welcome_send').disabled = false;
        welcomeError('');
        modal_open('modal-ringotel-welcome', 'ringotel_welcome_email');

        //connections: pick one when there is more than one
        const select = document.getElementById('ringotel_welcome_branch');
        try {
            const list = await getBranches();
            select.innerHTML = list.map((branch) =>
                '<option value="' + escapeHtml(branch.id) + '" data-name="' + escapeHtml(branch.name) + '">' +
                escapeHtml(branch.name) + (branch.address ? ' - ' + escapeHtml(branch.address) : '') + '</option>').join('');
            select.style.display = list.length > 1 ? 'block' : 'none';
            if (list.length == 0) welcomeError('There is no connection in ' + display_name + ' yet. Create one in the app first.');
        } catch (error) {
            console.error(display_name + ': could not load the connections', error);
            welcomeError('The connections could not be loaded.');
        }
    };

    window.ringotelWelcomeSend = function () {
        const email = document.getElementById('ringotel_welcome_email').value.trim();
        const branch = document.getElementById('ringotel_welcome_branch').selectedOptions[0];
        if (!welcome_target) return;
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            welcomeError('Enter the email address the welcome email goes to.');
            return;
        }
        if (!branch) {
            welcomeError('There is no connection to add the extension to.');
            return;
        }
        const button = document.getElementById('ringotel_welcome_send');
        button.disabled = true;
        welcomeError('');
        $.ajax({
            url: service_url + 'create_users',
            type: "post",
            cache: true,
            data: {
                orgid: organization.id,
                orgdomain: organization.domain,
                branchid: branch.value,
                branchname: branch.getAttribute('data-name'),
                preusers: [{ extension_uuid: welcome_target.uuid, create: 'true', active: 'true', email: email }]
            }
        }).then((response) => {
            let res = null;
            try { res = JSON.parse(response.replaceAll("\\", "")); } catch (e) { }
            if (!res || res.success === false || res.error) {
                const detail = res?.data ?? res?.error;
                throw new Error(typeof detail == 'object' && detail ? (detail.message || JSON.stringify(detail)) : (detail || 'Unexpected response'));
            }
            modal_close();
            initRingotelExtensions();
        }).catch((error) => {
            console.error(display_name + ': welcome email failed', error);
            button.disabled = false;
            welcomeError('The extension could not be added: ' + (error?.message || error?.statusText || 'unknown error'));
        });
    };

    //delete: remove the selected extensions from Ringotel first, then delete the extensions and voicemail
    const deleteUserRequest = (orgid, userId) => $.ajax({
            url: service_url + 'delete_user',
            type: "get",
            cache: true,
            data: { id: userId, orgid: orgid }
        });

    window.deleteTheAppExt = function () {
        const button = document.getElementById('delete_app_button');
        const usersData = [];
        document.querySelectorAll("#form_list input[type='checkbox']:checked").forEach((input) => {
            const app = input.closest('tr')?.querySelector('.ringotel_app');
            if (app) {
                usersData.push({ orgid: app.getAttribute('data-org-id'), userId: app.getAttribute('data-id') });
            }
        });

        const deleteExtensions = () => {
            modal_close();
            list_action_set('delete_extension_voicemail');
            list_form_submit('form_list');
        };

        if (usersData.length == 0) {
            deleteExtensions();
            return;
        }

        const label = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner spinner_sync" style="margin-right: 0.4rem;"></i>Deleting';
        Promise.all(usersData.map(({ orgid, userId }) => deleteUserRequest(orgid, userId)))
            .then(deleteExtensions)
            .catch((error) => {
                console.error(display_name + ': delete failed', error);
                button.disabled = false;
                button.innerHTML = label;
                alert('The users could not be removed from ' + display_name + '. Nothing was deleted.');
            });
    };

    //the extra button in the delete dialog (only shown when the user may delete voicemail too)
    const deleteModalActions = document.getElementById('modal-delete-options')?.querySelector('.modal-actions');
    if (can_delete && deleteModalActions) {
        deleteModalActions.insertAdjacentHTML('afterbegin', <?php echo json_encode(button::create(['type'=>'button','id'=>'delete_app_button','label'=>'Extension, VM & '.$ringotel_display_name,'icon'=>'user','collapse'=>'never','style'=>'float: right; margin-left: 15px;','onclick'=>"deleteTheAppExt();"])); ?>);
        if (window.matchMedia("(min-width: 700px)").matches) {
            const modal_window = document.getElementById('modal-delete-options').querySelector('.modal-window > div');
            if (modal_window) modal_window.style.width = '600px';
        }
    }
})();
</script>
