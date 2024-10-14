=== WP 2FA with Telegram ===
Contributors: dueclic, yordansoares
Tags: 2fa, authentication, telegram, authenticate, security
Requires at least: 5.0.0
Requires PHP: 7.0
Tested up to: 6.6
Stable tag: 3.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

This plugin enables two factor authentication with Telegram by increasing your website security and sends an alert every time a wrong login occurs.

== Description ==

WP 2FA with Telegram allows you to enable Two-factor authentication for WordPress Login using Telegram.

* **Easy Configuration**: install plugin and setup in a few seconds.
* **Increase security**: increase the level of security in your blog / website by adding and additional authentication factor
* **Speed**: Forget additional apps, sms or captcha. Use Telegram for a very fast experience!
* **Allow users to enable Two-factor authentication**: every user directly from own profile may decide to require secure login.
* **Send alert**: set a Telegram Chat Id for an admin to receive  for receiving notifications every time users fail login.
* **FAQ**: read the FAQ inside plugin for create your Telegram Bot
* **Languages**: plugin is available in Italian, English and Spanish languages.

== Frequently Asked Questions ==
= Can I customize the logo on the "WP 2FA with Telegram" login screen? =
Yes, you can do it. To use your custom logo, you must to use the <code>two_factor_login_telegram_logo</code> filter hook. Below you can see a useful code snippet as example of use (you must to put this in a custom plugin or the <code>functions.php</code> file of your active theme):

<code>
// Custom logo on "WP 2FA with Telegram" login screen:
function two_factor_login_telegram_custom_logo(){

  $image_path = home_url('/images/');
  $image_filename = 'custom-two-factor-telegram.png';

  return $image_path . $image_filename;
}

add_filter('two_factor_login_telegram_logo', 'two_factor_login_telegram_custom_logo');
</code>

Please note the URL generated in the example above is https://example.com/images/custom-two-factor-telegram.png. If you want to use this code, you'll need to update the path and filename to match with location of your custom logo.

== Screenshots ==
1. This is the setup tab. Here you must to enter your bot token and your chat ID. The plugin only works when this info is filled. Optinally, you can choose to show or not the site name and site URL in the  failed attempt to access message.
2. After configuring your bot token and chat ID, you will see a confirmation notice with a link to configure two-factor authentication with Telegram with your current user.
3. You will also find a tab with the FAQ.
4. After configuring your bot token and chat ID, you will able to activate two-factor authentication with Telegram for your current user.
5. The login page will show a second screen after the user's login has been successful. You must enter here your authentication code that your Telegram bot will send you to continue to the WordPress dashboard.
6. This plugin send three type of messages: 1. A message to each user when setup two-factor authentication with Telegram, 2. A message with access code for each time an users with two-factor authentication with Telegram enabled try login to your WordPress dashboard, 3. A message to admin for each time a user failed attempt to access.
7. You can customize the logo with yours using "two_factor_login_telegram_logo" filter hook. See the instructions of use in FAQ.

== Changelog ==

= 3.1 =
* Updated auth code storage
* Fix Suggestions tab

= 3.0 =
* Extended compatibility to WP 6.6

= 2.9 =
* Extended compatibility to WP 6.3

= 2.8 =
* Extended compatibility to WP 6.2

= 2.7 =
* Fix security issues

= 2.6 =
* Extended compatibility to WP 6.1
* Fix security issues

= 2.3 =
* Extend compatibility to WP 5.9

= 2.2 =
* Bugfixes

= 2.1 =
* Extend compatibility to WP 5.8

= 2.0.0 =
* Extend compatibility to WP 5.7

= 1.9.1 =
* Backend performance improvements (Javascript and CSS)

= 1.9 =
* Backend perfomance improvements

= 1.8.4 =
* Improved markup in setup page
* Tested up to WordPress 5.4
= 1.8.3 =
* Introduced <code>two_factor_login_telegram_logo</code> filter hook to customize the logo in «WP 2FA with Telegram» login screen
* Added new screenshot to show the <code>two_factor_login_telegram_logo</code> filter hook in action
* Added FAQ entry to explain of <code>two_factor_login_telegram_logo</code> filter hook use.
* Updated plugin name to "WP 2FA with Telegram" (Previusly "WP Two Factor Authentication with Telegram")
* Remove folders <strong>/languages</strong> and <strong>/screenshot</strong> from plugin root directory. Those directories are not uselful anymore.
* Fixed some fields in plugin header comment and Readme file according to the best practices recommended by [WP Developer Handbook](https://developer.wordpress.org/plugins/plugin-basics/header-requirements/) and [Plugin i18n Readiness](https://wp-info.org/tools/checkplugini18n.php?slug=two-factor-login-telegram).
= 1.8.2 =
* Small improves of code
* Updated the screenshots of plugin
= 1.8.1 =
* Fixed text domain in two strings of FAQ section
= 1.8 =
* Added two new options to failed login attempt message you can enable or disable when you need: Show site name & show site URL
= 1.7 =
* Added missing translations strings
= 1.6 =
* Improvements for WordPress 5.3
= 1.5 =
* Fixed a bug which prevented user to disable Telegram 2FA
* Fixed a bug which prevented user to receive a new code if inserted code is wrong
= 1.4 =
* Bugfixes, new logo and cover
= 1.3 =
* Extended compatibility to WP 4.9.4
= 1.2 =
* In failed send with Telegram the IP address behind a CloudFlare proxy (Thx Manuel for suggestion)
= 1.1 =
* Insert english translation
* Introduced a tab for report problems or leave suggestions
= 1.0 =
* First public release
