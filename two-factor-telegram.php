<?php

/**
 * Plugin Name: WP Two Factor Authentication with Telegram
 * Plugin URI: https://blog.dueclic.com/wordpress-autenticazione-due-fattori-telegram/
 * Description: This plugin enables two factor authentication with Telegram
 * Author: dueclic
 * Author URI: https://www.dueclic.com
 * Version: 1.2
 * License: GPL v3
 * Text Domain: two-factor-login-telegram
 * Domain Path: /languages/
 */

__('This plugin enables two factor authentication with Telegram', 'two-factor-login-telegram');

error_reporting( E_ERROR );

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 *
 * Full path to the WP Two Factor Telegram File
 *
 */

define( 'WP_FACTOR_TG_FILE', __FILE__ );

/**
 *
 * The main plugin class
 *
 */

require_once(  "includes/class-wp-factor-telegram-plugin.php" );

function WFT() {
	return WP_Factor_Telegram_Plugin::get_instance();
}

WFT();

