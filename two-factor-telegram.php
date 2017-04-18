<?php

/*
Plugin Name: WP Two Factor Authentication with Telegram
Plugin URI: https://www.dueclic.com/two-factor-telegram/
Description: Abilita l'autenticazione a due fattori con Telegram
Version: 1.0
Author: debba
Author URI: https://www.dueclic.com
License: GPLv2 or later
*/

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

