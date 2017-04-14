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

