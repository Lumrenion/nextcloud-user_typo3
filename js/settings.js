// settings.js of user_typo3

// declare namespace
var user_typo3 = user_typo3 ||
{
};

/**
 * init admin settings view
 */
user_typo3.adminSettingsUI = function()
{

    if($('#sqlDiv').length > 0)
    {
        // enable tabs on settings page
        $('#sqlDiv').tabs();
        
        // Verify the SQL database settings
        $('#sqlVerify').click(function(event)
        {
            event.preventDefault();

            var post = $('#sqlForm').serializeArray();
            var domain = $('#sql_domain_chooser option:selected').val();
            
            post.push({
                name: 'function',
                value: 'verifySettings'
            });
            
            post.push({
                name: 'domain',
                value: domain
            });

            $('#sql_verify_message').show();
            $('#sql_success_message').hide();
            $('#sql_error_message').hide();
            $('#sql_update_message').hide();
            // Ajax foobar
            $.post(OC.filePath('user_typo3', 'ajax', 'settings.php'), post, function(data)
            {
                $('#sql_verify_message').hide();
                if(data.status == 'success')
                {
                    $('#sql_success_message').html(data.data.message);
                    $('#sql_success_message').show();
                    window.setTimeout(function()
                    {
                        $('#sql_success_message').hide();
                    }, 10000);
                } else
                {
                    $('#sql_error_message').html(data.data.message);
                    $('#sql_error_message').show();
                }
            }, 'json');
            return false;
        });            

        // Save the settings for a domain
        $('#sqlSubmit').click(function(event)
        {
            event.preventDefault();

            var post = $('#sqlForm').serializeArray();
            var domain = $('#sql_domain_chooser option:selected').val();
            
            post.push({
                name: 'function',
                value: 'saveSettings'
            });
            
            post.push({
                name: 'domain',
                value: domain
            });

            $('#sql_update_message').show();
            $('#sql_success_message').hide();
            $('#sql_verify_message').hide();
            $('#sql_error_message').hide();
            // Ajax foobar
            $.post(OC.filePath('user_typo3', 'ajax', 'settings.php'), post, function(data)
            {
                $('#sql_update_message').hide();
                if(data.status == 'success')
                {
                    $('#sql_success_message').html(data.data.message);
                    $('#sql_success_message').show();
                    window.setTimeout(function()
                    {
                        $('#sql_success_message').hide();
                    }, 10000);
                } else
                {
                    $('#sql_error_message').html(data.data.message);
                    $('#sql_error_message').show();
                }
            }, 'json');
            return false;
        });

        // Attach event handler to the domain chooser
        $('#sql_domain_chooser').change(function() {
           user_typo3.loadDomainSettings($('#sql_domain_chooser option:selected').val());
        });
    }
};

/**
 * Load the settings for the selected domain
 * @param string domain The domain to load
 */
user_typo3.loadDomainSettings = function(domain)
{
    $('#sql_success_message').hide();
    $('#sql_error_message').hide();
    $('#sql_verify_message').hide();
    $('#sql_loading_message').show();
    var post = [
        {
            name: 'appname',
            value: 'user_typo3'
        },
        {
            name: 'function',
            value: 'loadSettingsForDomain'
        },
        {
            name: 'domain',
            value: domain
        }
    ];
    $.post(OC.filePath('user_typo3', 'ajax', 'settings.php'), post, function(data)
        {
            $('#sql_loading_message').hide();
            if(data.status == 'success')
            {
                for(key in data.settings)
                {
                    if(key == 'set_strip_domain')
                    {
                        if(data.settings[key] == 'true')
                            $('#' + key).prop('checked', true);
                        else
                            $('#' + key).prop('checked', false);
                    }
                    else if(key == 'set_allow_pwchange')
                    {
                        if(data.settings[key] == 'true')
                            $('#' + key).prop('checked', true);
                        else
                            $('#' + key).prop('checked', false);
                    }
                    else if(key == 'set_active_invert')
                    {
                        if(data.settings[key] == 'true')
                            $('#' + key).prop('checked', true);
                        else
                            $('#' + key).prop('checked', false);
                    }
                    else
                    {
                        $('#' + key).val(data.settings[key]);
                    }
                }
            }
            else
            {
                $('#sql_error_message').html(data.data.message);
                $('#sql_error_message').show();
            }
        }, 'json'
    );
};

user_typo3.initSelect2 = function() {
    var $adminGroupsInput = $('#set_admin_groups');
    OC.Settings.setupGroupsSelect($adminGroupsInput);
    // $adminGroupsInput.select2({
    //     ajax: {
    //         url: OC.filePath('user_typo3', 'ajax', 'settings.php'),
    //         method: 'POST',
    //         dataType: 'json',
    //         delay: 250,
    //         data: function (params) {
    //             var data = $('#sqlForm').serializeArray();
    //             var domain = $('#sql_domain_chooser option:selected').val();
    //
    //             data.push({
    //                 name: 'function',
    //                 value: 'getGroupAutocomplete'
    //             });
    //             data.push({
    //                 name: 'search',
    //                 value: params.term
    //             });
    //
    //             return data;
    //         }
    //     }
    // });
};

// Run our JS if the SQL settings are present
$(document).ready(function()
{
    if($('#sqlDiv'))
    {
        user_typo3.adminSettingsUI();
        user_typo3.loadDomainSettings($('#sql_domain_chooser option:selected').val());
        user_typo3.initSelect2();
    }
});

