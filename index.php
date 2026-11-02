<?php
/*
    Ringotel Integration for FusionPBX
    Version: 1.0

    The contents of this file are subject to the Mozilla Public License Version
    1.1 (the "License"); you may not use this file except in compliance with
    the License. You may obtain a copy of the License at
    http://www.mozilla.org/MPL/

    Software distributed under the License is distributed on an "AS IS" basis,
    WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
    for the specific language governing rights and limitations under the
    License.

    The Initial Developer of the Original Code is
    Vladimir Vladimirov <w@metastability.ai>
    Portions created by the Initial Developer are Copyright (C) 2022-2025
    the Initial Developer. All Rights Reserved.

    Contributor(s):
    Vladimir Vladimirov <w@metastability.ai>

    The Initial Developer of the Original Code is
    Mark J Crane <markjcrane@fusionpbx.com>
    Portions created by the Initial Developer are Copyright (C) 2008-2025
    the Initial Developer. All Rights Reserved.

    Contributor(s):
    Mark J Crane <markjcrane@fusionpbx.com>
*/

require_once dirname(__DIR__, 2) . "/resources/require.php";
require_once "resources/check_auth.php";
require_once "resources/paging.php";
require_once "resources/header.php";

//check the permissions
if (!(permission_exists("ringotel") || if_group("superadmin"))) {
	echo "access denied";
	exit;
}

//permission split inside the app:
//  ringotel_extensions - organization, connections, parks, users/extensions and integration
//  ringotel_contacts   - the Contacts tab (shared organization contacts)
$can_manage_extensions = permission_exists("ringotel_extensions") || if_group("superadmin");
$can_manage_contacts = permission_exists("ringotel_contacts") || if_group("superadmin");
if (!$can_manage_extensions) {
	if ($can_manage_contacts) {
		//contacts only: render the contacts module and nothing else
		require_once __DIR__ . "/resources/contacts_module.php";
		require_once "resources/footer.php";
		exit;
	}
	echo "access denied";
	exit;
}

//add / edit / delete inside the app (server side these are enforced in service.php;
//here the controls a user cannot use are hidden through body classes + CSS)
$can_add = permission_exists("ringotel_extension_add") || if_group("superadmin");
$can_edit = permission_exists("ringotel_extension_edit") || if_group("superadmin");
$can_delete = permission_exists("ringotel_extension_delete") || if_group("superadmin");
$can_manage = permission_exists("ringotel_extension_manage") || if_group("superadmin"); // activate / disable, resync, reset password
//view permissions for the sections of the extensions page (the Users/Extensions section itself is covered by ringotel_extensions)
$can_view_organization = permission_exists("ringotel_organization_view") || if_group("superadmin");
$can_view_connections = permission_exists("ringotel_connection_view") || if_group("superadmin");
$can_view_parks = permission_exists("ringotel_park_view") || if_group("superadmin");
$body_classes = array();
if (!$can_add) { $body_classes[] = 'ringotel-no-add'; }
if (!$can_edit) { $body_classes[] = 'ringotel-no-edit'; }
if (!$can_delete) { $body_classes[] = 'ringotel-no-delete'; }
if (!$can_manage) { $body_classes[] = 'ringotel-no-manage'; }
if (!$can_view_organization) { $body_classes[] = 'ringotel-no-organization'; }
if (!$can_view_connections) { $body_classes[] = 'ringotel-no-connections'; }
if (!$can_view_parks) { $body_classes[] = 'ringotel-no-parks'; }
if (!empty($body_classes)) {
	echo "<script>document.body.classList.add('" . implode("','", $body_classes) . "');</script>\n";
}

$language = new text;
$text = $language->get();

// for softphonepage
function get_less_than_30($str, $prefix)
{
	$lengthDomainNamePlusPrefix = strlen($str . $prefix);
	if ($lengthDomainNamePlusPrefix > 30) {
		$new_text = preg_replace('/([b-df-hj-np-tv-z])([b-df-hj-np-tv-z])/i', '$1-$2', $str);
		$exploded = explode('-', $new_text);
		array_pop($exploded);
		$Next = implode($exploded);
		return get_less_than_30($Next, $prefix);
	} else {
		return $str;
	}
}

//define the settings object
$settings = new settings(["domain_uuid" => $_SESSION['domain_uuid'], "user_uuid" => $_SESSION['user_uuid']]);

//white label name shown in place of "Ringotel"
$display_name = trim((string)$settings->get('ringotel', 'display_name', 'Ringotel')) ?: 'Ringotel';

//lookup the domain description to default the Ringotel organization name
$default_org_name = $_SESSION['domain_name'];
$db = new database;
$row = $db->select("select domain_description from v_domains where domain_uuid = :domain_uuid", ['domain_uuid' => $_SESSION['domain_uuid']], 'row');
if (is_array($row) && !empty(trim($row['domain_description'] ?? ''))) {
	$default_org_name = trim($row['domain_description']);
}
unset($db, $row);

//additional includes
require_once "resources/paging.php";

echo "<script language='JavaScript' type='text/javascript' src='" . PROJECT_PATH . "/app/ringotel/resources/javascript/qrcode/qrcode.min.js'></script>\n";
echo "<script language='JavaScript' type='text/javascript' src='" . PROJECT_PATH . "/app/ringotel/resources/javascript/html-to-image.min.js'></script>\n";
echo "<script language='JavaScript' type='text/javascript' src='" . PROJECT_PATH . "/app/ringotel/resources/javascript/multiselect-dropdown.js'></script>\n";

// ERROR Messages 
echo '	<div id="error_message" class="alert alert-danger alert-dismissible fade show" style="display: none;" role="alert">	';
echo '	  <strong>Error.</strong> <span id="error_message_text"></span>';
echo '	  <button type="button" class="close" data-dismiss="alert" aria-label="Close"> ';
echo '	    <span aria-hidden="true">&times;</span> ';
echo '	  </button> ';
echo '	</div> ';

// ORG INIT ERROR
echo '	<div id="not_exist_organization_note" class="alert alert-warning alert-dismissible fade show" style="display: none;" role="alert">	';
echo '	  <strong>You don\'t have an organization account.</strong> You should check it. If you already have, just try refresh page.';
echo '	  <button type="button" class="close" data-dismiss="alert" aria-label="Close"> ';
echo '	    <span aria-hidden="true">&times;</span> ';
echo '	  </button> ';
echo '	</div> ';

// ORGANIZATION MISSING, shown instead of the create panel to users without the organization view permission
if (!$can_view_organization) {
	echo '	<div id="not_exist_organization_limited" class="alert alert-warning" style="display: none;" role="alert">	';
	echo '	  <strong>' . escape($display_name) . ' is not set up for this domain yet.</strong> Please contact an administrator.';
	echo '	</div> ';
}

// LOADING INIT
echo '	<div id="init_loading" class="justify-content-center" style="display: flex;padding: 8rem;font-size: large;vertical-align: middle;flex-direction: row;align-items: center;align-content: center;flex-wrap: nowrap;">	';
echo ' 		<span class="spinner-grow spinner-grow-sm" role="status" style="width: 2rem;height: 2rem;" aria-hidden="true"></span>	';
echo ' 		<span>Organization loading...</span>';
echo '	</div>	';

// ORGANIZATION INIT ERROR
echo '	<div id="not_exist_organization" class="jumbotron" style="display: none;background-color: white; border: 0px solid #b5b5b5;">	';

echo '	  <p class="lead">Organization</p>	';
echo '	  <hr>	';
echo '	  <p>Use Admin Shell to create your own connection for organization.</p>	';
if (!$can_add) {
	echo '	  <p style="color: #a66;">You do not have permission to create the organization. Ask an administrator with the "ringotel_extension_add" permission.</p>	';
}
echo '	  <p class="lead">	';
echo '		<button type="button" class="btn btn-primary btn-lg" data-toggle="modal" data-target="#createOrgModal">';
echo '			<span id="create_org_text">Create</span>';
echo '			<span id="create_org_loading" style="display: none;">';
echo ' 				<span class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true"></span>	';
echo ' 				Loading...	';
echo '			</span>';
echo '		</button>';
echo '	  </p>	';
echo '	</div>	';

// ORGANIZATION INIT SUCCESSFUL

echo '<nav id="organizations" style="display:none;"> ';
echo '  <div class="nav nav-pills" id="nav-tab" role="tablist"> ';
echo '    	<a class="nav-item nav-link active" id="nav-org-tab" style="padding: 0.5rem 1rem 0.5rem 0.2rem;cursor: default;"';
// echo '			data-toggle="tab" href="#nav-org" role="tab" aria-controls="nav-org" aria-selected="true"';
echo '			>';
echo '			<h4 class="card-title" style="font-weight: 400;padding: 0.5rem 0rem 0rem 0rem;">Organization</h4>';
echo '		</a> ';

// icon for back to ORG
echo '    	<a class="nav-item nav-link active" id="nav-org-tab-back" style="padding: 0.5rem 1rem 0.5rem 0.2rem;display:none;">';
echo '			<h4 class="card-title" style="font-weight: 400;padding: 0.5rem 0rem 0rem 0rem;"><i class="fa fa-angle-left" aria-hidden="true"></i></h4>';
echo '		</a> ';

echo '    	<a class="nav-item nav-link" id="nav-integration-tab" style="cursor: pointer;" style="padding: 0.5rem 1rem 0.5rem 0.2rem;">';
// echo '			data-toggle="tab" href="#nav-integration" role="tab" aria-controls="nav-integration" aria-selected="false" ';
echo '				';
echo '			<h5 class="card-title" style="font-weight: 400;padding: 0.65rem 0rem 0rem 0rem;border-bottom: 1px solid #80808045;">Integration</h5>';
echo '		</a> ';

// Contacts tab (own permission: ringotel_contacts)
if ($can_manage_contacts) {
	echo '    	<a class="nav-item nav-link" id="nav-contacts-tab" style="cursor: pointer;padding: 0.5rem 1rem 0.5rem 0.2rem;">';
	echo '			<h5 class="card-title" style="font-weight: 400;padding: 0.65rem 0rem 0rem 0rem;border-bottom: 1px solid #80808045;">Contacts</h5>';
	echo '		</a> ';
}
echo '  </div> ';
echo '</nav> ';
echo '<div class="tab-content" id="nav-tabContent"> ';
echo '  <div class="tab-pane show active" id="nav-org" role="tabpanel" aria-labelledby="nav-org-tab">';
/////////////////////////////////////////////////////////////////////////
////// ENTRY POINT FOR ORGANIZATION, CONNECTIONS, USERS DOM ELEMENTS ////
/////////////////////////////////////////////////////////////////////////

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


// echo ' <h4 class="card-title" style="font-weight: 400; padding: 0.5rem 0.2rem;display:none;" id="organizations">Organization</h4>';
echo '	 <div id="exist_organization" style="display: none;margin-top: 16px;">';
echo '		<div class="card" style="width: 20rem;">';
echo '	   		<div class="card-body card-body-p">';
echo '				<div style="display: flex; flex-direction: row;align-items: center; justify-content: space-between;">';
echo '					<h5 class="card-title" id="organization_name" style="font-weight: 200;font-size: 14pt;overflow: hidden;text-overflow: ellipsis;text-wrap: nowrap;align-items: center;align-items: center;color: #007bff;margin: 0rem;"></h5>';
echo '					<div id="packageid_switcher_container">';
echo '					</div>';
echo '					<div style="position: absolute;bottom: 0;right: 0;padding: 1rem;">';
echo '						<a id="delete_organization" style="cursor: pointer;margin-bottom: 0rem;padding: 0.45rem;"><i class="fa fa-trash" aria-hidden="true"></i></a>';
echo '						<span id="deleting_org_loading" style="display: none;">';
echo '	 						<span class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true"></span>	';
echo '	 						Deleting...	';
echo '						</span>';
echo '					</div>';
echo '				</div>';
echo '	     		<p class="card-text" id="organization_domain" style="font-family: monospace;font-weight: 700;"></p>';
echo '				<p class="card-text"><small class="text-muted" id="organization_created"></small></p>';
echo '	   		</div>';
echo '		</div>';
echo '	 </div>';


// CONNECTION CONTAINER
echo '	<div id="connection_module" style="display:none;">';
echo '	 	<div style="display: flex; padding: 1rem 0rem;justify-content: flex-end;flex-direction: row;justify-content: space-between;">';
echo '			<h4 class="card-title" style="font-weight: 400; padding: 0.5rem 0.2rem;margin: 0;" id="connections">Connections</h4>';
echo '     		<button class="btn btn-primary" style="color: #007bff;background-color: transparent;border-color: transparent;font-weight: bold;" data-toggle="modal" data-target="#createConnectionModal">';
echo '     			<span id="create_connect_text" class="create_connect_text">+ Add Connection</span>';
echo '				<span id="create_connect_loading" class="create_connect_loading" style="display: none;">';
echo ' 					<span class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true"></span>	';
echo ' 					Loading...	';
echo '				</span>';
echo '			</button>';
echo '		</div>';

echo '		<div id="group_exist_connections" style="display: none;">';
echo '			<div id="group_exist_connections_container" style="display: flex; flex-direction: row;flex-direction: row;flex-wrap: wrap;gap: 10px;">';
// here we receive the connections
echo '			</div>';
echo '		</div>';
echo ' </div>';


// PARKS
echo '	<div id="parks_module" style="display: none;">';
echo '		<div style="display: flex; padding: 1rem 0rem;flex-direction: row;justify-content: space-between;">';
echo '			<h4 class="card-title" style="font-weight: 400; padding: 0.5rem 0.2rem;margin: 0;" id="parks">Parking Slots <span id="parks_count"></span></h4>';
echo '			<div style="margin-left: auto;">';
echo '			<button id="parks_save" class="btn btn-outline-primary parks_save" style="text-overflow: ellipsis;white-space: nowrap;border: 0px;padding: 2px 10px;margin: 10px;align-items: center;display: none;bottom: 10px;right: 130px;;font-size: 12pt;font-weight: 600;"> ';
echo '				Update Visual Call Park  ';
echo '			</button> ';
echo '			<button id="parks_close_updating" class="btn btn-outline-primary parks_close_updating" style="text-overflow: ellipsis;white-space: nowrap;display: none;border: 0px;padding: 2px 10px;margin: 10px;align-items: center;bottom: 10px;right: 40px;font-size: 12pt;font-weight: 600;color: gray;"> ';
echo '				Close ';
echo '			</button> ';
echo '			<span id="parks_save_loading" style="text-overflow: ellipsis;white-space: nowrap;border: 0px;padding: 2px 10px;margin: 10px;align-items: center;display: none;bottom: 10px;right: 100px;font-size: 12pt;font-weight: 600;"> ';
echo '				<span class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true"></span> ';
echo '				Loading...	 ';
echo '			</span> ';
echo '			<button id="parks_update" class="btn btn-outline-secondary parks_update" style="text-overflow: ellipsis;white-space: nowrap;border: 0px;padding: 2px 10px;margin: 10px;align-items: center;display: flex;bottom: 10px;right: 100px;font-size: 12pt;font-weight: 600;"> ';
echo '				Edit ';
echo '			</button> ';
echo '			</div>';
echo '     		<button data-toggle="modal" data-target="#createParksModal" id="create_parks_modal_button" class="btn btn-primary" style="text-overflow: ellipsis;white-space: nowrap;align-items: center;color: #007bff;background-color: transparent;border-color: transparent;font-weight: bold;">';
echo '     			<span id="create_parks_modal_text">+ Add Parks</span>';
echo '				<span id="create_parks_modal_loading" style="display: none;">';
echo ' 					<span class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true"></span>	';
echo ' 					Loading...	';
echo '				</span>';
echo '			</button>';
echo '		</div>';
echo '		<div id="group_exist_parks" style="display: none;">';
echo '			<div id="group_exist_parks_list" style="flex-direction: row;display: flex;flex-wrap: wrap;gap: 10px;">';
// here we receive the connections
echo '			</div>';
echo '		</div>';
echo '</div>';

// USERS
echo '		<div id="extension_module" style="display: none;">';

// Head of line with header and buttons
echo '			<div style="display: flex; padding: 1rem 0rem;flex-direction: row;justify-content: space-between;">';
echo '				<h4 class="card-title" style="font-weight: 400; padding: 0.5rem 0.2rem;margin: 0;" id="users">Users';
echo '					<span id="users_count">';
echo '					</span>';
echo '				</h4>';
echo '				<div class="reSyncAllNames" style="margin-left: auto;display: flex;align-items: center;color: #2196f3;cursor: pointer;padding: 8px;opacity: 0.75;" alt="Resync Names">';
echo '					<svg fill="#007bff" width="15px" height="15px" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">';
echo '						<path d="M370.72 133.28C339.458 104.008 298.888 87.962 255.848 88c-77.458.068-144.328 53.178-162.791 126.85-1.344 5.363-6.122 9.15-11.651 9.15H24.103c-7.498 0-13.194-6.807-11.807-14.176C33.933 94.924 134.813 8 256 8c66.448 0 126.791 26.136 171.315 68.685L463.03 40.97C478.149 25.851 504 36.559 504 57.941V192c0 13.255-10.745 24-24 24H345.941c-21.382 0-32.09-25.851-16.971-40.971l41.75-41.749zM32 296h134.059c21.382 0 32.09 25.851 16.971 40.971l-41.75 41.75c31.262 29.273 71.835 45.319 114.876 45.28 77.418-.07 144.315-53.144 162.787-126.849 1.344-5.363 6.122-9.15 11.651-9.15h57.304c7.498 0 13.194 6.807 11.807 14.176C478.067 417.076 377.187 504 256 504c-66.448 0-126.791-26.136-171.315-68.685L48.97 471.03C33.851 486.149 8 475.441 8 454.059V320c0-13.255 10.745-24 24-24z"/>';
echo '					</svg>';
echo '					<div id="resync_names" style="padding: 5px 10px;font-weight: 700;font-size: 10pt;width: 146px;">';
echo '						re-Sync Extensions';
echo '					</div>';
echo '				</div>';
echo '				<div class="reSyncAllPassword" style="display: flex;align-items: center;color: #2196f3;cursor: pointer;padding: 8px;opacity: 0.75;" alt="Resync Password">';
echo '					<svg fill="#007bff" width="15px" height="15px" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">';
echo '						<path d="M370.72 133.28C339.458 104.008 298.888 87.962 255.848 88c-77.458.068-144.328 53.178-162.791 126.85-1.344 5.363-6.122 9.15-11.651 9.15H24.103c-7.498 0-13.194-6.807-11.807-14.176C33.933 94.924 134.813 8 256 8c66.448 0 126.791 26.136 171.315 68.685L463.03 40.97C478.149 25.851 504 36.559 504 57.941V192c0 13.255-10.745 24-24 24H345.941c-21.382 0-32.09-25.851-16.971-40.971l41.75-41.749zM32 296h134.059c21.382 0 32.09 25.851 16.971 40.971l-41.75 41.75c31.262 29.273 71.835 45.319 114.876 45.28 77.418-.07 144.315-53.144 162.787-126.849 1.344-5.363 6.122-9.15 11.651-9.15h57.304c7.498 0 13.194 6.807 11.807 14.176C478.067 417.076 377.187 504 256 504c-66.448 0-126.791-26.136-171.315-68.685L48.97 471.03C33.851 486.149 8 475.441 8 454.059V320c0-13.255 10.745-24 24-24z"/>';
echo '					</svg>';
echo '					<div id="resync_password" style="padding: 5px 10px;font-weight: 700;font-size: 10pt;width: 143px;">';
echo '						re-Sync Passwords';
echo '					</div>';
echo '				</div>';
echo '	     		<button data-toggle="modal" data-target="#createUserModal" id="create_users_button" class="btn btn-primary create_users_button" style="width: 161px;color: #007bff;background-color: transparent;border-color: transparent;font-weight: bold;">';
echo '	     			<span id="create_users_text" class="create_users_text">+ Add Extensions</span>';
echo '					<span id="create_users_loading" class="create_users_loading" style="display: none;">';
echo '	 					<span class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true"></span>	';
echo '	 					Loading...	';
echo '					</span>';
echo '				</button>';
echo '			</div>';

// Users
echo '			<div id="group_exist_users" style="display: none;">';
echo '				<div id="group_exist_users_list" style="flex-direction: row;display: flex;flex-wrap: wrap;gap: 10px;">';
// here we receive the connections
echo '				</div>';
echo '			</div>';

// Extensions
echo '			<div id="group_exist_extensions" style="display: none;">';
echo '				<div style="display: flex; padding: 1rem 0rem;flex-direction: row;justify-content: space-between;">';
echo '					<h4 class="card-title" style="font-weight: 400; padding: 0.5rem 0.2rem;margin: 0;" id="users">Extensions <span id="extensions_count"></span></h4>';
echo '				</div>';
echo '			<div id="group_exist_extensions_list" style="flex-direction: row;display: flex;flex-wrap: wrap;gap: 10px;">';
// here we receive the connections
echo '			</div>';
echo '		</div>';



//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

echo '		</div> ';

echo '		</div> ';
echo '  	<div class="tab-pane show active" id="nav-integration" role="tabpanel" aria-labelledby="nav-integration-tab" style="margin: 10px 0px;display: none;">';



//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////	INTEGRATION	////////////////////////////////// INTEGRATION ////////////////////////////////// INTGRATION /////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////




// INTEGRATION INIT STATE



//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
echo '		</div> ';

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////	CONTACTS	////////////////////////////////// CONTACTS ////////////////////////////////// CONTACTS /////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
if ($can_manage_contacts) {
	echo '  	<div class="tab-pane show active" id="nav-contacts" role="tabpanel" aria-labelledby="nav-contacts-tab" style="margin: 10px 0px;display: none;">';
	require_once __DIR__ . "/resources/contacts_module.php";
	echo '		</div> ';
}

echo '</div>';

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

///////////////////////////////////////
/// MODAL FOR CREATING ORGANIZATION ///
///////////////////////////////////////
echo '	<div class="modal hide fade" id="createOrgModal" tabindex="-1" role="dialog" aria-labelledby="createOrgModalLabel" aria-hidden="true" style="display: none;">';
echo '	  <div class="modal-dialog modal-dialog-centered" role="document">';
echo '	    <div class="modal-content" style="width: max-content;">';
echo '	      <div class="modal-header">';
echo '	        <h5 class="modal-title" id="createOrgModalLabel">Create Organization</h5>';
echo '	        <button type="button" class="close" data-dismiss="modal" aria-label="Close">';
echo '	          <span aria-hidden="true">&times;</span>';
echo '	        </button>';
echo '	      </div>';
echo '	      <div class="modal-body" style="width: max-content;">';
echo '			<div class="input-group mb-3" style="flex-direction: row;width: 100%;">';
echo '			  <div class="input-group-prepend">';
echo '			    <span class="input-group-text" id="basic-addon1">Organization name</span>';
echo '			  </div>';
echo '			  <input type="text" class="form-control" id="organization_name_input" placeholder="Organization name" aria-label="Organization name" value="' . escape($default_org_name) . '">';
echo '			</div>';
echo '			<div class="input-group mb-3" style="flex-direction: row;">';
echo '			  <div class="input-group-prepend">';
echo '			    <span class="input-group-text" id="basic-addon1">Domain name</span>';
echo '			  </div>';
$default_domain_unique_name = get_less_than_30(explode('.', $_SESSION['domain_name'])[0], $settings->get('ringotel', 'domain_name_postfix', '-ringotel'));
echo '			  <input type="text" class="form-control" id="domain_unique_name" placeholder="Unique Organization Domain" aria-label="Unique Organization Domain" value=' . $default_domain_unique_name . '>';
echo '			  <div class="input-group-append">';
echo '			    <span class="input-group-text" id="basic-addon2">'.($settings->get('ringotel', 'domain_name_postfix', '-ringotel')).'</span>';
echo '			</div>';
echo '	      </div>';

// Region selector — IDs per Ringotel API docs
$ringotel_regions = [
	'10' => 'Africa (Cape Town)',
	'4'  => 'Asia Pacific (Singapore)',
	'7'  => 'Australia',
	'9'  => 'Canada',
	'8'  => 'Europe (Dublin)',
	'3'  => 'Europe (Frankfurt)',
	'5'  => 'Europe (London)',
	'6'  => 'India',
	'11' => 'South America (São Paulo)',
	'1'  => 'US East',
	'2'  => 'US West',
];
$default_region = (string) $settings->get('ringotel', 'ringotel_organization_region', '2');
echo '			<div class="input-group mb-3" style="flex-direction: row;width: 100%;">';
echo '			  <div class="input-group-prepend">';
echo '			    <span class="input-group-text" id="basic-addon1">Region</span>';
echo '			  </div>';
echo '			  <select class="form-control" id="organization_region_select" aria-label="Region">';
foreach ($ringotel_regions as $region_id => $region_label) {
	$selected = ((string) $region_id === $default_region) ? ' selected' : '';
	echo '			    <option value="' . escape($region_id) . '"' . $selected . '>' . escape($region_label) . '</option>';
}
echo '			  </select>';
echo '			</div>';
echo '	      <div class="modal-footer">';
echo '	        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>';
echo '	    	<button id="create_organization" class="btn btn-primary" role="button" style="color: white;width: 9rem;">';
echo '				<span id="create_org_text">Create</span>';
echo '				<span id="create_org_loading" style="display: none;">';
echo ' 					<span class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true"></span>	';
echo ' 					Loading...	';
echo '				</span>';
echo '			</button>	';
echo '	      </div>';
echo '	    </div>';
echo '	  </div>';
echo '	</div>';
echo '	</div>';

/////////////////////////////////////
/// MODAL FOR CREATING CONNECTION ///
/////////////////////////////////////
echo '	<div class="modal hide fade" id="createConnectionModal" tabindex="-1" role="dialog" aria-labelledby="createConnectionModalLabel" aria-hidden="true" style="display: none;">';
echo '	  <div class="modal-dialog modal-dialog-centered" role="document">';
echo '	    <div class="modal-content">';
echo '	      <div class="modal-header">';
echo '	        <h5 class="modal-title" id="createConnectionModalLabel">Create Connection</h5>';
echo '	        <button type="button" class="close" data-dismiss="modal" aria-label="Close">';
echo '	          <span aria-hidden="true">&times;</span>';
echo '	        </button>';
echo '	      </div>';
echo '	      <div class="modal-body" style="width: auto;">';
echo '			<div class="input-group mb-3">';
echo '			  <div class="input-group-prepend">';
echo '			    <div class="input-group-text" id="basic-addon1">Domain or IP address</div>';
echo '			  </div>';
echo '			  <input type="text" class="form-control" id="connection_domain" placeholder="Domain or IP address" aria-label="Domain or IP address" value=' . $_SESSION['domain_name'] . ':' . ($settings->get('ringotel', 'ringotel_organization_port', '5070')) . '>';
echo '			</div>';
echo '			<div class="input-group mb-3">';
echo '			  <div class="input-group-prepend">';
echo '			    <div class="input-group-text">Max registrations</div>';
echo '			  </div>';
echo '			  <input type="text" class="form-control" id="maxregs" placeholder="MaxRegs" aria-label="MaxRegs" value="1">';
echo '			</div>';
echo '	      </div>';
echo '	      <div class="modal-footer">';
echo '	        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>';
// button for creating connection
echo '	    	<button id="create_connect_button" class="btn btn-primary" role="button" style="color: white;width: 9rem;">';
echo '				<span id="create_connect_text" class="create_connect_text">Create</span>';
echo '				<span id="create_connect_loading" class="create_connect_loading" style="display: none;">';
echo ' 					<span class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true"></span>	';
echo ' 					Loading...	';
echo '				</span>';
echo '			</button>	';

echo '	      </div>';
echo '	    </div>';
echo '	  </div>';
echo '	</div>';
echo '	</div>';

///////////////////////////////
/// MODAL FOR CREATING USER ///
///////////////////////////////
echo '	<div class="modal hide fade" id="createUserModal" tabindex="-1" role="dialog" aria-labelledby="createUserModalLabel" aria-hidden="true" style="display: none;">';
echo '	  <div class="modal-dialog modal-dialog-centered" role="document">';
echo '	    <div class="modal-content" style="width: auto;">';
echo '	      <div class="modal-header">';
echo '	        <h5 class="modal-title" id="createUserModalLabel">Create Users</h5>';
echo '	        <button type="button" class="close" data-dismiss="modal" aria-label="Close">';
echo '	          <span aria-hidden="true">&times;</span>';
echo '	        </button>';
echo '	      </div>';

// Get List Of Extensions (with voicemail_mail_to fallback email)
$sql = "    select e.*, v.voicemail_mail_to ";
$sql .= "    from v_extensions as e ";
$sql .= "    left join v_voicemails as v ";
$sql .= "      on v.domain_uuid = e.domain_uuid ";
$sql .= "     and v.voicemail_id = e.extension ";
$sql .= "    where e.domain_uuid = :domain_uuid ";
$sql .= "    order by e.extension asc ";
$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
$db = new database;
$extensions = $db->select($sql, $parameters);
unset($sql, $db, $parameters);

echo '		  <div id="extensions" style="display: flex;flex-direction: row;">';

////////////////////////////////// left table for exist extensions /////////////////////////////////
echo '		  	<div id="exist_extensions" style="display:flex;">';
echo '				<table class="table table-hover" style="overflow-y: scroll;display: table-caption;height: 50vh;width: max-content;">';
echo '				  <thead>';
echo '				    <tr>';
echo '				      <th scope="col">';
echo '						  <input type="checkbox" aria-label="Select all" id="extensions_select_all" style="transform: scale(1.25);margin-bottom: 0.2rem;">';
echo '					  </th>';
echo '				      <th style="font-size: unset;vertical-align: middle;" scope="col">Extension</th>';
echo '				      <th style="font-size: unset;vertical-align: middle;" scope="col">Effective CID Name</th>';
echo '				      <th style="font-size: unset;vertical-align: middle;" scope="col">Outbound CID Number</th>';
echo '				      <th style="font-size: unset;vertical-align: middle;" scope="col">Email</th>';
echo '				      <th style="font-size: unset;vertical-align: middle;" scope="col">Activate</th>';
echo '				    </tr>';
echo '				  </thead>';
echo '				  <tbody id="table_exists_extensions">';

echo '				  </tbody>';
echo '				</table>';
echo '		  	</div>';
///////////////////////////////////////////////////////////////////////////////////////////////////

echo '		</div>';
echo '	      <div class="modal-footer">';

echo '			<div class="input-group">';
echo '				<div class="input-group-prepend">';
echo '					<span class="input-group-text" id="basic-addon1">';
echo '						Select Connection:';
echo '					</span>';
echo '				</div>';
echo '				<select id="users_selector_branch" class="form-control">';
echo '				</select>';
echo '			</div>';
// button for close
echo '	        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>';
// button for creating user
echo '	    	<button id="create_extensions_button" class="btn btn-primary" role="button" style="color: white;width: 20rem;height: 2.375rem;">';
echo '				<span id="create_extensions_text" class="create_extensions_text">Create</span>';
echo '				<span id="create_extensions_loading" class="create_extensions_loading" style="display: none;">';
echo ' 					<span class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true"></span>	';
echo ' 					Loading...	';
echo '				</span>';
echo '			</button>	';

echo '	      </div>';
echo '	    </div>';
echo '	  </div>';
echo '	</div>';
echo '	</div>';


/////////////////////////////////////
/// MODAL FOR CREATE PARKS ///
/////////////////////////////////////
echo '	<div class="modal hide fade" id="createParksModal" tabindex="-1" role="dialog" aria-labelledby="createParksModalLabel" aria-hidden="true" style="display: none;">';
echo '	  <div class="modal-dialog modal-dialog-centered" role="document" id="createParksModal_modal_body">';
echo '	    <div class="modal-content">';
echo '	      <div class="modal-header">';
echo '	        <h5 class="modal-title" id="createParksModalLabel">Create Parks</h5>';
echo '	        <button type="button" class="close" data-dismiss="modal" aria-label="Close">';
echo '	          <span aria-hidden="true">&times;</span>';
echo '	        </button>';
echo '	      </div>';
echo '	      <div class="modal-body" style="width: auto;" id="modal_body_createParks">';
echo '			<div class="input-group" style="padding-bottom: 1rem;">';
echo '				<div class="input-group-prepend">';
echo '					<span class="input-group-text" id="basic-addon1">';
echo '						Select Connection:';
echo '					</span>';
echo '				</div>';
echo '				<select id="users_selector_branch_for_parks" class="form-control">';
echo '				</select>';
echo '			</div>';
echo '			<div class="input-group mb-3">';
echo '			  <div class="input-group-prepend">';
echo '			    <div class="input-group-text" id="basic-addon1">From</div>';
echo '			  </div>';
echo '			  <input type="number" class="form-control" id="from_park_number" placeholder="...">';
echo '			  <div class="input-group-prepend">';
echo '			    <div class="input-group-text" id="basic-addon1">To</div>';
echo '			  </div>';
echo '			  <input type="number" class="form-control" id="to_park_number" placeholder="...">';
echo '			</div>';
echo '	      </div>';
echo '	      <div class="modal-footer">';
echo '	        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>';
// button for create Parks user
echo '	    	<button id="create_parks_button" class="btn btn-primary" role="button" style="color: white;width: 9rem;height: 2.375rem;">';
echo '				<span id="create_parks_text" 	class="create_parks_text">Create</span>';
echo '				<span id="create_parks_loading" class="create_parks_loading" style="display: none;">';
echo ' 					<span class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true"></span>	';
echo ' 					Loading...	';
echo '				</span>';
echo '			</button>	';
echo '	      </div>';
echo '	    </div>';
echo '	  </div>';
echo '	</div>';
echo '	</div>';


/////////////////////////////////////
/// MODAL FOR ACTIVATING ///
/////////////////////////////////////
echo '	<div class="modal hide fade" id="createActivationModal" tabindex="-1" role="dialog" aria-labelledby="createActivationModalLabel" aria-hidden="true" style="display: none;">';
echo '	  <div class="modal-dialog modal-dialog-centered" role="document" id="createActivationModal_modal_body">';
echo '	    <div class="modal-content">';
echo '	      <div class="modal-header">';
echo '	        <h5 class="modal-title" id="createActivationModalLabel">Activating User</h5>';
echo '	        <button type="button" class="close" data-dismiss="modal" aria-label="Close">';
echo '	          <span aria-hidden="true">&times;</span>';
echo '	        </button>';
echo '	      </div>';
echo '	      <div class="modal-body" style="width: auto;" id="modal_body_activateUser">';
echo '			<div class="input-group mb-3">';
echo '			  <div class="input-group-prepend">';
echo '			    <div class="input-group-text" id="basic-addon1">Email</div>';
echo '			  </div>';
echo '			  <input type="text" class="form-control" id="email_for_user" placeholder="Email for user">';
echo '			</div>';
echo '	      </div>';
echo '	      <div class="modal-footer">';
echo '	        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>';
// button for activate user
echo '	    	<button id="create_user_activate_button" class="btn btn-primary" role="button" style="color: white;width: 9rem;height: 2.375rem;">';
echo '				<span id="create_user_activate_text" class="create_user_activate_text">Activate</span>';
echo '				<span id="create_user_activate_loading" class="create_user_activate_loading" style="display: none;">';
echo ' 					<span class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true"></span>	';
echo ' 					Loading...	';
echo '				</span>';
echo '			</button>	';
echo '	      </div>';
echo '	    </div>';
echo '	  </div>';
echo '	</div>';
echo '	</div>';

/////////////////////////////////////
/// MODAL FOR RESET PASSWORD ///
/////////////////////////////////////
echo '	<div class="modal hide fade" id="resetPasswordModal" tabindex="-1" role="dialog" aria-labelledby="resetPasswordModalLabel" aria-hidden="true" style="display: none;">';
echo '	  <div class="modal-dialog modal-dialog-centered" role="document" id="resetPasswordModal_modal_body">';
echo '	    <div class="modal-content">';
echo '	      <div class="modal-header">';
echo '	        <h5 class="modal-title" id="resetPasswordModalLabel">Reset User Password</h5>';
echo '	        <button type="button" class="close" data-dismiss="modal" aria-label="Close">';
echo '	          <span aria-hidden="true">&times;</span>';
echo '	        </button>';
echo '	      </div>';
echo '	      <div class="modal-body" style="width: auto;" id="modal_body_resetPassword">';
echo '			<div class="input-group mb-3">';
echo '			  <div class="input-group-prepend">';
echo '			    <div class="input-group-text" id="basic-addon1">Email</div>';
echo '			  </div>';
echo '			  <input type="text" class="form-control" id="email_for_reset_password" placeholder="Email for user">';
echo '			</div>';
echo '	      </div>';
echo '	      <div class="modal-footer">';
echo '	        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>';
// button for reset user password
echo '	    	<button id="reset_user_password_button" class="btn btn-primary" role="button" style="color: white;width: 9rem;">';
echo '				<span id="reset_user_password_text" class="reset_user_password_text">Reset</span>';
echo '				<span id="reset_user_password_loading" class="reset_user_password_loading" style="display: none;">';
echo ' 					<span class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true"></span>	';
echo ' 					Loading...	';
echo '				</span>';
echo '			</button>	';
echo '	      </div>';
echo '	    </div>';
echo '	  </div>';
echo '	</div>';
echo '	</div>';


/////////////////////////////////////
/// Organization Package Error
/////////////////////////////////////
echo '	<div id="package_org_popup" style="-webkit-transition: all 0.5s;transition: all 0.5s;position: absolute;display: block;width: 14rem;opacity:0;" class="toast" role="alert" aria-live="assertive" aria-atomic="true">';
echo '	  <div class="toast-header" style="color: #bb1a1a;">';
echo '		<i class="fa fa-exclamation-circle" aria-hidden="true" style="padding: 0rem 0.5rem 0rem 0rem;color: #cf0000;"></i>';
echo '	    <strong class="mr-auto">Error</strong>';
echo '	    <button id="package_org_popup_close" type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">';
echo '	      <span aria-hidden="true">&times;</span>';
echo '	    </button>';
echo '	  </div>';
echo '	  <div id="package_org_popup_text" class="toast-body">';
echo '	  </div>';
echo '	</div>';
/////////////////////////////////////

include "resources/footer.php";

//inbound destinations for the Bandwidth integration (manage numbers)
$destination_options = '';
$sql = "select destination_number from v_destinations ";
$sql .= "where domain_uuid = :domain_uuid ";
$sql .= "and destination_type = :destination_type ";
$sql .= "order by destination_number asc ";
$parameters['destination_type'] = 'inbound';
$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
$db = new database;
$destinations = $db->select($sql, $parameters);
unset($sql, $db, $parameters);
if (is_array($destinations)) {
	foreach ($destinations as $dest) {
		$destination_options .= '<option value="' . escape($dest['destination_number']) . '">' . escape($dest['destination_number']) . '</option>';
	}
}
unset($destinations, $dest);

//styles and script of the page (the version query string makes browsers load a changed file)
$css_file = '/app/ringotel/resources/css/ringotel.css';
$js_file = '/app/ringotel/resources/javascript/ringotel.js';
echo "<link rel='stylesheet' type='text/css' href='" . PROJECT_PATH . $css_file . "?v=" . filemtime(dirname(__DIR__, 2) . $css_file) . "'>\n";

?>
<script>
	//values from the server used by ringotel.js
	const RINGOTEL_DISPLAY_NAME = <?php echo json_encode($display_name); ?>;
	const RINGOTEL_PROJECT_PATH = <?php echo json_encode(PROJECT_PATH); ?>;
	const RINGOTEL_EXTENSIONS = <?php echo json_encode($extensions); ?>;
	const RINGOTEL_DEFAULT_DOMAIN_UNIQUE_NAME = <?php echo json_encode($default_domain_unique_name); ?>;
	const RINGOTEL_CONNECTION_DOMAIN = <?php echo json_encode($_SESSION['domain_name'] . ':' . $settings->get('ringotel', 'ringotel_organization_port', '5070')); ?>;
	const RINGOTEL_DESTINATION_OPTIONS = <?php echo json_encode($destination_options); ?>;
</script>
<?php
echo "<script language='JavaScript' type='text/javascript' src='" . PROJECT_PATH . $js_file . "?v=" . filemtime(dirname(__DIR__, 2) . $js_file) . "'></script>\n";
