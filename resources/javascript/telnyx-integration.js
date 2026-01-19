/**
 * Telnyx Integration for Ringotel FusionPBX
 * 
 * This file adds Telnyx as an SMS provider option alongside Bandwidth.
 * Include this file AFTER the main index.php script loads.
 */

// Provider configurations
const SMS_PROVIDERS = {
    'Telnyx': {
        id: 'Telnyx',
        name: 'Telnyx',
        logo: '/app/rt/resources/images/telnyx-logo.svg',
        description: 'Allow sending and receiving SMS/MMS via Telnyx for users.'
    },
    'Bandwidth': {
        id: 'Bandwidth', 
        name: 'Bandwidth',
        logo: '/app/rt/resources/images/bandwidth-logo.svg',
        description: 'Allow sending and receiving SMS/MMS via Bandwidth for users.'
    }
};

// Default provider
const DEFAULT_PROVIDER = 'Telnyx';

// Override: Template for not exist integration (with provider dropdown)
window.notExistIntegrationElement = () => {
    const providerOptions = Object.keys(SMS_PROVIDERS).map(key => {
        const selected = key === DEFAULT_PROVIDER ? 'selected' : '';
        return `<option value="${key}" ${selected}>${SMS_PROVIDERS[key].name}</option>`;
    }).join('');

    return `
    <div id="not_exist_integration" class="jumbotron" style="background-color: white; border: 0px solid #b5b5b5;padding: 2rem 2rem;">
        <hr>
        <p style="font-size: 14pt;">Allow sending and receiving SMS/MMS for users.</p>
        
        <div class="input-group mb-3" style="max-width: 400px;">
            <div class="input-group-prepend">
                <label class="input-group-text" for="integration_provider_select">Provider</label>
            </div>
            <select class="custom-select" id="integration_provider_select">
                ${providerOptions}
            </select>
        </div>
        
        <p class="lead">
            <button type="button" class="btn btn-primary btn-lg" id="integration_create" style="padding: 0.15rem 1rem;">
                <span id="create_inter_text">Create</span>
                <span id="create_inter_loading" style="display: none;">
                    <span class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true"></span>
                    Loading...
                </span>
            </button>
        </p>
    </div>`;
};

// Override: Integration element template (shows correct provider)
window.IntegrationElement = (data) => {
    const provider = SMS_PROVIDERS[data.id] || SMS_PROVIDERS['Bandwidth'];
    
    return `
    <div class="card" id="integration_service" style="width: 25rem; display:none;transition: all 1s;-moz-transition: all 1s;-webkit-transition: all 1s;">
        <img src="${provider.logo}" class="card-img-top" alt="${data.id}" style="width: 2rem;left: 0;margin: 0.8rem 1.2rem 1rem;" onerror="this.style.display='none'">
        <div class="card-body card-body-p" style="padding: 0 1.25rem 1.25rem 1.25rem;">
            <h5 class="card-title" style="font-size: 17pt;font-weight: 700;display: flex;align-items: center;">
                ${provider.name}
                <span class="badge badge-info" style="font-size: 9pt;padding: 3px 8px;margin: 0px 10px;">${data.id}</span>
                <button id="manageNumbersModalDisable" class="btn btn-outline-danger" style="font-size: 9pt;padding: 0px 10px;margin: 0px 10px;height: 20px;align-items: center;display: flex;">Disable</button>
            </h5>
            <p class="card-text" style="font-size: 11pt;">${provider.description}</p>
            <button id="manageNumbersModal_button" class="btn btn-primary" data-toggle="modal" data-target="#manageNumbersModal">Manage numbers</button>
        </div>
    </div>`;
};

// Override: Create integration event (passes provider)
window.eventIntegrationCreate = () => {
    $('#integration_create').off('click');
    
    $('#integration_create').on('click', () => {
        $('#integration_create').attr('disabled', true);
        $('#create_inter_text').fadeOut(300);
        
        const profileid = $('#delete_organization').attr('data-account');
        const provider = $('#integration_provider_select').val() || DEFAULT_PROVIDER;
        
        setTimeout(() => {
            $('#create_inter_loading').fadeIn();
            $.ajax({
                url: "/app/rt/service.php?method=create_integration",
                type: "post",
                cache: true,
                data: { profileid, provider },
                success: function(response) {
                    checkErrors(response);
                    const { result } = JSON.parse(response.replaceAll("\\", ""));
                    
                    if (result?.status === 200 && result?.state === 1) {
                        getIntegration();
                    } else {
                        const not_exist_integration_note = notExistIntegrationNoteElement("Integration is available in PRO version.");
                        $('#nav-integration').prepend(not_exist_integration_note);
                        $('#create_inter_loading').fadeOut(300);
                        setTimeout(() => {
                            $('#create_inter_text').fadeIn(300);
                            $('#integration_create').attr('disabled', false);
                        }, 300);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('Create integration error:', textStatus, errorThrown);
                }
            });
        }, 300);
    });
};

// Override: Get integration (checks for both Telnyx AND Bandwidth)
window.getIntegration = (parameters) => {
    const orgid = $('#delete_organization').attr('data-account') || ORG_ID;
    
    $.ajax({
        url: "/app/rt/service.php?method=get_integration",
        type: "get",
        cache: true,
        data: { orgid },
        success: async function(response) {
            checkErrors(response);
            const { result, error } = JSON.parse(response.replaceAll("\\", ""));
            
            // Check for either Telnyx OR Bandwidth integration
            const hasActiveIntegration = Array.isArray(result) && 
                result[0]?.state === 1 && 
                (result[0]?.id === "Telnyx" || result[0]?.id === "Bandwidth");
            
            if (hasActiveIntegration) {
                $('#create_inter_loading').fadeOut(300);
                $('#not_exist_integration').slideUp(300);
                
                const modal_member_numbers = MembersIntegrationModalelement();
                const service_form = IntegrationElement(result[0]);
                const integration_service_container = integrationServiceContainerElement();
                
                $('#nav-integration').html(modal_member_numbers);
                $('#nav-integration').append(service_form);
                $('#nav-integration').append(integration_service_container);
                
                eventIntegrationCreate();
                $('#manage_numbers_activate_button').attr('disabled', false);
                
                $('#manageNumbersModal').on("hidden.bs.modal", function() {
                    $('#manage_numbers_friendly_name').val('');
                    $('#manage_numbers_phone_number').val('');
                });
                
                $('#integrations_users_select_all').on('click', function() {
                    $('#multiselect_dropdown_list_manage_numbers_users').children().click();
                    $('#multiselect_dropdown_list_wrapper_manage_numbers_users').hide();
                });
                
                eventSaveIntegratedUsers();
                eventDisableIntegrationFunc();
                
                const parksUserExtensions = await getUsersWithUpdateElements();
                getSMSTrunk(parksUserExtensions);
                
                setTimeout(() => {
                    $('#create_inter_text').fadeIn(300);
                    $('#integration_service').slideDown(300);
                    $('#integration_service_container').slideDown(300);
                    $('#manageNumbersModalDisable').attr('disabled', false);
                    $('#manageNumbersModal_button').attr('disabled', false);
                }, 300);
                
            } else {
                $('#integration_service').slideUp(300);
                const not_exist_integration = notExistIntegrationElement();
                $('#nav-integration').html(not_exist_integration);
                
                setTimeout(() => {
                    $('#not_exist_integration').slideDown(300);
                    eventIntegrationCreate();
                }, 300);
            }
            
            $('#integration_create').attr('disabled', false);
        },
        error: function(jqXHR, textStatus, errorThrown) {
            console.error('Get integration error:', textStatus, errorThrown);
            const not_exist_integration = notExistIntegrationElement();
            const not_exist_integration_note = notExistIntegrationNoteElement();
            $('#nav-integration').html(not_exist_integration_note);
            $('#nav-integration').append(not_exist_integration);
        }
    });
};

// Override: Save integrated users (passes provider)
window.eventSaveIntegratedUsers = () => {
    $('#manage_numbers_phone_number').on('change', function(el) {
        if (el.target?.value?.trim()) {
            functValidChecker();
        }
    });
    
    $('#manage_numbers_users').on('change', function() {
        functValidChecker();
    });
    
    $('#manage_numbers_activate_button').off('click');
    $('#manage_numbers_activate_button').on('click', function() {
        const name = $('#manage_numbers_friendly_name').val().trim();
        const number = $('#manage_numbers_phone_number').val().trim();
        const users = $('#manage_numbers_users').val();
        
        // Get the current provider from the integration service badge or default
        const currentProvider = $('#integration_service .badge').text() || DEFAULT_PROVIDER;
        
        if (users?.length === 0 || !number) {
            !number && $('#manage_numbers_phone_number').addClass('alert-danger');
            if (users?.length === 0) {
                $('#multiselect_dropdown').addClass('alert-danger');
                $('#manage_numbers_users').addClass('alert-danger');
            }
            $('#manage_numbers_activate_button').attr('disabled', true);
        } else {
            $('#manage_numbers_activate_button').attr('disabled', true);
            $('#manage_numbers_activate_text').slideUp(300);
            $('#manage_numbers_activate_loading').slideDown(300);
            
            const orgid = $('#delete_organization').attr('data-account') || ORG_ID;
            const data = { orgid, name, number, users, provider: currentProvider };
            
            $.ajax({
                url: "/app/rt/service.php?method=create_sms_trunk",
                type: "post",
                cache: true,
                data,
                success: async function(response) {
                    checkErrors(response);
                    const { result } = JSON.parse(response.replaceAll("\\", ""));
                    
                    const parksUserExtensions = await getUsersWithUpdateElements();
                    getSMSTrunk(parksUserExtensions);
                    
                    $('#manage_numbers_activate_button').attr('disabled', false);
                    $('#manage_numbers_activate_text').slideDown(300);
                    $('#manage_numbers_activate_loading').slideUp(300);
                    $('#manageNumbersModal').click();
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('Create SMS trunk error:', textStatus, errorThrown);
                }
            });
        }
    });
};

// Override: Delete integration (passes provider)
window.eventDisableIntegrationFunc = () => {
    $('#manageNumbersModalDisable').off('click');
    
    $('#manageNumbersModalDisable').on('click', function() {
        $('#manageNumbersModalDisable').attr('disabled', true);
        $('#manageNumbersModal_button').attr('disabled', true);
        $('#integration_service_container').slideUp();
        
        const profileid = $('#delete_organization').attr('data-account');
        const provider = $('#integration_service .badge').text() || DEFAULT_PROVIDER;
        
        $.ajax({
            url: "/app/rt/service.php?method=delete_integration",
            type: "post",
            cache: true,
            data: { profileid, provider },
            success: function(response) {
                checkErrors(response);
                getIntegration();
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Delete integration error:', textStatus, errorThrown);
            }
        });
    });
};

// Override: SMS Trunk element template (shows provider info)
window.elementSMSTrunk = (data, parksUserExtensions) => {
    const users_elements = data?.users.map((id) => {
        const user = parksUserExtensions?.filter((item) => item.id === id)[0];
        return `<div data-id="${user?.id}" style="background: #a1a1a1;padding: 0px 10px;font-size: 11pt;border-radius: 10px;color: white;margin: 5px 7px;">${user?.name} (${user?.extension})</div>`;
    }).join('');
    
    // Determine provider from trunk data or default
    const provider = data?.provider || 'Bandwidth';
    const providerBadge = provider === 'Telnyx' 
        ? '<span class="badge badge-success" style="font-size: 9pt;">Telnyx</span>'
        : '<span class="badge badge-primary" style="font-size: 9pt;">Bandwidth</span>';

    return `
    <div id="sms_trunk_${data.id}" class="card mb-3" style="height: 250px;max-width: 100%;margin: 10px 0px;box-shadow: rgba(0, 0, 0, 0.1) 0px 4px 6px -1px, rgba(0, 0, 0, 0.06) 0px 2px 4px -1px;">
        <div class="row no-gutters">
            <div class="col-md-5">
                <div class="card-body card-body-p">
                    <h5 id="table_sms_trunk_name_h5_${data.id}" class="card-title" style="font-size: 20pt;font-weight: 700;">
                        ${data?.name || '-'} ${providerBadge}
                    </h5>
                    <input id="table_sms_trunk_name_input_${data.id}" class="card-title" style="font-size: 20pt;font-weight: 700;display:none;font-size: 20pt;font-weight: 700;margin: 0;padding: 0px 10px;line-height: normal;border-radius: 10px;border: 1px solid #8080804f;" value="${data?.name || ''}"/>
                    <table id="table_sms_trunk_${data.id}" data-id="${data.id}" data-provider="${provider}" class="table table table-hover table-borderless" style="margin-bottom: 1rem;">
                        <tbody>
                            <tr>
                                <td style="font-size: 12pt;padding: 0.25rem 0.75rem;">Number:</td>
                                <td id="table_sms_trunk_number_td_${data.id}" name="table_sms_trunk_number_td" class="table_sms_trunk_number_td" style="font-size: 12pt;width: 120px;padding: 0.25rem 0.75rem;">
                                    ${data?.number || '-'}
                                </td>
                                <td style="font-size: 12pt;width: 120px;padding: 0.25rem 0.75rem;display:none;">
                                    <input type="number" id="table_sms_trunk_number_input_${data.id}" name="table_sms_trunk_number_input" class="table_sms_trunk_number_input" style="padding: 0;margin: 0;width: inherit;border: 1px solid #837e7e52;padding: 0px 4px;border-radius: 20px;" value="${data?.number?.replace('+1', '') || ''}"/>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size: 12pt;padding: 0.25rem 0.75rem;">Provider:</td>
                                <td style="font-size: 12pt;width: 120px;padding: 0.25rem 0.75rem;">${provider}</td>
                            </tr>
                            <tr>
                                <td style="font-size: 12pt;padding: 0.25rem 0.75rem;">Country:</td>
                                <td style="font-size: 12pt;width: 120px;padding: 0.25rem 0.75rem;">${data?.country || '-'}</td>
                            </tr>
                            <tr>
                                <td style="font-size: 12pt;padding: 0.25rem 0.75rem;">Reformat outbound:</td>
                                <td style="font-size: 12pt;width: 120px;padding: 0.25rem 0.75rem;">${data?.outboundFormat || '-'}</td>
                            </tr>
                            <tr>
                                <td style="font-size: 12pt;padding: 0.25rem 0.75rem;">Reformat inbound:</td>
                                <td style="font-size: 12pt;width: 120px;padding: 0.25rem 0.75rem;">${data?.inboundFormat || '-'}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-7">
                <div class="card-body card-body-p">
                    <h5 class="card-title">Users</h5>
                    <div id="table_sms_trunk_users_${data.id}" class="table_sms_trunk_users" data-id="${data.id}" style="display: flex;flex-wrap: wrap;">
                        ${users_elements}
                    </div>
                    <select id="table_sms_trunk_manage_users_${data.id}" style="height: 125px; overflow: hidden; display: none;" name="table_sms_trunk_manage_users" multiple="" multiselect-hide-x="true" type="text" class="form-control" placeholder="Who will be sending and receiving SMS via this phone number"></select>
                </div>
            </div>
        </div>

        <button id="sms_trunk_save_${data.id}" class="btn btn-outline-primary sms_trunk_save" data-id="${data.id}" data-provider="${provider}" style="border: 0px;padding: 2px 10px;margin: 10px;align-items: center;display: none;position: absolute;bottom: 10px;right: 130px;font-size: 10pt;font-weight: 600;">
            Save
        </button>

        <button id="sms_trunk_close_updating_${data.id}" class="btn btn-outline-primary sms_trunk_close_updating" data-id="${data.id}" style="display: none;border: 0px;padding: 2px 10px;margin: 10px;align-items: center;position: absolute;bottom: 10px;right: 40px;font-size: 10pt;font-weight: 600;color: gray;">
            Close
        </button>
        
        <span id="sms_trunk_save_loading_${data.id}" style="border: 0px;padding: 2px 10px;margin: 10px;align-items: center;display: none;position: absolute;bottom: 10px;right: 100px;font-size: 10pt;font-weight: 600;">
            <span class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true"></span>
            Loading...
        </span>
        
        <button id="sms_trunk_update_${data.id}" class="btn btn-outline-secondary sms_trunk_update" data-id="${data.id}" style="border: 0px;padding: 2px 10px;margin: 10px;align-items: center;display: flex;position: absolute;bottom: 10px;right: 100px;font-size: 10pt;font-weight: 600;">
            Edit
        </button>
        
        <button id="sms_trunk_delete_${data.id}" class="btn btn-outline-danger sms_trunk_delete" data-id="${data.id}" data-number="${data?.number?.replace('+1', '') || ''}" data-provider="${provider}" style="border: 0px;padding: 2px 10px;margin: 10px;align-items: center;display: flex;position: absolute;bottom: 10px;right: 10px;font-size: 10pt;font-weight: 600;">
            Delete
        </button>
    </div>`;
};

// Override: SMS Trunk Save Event (passes provider)
window.eventSMSTrunkSave = () => {
    $('.sms_trunk_save').off('click');
    
    $('.sms_trunk_save').on('click', function(e) {
        const id = e.target.getAttribute('data-id');
        const provider = e.target.getAttribute('data-provider') || DEFAULT_PROVIDER;
        const orgid = $('#delete_organization').attr('data-account') || ORG_ID;
        
        $('#sms_trunk_save_' + id).fadeOut(300);
        $('#sms_trunk_close_updating_' + id).fadeOut(300);
        
        $('#table_sms_trunk_name_input_' + id).attr('disabled', true);
        $('#table_sms_trunk_number_input_' + id).attr('disabled', true);
        $('#table_sms_trunk_users_' + id).parent().children('.multiselect-dropdown').css('opacity', '0.5');
        $('#table_sms_trunk_users_' + id).parent().children('.multiselect-dropdown').css('pointer-events', 'none');
        
        const name = $('#table_sms_trunk_name_input_' + id).val();
        const number = $('#table_sms_trunk_number_input_' + id).val();
        const users = $('#table_sms_trunk_manage_users_' + id).val();
        
        const data = { orgid, id, name, number, users, provider };
        
        setTimeout(() => {
            $('#sms_trunk_save_loading_' + id).fadeIn(300);
            
            $.ajax({
                url: "/app/rt/service.php?method=update_sms_trunk",
                type: "post",
                cache: true,
                data,
                success: async function(response) {
                    checkErrors(response);
                    
                    const parksUserExtensions = await getUsersWithUpdateElements();
                    getSMSTrunk(parksUserExtensions);
                    
                    $('#manage_numbers_activate_button').attr('disabled', false);
                    $('#manage_numbers_activate_text').slideDown(300);
                    $('#manage_numbers_activate_loading').slideUp(300);
                    $('#manageNumbersModal').click();
                    
                    setTimeout(() => {
                        $('#sms_trunk_save_loading_' + id).fadeOut(300);
                        $('#sms_trunk_delete_' + id).fadeIn(300);
                        $('#sms_trunk_update_' + id).fadeIn(300);
                        $('#sms_trunk_save_' + id).fadeOut(300);
                        $('#sms_trunk_close_updating_' + id).fadeOut(300);
                    }, 300);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('Update SMS trunk error:', textStatus, errorThrown);
                }
            });
        }, 300);
    });
};

// Override: SMS Trunk Delete Event (passes provider)
window.eventSMSTrunkDelete = () => {
    $('.sms_trunk_delete').off('click');
    
    $('.sms_trunk_delete').on('click', function(e) {
        $('#manageNumbersModal_button').attr('disabled', true);
        
        const sms_trunk_id = e.target.getAttribute('data-id');
        const sms_trunk_number = e.target.getAttribute('data-number');
        const provider = e.target.getAttribute('data-provider') || DEFAULT_PROVIDER;
        const orgid = $('#delete_organization').attr('data-account') || ORG_ID;
        
        $('#sms_trunk_delete_' + sms_trunk_id).attr('disabled', true);
        
        $.ajax({
            url: "/app/rt/service.php?method=delete_sms_trunk",
            type: "post",
            cache: true,
            data: { orgid, id: sms_trunk_id, provider },
            success: function(response) {
                checkErrors(response);
                $('#sms_trunk_' + sms_trunk_id).slideUp(300);
                
                sms_trunk_number && $('#manage_numbers_phone_number').children(`option[value=${sms_trunk_number.replace('+1', '')}]`)?.slideDown();
                
                setTimeout(() => {
                    $('#manageNumbersModal_button').attr('disabled', false);
                }, 300);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Delete SMS trunk error:', textStatus, errorThrown);
            }
        });
    });
};

console.log('Telnyx integration loaded - Provider options: Telnyx, Bandwidth');
