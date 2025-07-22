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

        // Handle checkbox toggle for 2FA configuration
        $twenabled.on("change", function(evt){
            if ($(this).is(":checked")) {
                $twconfig.show();
            } else {
                $twconfig.hide();
                $twctrl.val(0);
            }
        });

        // Initialize visibility based on checkbox state
        if ($twenabled.is(":checked")) {
            $twconfig.show();
        } else {
            $twconfig.hide();
        }

        $twfci.on("change", function(evt){
           $twctrl.val(0);
        });

        $twbtn.on("click", function(evt){

            evt.preventDefault();
            var chat_id = $twfci.val();
            send_tg_token(chat_id);

        });

        $twfcheck.on("click", function(evt){

            evt.preventDefault();
            var token = $twfciconf.val();
            var chat_id = $twfci.val();
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
            },
            dataType: "json",
            success: function(response){

                if (response.type === "success") {
                    $twfconf.hide();
                    $twfci.addClass("input-valid");
                    $twctrl.val(1);
                }
                else {
                    $twfcr.find(".wpft-notice p").text(response.msg);
                    $twfcr.show();
                    $twfci.removeClass("input-valid");
                    $twctrl.val(0);
                }

            },
            error: function(xhr, ajaxOptions, thrownError){
                $twfcr.find(".wpft-notice p").text(tlj.ajax_error+" "+thrownError+" ("+xhr.state+")");
                $twfcr.show();
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
            },
            dataType: "json",
            success: function(response){

                if (response.type === "success") {
                    $twfconf.show();
                    $twfci.removeClass("input-valid");
                }
                else {
                    $twfcr.find(".wpft-notice p").text(response.msg);
                    $twfcr.show();
                }

            },
            error: function(xhr, ajaxOptions, thrownError){
                $twfcr.find(".wpft-notice p").text(tlj.ajax_error+" "+thrownError+" ("+xhr.state+")");
                $twfcr.show();
            },
            complete: function() {
                $twbtn.removeClass('disabled');
                $(".load-spinner").remove();
                $twfci.removeClass("input-valid");
            }

        });

    }



}(jQuery);
