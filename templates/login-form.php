<?php
/**
 * Template for 2FA Login Form
 *
 * Available variables:
 * - $user: User object
 * - $redirect_to: Redirect URL after login
 * - $error_msg: Error message to display
 * - $plugin_logo: URL of the plugin logo
 * - $rememberme: Remember me value
 * - $nonce: Security nonce
 */

if (!defined('ABSPATH')) {
    exit;
}

// Call login_header() to display WordPress login page header
login_header();

if (!empty($error_msg)) {
    echo '<div id="login_error"><strong>' . esc_html($error_msg) . '</strong><br /></div>';
}
?>

<style>
    body.login div#login h1 a {
        background-image: url("<?php echo esc_url($plugin_logo); ?>");
    }
</style>

<form name="validate_tg" id="loginform" action="<?php echo esc_url(site_url('wp-login.php?action=validate_tg', 'login_post')); ?>" method="post" autocomplete="off">
    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('wp2fa_telegram_auth_nonce_' . $user->ID); ?>">
    <input type="hidden" name="wp-auth-id" id="wp-auth-id" value="<?php echo esc_attr($user->ID); ?>"/>
    <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>"/>
    <input type="hidden" name="rememberme" id="rememberme" value="<?php echo esc_attr($rememberme); ?>"/>

    <p class="notice notice-warning">
        <?php _e("Enter the code sent to your Telegram account.", "two-factor-login-telegram"); ?>
    </p>
    
    <p>
        <label for="authcode" style="padding-top:1em">
            <?php _e("Authentication code:", "two-factor-login-telegram"); ?>
        </label>
        <input type="text" name="authcode" id="authcode" class="input" value="" size="5"/>
    </p>
    
    <?php submit_button(__('Login with Telegram', 'two-factor-login-telegram')); ?>
</form>

<p id="backtoblog">
    <a href="<?php echo esc_url(home_url('/')); ?>" title="<?php esc_attr_e("Are you lost?", "two-factor-login-telegram"); ?>">
        <?php echo sprintf(__('&larr; Back to %s', 'two-factor-login-telegram'), get_bloginfo('title', 'display')); ?>
    </a>
</p>

<script type="text/javascript">
// Auto-expire token after timeout period
setTimeout(function() {
    var errorDiv = document.getElementById('login_error');
    if (!errorDiv) {
        errorDiv = document.createElement('div');
        errorDiv.id = 'login_error';
        var loginForm = document.getElementById('loginform');
        if (loginForm) {
            loginForm.parentNode.insertBefore(errorDiv, loginForm);
        }
    }
    errorDiv.innerHTML = '<strong><?php echo esc_js(__('The verification code has expired. Please request a new code to login.', 'two-factor-login-telegram')); ?></strong><br />';
}, <?php echo WP_FACTOR_AUTHCODE_EXPIRE_SECONDS * 1000; ?>);
</script>

<?php
do_action('login_footer');
?>
<div class="clear"></div>
</body>
</html>