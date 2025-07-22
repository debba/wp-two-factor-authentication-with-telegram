<?php
/**
 * Template for Security Error Page
 *
 * Available variables:
 * - $user_id: User ID
 * - $token: The token that failed security check
 */

if (!defined('ABSPATH')) {
    exit;
}

$title = __('Security Error', 'two-factor-login-telegram');
$message = __('Security check failed. The link may have expired or been tampered with.', 'two-factor-login-telegram');
// Get plugin logo
$plugin_logo = apply_filters('two_factor_login_telegram_logo',
    plugins_url('assets/img/plugin_logo.png', WP_FACTOR_TG_FILE));

// Set HTTP response code
http_response_code(403);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($title); ?> - <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    <style>
        body { 
            font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;
            margin: 0;
            padding: 20px;
            background: #f1f1f1;
        }
        .error-container {
            max-width: 600px;
            margin: 50px auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12);
            text-align: center;
        }
        .plugin-logo {
            margin-bottom: 30px;
        }
        .plugin-logo img {
            max-width: 150px;
            height: auto;
        }
        .error-title {
            font-size: 24px;
            margin-bottom: 15px;
            color: #23282d;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .error-icon {
            font-size: 28px;
            color: #dc3232;
        }
        .error-message {
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .back-link {
            display: inline-block;
            background: #0073aa;
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 3px;
            font-weight: 500;
        }
        .back-link:hover {
            background: #005a87;
            color: white;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="plugin-logo">
            <img src="<?php echo esc_url($plugin_logo); ?>" alt="<?php bloginfo('name'); ?> 2FA">
        </div>
        <h1 class="error-title">
            <span class="error-icon">🔒</span>
            <?php echo esc_html($title); ?>
        </h1>
        <p class="error-message"><?php echo esc_html($message); ?></p>
        <a href="<?php echo esc_url(wp_login_url()); ?>" class="back-link">
            <?php _e('← Back to Login', 'two-factor-login-telegram'); ?>
        </a>
    </div>
    <?php wp_footer(); ?>
</body>
</html>
<?php
exit;
?>