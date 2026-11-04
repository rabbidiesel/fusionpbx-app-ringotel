# Ringotel Integration for FusionPBX

https://github.com/user-attachments/assets/cab7ccd9-1b0b-4d2e-9a7f-adf9d9a02955

## Ringotel Install

Install Ringotel from the command line on your server
```
cd /var/www/fusionpbx/app
git clone https://github.com/fusionpbx/fusionpbx-app-ringotel.git ringotel
git config --global --add safe.directory /var/www/fusionpbx/app/ringotel
php /var/www/fusionpbx/core/upgrade/upgrade.php --permissions
php /var/www/fusionpbx/core/upgrade/upgrade.php
```

The app must be installed in `app/ringotel`. The upgrade's App Defaults step (included in the full upgrade above) also adds the Ringotel column to the extensions list, see section 2.

**White label / display name**

The folder stays `ringotel`; the name users see comes from one setting:
- Default Settings
  - Category: ringotel
  - Subcategory: display_name
  - Value: the name to show, for example `My Softphone` (default `Ringotel`)

It is used on the app page and browser tab, for the column on the extensions list and for the delete button there. It can also be set per domain in Domain Settings. The menu title is taken from it when the menu is restored (`upgrade.php --menu`); to rename the menu item of an existing install without restoring the whole menu, use Advanced -> Menu Manager.

To also hide `ringotel` in the address bar, set **ringotel > url_alias** (for example `my-app`, letters, digits, `-` and `_`). The App Defaults upgrade, or `php /var/www/fusionpbx/app/ringotel/resources/hooks.php install`, then creates `app/my-app/index.php`, a one line page that loads `app/ringotel/index.php`. Point the menu item at `/app/my-app/index.php` in Menu Manager (a menu restore uses the alias by itself). Changing the alias removes the old address; the app only touches folders it created itself.

**Ringotel Website**
- Menu -> Integrations -> API Settings
  - Webhook URL: your.domain.com/app/ringotel/webhook.php
- Create Key
- Save the key to use later

**Your FusionPBX Server**
- Default Settings
  - Category: ringotel
  - Subcategory: ringotel_token
  - Value: Save the API Key here
  

## 1\. Ringotel Overview

The Ringotel Feature Side App integrates with FusionPBX to help administrators:

*   **Manage Extensions**: Control FusionPBX extensions.
    
*   **Bind Extensions to Ringotel**: Connect and synchronize extensions with Ringotel.
    
*   **Monitor Ringotel Status**: View Ringotel status for extensions within FusionPBX.

*   **SMS Integrations**: Connect Bandwidth, Telnyx or BulkVS to the Ringotel organization and give users SMS/MMS on the provider's numbers.
    
*   **Call Parks**: Enable and manage call parking functionality directly within the Ringotel environmen.

*   **Contacts**: Manage the organization's shared contacts (the "Manage contacts" list in the Ringotel portal). Contacts created here appear in the contact book of every Ringotel user in the organization. Contacts have their own tab and their own permission. Contacts can be selected individually or all at once for bulk edit (organization, position, department), bulk delete, CSV export, and there is a CSV import using the same column headings as the Ringotel portal; every bulk action asks for confirmation.

**Permissions**: `ringotel` opens the app. `ringotel_extensions` (default: superadmin, admin) lets a user see the organization, connections, parks, users/extensions and integration. Changes there need, in addition, `ringotel_extension_add` (create organization / connection / parks / extensions / integration), `ringotel_extension_edit` (edit users, connections, parks, integration), `ringotel_extension_manage` (activate / disable extensions, resync names and passwords, reset password) or `ringotel_extension_delete` (delete organization, connection, users, integration). All three default to superadmin only; grant them to other groups in Group Manager. Deleting an organization, connection or extension, disabling an extension and resetting a password ask for confirmation. Controls a user may not use are hidden, and the server refuses the call regardless. `ringotel_organization_view`, `ringotel_connection_view` and `ringotel_park_view` (default: superadmin only) show the Organization card, the Connections section and the Parking Slots section; without them those sections are hidden while the Users/Extensions section stays visible. `ringotel_contacts` (default: superadmin, admin) covers the Contacts tab. A user with `ringotel` and `ringotel_contacts` only sees the contacts.

`ringotel_extension_welcome` (default: superadmin, admin) allows only the welcome email button on the extensions list: it can create a Ringotel user only as active with an email address. `ringotel_extension_password` (default: superadmin, admin) allows only Reset password, which asks for confirmation; the email address can be changed there but not the name. Outside the superadmin group, every call must use the domain's own Ringotel organization.

**Admins out of the box**: the admin group gets `ringotel`, `ringotel_extensions`, `extension_ringotel`, `ringotel_contacts`, `ringotel_extension_welcome`, `ringotel_extension_password`, `ringotel_sms_number_users` and the menu item, so a domain admin can open the app, see the domain's Ringotel users, use the Ringotel column on the extensions list, send the welcome email to a new extension, reset passwords, choose the users of existing SMS numbers and edit contacts, but cannot add (from the app), edit, delete, activate or disable Ringotel users. App Defaults (`upgrade.php --defaults`) adds these when the group has no row for them yet, so a permission switched off in Group Manager stays off.

This streamlines user management for those using both FusionPBX and Ringotel.

**SMS integrations (Integration tab)**: Bandwidth, Telnyx and BulkVS. Each provider is its own class, `resources/classes/ringotel_sms_<name>.php` (see `resources/interfaces/ringotel_sms_interface.php`), with its credentials in Default Settings > ringotel (a domain setting gives a client its own account):

| Provider | Default settings |
|---|---|
| Bandwidth | `ringotel_bandwidth_integration_username`, `_password`, `_account_id`, `_application_id` |
| Telnyx | `ringotel_telnyx_api_key` |
| BulkVS | `ringotel_bulkvs_username`, `ringotel_bulkvs_password` (the BulkVS API credentials) |

The tab lists every provider that is set up or already connected in the Ringotel portal. **Connect** (needs `ringotel_extension_add`) turns the Ringotel integration on with those credentials; once connected the tab shows the inbound SMS webhook to set at the provider. **Disconnect** needs `ringotel_extension_delete` and asks for confirmation. `ringotel_sms_number_users` (default: superadmin, admin) only lets a user change which users are on an existing number; the name, number and provider are kept from the stored configuration. **Add number** picks a connected provider, lists the numbers on the provider account that are inbound destinations of the domain (a superadmin also sees the account's other numbers; Bandwidth, which has no number listing here, offers the domain's destinations) and assigns users to the number. Stored credentials are never sent to the page. A number configured in the portal with a provider this app has no class for (for example Twilio) is shown and can be edited without changing its provider.

To add a provider, copy `ringotel_sms_telnyx.php`, set the Ringotel service id and its Ringotel credential field names (from the provider's page in the Ringotel portal), optionally list its numbers, and add its default settings to `app_config.php`.

#### **Before accessing the Ringotel application page**, **update permissions and default settings** in FusionPBX. This ensures proper user access and module functionality.

![ringotel default settingsd](https://github.com/user-attachments/assets/8437e08e-6f79-4fe2-8857-fd9a80068e99)


## 2. Ringotel Extensions List App
![ringotel_extension_list_example](https://github.com/user-attachments/assets/b513449b-c6ff-4b5d-9abd-d71345bff1ae)

This feature adds a "Ringotel status of user" column to FusionPBX's `extensions.php` page.

Extensions that are not in Ringotel yet (red) get an envelope button: it adds the extension to Ringotel as an active user and Ringotel emails the welcome instructions (app download and login). The address is prefilled from the voicemail email; with more than one connection you pick one. It needs `ringotel_extension_add` and `ringotel_extension_manage`. Everything after that (resend, reset password, disable) is done in the app.

It also adds an **Extension, VM & Ringotel** button to the delete dialog, which removes the selected users from Ringotel before deleting the extensions and their voicemail. The button needs the `ringotel_extension_delete` permission.

FusionPBX has no hook for this page, so the app adds these lines in front of the footer of `app/extensions/extensions.php`:
```
	//ringotel: column and delete button on the extensions list (added by app/ringotel, safe to remove)
	if (file_exists(dirname(__DIR__, 2)."/app/ringotel/ringotel_extension_list.php")) {
		require_once dirname(__DIR__, 2)."/app/ringotel/ringotel_extension_list.php";
	}
```
A FusionPBX update replaces `extensions.php`; the App Defaults upgrade puts the lines back. To check, add or remove them without an upgrade:
```
php /var/www/fusionpbx/app/ringotel/resources/hooks.php status
php /var/www/fusionpbx/app/ringotel/resources/hooks.php install
php /var/www/fusionpbx/app/ringotel/resources/hooks.php remove
```
The lines check that the app is there, so removing the app does not break the extensions page.

![ringotel_extension_list](https://github.com/user-attachments/assets/be0f82c7-8696-402a-ab2c-6a99d7af0282)

The new column will display statuses and user's information providing quick insight into each extension's Ringotel integration.

#### Example how to enable the "Ringotel Extensions List App":
https://github.com/user-attachments/assets/6508c37c-6533-4128-9ce2-a8be88b562d4

 
 
