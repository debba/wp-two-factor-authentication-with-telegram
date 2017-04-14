<?php

/*
Plugin Name: Two Factor Login with Telegram
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: A brief description of the Plugin.
Version: 1.0
Author: debba
Author URI: http://URI_Of_The_Plugin_Author
License: A "Slug" license name e.g. GPL2
*/

function send_tg_msg($msg){
	$chat_id = get_option("tg_col")['chat_id'];
	$bot_token = get_option("tg_col")['bot_token'];

	$endpoint = sprintf( "https://api.telegram.org/bot%s/sendMessage", $bot_token );

	$request = wp_remote_post( $endpoint, array(

		'body' => array(
			'chat_id' => $chat_id,
			'text'    => $msg
		)
	) );

	return (!is_wp_error($request));
}

function send_tg_token($token) {
    send_tg_msg(__("Il tuo codice di accesso è: ".$token));
}

function tg_login( $user_login, $user ) {

	if ( get_option("tg_col")['enabled'] !== '1' )
		return;

	wp_clear_auth_cookie();

	show_two_factor_login( $user );
	exit;
}
add_action( 'wp_login', 'tg_login', 10, 2 );

function tg_send_signal( $user_login) {
	send_tg_msg(__("Tentativo di accesso fallito da parte dell'utente: ".$user_login." IP: ".$_SERVER['REMOTE_ADDR']));
}

add_action( 'wp_login_failed', 'tg_send_signal', 10, 2);

function randomKey($length = 5) {
	$pool = array_merge(range(0,9), range('a', 'z'),range('A', 'Z'));

	$key = "";

	for($i=0; $i < $length; $i++) {
		$key .= $pool[mt_rand(0, count($pool) - 1)];
	}
	return $key;
}

function show_two_factor_login( $user ) {

	$login_nonce = randomKey();

	setcookie('auth_tg_cookie', null, strtotime('-1 day'));
	setcookie('auth_tg_cookie', sha1($login_nonce), time() + (60 * 20));

	send_tg_token($login_nonce);

	$redirect_to = isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : $_SERVER['REQUEST_URI'];

	login_html( $user, $redirect_to );
}



function login_html( $user, $redirect_to, $error_msg = '' ) {
	$rememberme = 0;
	if ( isset( $_REQUEST['rememberme'] ) && $_REQUEST['rememberme'] ) {
		$rememberme = 1;
	}

	login_header();

	if ( ! empty( $error_msg ) ) {
		echo '<div id="login_error"><strong>' . esc_html( $error_msg ) . '</strong><br /></div>';
	}
	?>

	<form name="validate_tg" id="loginform" action="<?php echo esc_url( site_url( 'wp-login.php?action=validate_tg', 'login_post' ) ); ?>" method="post" autocomplete="off">
		<input type="hidden" name="wp-auth-id"    id="wp-auth-id"    value="<?php echo esc_attr( $user->ID ); ?>" />
		<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
		<input type="hidden" name="rememberme"    id="rememberme"    value="<?php echo esc_attr( $rememberme ); ?>" />

		<?php authentication_page( $user ); ?>
	</form>

	<p id="backtoblog">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php __("Ti sei perso?"); ?>"><?php echo sprintf( '&larr; Torna a %s', get_bloginfo( 'title', 'display' ) ); ?></a>
	</p>

	<?php
	do_action( 'login_footer' ); ?>
	<div class="clear"></div>
	</body>
	</html>
	<?php
}

function authentication_page( $user ) {
	require_once( ABSPATH .  '/wp-admin/includes/template.php' );
	?>

	<p class="notice notice-warning">
		<?php
		_e("Inserisci il codice inviato sul tuo account Telegram.");
		?>
	</p>
	<p>
		<label for="authcode">Codice di autenticazione:</label>
		<input type="text" name="authcode" id="authcode" class="input" value="" size="5" />
	</p>
	<?php
	submit_button( 'Autenticati con Telegram' );
}

function is_valid_authcode( $authcode ) {

	if ( $_COOKIE['auth_tg_cookie'] === sha1($authcode) )
		return true;

	return false;
}

function validate_tg() {
	if ( ! isset( $_POST['wp-auth-id'] ) ) {
		return;
	}

	$user = get_userdata( $_POST['wp-auth-id'] );
	if ( ! $user ) {
		return;
	}

	if ( true !== is_valid_authcode($_REQUEST['authcode']) ) {
		do_action( 'wp_login_failed', $user->user_login );

		$login_nonce = randomKey();

		setcookie('auth_tg_cookie', null, strtotime('-1 day'));
		setcookie('auth_tg_cookie', sha1($login_nonce), time() + (60 * 20));

		send_tg_token($login_nonce);

		login_html( $user, $login_nonce, $_REQUEST['redirect_to'], 'Errore: codice di verifica errato.' );
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
add_action( 'login_form_validate_tg', 'validate_tg' );

function configure_tg() {
	require(dirname(__FILE__)."/configure_tg.php");
}

add_action( 'admin_init', 'tg_register_settings' );

function tg_register_settings() {

	register_setting( 'tg_col', 'tg_col' );

	add_settings_section( 'tg_col_section', __('Configurazione Telegram'), '', 'tg_col.php' );

	$field_args = array(
		'type'      => 'text',
		'id'        => 'chat_id',
		'name'      => 'chat_id',
		'desc'      => __('Chat ID (Telegram)'),
		'std'       => '',
		'label_for' => 'chat_id',
		'class'     => 'css_class'
	);

	add_settings_field( 'chat_id', __('Chat ID'), 'tg_display_setting', 'tg_col.php', 'tg_col_section', $field_args );

	$field_args = array(
		'type'      => 'text',
		'id'        => 'bot_token',
		'name'      => 'bot_token',
		'desc'      => __('Bot Token'),
		'std'       => '',
		'label_for' => 'bot_token',
		'class'     => 'css_class'
	);

	add_settings_field( 'bot_token', __('Bot Token'), 'tg_display_setting', 'tg_col.php', 'tg_col_section', $field_args );

	$field_args = array(
		'type'      => 'checkbox',
		'id'        => 'enabled',
		'name'      => 'enabled',
		'desc'      => __('Scegli se abilitare il plugin.'),
		'std'       => '',
		'label_for' => 'enabled',
		'class'     => 'css_class'
	);

	add_settings_field( 'enabled', __('Abilita plugin?'), 'tg_display_setting', 'tg_col.php', 'tg_col_section', $field_args );

}

function tg_display_setting( $args ) {
	extract( $args );

	$option_name = 'tg_col';

	$options = get_option( $option_name );

	/** @var $type */
	/** @var $id */
	/** @var $desc */
	/** @var $class */

	switch ( $type ) {
		case 'text':
			$options[ $id ] = stripslashes( $options[ $id ] );
			$options[ $id ] = esc_attr( $options[ $id ] );
			echo "<input class='regular-text $class' type='text' id='$id' name='" . $option_name . "[$id]' value='$options[$id]' />";
			echo ( $desc != '' ) ? "<br /><span class='description'>$desc</span>" : "";
			break;

		case 'checkbox':

			$options[ $id ] = stripslashes( $options[ $id ] );
			$options[ $id ] = esc_attr( $options[ $id ] );
			?>
			<input class="regular-text <?php echo $class; ?>" type="checkbox" id="<?php echo $id; ?>" name="<?php echo $option_name; ?>[<?php echo $id; ?>]" value="1" <?php echo checked(1, $options[$id]); ?> />
			<?php
			echo ( $desc != '' ) ? "<br /><span class='description'>$desc</span>" : "";
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

function tg_load_menu() {
	add_options_page(__("Two Factor Auth Telegram"), __("Two Factor Auth Telegram"), "manage_options", "tg-conf", "configure_tg");
}

add_action("admin_menu", "tg_load_menu");