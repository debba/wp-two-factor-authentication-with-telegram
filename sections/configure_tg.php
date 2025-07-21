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
    $nonce = sanitize_text_field($_GET['nonce']);
    
    // Verify nonce
    if (wp_verify_nonce($nonce, 'telegram_validate_' . $user_id . '_' . $token)) {
        // Check if the token is valid using transient
        $chat_id = get_user_meta($user_id, 'tg_wp_factor_chat_id', true);
        if ($chat_id && hash('sha256', $token) === get_transient('wp2fa_telegram_authcode_' . $chat_id)) {
            // Token is valid, show success message
            echo '<div class="notice notice-success is-dismissible"><p>';
            printf(__('✅ Telegram validation successful! Your 2FA setup with chat ID %s is now confirmed.', 'two-factor-login-telegram'), $chat_id);
            echo '</p></div>';
            // Delete the transient as it's been used
            delete_transient('wp2fa_telegram_authcode_' . $chat_id);
        } else {
            // Token is invalid or expired
            echo '<div class="notice notice-error is-dismissible"><p>';
            _e('❌ Validation failed. The token is invalid or has expired.', 'two-factor-login-telegram');
            echo '</p></div>';
        }
    } else {
        // Nonce verification failed
        echo '<div class="notice notice-error is-dismissible"><p>';
        _e('❌ Security check failed. Please try again.', 'two-factor-login-telegram');
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

            // Handle clear logs action
            if (isset($_POST['clear_logs']) && wp_verify_nonce($_POST['_wpnonce'], 'clear_telegram_logs')) {
                delete_option('telegram_bot_logs');
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Logs cleared successfully.', 'two-factor-login-telegram') . '</p></div>';
            }

            $logs = get_option('telegram_bot_logs', array());
            ?>

            <h2><?php _e("Bot Logs", "two-factor-login-telegram"); ?></h2>

            <form method="post">
                <?php wp_nonce_field('clear_telegram_logs'); ?>
                <input type="submit" name="clear_logs" class="button button-secondary" value="<?php _e('Clear Logs', 'two-factor-login-telegram'); ?>" onclick="return confirm('<?php _e('Are you sure you want to clear all logs?', 'two-factor-login-telegram'); ?>')">
            </form>

            <br>

            <?php if (empty($logs)): ?>
                <p><?php _e('No logs available.', 'two-factor-login-telegram'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 15%;"><?php _e('Timestamp', 'two-factor-login-telegram'); ?></th>
                            <th style="width: 15%;"><?php _e('Action', 'two-factor-login-telegram'); ?></th>
                            <th><?php _e('Data', 'two-factor-login-telegram'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html($log['timestamp']); ?></td>
                                <td><?php echo esc_html($log['action']); ?></td>
                                <td>
                                    <details>
                                        <summary><?php _e('View details', 'two-factor-login-telegram'); ?></summary>
                                        <pre style="background: #f1f1f1; padding: 10px; margin-top: 10px; overflow-x: auto;"><?php echo esc_html(print_r($log['data'], true)); ?></pre>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php

        } else if ($active_tab == "howto") {

            ?>

            <h2><?php _e("FAQ", "two-factor-login-telegram"); ?></h2>

            <div id="wpft-howto">
                <h3 id="first"><?php _e("Bot token", "two-factor-login-telegram"); ?></h3>
                <div>
                    <p>
                        <?php _e('If you want to enable <strong>2FA with Telegram</strong> plugin you need to provide a valid token for a Telegram Bot.',
                            "two-factor-login-telegram"); ?>
                        <br/>
                        <?php _e('Have you ever created a bot in Telegram? It\'s so easy!',
                            "two-factor-login-telegram"); ?>
                        <br/>

                    <ol>
                        <li>
                            <?php
                            printf(__('Open Telegram and start a conversation with %s',
                                "two-factor-login-telegram"),
                                '<a href="https://telegram.me/botfather" target="_blank">@BotFather</a>');
                            ?>
                        </li>

                        <li>
                            <?php
                            printf(__('Type command %s to create a new bot',
                                "two-factor-login-telegram"), '<code>/newbot</code>');
                            ?>
                        </li>
                        <li><?php
                            _e('Provide username and name for the new bot.',
                                'two-factor-login-telegram'); ?></li>
                        <li>
                            <?php _e('In the anwser will be your <strong>Bot Token</strong>',
                                'two-factor-login-telegram'); ?>

                            <img style="width:500px;height:auto;"
                                 src="<?php echo plugins_url("/assets/img/help-api-token.png",
                                     WP_FACTOR_TG_FILE); ?>">

                        </li>
                    </ol>
                </div>
                <h3><?php _e("Get Chat ID for Telegram user", "two-factor-login-telegram"); ?></h3>
                <div>
                    <p>
                        <?php _e("Chat ID identifies your user profile in Telegram.",
                            "two-factor-login-telegram"); ?>
                        <br/>
                        <?php _e("You have no idea what is your Chat ID? Follow these simple steps:",
                            "two-factor-login-telegram"); ?>

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
                                <?php
                                printf(__('Open Telegram and start a conversation with your configured bot %s and press on <strong>Start</strong>',
                                    "two-factor-login-telegram"),
                                    '<a href="https://telegram.me/' . $bot_username . '" target="_blank">@' . $bot_username . '</a>');
                                ?>
                            </li>
                            <li>
                                <?php
                                printf(__('Type command %s to obtain your Chat ID.',
                                    "two-factor-login-telegram"), '<code>/get_id</code>');
                                ?>
                            </li>
                            <li>
                                <?php
                                _e("The bot will reply with your <strong>Chat ID</strong> number",
                                    'two-factor-login-telegram');
                                ?>
                            </li>
                        <?php else: ?>
                            <li>
                                <?php _e('First configure your bot token in the Setup tab, then return here for specific instructions.',
                                    "two-factor-login-telegram"); ?>
                            </li>
                            <li>
                                <?php _e('Alternatively, you can use a generic bot like',
                                    "two-factor-login-telegram"); ?>
                                <?php
                                printf(__(' %s and type %s to get your Chat ID.',
                                    "two-factor-login-telegram"),
                                    '<a href="https://telegram.me/myidbot" target="_blank">@MyIDBot</a>',
                                    '<code>/getid</code>');
                                ?>
                            </li>
                        <?php endif; ?>
                    </ol>

                    </p>
                </div>
                <h3><?php _e("Activation of service", "two-factor-login-telegram"); ?></h3>
                <div>
                    <p>
                        <?php _e('Open a conversation with the created bot that you provided for the plugin and push <strong>Start</strong>',
                            'two-factor-login-telegram'); ?>
                        .
                    </p>
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
                    <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>"/>
                </p>

            </form>

            <?php
        }
        ?>

    </div>

</div>

<?php do_action("tft_copyright"); ?>
