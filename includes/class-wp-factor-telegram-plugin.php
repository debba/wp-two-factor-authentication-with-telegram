<?php

final class WP_Factor_Telegram_Plugin {

	/**
	 * Get an instance
	 * @var WP_Factor_Telegram_Plugin
	 */

	private static $instance;

	/**
	 * Namespace for prefixed setting
	 * @var string
	 */

	private $namespace = "tg_col";

	/**
	 * Cookie Name
	 * @var string
	 */

	private $cookie_name = "auth_tg_cookie";

	/**
	 * Check cookie Name
	 * @var string
	 */

	private $check_cookie_name = "check_tg_cookie";

	/**
	 * @var WP_Telegram
	 */

	private $telegram;

	/**
	 * Get WP Factor Telegram
	 * @return WP_Factor_Telegram_Plugin
	 */

	public static function get_instance() {

		if ( empty( self::$instance ) && ! ( self::$instance instanceof WP_Factor_Telegram_Plugin ) ) {

			self::$instance = new WP_Factor_Telegram_Plugin;
			self::$instance->includes();
			self::$instance->telegram = new WP_Telegram;
			self::$instance->add_hooks();

			do_action( "wp_factor_telegram_loaded" );

		}

		return self::$instance;

	}

	/**
	 * Include classes
	 */

	public function includes() {
		require_once( dirname( WP_FACTOR_TG_FILE ) . "/includes/class-wp-telegram.php" );
	}


	/**
	 * Get authentication code
	 *
	 * @param int $length
	 *
	 * @return string
	 */

	private function get_auth_code( $length = 5 ) {
		$pool = array_merge( range( 0, 9 ), range( 'a', 'z' ), range( 'A', 'Z' ) );

		$key = "";

		for ( $i = 0; $i < $length; $i ++ ) {
			$key .= $pool[ mt_rand( 0, count( $pool ) - 1 ) ];
		}

		return $key;
	}

	/**
	 * Show to factor login html
	 *
	 * @param $user
	 */

	private function show_two_factor_login( $user ) {

		$auth_code = $this->get_auth_code();

		setcookie( $this->cookie_name, null, strtotime( '-1 day' ) );
		setcookie( $this->cookie_name, sha1( $auth_code ), time() + ( 60 * 20 ) );

		$chat_id = get_the_author_meta(  "tg_wp_factor_chat_id", $user->ID );
		$this->telegram->send_tg_token( $auth_code, $chat_id );

		$redirect_to = isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : $_SERVER['REQUEST_URI'];

		$this->login_html( $user, $redirect_to );

	}

	/**
	 * Authentication page
	 *
	 * @param $user
	 */

	private function authentication_page( $user ) {
		require_once( ABSPATH . '/wp-admin/includes/template.php' );
		?>

        <p class="notice notice-warning">
			<?php
			_e( "Inserisci il codice inviato sul tuo account Telegram.", "two-factor-telegram" );
			?>
        </p>
        <p>
            <label for="authcode">Codice di autenticazione:</label>
            <input type="text" name="authcode" id="authcode" class="input" value="" size="5"/>
        </p>
		<?php
		submit_button( 'Autenticati con Telegram', 'two-factor-telegram' );
	}

	/**
	 * Login HTML Page
	 *
	 * @param $user
	 * @param $redirect_to
	 * @param string $error_msg
	 */

	private function login_html( $user, $redirect_to, $error_msg = '' ) {

		$rememberme = 0;
		if ( isset( $_REQUEST['rememberme'] ) && $_REQUEST['rememberme'] ) {
			$rememberme = 1;
		}

		login_header();

		if ( ! empty( $error_msg ) ) {
			echo '<div id="login_error"><strong>' . esc_html( $error_msg ) . '</strong><br /></div>';
		}
		?>

        <form name="validate_tg" id="loginform"
              action="<?php echo esc_url( site_url( 'wp-login.php?action=validate_tg', 'login_post' ) ); ?>"
              method="post" autocomplete="off">
            <input type="hidden" name="wp-auth-id" id="wp-auth-id" value="<?php echo esc_attr( $user->ID ); ?>"/>
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>"/>
            <input type="hidden" name="rememberme" id="rememberme" value="<?php echo esc_attr( $rememberme ); ?>"/>

			<?php $this->authentication_page( $user ); ?>
        </form>

        <p id="backtoblog">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>"
               title="<?php __( "Ti sei perso?", "two-factor-telegram" ); ?>"><?php echo sprintf( '&larr; Torna a %s', get_bloginfo( 'title', 'display' ) ); ?></a>
        </p>

		<?php
		do_action( 'login_footer' ); ?>
        <div class="clear"></div>
        </body>
        </html>
		<?php
	}

	/**
	 * Show telegram login
	 *
	 * @param $user_login
	 * @param $user
	 */

	public function tg_login( $user_login, $user ) {

		if ( $this->is_valid_bot() && $this->is_enabled() && $this->is_enabled_user($user->ID) ) {

			wp_clear_auth_cookie();

			$this->show_two_factor_login( $user );
			exit;

		}

		return;

	}

	private function is_valid_authcode( $authcode, $cookie = false ) {

		if ( $cookie === false ) {
			$cookie = $this->cookie_name;
		}

		if ( $_COOKIE[ $cookie ] === sha1( $authcode ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Validate telegram auth code login
	 */

	public function validate_tg() {
		if ( ! isset( $_POST['wp-auth-id'] ) ) {
			return;
		}

		$user = get_userdata( $_POST['wp-auth-id'] );
		if ( ! $user ) {
			return;
		}

		if ( true !== $this->is_valid_authcode( $_REQUEST['authcode'] ) ) {
			do_action( 'wp_factor_telegram_failed', $user->user_login );

			$auth_code = $this->get_auth_code();

			setcookie( $this->cookie_name, null, strtotime( '-1 day' ) );
			setcookie( $this->cookie_name, sha1( $auth_code ), time() + ( 60 * 20 ) );

			$this->telegram->send_tg_token( $auth_code );

			$this->login_html( $user, $_REQUEST['redirect_to'], __( 'Errore: codice di verifica errato.', 'two-factor-telegram' ) );
			exit;
		}

		$rememberme = false;
		if ( isset( $_REQUEST['rememberme'] ) && $_REQUEST['rememberme'] ) {
			$rememberme = true;
		}

		wp_set_auth_cookie( $user->ID, $rememberme );

		$redirect_to = apply_filters( 'login_redirect', $_REQUEST['redirect_to'], $_REQUEST['redirect_to'], $user );
		wp_safe_redirect( $redirect_to );

		exit;
	}

	public function configure_tg() {
		require( dirname( WP_FACTOR_TG_FILE ) . "/sections/configure_tg.php" );
	}

	public function tg_load_menu() {
		add_options_page( __( "Autenticazione a due fattori con Telegram", "two-factor-telegram" ), __( "Autenticazione a due fattori con Telegram", "two-factor-telegram" ), "manage_options", "tg-conf", array(
			$this,
			"configure_tg"
		) );
	}

	function tg_register_settings() {

		register_setting( $this->namespace, $this->namespace );

		add_settings_section( $this->namespace . '_section', __( 'Configurazione Telegram', "two-factor-telegram" ), '', $this->namespace . '.php' );

		$field_args = array(
			'type'      => 'text',
			'id'        => 'bot_token',
			'name'      => 'bot_token',
			'desc'      => __( 'Bot Token', "two-factor-telegram" ),
			'std'       => '',
			'label_for' => 'bot_token',
			'class'     => 'css_class'
		);

		add_settings_field( 'bot_token', __( 'Bot Token', "two-factor-telegram" ), array(
			$this,
			'tg_display_setting'
		), $this->namespace . '.php', $this->namespace . '_section', $field_args );

		$field_args = array(
			'type'      => 'text',
			'id'        => 'chat_id',
			'name'      => 'chat_id',
			'desc'      => __( 'Chat ID (Telegram) per segnalazione login falliti.', "two-factor-telegram" ),
			'std'       => '',
			'label_for' => 'chat_id',
			'class'     => 'css_class'
		);

		add_settings_field( 'chat_id', __( 'Chat ID', "two-factor-telegram" ), array(
			$this,
			'tg_display_setting'
		), $this->namespace . '.php', $this->namespace . '_section', $field_args );

		$field_args = array(
			'type'      => 'checkbox',
			'id'        => 'enabled',
			'name'      => 'enabled',
			'desc'      => __( 'Scegli se abilitare il plugin.', 'two-factor-telegram' ),
			'std'       => '',
			'label_for' => 'enabled',
			'class'     => 'css_class'
		);

		add_settings_field( 'enabled', __( 'Abilita plugin?', 'two-factor-telegram' ), array(
			$this,
			'tg_display_setting'
		), $this->namespace . '.php', $this->namespace . '_section', $field_args );

	}

	public function tg_display_setting( $args ) {
		extract( $args );

		$option_name = $this->namespace;

		$options                = get_option( $option_name );

		/** @var $type */
		/** @var $id */
		/** @var $desc */
		/** @var $class */

		switch ( $type ) {
			case 'text':
				$options[ $id ] = stripslashes( $options[ $id ] );
				$options[ $id ] = esc_attr( $options[ $id ] );
				echo "<input class='regular-text $class' type='text' id='$id' name='" . $option_name . "[$id]' value='$options[$id]' />";

				if ( $id == "bot_token" ) {
					?>
                    <button id="checkbot" class="button-secondary" type="button"><?php echo __( "Controlla", "two-factor-telegram" ) ?></button>
					<?php
				}

				echo ( $desc != '' ) ? '<br /><p class="wft-settings-description" id="'.$id.'_desc">'.$desc.'</p>' : "";
				break;

			case 'checkbox':

				$options[ $id ] = stripslashes( $options[ $id ] );
				$options[ $id ] = esc_attr( $options[ $id ] );
				?>
                <input class="regular-text <?php echo $class; ?>" type="checkbox" id="<?php echo $id; ?>"
                       name="<?php echo $option_name; ?>[<?php echo $id; ?>]"
                       value="1" <?php echo checked( 1, $options[ $id ] ); ?> />
				<?php
				echo ( $desc != '' ) ? "<br /><p class='wft-settings-description'>$desc</p>" : "";
				break;
			case 'textarea':

				wp_editor( $options[ $id ], $id,
					array(
						'textarea_name' => $option_name . "[$id]",
						'style'         => 'width: 200px'
					) );

				break;
		}
	}

	/**
	 * Action links
	 *
	 * @param $links
	 *
	 * @return array
	 */

	public function action_links( $links ) {

		/** @noinspection PhpUndefinedConstantInspection */

		$plugin_links = array(
			'<a href="' . admin_url( 'options-general.php?page=tg-conf' ) . '">' . __( 'Impostazioni', 'two-factor-telegram' ) . '</a>',
		);

		return array_merge( $plugin_links, $links );

	}

	public function settings_error_set_chatid() {

	    if (get_current_screen()->id != "profile") {
		    ?>
            <div class="notice notice-warning is-dismissible">
                <p><?php _e( sprintf( 'Per impostare l\'autenticazione a due fattori con Telegram, <a href="%s">clicca qui</a>.', admin_url( 'profile.php' ) ), "two-factor-telegram" ); ?></p>
            </div>
		    <?php
	    }
	}

	public function settings_error_set_correct_chatid() {

		if (get_current_screen()->id != "profile") {
			?>
            <div class="notice notice-warning is-dismissible">
                <p><?php _e( "Le tue preferenze sono state salvate, tuttavia la Chat ID inserita non è corretta." , "two-factor-telegram" ); ?></p>
            </div>
			<?php
		}
	}

	public function settings_error_not_valid_bot() {

	    if (get_current_screen()->id != "settings_page_tg-conf") {
		    ?>
            <div class="notice notice-error is-dismissible">
                <p><?php _e( sprintf( 'Per configurare correttamente l\'autenticazione a due fattori con Telegram, <a href="%s">clicca qui</a>.', 'options-general.php?page=tg-conf' ), "two-factor-telegram" ); ?></p>
            </div>
		    <?php
	    }
    }

	/**
	 * @param $user
	 */

	public function tg_add_two_factor_fields( $user ) {

		?>
        <h3><?php _e( 'Autenticazione a due fattori con Telegram', 'two-factor-telegram' ); ?></h3>

        <table class="form-table">

            <tr>
                <th>
                    <label for="tg_wp_factor_enabled"><?php _e( 'Abilita autenticazione a due fattori', 'two-factor-telegram' ); ?>
                    </label>
                </th>
                <td colspan="2">
                    <input type="hidden" name="tg_wp_factor_valid" id="tg_wp_factor_valid" value="0">
                    <input type="checkbox" name="tg_wp_factor_enabled" id="tg_wp_factor_enabled" value="1" class="regular-text" <?php echo checked( esc_attr( get_the_author_meta( 'tg_wp_factor_enabled', $user->ID ) ), 1 ); ?> /><br/>
                </td>
            </tr>

            <tr>

                <td colspan="3">

                    <?php
                        $username = $this->telegram->get_me()->username;
                    ?>

                    <div>

                        <ol>
                            <li><?php
						        _e(sprintf('Apri Telegram e avvia una conversazione con %s', '<a href="https://telegram.me/WordPressLoginBot" target="_blank">@WordpressLoginBot</a>'), 'two-factor-telegram'); ?></li>
                            <li><?php
						        _e(sprintf('Digita il comando %s per ottenere la tua Chat ID.', '<code>/get_id</code>'), 'two-factor-telegram'); ?></li>
                            <li><?php
						        _e('All\' interno della risposta sarà presente la <strong>Chat ID</strong>', 'two-factor-telegram'); ?></li>
                            <li><?php _e( sprintf('Ora apri una conversazione con %s e schiaccia su <strong>Avvia</strong>', '<a href="https://telegram.me/'.$username.'">@'.$username.'</a>' ), 'two-factor-telegram'); ?></li>
                            <li><?php _e('Adesso puoi proseguire :) Inserisci la tua Chat ID di seguito e schiaccia su <strong>Invia codice</strong> per la verifica.', 'two-factor-plugin'); ?></li>
                        </ol>

                        </p>
                    </div>
                </td>

            </tr>

            <tr>
                <th>
                    <label for="tg_wp_factor_chat_id"><?php _e( 'Telegram Chat ID', 'two-factor-telegram' ); ?>
                    </label></th>
                <td>
                    <input type="text" name="tg_wp_factor_chat_id" id="tg_wp_factor_chat_id"
                           value="<?php echo esc_attr( get_the_author_meta( 'tg_wp_factor_chat_id', $user->ID ) ); ?>"
                           class="regular-text"/><br/>
                    <span class="description"><?php _e( 'Inserisci la tua Telegram Chat ID', 'two-factor-telegram' ); ?></span>
                </td>
                <td>
                    <button class="button"
                            id="tg_wp_factor_chat_id_send"><?php _e( "Invia codice", "two-factor-telegram" ); ?></button>
                </td>
            </tr>

            <tr id="factor-chat-confirm">
                <th>
                    <label for="tg_wp_factor_chat_id_confirm"><?php _e( 'Codice di conferma', 'two-factor-telegram' ); ?>
                    </label></th>
                <td>
                    <input type="text" name="tg_wp_factor_chat_id_confirm" id="tg_wp_factor_chat_id_confirm" value=""
                           class="regular-text"/><br/>
                    <span class="description"><?php _e( 'Inserisci il codice di conferma che hai ricevuto su Telegram', 'two-factor-telegram' ); ?></span>
                </td>
                <td>
                    <button class="button"
                            id="tg_wp_factor_chat_id_check"><?php _e( "Controlla", "two-factor-telegram" ); ?></button>
                </td>
            </tr>
            <tr id="factor-chat-response">
                <td colspan="3">
                    <div class="wpft-notice wpft-notice-warning">
                        <p></p>
                    </div>
                </td>
            </tr>
        </table>
		<?php
	}

	public function load_tg_lib() {

		wp_register_style( "tg_lib_css", plugins_url( "assets/css/wp-factor-telegram-plugin.css", dirname( __FILE__ ) ) );
		wp_enqueue_style( "tg_lib_css" );

		wp_register_script( "tg_lib_js", plugins_url( "assets/js/wp-factor-telegram-plugin.js", dirname( __FILE__ ) ), array( 'jquery' ), '1.0.0', true );

		wp_localize_script( "tg_lib_js", "tlj", array(

			"ajax_error" => __( 'Errore server temporaneo ', 'two-factor-telegram' ),
			"spinner"    => admin_url( "/images/spinner.gif" )

		) );

		wp_enqueue_script( "tg_lib_js" );

		wp_enqueue_script('jquery-ui-accordion');
        wp_enqueue_script(
            'custom-accordion',
	        plugins_url( 'assets/js/wp-factor-telegram-accordion.js', dirname(__FILE__)),
            array('jquery')
        );

		wp_register_style('jquery-custom-style', plugins_url('/assets/jquery-ui-1.11.4.custom/jquery-ui.css', dirname(__FILE__)), array(), '1', 'screen');
		wp_enqueue_style('jquery-custom-style');


	}

	public function hook_tg_lib() {

		?>

        <script>

            (function ($) {

                $(document).ready(function () {
                    WP_Factor_Telegram_Plugin.init();

                });

            })(jQuery);

        </script>

		<?php

	}

	public function send_token_check() {

		$response = array(
			'type' => 'error',
			'msg'  => __( 'La Chat ID è vuota o inesistente.', 'two-factor-telegram' )
		);


		if ( ! isset( $_POST['chat_id'] ) || $_POST['chat_id'] == "" ) {
			die( json_encode( $response ) );
		}

		$auth_code = $this->get_auth_code();

		setcookie( $this->check_cookie_name, null, strtotime( '-1 day' ) );
		setcookie( $this->check_cookie_name, sha1( $auth_code ), time() + ( 60 * 20 ) );

		$tg   = $this->telegram;
		$send = $tg->send( __( sprintf( "Il codice di controllo per WP Two Factor Telegram è %s", $auth_code ), 'two-factor-telegram' ), $_POST['chat_id'] );

		if ( ! $send ) {
			$response['msg'] = __( 'Il codice di controllo non è stato mandato ' . $tg->lastError, 'two-factor-telegram' );
		} else {

			$response['type'] = "success";
			$response['msg']  = __( "Codice di controllo inviato con successo", 'two-factor-telegram' );

		}

		die( json_encode( $response ) );

	}

	public function check_bot() {

		$response = array(
			'type' => 'error',
			'msg'  => __( 'Il bot indicato non esiste.', 'two-factor-telegram' )
		);

		if ( ! isset( $_POST['bot_token'] ) || $_POST['bot_token'] == "" ) {
			die( json_encode( $response ) );
		}

		$tg = $this->telegram;
		$me = $tg->set_bot_token($_POST['bot_token'])->get_me();

		if ( $me === false ) {
			die ( json_encode( $response ) );
		}

		$response = array(
            'type' => 'success',
            'msg' => __('Il bot indicato esiste', 'two-factor-telegram'),
            'args' => array(
                'id' => $me->id,
                'first_name' => $me->first_name,
                'username' => $me->username
            )
        );

		die (json_encode($response));

	}

	public function token_check() {

		$response = array(
			'type' => 'error',
			'msg'  => __( 'Il token indicato non è corretto.', 'two-factor-telegram' )
		);


		if ( ! isset( $_POST['token'] ) || $_POST['token'] == "" ) {
			die( json_encode( $response ) );
		}


		if ( ! $this->is_valid_authcode( $_POST['token'], $this->check_cookie_name ) ) {
			$response['msg'] = __( 'Il codice di controllo inserito è errato.', 'two-factor-telegram' );
		} else {

			$response['type'] = "success";
			$response['msg']  = __( "Il codice di controllo è corretto.", 'two-factor-telegram' );

		}

		die( json_encode( $response ) );

	}

	public function tg_save_custom_user_profile_fields( $user_id ) {

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}

		update_user_meta( $user_id, 'tg_wp_factor_enabled', $_POST['tg_wp_factor_enabled'] );

		if ($_POST['tg_wp_factor_valid'] == 0 || $_POST['tg_wp_factor_chat_id'] == "") {
			add_action('admin_notices', array($this, 'settings_error_set_correct_chatid'));
			return false;
		}

		update_user_meta( $user_id, 'tg_wp_factor_chat_id', $_POST['tg_wp_factor_chat_id'] );
		$this->telegram->send(__("WP Two Factor Plugin è stato configurato correttamente.", "two-factor-telegram"), $_POST['tg_wp_factor_chat_id']);

		return true;

	}

	public function is_valid_bot() {
	    return ( $this->telegram->get_me() !== FALSE );
    }

    public function is_enabled() {
	    return get_option( $this->namespace )['enabled'] === '1';
    }

    public function is_enabled_user($user_id){
	    return (get_the_author_meta(  "tg_wp_factor_enabled", $user_id ) === "1" && get_the_author_meta( "tg_wp_factor_chat_id", $user_id ) !== '');
    }

	function ts_footer_admin_text()
	{
		return __('Questo plugin è stato creato da', 'two-factor-telegram').' <a href="https://www.dueclic.com/" target="_blank">dueclic</a>. <a class="social-foot" href="https://www.facebook.com/dueclic/"><span class="dashicons dashicons-facebook bg-fb"></span></a>';
	}

	function ts_footer_version()
	{
		return "";
	}

	public function change_copyright() {
		add_filter('admin_footer_text', array($this, 'ts_footer_admin_text'), 11);
		add_filter('update_footer', array($this, 'ts_footer_version'), 11);
	}

	public function check_valid_plugin(){

		if ( !$this->is_valid_bot() ) {
			add_action( 'admin_notices', array( $this, 'settings_error_not_valid_bot' ) );
		}

		if ( $this->is_valid_bot() && $this->is_enabled() && (get_the_author_meta( "tg_wp_factor_chat_id", get_current_user_id()) === '') ) {
			add_action( 'admin_notices', array( $this, 'settings_error_set_chatid' ) );
		}
    }


	/**
	 * Add hooks
	 */

	public function add_hooks() {
		add_action( 'wp_login', array( $this, 'tg_login' ), 10, 2 );
		add_action( 'wp_login_failed', array( $this->telegram, 'send_tg_failed_login' ), 10, 2 );
		add_action( 'login_form_validate_tg', array( $this, 'validate_tg' ) );

		if ( is_admin() ) {

			add_action( 'admin_init', array( $this, 'tg_register_settings' ) );
			add_action( "admin_menu", array( $this, 'tg_load_menu' ) );
			add_filter( "plugin_action_links_" . plugin_basename( WP_FACTOR_TG_FILE ), array( $this, 'action_links' ) );

		}

		add_action( 'admin_init', array($this, 'check_valid_plugin'));

		add_action( 'show_user_profile', array( $this, 'tg_add_two_factor_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'tg_add_two_factor_fields' ) );

		add_action( 'personal_options_update', array( $this, 'tg_save_custom_user_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'tg_save_custom_user_profile_fields' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_tg_lib' ) );
		add_action( 'admin_footer', array( $this, 'hook_tg_lib' ) );
		add_action( 'wp_ajax_send_token_check', array( $this, 'send_token_check' ) );
		add_action( 'wp_ajax_token_check', array( $this, 'token_check' ) );
		add_action( 'wp_ajax_check_bot', array( $this, 'check_bot' ) );

		add_action("tft_copyright", array($this, "change_copyright"));

	}


}