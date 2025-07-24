/**
 * WP Factor Telegram Plugin
 */

var WP_Factor_Telegram_Plugin = function ($) {

    var $twfci = $("#tg_wp_factor_chat_id");
    var $twfciconf = $("#tg_wp_factor_chat_id_confirm");
    var $twbtn = $("#tg_wp_factor_chat_id_send");
    var $twctrl = $("#tg_wp_factor_valid");
    var $twenabled = $("#tg_wp_factor_enabled");
    var $twconfig = $("#tg-2fa-configuration");
    var $tweditbtn = $("#tg-edit-chat-id");
    var $twconfigrow = $(".tg-configured-row");

    var $twfcr = $("#factor-chat-response");
    var $twfconf = $("#factor-chat-confirm");
    var $twfcheck = $("#tg_wp_factor_chat_id_check");
    var $twbcheck = $("#checkbot");
    var $twb = $("#bot_token");
    var $twbdesc = $("#bot_token_desc");

    return {
        init: init
    };

    function init() {

        // Handle checkbox toggle for 2FA configuration with smooth animation
        $twenabled.on("change", function(evt){
            var isConfigured = $twconfigrow.length > 0;

            if ($(this).is(":checked")) {
                // Enable 2FA = 1, so tg_wp_factor_valid = 0
                $twctrl.val(0);
                if (!isConfigured) {
                    $twconfig.addClass('show').show();
                    updateProgress(25);
                }
            } else {
                // Enable 2FA = 0, so tg_wp_factor_valid = 1
                $twctrl.val(1);
                $twconfig.removeClass('show');
                setTimeout(function() {
                    $twconfig.hide();
                }, 300);
                updateProgress(0);
                resetStatusIndicators();
            }
        });

        // Handle edit button click (when 2FA is already configured)
        $tweditbtn.on("click", function(evt){
            evt.preventDefault();

            // Hide configured row and show configuration form
            $twconfigrow.hide();
            $twconfig.addClass('show').show();

            // Make the input editable and clear it
            $twfci.prop('readonly', false).removeClass('input-valid').css('background', '').val('');

            // Reset validation state
            $twctrl.val(0);
            updateProgress(25);
            resetStatusIndicators();
            
            // Show modifying status message
            $('.tg-status.success').removeClass('success').addClass('warning').text(tlj.modifying_setup);
        });

        // Watch for changes in Chat ID when in edit mode
        $twfci.on("input", function(){
            var currentValue = $(this).val();
            var originalValue = $(this).data('original-value');

            // If value changed from original, require re-validation
            if (currentValue !== originalValue) {
                $twctrl.val(0);
                $(this).removeClass('input-valid');
            }
        });

        // Initialize visibility based on checkbox state and configuration
        var isConfigured = $twconfigrow.length > 0;
        if ($twenabled.is(":checked") && !isConfigured) {
            $twconfig.addClass('show').show();
            updateProgress(25);
        } else {
            $twconfig.removeClass('show').hide();
        }

        // Store original chat ID value for comparison
        if ($twfci.length) {
            $twfci.data('original-value', $twfci.val());
        }

        $twfci.on("change", function(evt){
           $twctrl.val(0);
           // Validate Chat ID format (basic validation)
           validateChatId($(this).val());
        });

        $twbtn.on("click", function(evt){
            evt.preventDefault();
            var chat_id = $twfci.val();

            if (!validateChatId(chat_id)) {
                showStatus('#chat-id-status', 'error', tlj.invalid_chat_id);
                return;
            }

            send_tg_token(chat_id);
        });

        $twfcheck.on("click", function(evt){
            evt.preventDefault();
            var token = $twfciconf.val();
            var chat_id = $twfci.val();

            if (!token.trim()) {
                showStatus('#validation-status', 'error', tlj.enter_confirmation_code);
                return;
            }

            check_tg_token(token, chat_id);
        });

        $twbcheck.on("click", function(evt){

            evt.preventDefault();
            var bot_token = $twb.val();
            check_tg_bot(bot_token);

        });

    }

    function check_tg_bot(bot_token){

        $twctrl.val(0);

        $.ajax({

            type:"POST",
            url: ajaxurl,
            data: {
                'nonce': tlj.checkbot_nonce,
                'action' : 'check_bot',
                'bot_token' : bot_token
            },
            beforeSend: function(){
                $twbcheck.addClass('disabled').after('<div class="load-spinner"><img src="'+tlj.spinner+'" /></div>');
            },
            dataType: 'json',
            success: function(response) {

                if (response.type === "success") {
                    $twbdesc.html("Bot info: <span class='success'>"+response.args.first_name+" (@"+response.args.username+")</span>");
                }

                else {
                    $twbdesc.html("<span class='error'>"+response.msg+"</span>");
                }

            },
            complete: function() {
                $twbcheck.removeClass('disabled');
                $(".load-spinner").remove();
            }

        })

    }

    function check_tg_token(token, chat_id){

        $.ajax({

            type: "POST",
            url: ajaxurl,
            data: {
                'action' : 'token_check',
                'nonce': tlj.sendtoken_nonce,
                'chat_id': chat_id,
                'token' : token
            },
            beforeSend: function(){
                $twfcheck.addClass('disabled').after('<div class="load-spinner"><img src="'+tlj.spinner+'" /></div>');
                $twfcr.hide();
                hideStatus('#validation-status');
            },
            dataType: "json",
            success: function(response){

                if (response.type === "success") {
                    $twfconf.hide();
                    $twfci.addClass("input-valid");
                    $twctrl.val(1);
                    updateProgress(100);
                    showStatus('#validation-status', 'success', tlj.setup_completed);
                }
                else {
                    showStatus('#validation-status', 'error', response.msg);
                    $twfci.removeClass("input-valid");
                    $twctrl.val(0);
                }

            },
            error: function(xhr, ajaxOptions, thrownError){
                showStatus('#validation-status', 'error', tlj.ajax_error+" "+thrownError+" ("+xhr.state+")");
                $twfci.removeClass("input-valid");
            },
            complete: function() {
                $twfcheck.removeClass('disabled');
                $(".load-spinner").remove();
            }

        });

    }

    function send_tg_token(chat_id) {

        $.ajax({

            type: "POST",
            url: ajaxurl,
            data: {
                'action' : 'send_token_check',
                'nonce': tlj.tokencheck_nonce,
                'chat_id' : chat_id
            },
            beforeSend: function(){
                $twbtn.addClass('disabled').after('<div class="load-spinner"><img src="'+tlj.spinner+'" /></div>');
                $twfcr.hide();
                $twfconf.hide();
                hideStatus('#chat-id-status');
            },
            dataType: "json",
            success: function(response){

                if (response.type === "success") {
                    $twfconf.show();
                    $twfci.removeClass("input-valid");
                    updateProgress(75);
                    showStatus('#chat-id-status', 'success', tlj.code_sent);
                }
                else {
                    showStatus('#chat-id-status', 'error', response.msg);
                }

            },
            error: function(xhr, ajaxOptions, thrownError){
                showStatus('#chat-id-status', 'error', tlj.ajax_error+" "+thrownError+" ("+xhr.state+")");
            },
            complete: function() {
                $twbtn.removeClass('disabled');
                $(".load-spinner").remove();
                $twfci.removeClass("input-valid");
            }

        });

    }

    // Helper functions
    function updateProgress(percentage) {
        $('#tg-progress-bar').css('width', percentage + '%');
    }

    function validateChatId(chatId) {
        // Telegram Chat ID validation: must be numeric (positive for users, negative for groups)
        if (!chatId || typeof chatId !== 'string') {
            return false;
        }
        
        var trimmedId = chatId.trim();
        if (trimmedId === '') {
            return false;
        }
        
        // Check if it's a valid number (can be negative for groups)
        var numericId = parseInt(trimmedId, 10);
        return !isNaN(numericId) && trimmedId === numericId.toString();
    }

    function showStatus(selector, type, message) {
        var $status = $(selector);
        $status.removeClass('success error warning')
               .addClass(type)
               .text(message)
               .fadeIn(300);
    }

    function hideStatus(selector) {
        $(selector).fadeOut(300);
    }

    function resetStatusIndicators() {
        hideStatus('#chat-id-status');
        hideStatus('#validation-status');
    }

}(jQuery);

// Functionality to disable 2FA from users list (admin)
jQuery(document).ready(function($) {
    // Handler for 2FA disable buttons in users list
    $('.disable-2fa-btn').on('click', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var userId = $btn.data('user-id');
        var userName = $btn.data('user-name');
        
        if (!confirm(tlj.confirm_disable.replace('%s', userName))) {
            return;
        }
        
        // Add loading spinner
        $btn.prop('disabled', true).text(tlj.disabling);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'disable_user_2fa',
                user_id: userId,
                nonce: tlj.admin_nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update icon and button
                    var $cell = $btn.closest('td');
                    $cell.html('<span style="color: #999;">❌ ' + tlj.inactive + '</span>');
                    
                    // Show success message
                    $('<div class="notice notice-success is-dismissible"><p>' + tlj.success_disabled.replace('%s', userName) + '</p></div>')
                        .insertAfter('.wp-header-end')
                        .delay(3000)
                        .fadeOut();
                } else {
                    alert(tlj.disable_error + ': ' + (response.data || tlj.unknown_error));
                    $btn.prop('disabled', false).text(tlj.disable);
                }
            },
            error: function() {
                alert(tlj.server_error);
                $btn.prop('disabled', false).text(tlj.disable);
            }
        });
    });
});
