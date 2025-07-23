<?php

final class WP_Factor_Telegram_Plugin
{

    /**
     * Get an instance
     *
     * @var WP_Factor_Telegram_Plugin
     */

    private static $instance;

    /**
     * Namespace for prefixed setting
     *
     * @var string
     */

    private $namespace = "tg_col";

    /**
     * @var WP_Telegram
     */

    private $telegram;

    /**
     * Get WP Factor Telegram
     *
     * @return WP_Factor_Telegram_Plugin
     */

    public static function get_instance()
    {
        if (
            empty(self::$instance)
            && !(self::$instance instanceof WP_Factor_Telegram_Plugin)
        ) {
            self::$instance = new WP_Factor_Telegram_Plugin;
            self::$instance->includes();
            self::$instance->telegram = new WP_Telegram;
            self::$instance->add_hooks();

            do_action("wp_factor_telegram_loaded");
        }

        return self::$instance;
    }

    /**
     * Include classes
     */

    public function includes()
    {
        require_once(dirname(WP_FACTOR_TG_FILE)
            . "/includes/class-wp-telegram.php");
        require_once(dirname(WP_FACTOR_TG_FILE)
            . "/includes/class-telegram-logs-list-table.php");
    }


    /**
     * Get authentication code
     *
     * @param int $length
     *
     * @return string
     */

    private function get_auth_code($length = 5)
    {
        $pool = array_merge(range(0, 9), range('a', 'z'), range('A', 'Z'));
        $key = "";

        for ($i = 0; $i < $length; $i++) {
            $key .= $pool[random_int(0, count($pool) - 1)];
        }

        return $key;
    }

    private function token_exists($token)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'telegram_auth_codes';
        $current_datetime = current_time('mysql');
        $hashed_token = hash('sha256', $token);

        $query = $wpdb->prepare(
            "SELECT COUNT(*) 
        FROM $table_name 
        WHERE auth_code = %s 
        AND expiration_date > %s",
            $hashed_token,
            $current_datetime
        );

        return ($wpdb->get_var($query) > 0);
    }


    /**
     * Get unique authentication code
     *
     * @param int $length
     *
     * @return string
     */

    private function get_unique_auth_code($length = 5)
    {
        do {
            $token = $this->get_auth_code($length);
        } while ($this->token_exists($token));

        return $token;
    }

    private function invalidate_existing_auth_codes($user_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'telegram_auth_codes';

        $wpdb->update(
            $table_name,
            array('expiration_date' => current_time('mysql')), // Imposta l'expiration_date nel passato
            array('user_id' => $user_id),
            array('%s'),
            array('%d')
        );
    }

    private function cleanup_old_auth_codes($user_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'telegram_auth_codes';

        // Conta quanti codici ci sono già per l'utente
        $auth_codes_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE user_id = %d",
            $user_id
        ));

        // Se ci sono più di 5 codici, elimina quelli più vecchi
        if ($auth_codes_count > 5) {
            // Seleziona gli ID dei codici più vecchi da eliminare, escludendo i 5 più recenti
            $old_auth_codes = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM $table_name WHERE user_id = %d ORDER BY creation_date DESC LIMIT %d, %d",
                $user_id,
                5,
                $auth_codes_count - 5
            ));

            // Elimina i codici più vecchi
            if (!empty($old_auth_codes)) {
                $placeholders = implode(',', array_fill(0, count($old_auth_codes), '%d'));
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM $table_name WHERE id IN ($placeholders)",
                    ...$old_auth_codes
                ));
            }
        }
    }


    /**
     * Save authentication code in database table
     *
     * @param $user
     * @param $authcode_length
     * @return string | false
     */

    private function save_authcode($user, $authcode_length = 5)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'telegram_auth_codes';
        $auth_code = $this->get_unique_auth_code($authcode_length);
        $user_id = is_object($user) ? $user->ID : intval($user);

        $creation_date = current_time('mysql');  // Data attuale
        $expiration_date = date('Y-m-d H:i:s', strtotime($creation_date) + WP_FACTOR_AUTHCODE_EXPIRE_SECONDS);

        $this->invalidate_existing_auth_codes($user_id);

        $wpdb->insert(
            $table_name,
            array(
                'auth_code' => hash('sha256', $auth_code),
                'user_id' => $user_id,
                'creation_date' => $creation_date,
                'expiration_date' => $expiration_date
            ),
            array(
                '%s',
                '%d',
                '%s',
                '%s'
            )
        );

        if ($wpdb->insert_id) {
            $this->cleanup_old_auth_codes($user_id);
            return $auth_code;
        } else {
            return false;
        }
    }

    /**
     * Show to factor login html
     *
     * @param $user
     */

    private function show_two_factor_login($user)
    {
        $auth_code = $this->save_authcode($user);
        $chat_id = $this->get_user_chatid($user->ID);

        $result = $this->telegram->send_tg_token($auth_code, $chat_id, $user->ID);

        $this->log_telegram_action('auth_code_sent', array(
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'chat_id' => $chat_id,
            'success' => $result !== false
        ));

        $redirect_to = isset($_REQUEST['redirect_to'])
            ? wp_sanitize_redirect($_REQUEST['redirect_to']) : wp_unslash($_SERVER['REQUEST_URI']);

        $this->login_html($user, $redirect_to);
    }

    /**
     * Login HTML Page
     *
     * @param          $user
     * @param          $redirect_to
     * @param string $error_msg
     */

    private function login_html($user, $redirect_to, $error_msg = '')
    {
        $rememberme = 0;
        if (isset($_REQUEST['rememberme']) && $_REQUEST['rememberme']) {
            $rememberme = 1;
        }

        // Filter hook to add a custom logo to the 2FA login screen
        $plugin_logo = apply_filters(
            'two_factor_login_telegram_logo',
            plugins_url('assets/img/plugin_logo.png', WP_FACTOR_TG_FILE)
        );

        require_once(ABSPATH . '/wp-admin/includes/template.php');
        require_once(dirname(WP_FACTOR_TG_FILE) . "/templates/login-form.php");
    }

    /**
     * Show telegram login
     *
     * @param $user_login
     * @param $user
     */

    public function tg_login($user_login, $user)
    {
        if (
            get_option($this->namespace)['enabled'] === '1'
            && get_the_author_meta("tg_wp_factor_enabled", $user->ID) === "1"
        ) {
            wp_clear_auth_cookie();
            $this->show_two_factor_login($user);
            exit;
        }
    }

    private function is_valid_tokencheck_authcode($authcode, $chat_id)
    {
        return hash('sha256', $authcode) === get_transient("wp2fa_telegram_authcode_" . $chat_id);
    }

    private function is_valid_authcode($authcode, $user_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'telegram_auth_codes';
        $hashed_auth_code = hash('sha256', $authcode);
        $current_datetime = current_time('mysql');

        // Check if token exists for this user
        $token_exists_query = $wpdb->prepare(
            "SELECT COUNT(*) 
            FROM $table_name 
            WHERE auth_code = %s 
            AND user_id = %d",
            $hashed_auth_code,
            $user_id
        );

        $token_exists = ($wpdb->get_var($token_exists_query) > 0);

        if (!$token_exists) {
            return 'invalid'; // Invalid token
        }

        // Check if token is not expired
        $valid_token_query = $wpdb->prepare(
            "SELECT COUNT(*) 
            FROM $table_name 
            WHERE auth_code = %s 
            AND user_id = %d 
            AND expiration_date > %s",
            $hashed_auth_code,
            $user_id,
            $current_datetime
        );

        $is_valid = ($wpdb->get_var($valid_token_query) > 0);

        if (!$is_valid) {
            return 'expired'; // Token exists but expired
        }

        return 'valid'; // Valid token
    }

    /**
     * Validate telegram auth code login
     */

    public function validate_tg()
    {

        if (!isset($_POST['wp-auth-id'])) {
            return;
        }

        $user = get_userdata($_POST['wp-auth-id']);
        if (!$user) {
            return;
        }

        if (!wp_verify_nonce($_POST['nonce'], 'wp2fa_telegram_auth_nonce_' . $user->ID)) {
            return;
        }

        // Check which login method was used
        $login_method = isset($_POST['login_method']) ? $_POST['login_method'] : 'telegram';
        $login_successful = false;
        $error_message = '';

        if ($login_method === 'recovery') {
            // Handle recovery code login
            $recovery_code = isset($_POST['recovery_code']) ? $_POST['recovery_code'] : '';
            
            if (empty($recovery_code)) {
                $error_message = __('Please enter a recovery code.', 'two-factor-login-telegram');
            } else {
                $recovery_validation = $this->validate_recovery_code($recovery_code, $user->ID);
                
                if ($recovery_validation) {
                    $login_successful = true;
                    
                    $this->log_telegram_action('recovery_code_login_success', array(
                        'user_id' => $user->ID,
                        'user_login' => $user->user_login,
                        'recovery_code_used' => substr($recovery_code, 0, 4) . '****'
                    ));
                } else {
                    $error_message = __('Invalid recovery code. Please check and try again.', 'two-factor-login-telegram');
                    
                    $this->log_telegram_action('recovery_code_login_failed', array(
                        'user_id' => $user->ID,
                        'user_login' => $user->user_login,
                        'attempted_code' => substr($recovery_code, 0, 4) . '****'
                    ));
                    
                    do_action('wp_factor_telegram_failed', $user->user_login);
                }
            }
        } else {
            // Handle Telegram code login (original logic)
            $authcode_validation = $this->is_valid_authcode($_REQUEST['authcode'], $user->ID);

            if ('valid' === $authcode_validation) {
                $login_successful = true;
            } else {
                do_action('wp_factor_telegram_failed', $user->user_login);

                $auth_code = $this->save_authcode($user);

                $chat_id = $this->get_user_chatid($user->ID);
                $result = $this->telegram->send_tg_token($auth_code, $chat_id, $user->ID);

                // Determine error message based on validation result
                $log_reason = '';

                if ($authcode_validation === 'expired') {
                    $error_message = __(
                        'The verification code has expired. We just sent you a new code, please try again!',
                        'two-factor-login-telegram'
                    );
                    $log_reason = 'expired_verification_code';
                } else {
                    $error_message = __(
                        'Wrong verification code, we just sent a new code, please try again!',
                        'two-factor-login-telegram'
                    );
                    $log_reason = 'wrong_verification_code';
                }

                $this->log_telegram_action('auth_code_resent', array(
                    'user_id' => $user->ID,
                    'user_login' => $user->user_login,
                    'chat_id' => $chat_id,
                    'success' => $result !== false,
                    'reason' => $log_reason
                ));
            }
        }

        // If login failed, show error and exit
        if (!$login_successful) {
            $this->login_html($user, $_REQUEST['redirect_to'], $error_message);
            exit;
        }

        $rememberme = false;
        if (isset($_REQUEST['rememberme']) && $_REQUEST['rememberme']) {
            $rememberme = true;
        }

        wp_set_auth_cookie($user->ID, $rememberme);

        // Se l'utente non ha recovery codes, mostra wizard
        if (!$this->has_recovery_codes($user->ID)) {
            $codes = $this->regenerate_recovery_codes($user->ID);
            $plugin_logo = apply_filters('two_factor_login_telegram_logo', plugins_url('assets/img/plugin_logo.png', WP_FACTOR_TG_FILE));
            $redirect_to = apply_filters('login_redirect', $_REQUEST['redirect_to'], $_REQUEST['redirect_to'], $user);
            require_once(dirname(WP_FACTOR_TG_FILE) . '/templates/recovery-codes-wizard.php');
            exit;
        }

        $redirect_to = apply_filters(
            'login_redirect',
            $_REQUEST['redirect_to'],
            $_REQUEST['redirect_to'],
            $user
        );
        wp_safe_redirect($redirect_to);

        exit;
    }

    public function configure_tg()
    {
        require_once(dirname(WP_FACTOR_TG_FILE) . "/templates/configuration.php");
    }

    /**
     * Show Telegram bot logs page
     */
    public function show_telegram_logs()
    {
        require_once(dirname(WP_FACTOR_TG_FILE) . "/templates/logs-page.php");
    }

    public function tg_load_menu()
    {
        add_options_page(
            __(
                "2FA with Telegram",
                "two-factor-login-telegram"
            ),
            __(
                "2FA with Telegram",
                "two-factor-login-telegram"
            ),
            "manage_options",
            "tg-conf",
            array(
                $this,
                "configure_tg",
            )
        );
    }

    function sanitize_settings($input)
    {
        // Sanitize input values
        $sanitized = array();

        if (isset($input['bot_token'])) {
            $sanitized['bot_token'] = sanitize_text_field($input['bot_token']);
        }

        if (isset($input['chat_id'])) {
            $sanitized['chat_id'] = sanitize_text_field($input['chat_id']);
        }

        if (isset($input['enabled'])) {
            $sanitized['enabled'] = $input['enabled'] === '1' ? '1' : '0';
        }

        if (isset($input['show_site_name'])) {
            $sanitized['show_site_name'] = $input['show_site_name'] === '1' ? '1' : '0';
        }

        if (isset($input['show_site_url'])) {
            $sanitized['show_site_url'] = $input['show_site_url'] === '1' ? '1' : '0';
        }

        if (isset($input['delete_data_on_deactivation'])) {
            $sanitized['delete_data_on_deactivation'] = $input['delete_data_on_deactivation'] === '1' ? '1' : '0';
        }

        // Set webhook if bot token is provided
        if (!empty($sanitized['bot_token'])) {

            $is_valid_bot = $this->telegram->get_me() !== false;
            set_transient(WP_FACTOR_TG_GETME_TRANSIENT, $is_valid_bot, 60 * 60 * 24);

            if ($is_valid_bot) {
                $webhook_url = rest_url('telegram/v1/webhook');
                $this->telegram->set_bot_token($sanitized['bot_token'])->set_webhook($webhook_url);
            }
        }

        return $sanitized;
    }

    function tg_register_settings()
    {
        register_setting($this->namespace, $this->namespace, array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));

        add_settings_section(
            $this->namespace . '_section',
            __('Telegram Configuration', "two-factor-login-telegram"),
            '',
            $this->namespace . '.php'
        );

        add_settings_section(
            $this->namespace . '_failed_login_section',
            __('Failed Login Report', "two-factor-login-telegram"),
            array($this, 'failed_login_section_callback'),
            $this->namespace . '.php'
        );

        add_settings_section(
            $this->namespace . '_data_management_section',
            __('Data Management', "two-factor-login-telegram"),
            array($this, 'data_management_section_callback'),
            $this->namespace . '.php'
        );

        $field_args = array(
            'type' => 'text',
            'id' => 'bot_token',
            'name' => 'bot_token',
            'desc' => __('Bot Token', "two-factor-login-telegram"),
            'std' => '',
            'label_for' => 'bot_token',
            'class' => 'css_class',
        );

        add_settings_field(
            'bot_token',
            __('Bot Token', "two-factor-login-telegram"),
            array(
                $this,
                'tg_display_setting',
            ),
            $this->namespace . '.php',
            $this->namespace . '_section',
            $field_args
        );

        // Move Chat ID to Failed Login Report section
        $field_args = array(
            'type' => 'text',
            'id' => 'chat_id',
            'name' => 'chat_id',
            'desc' => __(
                'Enter your Telegram Chat ID to receive notifications about failed login attempts.',
                "two-factor-login-telegram"
            ),
            'std' => '',
            'label_for' => 'chat_id',
            'class' => 'css_class',
        );

        add_settings_field(
            'chat_id',
            __('Chat ID for Reports', "two-factor-login-telegram"),
            array(
                $this,
                'tg_display_setting',
            ),
            $this->namespace . '.php',
            $this->namespace . '_failed_login_section',
            $field_args
        );

        $field_args = array(
            'type' => 'checkbox',
            'id' => 'enabled',
            'name' => 'enabled',
            'desc' => __(
                'Select this checkbox to enable the plugin.',
                'two-factor-login-telegram'
            ),
            'std' => '',
            'label_for' => 'enabled',
            'class' => 'css_class',
        );

        add_settings_field(
            'enabled',
            __('Enable plugin?', 'two-factor-login-telegram'),
            array(
                $this,
                'tg_display_setting',
            ),
            $this->namespace . '.php',
            $this->namespace . '_section',
            $field_args
        );

        // Move Show site name to Failed Login Report section
        $field_args = array(
            'type' => 'checkbox',
            'id' => 'show_site_name',
            'name' => 'show_site_name',
            'desc' => __(
                'Include site name in failed login notifications.<br>Useful when using the same bot for multiple sites.',
                'two-factor-login-telegram'
            ),
            'std' => '',
            'label_for' => 'show_site_name',
            'class' => 'css_class',
        );

        add_settings_field(
            'show_site_name',
            __('Show Site Name', 'two-factor-login-telegram'),
            array(
                $this,
                'tg_display_setting',
            ),
            $this->namespace . '.php',
            $this->namespace . '_failed_login_section',
            $field_args
        );

        // Move Show site URL to Failed Login Report section
        $field_args = array(
            'type' => 'checkbox',
            'id' => 'show_site_url',
            'name' => 'show_site_url',
            'desc' => __(
                'Include site URL in failed login notifications.<br>Useful when using the same bot for multiple sites.',
                'two-factor-login-telegram'
            ),
            'std' => '',
            'label_for' => 'show_site_url',
            'class' => 'css_class',
        );

        add_settings_field(
            'show_site_url',
            __('Show Site URL', 'two-factor-login-telegram'),
            array(
                $this,
                'tg_display_setting',
            ),
            $this->namespace . '.php',
            $this->namespace . '_failed_login_section',
            $field_args
        );

        // Add data cleanup option to Data Management section
        $field_args = array(
            'type' => 'checkbox',
            'id' => 'delete_data_on_deactivation',
            'name' => 'delete_data_on_deactivation',
            'desc' => __(
                'Delete all plugin data when the plugin is deactivated.<br><strong>Warning:</strong> This will permanently remove all settings, user configurations, authentication codes, and logs.',
                'two-factor-login-telegram'
            ),
            'std' => '',
            'label_for' => 'delete_data_on_deactivation',
            'class' => 'css_class',
        );

        add_settings_field(
            'delete_data_on_deactivation',
            __('Delete Data on Deactivation', 'two-factor-login-telegram'),
            array(
                $this,
                'tg_display_setting',
            ),
            $this->namespace . '.php',
            $this->namespace . '_data_management_section',
            $field_args
        );
    }

    public function tg_display_setting($args)
    {
        extract($args);

        $option_name = $this->namespace;

        $options = get_option($option_name);

        /** @var $type */
        /** @var $id */
        /** @var $desc */
        /** @var $class */

        switch ($type) {
            case 'text':
                $options[$id] = stripslashes($options[$id]);
                $options[$id] = esc_attr($options[$id]);
                echo "<input class='regular-text $class' type='text' id='$id' name='"
                    . $option_name . "[$id]' value='$options[$id]' />";

                if ($id == "bot_token") {
                    ?>
                    <button id="checkbot" class="button-secondary" type="button"><?php
                    echo __(
                        "Check",
                        "two-factor-login-telegram"
                    ) ?></button>
                    <?php
                }

                echo ($desc != '')
                    ? '<br /><p class="wft-settings-description" id="' . $id
                    . '_desc">' . $desc . '</p>' : "";
                break;

            case 'checkbox':

                $options[$id] = stripslashes($options[$id]);
                $options[$id] = esc_attr($options[$id]);
                ?>
                <label for="<?php
                echo esc_attr($id); ?>">
                    <input class="regular-text <?php
                    echo esc_attr($class); ?>" type="checkbox" id="<?php
                      echo esc_attr($id); ?>" name="<?php
                        echo esc_attr($option_name); ?>[<?php
                          echo esc_attr($id); ?>]" value="1" <?php
                            echo checked(1, $options[$id]); ?> />
                    <?php
                    _e($desc); ?>
                </label>
                <?php
                break;
            case 'textarea':

                wp_editor(
                    $options[$id],
                    $id,
                    array(
                        'textarea_name' => $option_name . "[$id]",
                        'style' => 'width: 200px',
                    )
                );

                break;
        }
    }

    /**
     * Failed login section callback
     */
    public function failed_login_section_callback()
    {
        echo '<p>' . __('Configure how to receive notifications when someone fails to log in to your site.', 'two-factor-login-telegram') . '</p>';
    }

    /**
     * Data management section callback
     */
    public function data_management_section_callback()
    {
        echo '<p>' . __('Manage plugin data and cleanup options.', 'two-factor-login-telegram') . '</p>';
    }

    /**
     * Action links
     *
     * @param $links
     *
     * @return array
     */

    public function action_links($links)
    {
        /** @noinspection PhpUndefinedConstantInspection */

        $plugin_links = array(
            '<a href="' . admin_url('options-general.php?page=tg-conf') . '">'
            . __('Settings', 'two-factor-login-telegram') . '</a>',
        );

        return array_merge($plugin_links, $links);
    }

    public function settings_error_set_chatid()
    {
        if (get_current_screen()->id != "profile") {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p><?php
                printf(
                    __(
                        'Do you want to configure 2FA with Telegram?  <a href="%s">click here</a>!',
                        "two-factor-login-telegram"
                    ),
                    admin_url("profile.php")
                );
                ?></p>
            </div>
            <?php
        }
    }

    public function settings_error_not_valid_bot()
    {
        if (get_current_screen()->id != "settings_page_tg-conf") {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php
                printf(
                    __(
                        'Do you want to configure 2FA with Telegram?  <a href="%s">click here</a>!',
                        "two-factor-login-telegram"
                    ),
                    admin_url("options-general.php?page=tg-conf")
                ); ?>
                </p>
            </div>
            <?php
        }
    }

    /**
     * @param $user
     */

    public function tg_add_two_factor_fields($user)
    {
        $current_user_id = get_current_user_id();
        if ($current_user_id != $user->ID) {
            return;
        }

        // Gestione rigenerazione recovery codes
        if (
            isset($_POST['tg_regenerate_recovery_codes']) &&
            check_admin_referer('tg_regenerate_recovery_codes_' . $user->ID)
        ) {
            // Genera nuovi codici in chiaro
            $codes = $this->generate_recovery_codes(8, 10);
            // Salva hashati
            $this->set_user_recovery_codes($user->ID, $codes);
            // Salva i codici in chiaro in una transient temporanea per mostrarli via AJAX
            set_transient('tg_show_new_recovery_' . $user->ID, $codes, 120);
            // Redirect per mostrare la modale
            wp_safe_redirect(add_query_arg('show_new_recovery', 1, remove_query_arg('show_new_recovery', $_SERVER['REQUEST_URI'])));
            exit;
        }

        // Endpoint AJAX per la modale
        if (isset($_GET['tg_ajax_recovery']) && $_GET['tg_ajax_recovery'] == 1 && $current_user_id == $user->ID) {
            $codes = get_transient('tg_show_new_recovery_' . $user->ID);
            if ($codes && is_array($codes)) {
                delete_transient('tg_show_new_recovery_' . $user->ID);
                $plugin_logo = apply_filters('two_factor_login_telegram_logo', plugins_url('assets/img/plugin_logo.png', WP_FACTOR_TG_FILE));
                $redirect_to = remove_query_arg(['show_new_recovery', 'tg_ajax_recovery'], $_SERVER['REQUEST_URI']);
                define('IS_PROFILE_PAGE', true);
                require(dirname(WP_FACTOR_TG_FILE) . '/templates/recovery-codes-wizard.php');
                exit;
            }
            exit; // niente codici
        }

        require_once(dirname(WP_FACTOR_TG_FILE) . "/templates/user-2fa-form.php");
    }

    public function load_tg_lib()
    {
        $screen = get_current_screen();
        if (in_array($screen->id, ["profile", "settings_page_tg-conf", "users"])) {
            wp_register_style(
                "tg_lib_css",
                plugins_url(
                    "assets/css/wp-factor-telegram-plugin.css",
                    dirname(__FILE__)
                ),
                array(),
                WP_FACTOR_PLUGIN_VERSION
            );
            wp_enqueue_style("tg_lib_css");

            wp_register_script(
                "tg_lib_js",
                plugins_url(
                    "assets/js/wp-factor-telegram-plugin.js",
                    dirname(__FILE__)
                ),
                array('jquery'),
                WP_FACTOR_PLUGIN_VERSION,
                true
            );

            wp_localize_script("tg_lib_js", "tlj", array(

                "ajax_error" => __(
                    'Ooops! Server failure, try again! ',
                    'two-factor-login-telegram'
                ),
                "checkbot_nonce" => wp_create_nonce('ajax-checkbot-nonce'),
                "sendtoken_nonce" => wp_create_nonce('ajax-sendtoken-nonce'),
                "tokencheck_nonce" => wp_create_nonce('ajax-tokencheck-nonce'),
                "spinner" => admin_url("/images/spinner.gif"),
                // Translated messages
                "invalid_chat_id" => __('Please enter a valid Chat ID', 'two-factor-login-telegram'),
                "enter_confirmation_code" => __('Please enter the confirmation code', 'two-factor-login-telegram'),
                "setup_completed" => __('✅ 2FA setup completed successfully!', 'two-factor-login-telegram'),
                "code_sent" => __('✅ Code sent! Check your Telegram', 'two-factor-login-telegram'),
                "modifying_setup" => __('⚠️ Modifying 2FA configuration - validation required', 'two-factor-login-telegram')
            ));

            wp_enqueue_script("tg_lib_js");

            wp_enqueue_script('jquery-ui-accordion');
            wp_enqueue_script(
                'custom-accordion',
                plugins_url(
                    'assets/js/wp-factor-telegram-accordion.js',
                    dirname(__FILE__)
                ),
                array('jquery', 'jquery-ui-core', 'jquery-ui-accordion')
            );


            /*wp_register_style( 'jquery-custom-style',
                plugins_url( '/assets/css/jquery-ui.custom.style.min.css',
                    dirname( __FILE__ ) ), array(), '1', 'screen' );
            wp_enqueue_style( 'jquery-custom-style' );*/

        }
    }

    public function hook_tg_lib()
    {
        $screen = get_current_screen();
        if (in_array($screen->id, ["profile", "settings_page_tg-conf", "users"])):

            ?>

            <script>
                (function ($) {

                    $(document).ready(function () {
                        WP_Factor_Telegram_Plugin.init();

                        // Handle disable 2FA button clicks in users list
                        $('.disable-2fa-telegram').on('click', function (e) {
                            e.preventDefault();

                            var $btn = $(this);
                            var user_id = $btn.data('user-id');
                            var nonce = $btn.data('nonce');

                            if (!confirm('<?php echo esc_js(__('Are you sure you want to disable 2FA for this user?', 'two-factor-login-telegram')); ?>')) {
                                return;
                            }

                            $btn.prop('disabled', true).text('<?php echo esc_js(__('Disabling...', 'two-factor-login-telegram')); ?>');

                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'disable_user_2fa_telegram',
                                    user_id: user_id,
                                    nonce: nonce
                                },
                                success: function (response) {
                                    if (response.success) {
                                        $btn.closest('td').html(response.data.new_status);
                                        alert(response.data.message);
                                    } else {
                                        alert('Error: ' + response.data);
                                        $btn.prop('disabled', false).text('<?php echo esc_js(__('Disable', 'two-factor-login-telegram')); ?>');
                                    }
                                },
                                error: function () {
                                    alert('<?php echo esc_js(__('An error occurred. Please try again.', 'two-factor-login-telegram')); ?>');
                                    $btn.prop('disabled', false).text('<?php echo esc_js(__('Disable', 'two-factor-login-telegram')); ?>');
                                }
                            });
                        });
                    });

                })(jQuery);
            </script>

            <?php
        endif;
    }

    public function send_token_check()
    {
        $response = array(
            'type' => 'error'
        );

        if (!wp_verify_nonce($_POST['nonce'], 'ajax-tokencheck-nonce')) {
            $response['msg'] = __(
                'Security check error',
                'two-factor-login-telegram'
            );
            die(json_encode($response));
        }

        $response['msg'] = __(
            'Please fill Chat ID field.',
            'two-factor-login-telegram'
        );

        if (!isset($_POST['chat_id']) || $_POST['chat_id'] == "") {
            die(json_encode($response));
        }

        $auth_code = $this->get_auth_code();

        set_transient('wp2fa_telegram_authcode_' . $_POST['chat_id'], hash('sha256', $auth_code), WP_FACTOR_AUTHCODE_EXPIRE_SECONDS);

        // Get current user ID for the validation button
        $current_user_id = get_current_user_id();

        $tg = $this->telegram;
        $validation_message = sprintf(
            "🔐 *%s*\n\n`%s`\n\n%s",
            __("WordPress 2FA Validation Code", "two-factor-login-telegram"),
            $auth_code,
            __("Use this code to complete your 2FA setup in WordPress, or click the button below:", "two-factor-login-telegram")
        );

        // Create inline keyboard with validation button
        $reply_markup = null;
        if ($current_user_id) {
            $nonce = wp_create_nonce('telegram_validate_' . $current_user_id . '_' . $auth_code);
            $validation_url = admin_url('profile.php?action=telegram_validate&chat_id=' . $_POST['chat_id'] . '&user_id=' . $current_user_id . '&token=' . $auth_code . '&nonce=' . $nonce);

            $reply_markup = array(
                'inline_keyboard' => array(
                    array(
                        array(
                            'text' => '✅ ' . __('Validate Setup', 'two-factor-login-telegram'),
                            'url' => $validation_url
                        )
                    )
                )
            );
        }

        $send = $tg->send_with_keyboard($validation_message, $_POST['chat_id'], $reply_markup);

        $this->log_telegram_action('validation_code_sent', array(
            'chat_id' => $_POST['chat_id'],
            'success' => $send !== false,
            'error' => $send === false ? $tg->lastError : null
        ));

        if (!$send) {
            $response['msg']
                = sprintf(__(
                    "Error (%s): validation code was not sent, try again!",
                    'two-factor-login-telegram'
                ), $tg->lastError);
        } else {
            $response['type'] = "success";
            $response['msg'] = __(
                "Validation code was successfully sent",
                'two-factor-login-telegram'
            );
        }

        die(json_encode($response));
    }

    public function check_bot()
    {

        $response = array(
            'type' => 'error'
        );

        if (!wp_verify_nonce($_POST['nonce'], 'ajax-checkbot-nonce')) {
            $response['msg'] = __(
                'Security check error',
                'two-factor-login-telegram'
            );
            die(json_encode($response));
        }

        if (!isset($_POST['bot_token']) || $_POST['bot_token'] == "") {
            $response['msg'] = __(
                'This bot does not exists.',
                'two-factor-login-telegram'
            );
            die(json_encode($response));
        }

        $tg = $this->telegram;
        $me = $tg->set_bot_token($_POST['bot_token'])->get_me();

        if ($me === false) {
            $response['msg'] = __(
                'Unable to get Bot infos, please retry.',
                'two-factor-login-telegram'
            );
            die(json_encode($response));
        }

        $response = array(
            'type' => 'success',
            'msg' => __('This bot exists.', 'two-factor-login-telegram'),
            'args' => array(
                'id' => $me->id,
                'first_name' => $me->first_name,
                'username' => $me->username,
            ),
        );

        die(json_encode($response));
    }

    public function token_check()
    {
        $response = array(
            'type' => 'error'
        );

        if (!wp_verify_nonce($_POST['nonce'], 'ajax-sendtoken-nonce')) {
            $response['msg'] = __(
                'Security check error',
                'two-factor-login-telegram'
            );
            die(json_encode($response));
        }

        $messages = [
            "token_wrong" => __(
                'The token entered is wrong.',
                'two-factor-login-telegram'
            ),
            "chat_id_wrong" => __(
                'Chat ID is wrong.',
                'two-factor-login-telegram'
            )
        ];


        if (!isset($_POST['token']) || $_POST['token'] == "") {
            $response['msg'] = $messages["token_wrong"];
            die(json_encode($response));
        }

        if (!isset($_POST['chat_id']) || $_POST['chat_id'] == "") {
            $response['msg'] = $messages["chat_id_wrong"];
            die(json_encode($response));
        }

        if (!$this->is_valid_tokencheck_authcode($_POST['token'], $_POST['chat_id'])) {
            $response['msg'] = __(
                'Validation code entered is wrong.',
                'two-factor-login-telegram'
            );
        } else {
            $response['type'] = "success";
            $response['msg'] = __(
                "Validation code is correct.",
                'two-factor-login-telegram'
            );
        }

        die(json_encode($response));
    }

    /**
     * Save user's 2FA settings
     *
     * @param int $user_id User ID
     * @param string $chat_id Telegram Chat ID
     * @param bool $enabled Whether 2FA is enabled
     * @return bool Success status
     */
    public function save_user_2fa_settings($user_id, $chat_id, $enabled = true)
    {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }

        if (empty($chat_id)) {
            return false;
        }

        update_user_meta($user_id, 'tg_wp_factor_chat_id', sanitize_text_field($chat_id));
        update_user_meta($user_id, 'tg_wp_factor_enabled', $enabled ? '1' : '0');

        return true;
    }

    public function tg_save_custom_user_profile_fields($user_id)
    {
        // Permetti il salvataggio delle impostazioni 2FA solo se l'utente sta modificando il proprio profilo
        $current_user_id = get_current_user_id();
        if ($current_user_id != $user_id) {
            return false;
        }

        if (
            $_POST['tg_wp_factor_valid'] == 0
            || $_POST['tg_wp_factor_chat_id'] == ""
        ) {
            return false;
        }

        return $this->save_user_2fa_settings(
            $user_id,
            $_POST['tg_wp_factor_chat_id'],
            isset($_POST['tg_wp_factor_enabled'])
        );
    }

    public function is_valid_bot()
    {
        $valid_bot_transient = WP_FACTOR_TG_GETME_TRANSIENT;

        if (
            ($is_valid_bot = get_transient($valid_bot_transient))
            === false
        ) {
            $is_valid_bot = $this->telegram->get_me() !== false;
            set_transient($valid_bot_transient, $is_valid_bot, 60 * 60 * 24);
        }

        return boolval($is_valid_bot);
    }

    public function get_user_chatid($user_id = false)
    {
        if ($user_id === false) {
            $user_id = get_current_user_id();
        }

        return get_the_author_meta("tg_wp_factor_chat_id", $user_id);
    }

    public function is_setup_chatid($user_id = false)
    {
        $chat_id = $this->get_user_chatid($user_id);

        return $chat_id !== false;
    }


    function ts_footer_admin_text()
    {
        return __(' | This plugin is powered by', 'two-factor-login-telegram')
            . ' <a href="https://www.dueclic.com/" target="_blank">dueclic</a>. <a class="social-foot" href="https://www.facebook.com/dueclic/"><span class="dashicons dashicons-facebook bg-fb"></span></a>';
    }

    function ts_footer_version()
    {
        return "";
    }

    public function change_copyright()
    {
        add_filter(
            'admin_footer_text',
            array($this, 'ts_footer_admin_text'),
            11
        );
        add_filter('update_footer', array($this, 'ts_footer_version'), 11);
    }

    private function create_or_update_telegram_auth_codes_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'telegram_auth_codes';

        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,  
        auth_code varchar(64) NOT NULL,            
        user_id bigint(20) UNSIGNED NOT NULL,    
        creation_date datetime NOT NULL,          
        expiration_date datetime NOT NULL,        
        PRIMARY KEY (id),                         
        KEY auth_code (auth_code)
    ) $charset_collate";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Esegue la query per creare/aggiornare la tabella
        dbDelta($sql);
    }

    private function create_or_update_activities_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2fat_activities';

        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,  
        timestamp datetime NOT NULL,
        action varchar(100) NOT NULL,
        data longtext,
        PRIMARY KEY (id),
        KEY timestamp (timestamp),
        KEY action (action)
    ) $charset_collate";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Esegue la query per creare/aggiornare la tabella
        dbDelta($sql);
    }

    private function migrate_logs_to_activities_table()
    {
        global $wpdb;

        // Check if old logs exist in option
        $old_logs = get_option('telegram_bot_logs', array());

        if (!empty($old_logs)) {
            $activities_table = $wpdb->prefix . 'wp2fat_activities';

            // Migrate each log entry
            foreach ($old_logs as $log) {
                $wpdb->insert(
                    $activities_table,
                    array(
                        'timestamp' => $log['timestamp'],
                        'action' => $log['action'],
                        'data' => maybe_serialize($log['data'])
                    ),
                    array('%s', '%s', '%s')
                );
            }

            // Delete the old option after migration
            delete_option('telegram_bot_logs');
        }
    }

    function plugin_activation()
    {
        $this->create_or_update_telegram_auth_codes_table();
        $this->create_or_update_activities_table();
        $this->migrate_logs_to_activities_table();
        update_option('wp_factor_plugin_version', WP_FACTOR_PLUGIN_VERSION);

        // Add rewrite rules before flushing
        $this->add_telegram_rewrite_rules();

        // Flush rewrite rules to add our new rules
        flush_rewrite_rules();
    }

    function plugin_deactivation()
    {
        // Check if data cleanup is enabled
        $plugin_options = get_option($this->namespace, array());

        if (isset($plugin_options['delete_data_on_deactivation']) && $plugin_options['delete_data_on_deactivation'] === '1') {
            $this->cleanup_all_plugin_data();
        }

        // Always flush rewrite rules on deactivation
        flush_rewrite_rules();
    }

    private function cleanup_all_plugin_data()
    {
        global $wpdb;

        // Delete plugin settings
        delete_option($this->namespace);
        delete_option('wp_factor_plugin_version');
        delete_option('telegram_bot_logs'); // For backward compatibility

        // Delete auth codes table
        $auth_codes_table = $wpdb->prefix . 'telegram_auth_codes';
        $wpdb->query("DROP TABLE IF EXISTS $auth_codes_table");

        // Delete activities table
        $activities_table = $wpdb->prefix . 'wp2fat_activities';
        $wpdb->query("DROP TABLE IF EXISTS $activities_table");

        // Delete user meta data for all users
        $wpdb->delete(
            $wpdb->usermeta,
            array(
                'meta_key' => 'tg_wp_factor_chat_id'
            )
        );

        $wpdb->delete(
            $wpdb->usermeta,
            array(
                'meta_key' => 'tg_wp_factor_enabled'
            )
        );

        // Delete all transients related to the plugin
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_wp2fa_telegram_authcode_%'");
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_wp2fa_telegram_authcode_%'");
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_" . WP_FACTOR_TG_GETME_TRANSIENT . "%'");
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_" . WP_FACTOR_TG_GETME_TRANSIENT . "%'");
    }

    function check_plugin_update()
    {
        $installed_version = get_option('wp_factor_plugin_version');

        if ($installed_version !== WP_FACTOR_PLUGIN_VERSION) {
            $this->create_or_update_telegram_auth_codes_table();
            $this->create_or_update_activities_table();
            $this->migrate_logs_to_activities_table();
            update_option('wp_factor_plugin_version', WP_FACTOR_PLUGIN_VERSION);
        }
    }


    /**
     * Add hooks
     */

    public function add_hooks()
    {
        add_action('wp_login', array($this, 'tg_login'), 10, 2);
        add_action(
            'wp_login_failed',
            array($this->telegram, 'send_tg_failed_login'),
            10,
            2
        );
        add_action('login_form_validate_tg', array($this, 'validate_tg'));

        register_activation_hook(WP_FACTOR_TG_FILE, array($this, 'plugin_activation'));
        register_deactivation_hook(WP_FACTOR_TG_FILE, array($this, 'plugin_deactivation'));
        add_action('plugins_loaded', array($this, 'check_plugin_update'));

        if (is_admin()) {
            add_action('admin_init', array($this, 'tg_register_settings'));
            add_action("admin_menu", array($this, 'tg_load_menu'));
            add_filter(
                "plugin_action_links_"
                . plugin_basename(WP_FACTOR_TG_FILE),
                array($this, 'action_links')
            );
        }

        if (!$this->is_valid_bot()) {
            add_action(
                'admin_notices',
                array($this, 'settings_error_not_valid_bot')
            );
        }

        if ($this->is_valid_bot() && !$this->is_setup_chatid()) {
            add_action(
                'admin_notices',
                array($this, 'settings_error_set_chatid')
            );
        }

        if ($this->is_valid_bot()) {
            add_action(
                'show_user_profile',
                array($this, 'tg_add_two_factor_fields'),
                15
            );
            add_action(
                'edit_user_profile',
                array($this, 'tg_add_two_factor_fields'),
                15
            );
            add_action(
                'show_user_profile',
                array($this, 'handle_telegram_validation_in_profile')
            );
            add_action(
                'edit_user_profile',
                array($this, 'handle_telegram_validation_in_profile')
            );
        }

        add_action(
            'personal_options_update',
            array($this, 'tg_save_custom_user_profile_fields')
        );
        add_action(
            'edit_user_profile_update',
            array($this, 'tg_save_custom_user_profile_fields')
        );
        add_action('admin_enqueue_scripts', array($this, 'load_tg_lib'));
        add_action('admin_footer', array($this, 'hook_tg_lib'));
        add_action(
            'wp_ajax_send_token_check',
            array($this, 'send_token_check')
        );
        add_action('wp_ajax_token_check', array($this, 'token_check'));
        add_action('wp_ajax_check_bot', array($this, 'check_bot'));
        add_action("tft_copyright", array($this, "change_copyright"));

        // Add REST API endpoint for Telegram webhook
        add_action('rest_api_init', array($this, 'register_telegram_webhook_route'));

        // Add rewrite rules for Telegram confirmation URLs
        add_action('init', array($this, 'add_telegram_rewrite_rules'));
        add_action('parse_request', array($this, 'parse_telegram_request'));

        // Add user list table customization for administrators
        if ($this->is_valid_bot() && is_admin()) {
            add_filter('manage_users_columns', array($this, 'add_2fa_telegram_column'));
            add_filter('manage_users_custom_column', array($this, 'show_2fa_telegram_column_content'), 10, 3);
            add_action('wp_ajax_disable_user_2fa_telegram', array($this, 'handle_disable_user_2fa_ajax'));
        }

        add_action('wp_ajax_regenerate_recovery_codes', array($this, 'ajax_regenerate_recovery'));

    }

    /**
     * Register REST API endpoints for Telegram
     */
    public function register_telegram_webhook_route()
    {
        register_rest_route('telegram/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_telegram_webhook'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Add rewrite rules for Telegram confirmation URLs
     */
    public function add_telegram_rewrite_rules()
    {
        add_rewrite_rule(
            '^telegram-confirm/([0-9]+)/([a-zA-Z0-9]+)/?$',
            'index.php?telegram_confirm=1&user_id=$matches[1]&token=$matches[2]',
            'top'
        );

        // Add query vars
        add_filter('query_vars', function ($vars) {
            $vars[] = 'telegram_confirm';
            $vars[] = 'user_id';
            $vars[] = 'token';
            return $vars;
        });
    }

    /**
     * Parse Telegram confirmation requests
     */
    public function parse_telegram_request()
    {
        global $wp;

        if (isset($wp->query_vars['telegram_confirm']) && $wp->query_vars['telegram_confirm'] == 1) {
            $user_id = intval($wp->query_vars['user_id']);
            $token = sanitize_text_field($wp->query_vars['token']);
            $nonce = sanitize_text_field($_GET['nonce'] ?? '');

            $this->handle_telegram_confirmation_direct($user_id, $token, $nonce);
        }
    }

    /**
     * Handle Telegram confirmation via direct URL (not REST API)
     */
    public function handle_telegram_confirmation_direct($user_id, $token, $nonce)
    {
        // Verify nonce
        if (!wp_verify_nonce($nonce, 'telegram_confirm_' . $user_id . '_' . $token)) {
            $this->log_telegram_action('confirmation_failed', array(
                'user_id' => $user_id,
                'token' => $token,
                'reason' => 'invalid_nonce'
            ));

            // Include error template
            require_once(dirname(WP_FACTOR_TG_FILE) . "/templates/error-security-failed.php");
        }

        // Validate the token
        $authcode_validation = $this->is_valid_authcode($token, $user_id);

        if ('valid' !== $authcode_validation) {
            if ($authcode_validation === 'expired') {
                $log_reason = 'expired_token';
            } else {
                $log_reason = 'invalid_token';
            }

            $this->log_telegram_action('confirmation_failed', array(
                'user_id' => $user_id,
                'token' => $token,
                'reason' => $log_reason
            ));

            // Include appropriate error template
            if ($authcode_validation === 'expired') {
                require_once(dirname(WP_FACTOR_TG_FILE) . "/templates/error-expired-token.php");
            } else {
                require_once(dirname(WP_FACTOR_TG_FILE) . "/templates/error-invalid-token.php");
            }
        }

        // Get user
        $user = get_userdata($user_id);
        if (!$user) {
            require_once(dirname(WP_FACTOR_TG_FILE) . "/templates/error-invalid-token.php");
        }

        // Log the user in
        wp_set_auth_cookie($user_id, false);

        $this->log_telegram_action('confirmation_success', array(
            'user_id' => $user_id,
            'user_login' => $user->user_login,
            'token' => $token,
            'method' => 'direct_confirmation'
        ));

        // Redirect to admin or specified location
        $redirect_url = apply_filters('telegram_confirmation_redirect_url', admin_url(), $user);

        // Redirect directly
        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Log Telegram bot actions
     */
    public function log_telegram_action($action, $data = array())
    {
        global $wpdb;

        $activities_table = $wpdb->prefix . 'wp2fat_activities';

        // Insert new log entry
        $wpdb->insert(
            $activities_table,
            array(
                'timestamp' => current_time('mysql'),
                'action' => $action,
                'data' => maybe_serialize($data)
            ),
            array('%s', '%s', '%s')
        );

        // Clean up old entries - keep only last 1000 entries
        $wpdb->query("DELETE FROM $activities_table WHERE id NOT IN (SELECT id FROM (SELECT id FROM $activities_table ORDER BY timestamp DESC LIMIT 1000) temp_table)");
    }

    /**
     * Handle incoming Telegram webhook
     */
    public function handle_telegram_webhook($request = null)
    {
        // Get the JSON payload from Telegram using WordPress REST API methods
        if ($request instanceof WP_REST_Request) {
            $update = $request->get_json_params();
            $input = wp_json_encode($update);
        } else {
            // Fallback for direct calls
            $input = file_get_contents('php://input');
            $update = json_decode($input, true);
        }

        $this->log_telegram_action('webhook_received', array(
            'raw_input' => $input,
            'parsed_update' => $update
        ));

        if (!$update || !isset($update['message'])) {
            $this->log_telegram_action('webhook_error', array('error' => 'Invalid update or missing message'));
            return new WP_Error('invalid_webhook', 'Invalid webhook data', array('status' => 400));
        }



        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $text = isset($message['text']) ? $message['text'] : '';

        $this->log_telegram_action('message_received', array(
            'chat_id' => $chat_id,
            'text' => $text,
            'from' => $message['from'] ?? null
        ));

        // Handle /get_id command
        if ($text === '/get_id') {
            $response_text = sprintf(
                "ℹ️ *%s*\n\n`%s`\n\n%s",
                __("Your Telegram Chat ID", "two-factor-login-telegram"),
                $chat_id,
                __("Copy this ID and paste it in your WordPress profile to enable 2FA with Telegram.", "two-factor-login-telegram")
            );

            $result = $this->telegram->send_with_keyboard($response_text, $chat_id);

            $this->log_telegram_action('get_id_response', array(
                'chat_id' => $chat_id,
                'response_sent' => $result !== false
            ));
        }

        return rest_ensure_response(array('status' => 'ok'));
    }

    /**
     * Add 2FA Telegram column to users list table
     */
    public function add_2fa_telegram_column($columns)
    {
        if (current_user_can('manage_options')) {
            $columns['tg_2fa_status'] = __('2FA Telegram', 'two-factor-login-telegram');
        }
        return $columns;
    }

    /**
     * Show content for 2FA Telegram column in users list table
     */
    public function show_2fa_telegram_column_content($value, $column_name, $user_id)
    {
        if ($column_name == 'tg_2fa_status' && current_user_can('manage_options')) {
            $is_enabled = get_the_author_meta('tg_wp_factor_enabled', $user_id) === '1';
            $chat_id = get_the_author_meta('tg_wp_factor_chat_id', $user_id);

            if ($is_enabled && !empty($chat_id)) {
                $disable_nonce = wp_create_nonce('disable_2fa_telegram_' . $user_id);
                return '<span style="color: green;">✅ ' . __('Active', 'two-factor-login-telegram') . '</span><br>' .
                    '<a href="#" class="button button-small disable-2fa-telegram" data-user-id="' . $user_id . '" data-nonce="' . $disable_nonce . '" style="margin-top: 5px;">' .
                    __('Disable', 'two-factor-login-telegram') . '</a>';
            } else {
                return '<span style="color: #ccc;">❌ ' . __('Inactive', 'two-factor-login-telegram') . '</span>';
            }
        }
        return $value;
    }

    /**
     * Handle AJAX request to disable user 2FA
     */
    public function handle_disable_user_2fa_ajax()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'two-factor-login-telegram'));
        }

        $user_id = intval($_POST['user_id']);
        $nonce = sanitize_text_field($_POST['nonce']);

        if (!wp_verify_nonce($nonce, 'disable_2fa_telegram_' . $user_id)) {
            wp_die(__('Security verification failed', 'two-factor-login-telegram'));
        }

        // Disable 2FA for the user
        update_user_meta($user_id, 'tg_wp_factor_enabled', '0');
        delete_user_meta($user_id, 'tg_wp_factor_chat_id');

        // Log the action
        $user = get_userdata($user_id);
        $current_user = wp_get_current_user();

        $this->log_telegram_action('admin_disabled_2fa', array(
            'disabled_user_id' => $user_id,
            'disabled_user_login' => $user ? $user->user_login : 'unknown',
            'admin_user_id' => $current_user->ID,
            'admin_user_login' => $current_user->user_login
        ));

        wp_send_json_success(array(
            'message' => __('2FA has been disabled for this user.', 'two-factor-login-telegram'),
            'new_status' => '<span style="color: #ccc;">❌ ' . __('Inactive', 'two-factor-login-telegram') . '</span>'
        ));
    }

    /**
     * Handle Telegram validation in user profile page
     */
    public function handle_telegram_validation_in_profile($user)
    {
        // Only handle if we're on the current user's profile and we have validation parameters
        $current_user_id = get_current_user_id();
        if ($current_user_id != $user->ID) {
            return;
        }

        if (isset($_GET['action']) && $_GET['action'] === 'telegram_validate') {
            $user_id = intval($_GET['user_id']);
            $token = sanitize_text_field($_GET['token']);
            $chat_id = sanitize_text_field($_GET['chat_id']);
            $nonce = sanitize_text_field($_GET['nonce']);
            $validation_success = false;

            // Verify that the user_id matches the current user
            if ($user_id !== $current_user_id) {
                echo '<div class="notice notice-error is-dismissible"><p>';
                _e('❌ Validation failed. Security error: user mismatch.', 'two-factor-login-telegram');
                echo '</p></div>';
                return;
            }

            // Verify nonce
            if (wp_verify_nonce($nonce, 'telegram_validate_' . $user_id . '_' . $token)) {
                // Check if the token is valid using the transient method
                if ($this->is_valid_tokencheck_authcode($token, $chat_id)) {
                    // Save user 2FA settings - this enables 2FA and saves the chat_id
                    $save_result = $this->save_user_2fa_settings($user_id, $chat_id, true);

                    if ($save_result) {
                        $validation_success = true;
                        // Delete the transient as it's been used
                        delete_transient('wp2fa_telegram_authcode_' . $chat_id);

                        // Log the successful validation
                        $this->log_telegram_action('validation_success', array(
                            'user_id' => $user_id,
                            'chat_id' => $chat_id,
                            'method' => 'validate_setup_button'
                        ));
                    } else {
                        $this->log_telegram_action('validation_failed', array(
                            'user_id' => $user_id,
                            'token' => $token,
                            'reason' => 'save_settings_failed'
                        ));
                    }
                } else {
                    $this->log_telegram_action('validation_failed', array(
                        'user_id' => $user_id,
                        'token' => $token,
                        'reason' => 'invalid_or_expired_token'
                    ));
                }
            } else {
                $this->log_telegram_action('validation_failed', array(
                    'user_id' => $user_id,
                    'token' => $token,
                    'reason' => 'nonce_verification_failed'
                ));
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
    }

    /**
     * Genera N recovery codes randomici
     * @param int $num
     * @param int $length
     * @return array
     */
    private function generate_recovery_codes($num = 8, $length = 10)
    {
        $codes = [];
        for ($i = 0; $i < $num; $i++) {
            $codes[] = $this->get_auth_code($length);
        }
        return $codes;
    }

    /**
     * Restituisce i recovery codes hashati per l'utente
     * @param int $user_id
     * @return array
     */
    private function get_user_recovery_codes($user_id)
    {
        $codes = get_user_meta($user_id, 'tg_wp_factor_recovery_codes', true);
        if (!is_array($codes))
            return [];
        return $codes;
    }

    /**
     * Salva i recovery codes hashati per l'utente
     * @param int $user_id
     * @param array $codes
     */
    private function set_user_recovery_codes($user_id, $codes)
    {
        $hashed = array_map(function ($c) {
            return hash('sha256', $c);
        }, $codes);
        update_user_meta($user_id, 'tg_wp_factor_recovery_codes', $hashed);
    }

    /**
     * Consuma (elimina) un recovery code se valido
     * @param int $user_id
     * @param string $code
     * @return bool
     */
    private function consume_recovery_code($user_id, $code)
    {
        $hashed = hash('sha256', $code);
        $codes = $this->get_user_recovery_codes($user_id);
        $idx = array_search($hashed, $codes);
        if ($idx !== false) {
            unset($codes[$idx]);
            update_user_meta($user_id, 'tg_wp_factor_recovery_codes', array_values($codes));
            return true;
        }
        return false;
    }

    /**
     * Verifica se l'utente ha recovery codes
     * @param int $user_id
     * @return bool
     */
    private function has_recovery_codes($user_id)
    {
        $codes = $this->get_user_recovery_codes($user_id);
        return is_array($codes) && count($codes) > 0;
    }

    /**
     * Rigenera i recovery codes per l'utente
     * @param int $user_id
     * @param int $num
     * @param int $length
     * @return array I nuovi codici in chiaro
     */
    public function regenerate_recovery_codes($user_id, $num = 8, $length = 10)
    {
        $codes = $this->generate_recovery_codes($num, $length);
        $this->set_user_recovery_codes($user_id, $codes);
        return $codes;
    }

    /**
     * Valida e consuma un recovery code per il login
     * @param string $code Il recovery code inserito dall'utente
     * @param int $user_id ID dell'utente
     * @return bool True se il codice è valido e è stato consumato
     */
    public function validate_recovery_code($code, $user_id)
    {
        // Normalizza il codice rimuovendo spazi e trattini
        $normalized_code = strtoupper(str_replace([' ', '-'], '', trim($code)));
        
        // Controlla che il codice non sia vuoto
        if (empty($normalized_code)) {
            return false;
        }
        
        // Controlla che l'utente abbia recovery codes
        if (!$this->has_recovery_codes($user_id)) {
            return false;
        }
        
        // Prova a consumare il codice
        return $this->consume_recovery_code($user_id, $normalized_code);
    }

    public function ajax_regenerate_recovery()
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Not authorized.', 'two-factor-login-telegram')]);
        }
        $user_id = get_current_user_id();
        if (!wp_verify_nonce($_POST['_wpnonce'], 'tg_regenerate_recovery_codes_' . $user_id)) {
            wp_send_json_error(['message' => __('Invalid request.', 'two-factor-login-telegram')]);
        }
        // Genera nuovi codici
        $codes = $this->generate_recovery_codes(8, 10);
        $this->set_user_recovery_codes($user_id, $codes);
        $plugin_logo = apply_filters('two_factor_login_telegram_logo', plugins_url('assets/img/plugin_logo.png', WP_FACTOR_TG_FILE));
        $redirect_to = $_POST['redirect_to'] ?? admin_url('profile.php');
        ob_start();
        define('IS_PROFILE_PAGE', true);
        require(dirname(WP_FACTOR_TG_FILE) . '/templates/recovery-codes-wizard.php');
        $html = ob_get_clean();
        wp_send_json_success(['html' => $html]);
    }

}
