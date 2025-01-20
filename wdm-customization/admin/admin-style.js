
jQuery(document).ready(function ($) {
    $('#wdm_event_list_table').DataTable();
    $('#wdm_export_events_data_teams').DataTable({
        layout: {
            topStart: {
                buttons: [
                    'csv'
                ]
            }
        },
        rowGroup: {
            dataSrc: 0
        }
    });
    $('#wdm_export_events_data_single').DataTable({
        layout: {
            topStart: {
                buttons: [
                    'csv'
                ]
            }
        }
    });
    $('#wdm_customizer_event_start_time').flatpickr({
        enableTime: true,
        dateFormat: "U",
        altInput: true,
        altFormat: "F j, Y H:i",
        minDate: Date.now(),
        time_24hr: true
    });

    $('#wdm_customizer_event_end_time').flatpickr({
        enableTime: true,
        dateFormat: "U",
        altInput: true,
        altFormat: "F j, Y H:i",
        minDate: Date.now(),
        time_24hr: true,
        onOpen: function (selectedDates, dateStr, instance) {
            if ($('#wdm_customizer_event_start_time').val() != '') {
                instance.set('minDate', $('#wdm_customizer_event_start_time').val());
            }
        },

    });
    $('#wdm_customizer_event_deadline_time').flatpickr({
        enableTime: true,
        dateFormat: "U",
        altInput: true,
        altFormat: "F j, Y H:i",
        minDate: Date.now(),
        time_24hr: true,
        onOpen: function (selectedDates, dateStr, instance) {
            if ($('#wdm_customizer_event_start_time').val() != '' && $('#wdm_customizer_event_end_time').val() != '') {
                instance.set('minDate', $('#wdm_customizer_event_start_time').val());
                instance.set('maxDate', $('#wdm_customizer_event_end_time').val());
            }
        },

    });
    $("[name='wdm_customizer_is_alt_event']").on('change', function () {
        if ($(this).val() == 1) {
            $('#wdm_customizer_is_team_event_no').prop("checked", true).change();
            $('#wdm_customizer_is_team_event_yes').prop('disabled', true);
        } else {
            $('#wdm_customizer_is_team_event_no').prop("checked", false).change();
            $('#wdm_customizer_is_team_event_yes').prop('disabled', false);
            $('#wdm_customizer_min_event_team_member').prop('readonly', false);
            $('#wdm_customizer_max_event_team_member').prop('readonly', false);
            $('#wdm_customizer_alt_event_type_selector').prop('disabled', false);
            $('#wdm_customizer_min_event_team_member').val('');
            $('#wdm_customizer_max_event_team_member').val('');
            $('#wdm_customizer_alt_event_type_selector').val(-1);
        }
    })
    $("[name='wdm_customizer_is_team_event']").on('change', function () {
        if ($(this).val() == '1') {
            $('#wdm_customizer_min_event_team_member').prop('readonly', false);
            $('#wdm_customizer_max_event_team_member').prop('readonly', false);
            $('#wdm_customizer_alt_event_type_selector').prop('disabled', false);
        } else {
            $('#wdm_customizer_min_event_team_member').prop('readonly', true);
            $('#wdm_customizer_max_event_team_member').prop('readonly', true);
            $('#wdm_customizer_alt_event_type_selector').prop('disabled', true);
            $('#wdm_customizer_min_event_team_member').val(-1);
            $('#wdm_customizer_max_event_team_member').val(-1);
            $('#wdm_customizer_alt_event_type_selector').val(-1);
        }
    })
    $('#wdm_add_events_form').on('submit', function () {
        if ($('#wdm_customizer_alt_event_type_selector').is(':disabled')) {
            $('#wdm_customizer_alt_event_type_selector').prop('disabled', false);
        }
    })
    $('#wdm_enrolled_events').on('change', function () {
        $.ajax({
            url: wdm_ajax_admin_obj.ajax_url,
            method: 'POST',
            data: {
                action: 'wdm_list_subacc_users',
                event_id: $('#wdm_enrolled_events').val(),
                _ajax_nonce: wdm_ajax_admin_obj.nonce
            },
            success: function (response) {
                $('#wdm_team_user_selector').empty();
                $('#wdm_team_user_selector').append(`<option value="-1">Select User</option>`)
                let users = response.data;
                users.forEach(user => {
                    $('#wdm_team_user_selector').append(`<option value="${user.user_id}">${user.user_email}</option>`)
                });
            },
            error: function (response) {
                console.log(response);
                alert('Error while fetching user list');
            }
        })
    })

});