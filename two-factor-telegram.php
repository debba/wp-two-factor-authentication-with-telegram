<?php

/*
Plugin Name: Two Factor Authentication with Telegram
Plugin URI: https://www.dueclic.com/two-factor-telegram/
Description: Enable Two Factor Authentication with Telegram.
Version: 1.0
Author: debba
Author URI: https://www.dueclic.com
License: A "Slug" license name e.g. GPL2
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

