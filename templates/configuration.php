<?php
if (isset($_GET['tab'])) {
    $active_tab = sanitize_text_field($_GET['tab']);
} else {
    $active_tab = 'config';
}

// Handle telegram validation action
if (isset($_GET['action']) && $_GET['action'] === 'telegram_validate') {
    $user_id = intval($_GET['user_id']);
    $token = sanitize_text_field($_GET['token']);
    $chat_id = sanitize_text_field($_GET['chat_id']);
    $nonce = sanitize_text_field($_GET['nonce']);
    $validation_success = false;

    // Verify nonce
    if (wp_verify_nonce($nonce, 'telegram_validate_' . $user_id . '_' . $token)) {
        $plugin_instance = WP_Factor_Telegram_Plugin::get_instance();

        // Save user 2FA settings - this enables 2FA and saves the chat_id
        $save_result = $plugin_instance->save_user_2fa_settings($user_id, $chat_id, true);

        if ($save_result) {
            $validation_success = true;
            // Delete the transient as it's been used
            delete_transient('wp2fa_telegram_authcode_' . $chat_id);

            // Log the successful validation
            $plugin_instance->log_telegram_action('validation_success', array(
                'user_id' => $user_id,
                'chat_id' => $chat_id,
                'method' => 'validate_setup_button'
            ));
        } else {
            // Log nonce verification failure
            $plugin_instance = WP_Factor_Telegram_Plugin::get_instance();
            $plugin_instance->log_telegram_action('validation_failed', array(
                'user_id' => $user_id,
                'token' => $token,
                'reason' => 'nonce_verification_failed'
            ));
        }

    }

    if ($validation_success) {
        echo '<div class="notice notice-success is-dismissible"><p>';
        _e('✅ Telegram validation successful! Your 2FA setup is now confirmed and enabled.', 'two-factor-login-telegram');
        echo '</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>';
        _e('❌ Validation failed. The token is invalid, has expired, or there was a security error.', 'two-factor-login-telegram');
        echo '</p></div>';
    }
}

?>

<div id="wft-wrap" class="wrap">

    <div class="heading-top">
        <div class="cover-tg-plugin">
        </div>
        <h1><?php _e("Setup", "two-factor-login-telegram"); ?>
            - <?php _e("2FA with Telegram", "two-factor-login-telegram"); ?></h1>
    </div>
    <h2 class="wpft-tab-wrapper nav-tab-wrapper">
        <a href="<?php echo admin_url('options-general.php?page=tg-conf&tab=config'); ?>"
           class="nav-tab <?php echo $active_tab == 'config' ? 'nav-tab-active' : ''; ?>"><span
                    class="dashicons dashicons-admin-settings"></span> <?php _e("Setup",
                "two-factor-login-telegram"); ?></a>
        <a href="<?php echo admin_url('options-general.php?page=tg-conf&tab=howto'); ?>"
           class="nav-tab <?php echo $active_tab == 'howto' ? 'nav-tab-active' : ''; ?>"><span
                    class="dashicons dashicons-editor-help"></span> <?php _e("FAQ",
                "two-factor-login-telegram"); ?>
        </a>

       <?php if ($this->is_valid_bot()) { ?>
            <a href="<?php echo admin_url('options-general.php?page=tg-conf&tab=logs'); ?>"
               class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>"><span
                        class="dashicons dashicons-list-view"></span> <?php _e("Bot Logs",
                    "two-factor-login-telegram"); ?>
            </a>
        <?php } ?>

        <?php

        if ($this->is_valid_bot() && get_the_author_meta("tg_wp_factor_chat_id",
                get_current_user_id()) !== false) {
            ?>

            <a href="<?php echo admin_url('options-general.php?page=tg-conf&tab=suggestions'); ?>"
               class="nav-tab <?php echo $active_tab == 'suggestions' ? 'nav-tab-active' : ''; ?>"><span
                        class="dashicons dashicons-heart"></span> <?php _e("Suggestions",
                    "two-factor-login-telegram"); ?>
            </a>

            <?php
        }

        ?>

    </h2>

    <div class="wpft-container">

        <?php

        if ($active_tab == "logs") {
            global $wpdb;

            $activities_table = $wpdb->prefix . 'wp2fat_activities';

            // Create an instance of our package class
            $logs_list_table = new Telegram_Logs_List_Table();

            // Handle clear logs action
            if (isset($_POST['clear_logs']) && wp_verify_nonce($_POST['_wpnonce'], 'clear_telegram_logs')) {
                $wpdb->query("DELETE FROM $activities_table");
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Logs cleared successfully.', 'two-factor-login-telegram') . '</p></div>';
            }

            // Process bulk actions
            $logs_list_table->process_bulk_action();

            // Prepare the table
            $logs_list_table->prepare_items();
            ?>

            <h2><?php _e("Bot Logs", "two-factor-login-telegram"); ?></h2>

            <form method="post">
                <?php wp_nonce_field('clear_telegram_logs'); ?>
                <input type="submit" name="clear_logs" class="tg-action-button" value="<?php _e('Clear Logs', 'two-factor-login-telegram'); ?>" onclick="return confirm('<?php _e('Are you sure you want to clear all logs?', 'two-factor-login-telegram'); ?>')">
            </form>

            <br>

            <form method="get">
                <input type="hidden" name="page" value="<?php echo $_REQUEST['page']; ?>" />
                <input type="hidden" name="tab" value="logs" />
                <?php $logs_list_table->display(); ?>
            </form>

            <?php

        } else if ($active_tab == "howto") {

            ?>

            <h2><?php _e("FAQ", "two-factor-login-telegram"); ?></h2>

            <div id="wpft-howto">
                <!-- Bot Token Section -->
                <h3 id="first"><?php _e("Bot token", "two-factor-login-telegram"); ?></h3>
                <div class="faq-content">
                    <p>
                        <?php _e('If you want to enable <strong>2FA with Telegram</strong> plugin you need to provide a valid token for a Telegram Bot.',
                            "two-factor-login-telegram"); ?>
                    </p>
                    <p>
                        <?php _e('Have you ever created a bot in Telegram? It\'s so easy!',
                            "two-factor-login-telegram"); ?>
                    </p>

                    <ol>
                        <li>
                            <strong><?php _e('Open Telegram', 'two-factor-login-telegram'); ?></strong><br>
                            <?php
                            printf(__('Start a conversation with %s',
                                "two-factor-login-telegram"),
                                '<a href="https://telegram.me/botfather" target="_blank" class="external-link">@BotFather</a>');
                            ?>
                        </li>

                        <li>
                            <strong><?php _e('Create new bot', 'two-factor-login-telegram'); ?></strong><br>
                            <?php
                            printf(__('Type command %s to create a new bot',
                                "two-factor-login-telegram"), '<code class="command">/newbot</code>');
                            ?>
                        </li>

                        <li>
                            <strong><?php _e('Configure bot', 'two-factor-login-telegram'); ?></strong><br>
                            <?php _e('Provide username and name for the new bot.',
                                'two-factor-login-telegram'); ?>
                        </li>

                        <li>
                            <strong><?php _e('Get your Bot Token', 'two-factor-login-telegram'); ?></strong><br>
                            <?php _e('In the answer will be your <strong>Bot Token</strong>',
                                'two-factor-login-telegram'); ?>
                            
                            <div class="screenshot-container">
                                <img class="help-screenshot" 
                                     src="<?php echo plugins_url("/assets/img/help-api-token.png", WP_FACTOR_TG_FILE); ?>"
                                     alt="<?php _e('Bot token example', 'two-factor-login-telegram'); ?>">
                            </div>
                        </li>
                    </ol>
                </div>

                <!-- Chat ID Section -->
                <h3><?php _e("Get Chat ID for Telegram user", "two-factor-login-telegram"); ?></h3>
                <div class="faq-content">
                    <p>
                        <?php _e("Chat ID identifies your user profile in Telegram.",
                            "two-factor-login-telegram"); ?>
                    </p>
                    <p>
                        <?php _e("You have no idea what is your Chat ID? Follow these simple steps:",
                            "two-factor-login-telegram"); ?>
                    </p>

                    <ol>
                        <?php
                        $bot_username = null;
                        if ($this->is_valid_bot()) {
                            $me = $this->telegram->get_me();
                            if ($me && isset($me->username)) {
                                $bot_username = $me->username;
                            }
                        }

                        if ($bot_username): ?>
                            <li>
                                <strong><?php _e('Contact your bot', 'two-factor-login-telegram'); ?></strong><br>
                                <?php
                                printf(__('Open Telegram and start a conversation with your configured bot %s and press on <strong>Start</strong>',
                                    "two-factor-login-telegram"),
                                    '<a href="https://telegram.me/' . $bot_username . '" target="_blank" class="external-link">@' . $bot_username . '</a>');
                                ?>
                            </li>
                            <li>
                                <strong><?php _e('Get your ID', 'two-factor-login-telegram'); ?></strong><br>
                                <?php
                                printf(__('Type command %s to obtain your Chat ID.',
                                    "two-factor-login-telegram"), '<code class="command">/get_id</code>');
                                ?>
                            </li>
                            <li>
                                <strong><?php _e('Copy the ID', 'two-factor-login-telegram'); ?></strong><br>
                                <?php _e("The bot will reply with your <strong>Chat ID</strong> number",
                                    'two-factor-login-telegram'); ?>
                            </li>
                        <?php else: ?>
                            <li>
                                <strong><?php _e('Configure bot first', 'two-factor-login-telegram'); ?></strong><br>
                                <?php _e('First configure your bot token in the Setup tab, then return here for specific instructions.',
                                    "two-factor-login-telegram"); ?>
                            </li>
                            <li>
                                <strong><?php _e('Alternative method', 'two-factor-login-telegram'); ?></strong><br>
                                <?php _e('Alternatively, you can use a generic bot like',
                                    "two-factor-login-telegram"); ?>
                                <?php
                                printf(__(' %s and type %s to get your Chat ID.',
                                    "two-factor-login-telegram"),
                                    '<a href="https://telegram.me/myidbot" target="_blank" class="external-link">@MyIDBot</a>',
                                    '<code class="command">/getid</code>');
                                ?>
                            </li>
                        <?php endif; ?>
                    </ol>
                </div>

                <!-- Activation Section -->
                <h3><?php _e("Activation of service", "two-factor-login-telegram"); ?></h3>
                <div class="faq-content">
                    <p>
                        <?php _e('Open a conversation with the created bot that you provided for the plugin and push <strong>Start</strong>',
                            'two-factor-login-telegram'); ?>.
                    </p>
                    <div class="notice notice-info">
                        <p>
                            <?php _e('This step is crucial for the bot to be able to send you messages!', 'two-factor-login-telegram'); ?>
                        </p>
                    </div>
                </div>
            </div>

            <?php

        } else if ($active_tab == 'suggestions') {
            ?>
            <h2><?php _e("Suggestions", "two-factor-login-telegram"); ?></h2>

            <div id="wpft-suggestions">
                <p>
                    <?php _e("We would love to hear your feedback and suggestions! You can share them with us in three ways:", "two-factor-login-telegram"); ?>
                </p>
                <ol>
                    <li>
                        <?php _e('Send us an email at <a href="mailto:info@dueclic.com">info@dueclic.com</a>.',
                            "two-factor-login-telegram"); ?>
                    </li>
                    <li>
                        <?php
                        printf(__('Visit the <a href="%s" target="_blank">support section on WordPress.org</a>.',
                            "two-factor-login-telegram"),
                            'https://wordpress.org/support/plugin/two-factor-login-telegram/');
                        ?>
                    </li>
                    <li>
                        <?php
                        printf(__('Submit your issues or ideas on our <a href="%s" target="_blank">GitHub project page</a>.',
                            "two-factor-login-telegram"),
                            'https://github.com/debba/wp-two-factor-authentication-with-telegram/issues');
                        ?>
                    </li>
                </ol>
            </div>
            <?php
        } else {

            if ($this->is_valid_bot() && get_the_author_meta("tg_wp_factor_chat_id",
                    get_current_user_id()) !== false) :

                $chat_id = get_user_meta(get_current_user_id(), "tg_wp_factor_chat_id", true);

                if (empty($chat_id)) :

                    ?>

                    <div class="wpft-notice wpft-notice-warning">
                        <p>
                            <span class="dashicons dashicons-editor-help"></span> <?php

                            printf(__('Ok! <a href="%s">It\'s time to set your user</a>.',
                                "two-factor-login-telegram"),
                                admin_url('profile.php#wptl'));

                            ?>
                        </p>
                    </div>

                <?php

                endif;

            else:

                ?>

                <div class="wpft-notice wpft-notice-warning">
                    <p>
                        <span class="dashicons dashicons-editor-help"></span> <?php

                        printf(__('First time? <a href="%s">Take a look into the FAQ!</a>',
                            "two-factor-login-telegram"),
                            admin_url('options-general.php?page=tg-conf&tab=howto'));

                        ?>
                    </p>
                </div>

            <?php
            endif;
            ?>

            <form method="post" enctype="multipart/form-data" action="options.php">

                <?php
                settings_fields('tg_col');
                do_settings_sections('tg_col.php');
                ?>
                <p class="submit">
                    <input type="submit" class="tg-action-button" value="<?php _e('Save Changes') ?>"/>
                </p>

            </form>

            <?php
        }
        ?>

    </div>

</div>

<?php do_action("tft_copyright"); ?>
