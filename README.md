
![WP 2FA with Telegram](https://raw.githubusercontent.com/debba/wp-two-factor-authentication-with-telegram/master/assets/img/plugin_cover.png)

WP 2FA with Telegram allows you to enable Two-factor authentication for WordPress Login using Telegram.

* **Easy Configuration**: install plugin and setup in a few seconds.
* **Increase security**: increase the level of security in your blog / website by adding and additional authentication factor
* **Speed**: Forget additional apps, sms or captcha. Use Telegram for a very fast experience!
* **Allow users to enable Two-factor authentication**: every user directly from own profile may decide to require secure login.
* **Send alert**: set a Telegram Chat Id for an admin to receive  for receiving notifications every time users fail login.
* **FAQ**: read the FAQ inside plugin for create your Telegram Bot
* **Languages**: plugin is available in Italian, English and Spanish languages.

## Frequently Asked Questions ##

### Can I customize the logo on the "WP 2FA with Telegram" login screen? =
Yes, you can do it. To use your custom logo, you must to use the <code>two_factor_login_telegram_logo</code> filter hook. Below you can see a useful code snippet as example of use (you must to put this in a custom plugin or the <code>functions.php</code> file of your active theme):

```php
// Custom logo on "WP 2FA with Telegram" login screen:
function two_factor_login_telegram_custom_logo(){

  $image_path = home_url('/images/');
  $image_filename = 'custom-two-factor-telegram.png';

  return $image_path . $image_filename;
}

add_filter('two_factor_login_telegram_logo', 'two_factor_login_telegram_custom_logo');
```

Please note the URL generated in the example above is https://example.com/images/custom-two-factor-telegram.png. If you want to use this code, you'll need to update the path and filename to match with location of your custom logo.


