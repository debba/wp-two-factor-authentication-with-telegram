<?php

/**
 * Plugin Name: WP 2FA with Telegram
 * Plugin URI: https://blog.dueclic.com/wordpress-autenticazione-due-fattori-telegram/
 * Description: This plugin enables two factor authentication with Telegram by increasing your website security and sends an alert every time a wrong login occurs.
 * Version: 2.8
 * Requires at least: 4.7.4
 * Tested up to: 6.2
 * Requires PHP: 7.0
 * Author: dueclic
 * Author URI: https://www.dueclic.com
 * Text Domain: two-factor-login-telegram
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

__(
    'This plugin enables two factor authentication with Telegram by increasing your website security and sends an alert every time a wrong login occurs.',
    'two-factor-login-telegram'
);

error_reporting(E_ERROR);

if ( ! defined('ABSPATH')) {
    die;
}

/**
 *
 * Full path to the WP Two Factor Telegram File
 *
 */

define('WP_FACTOR_TG_FILE', __FILE__);

define('WP_FACTOR_TG_GETME_TRANSIENT', 'tg_wp_factor_valid_bot');

/**
 *
 * The main plugin class
 *
 */

require_once("includes/class-wp-factor-telegram-plugin.php");

function WFT()
{
    return WP_Factor_Telegram_Plugin::get_instance();
}

WFT();

