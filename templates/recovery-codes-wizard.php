<?php
if (!defined('ABSPATH'))
    exit;
/*
 * Template: Recovery Codes Wizard (solo contenuto modale)
 * Variabili disponibili:
 * - $codes: array dei recovery codes in chiaro
 * - $plugin_logo: url logo plugin
 * - $redirect_to: url di redirect dopo conferma
 */
$is_profile = (defined('IS_PROFILE_PAGE') && IS_PROFILE_PAGE);
?>
<div class="tg-modal-recovery" id="tg-modal-recovery">
    <div class="tg-modal-recovery-bg" onclick="closeRecoveryModal()"></div>
    <div class="wizard-container tg-modal-recovery-content">
        <button class="tg-modal-close" onclick="closeRecoveryModal()">&times;</button>
        <div class="plugin-logo">
            <img src="<?php echo esc_url($plugin_logo); ?>" alt="2FA Plugin Logo">
        </div>
        <h2><?php _e('Recovery Codes', 'two-factor-login-telegram'); ?></h2>
        <div class="notice-warning">
            <?php
            if ($is_profile) {
                _e('You have just regenerated Recovery Codes. Save them now: <b>they will only be shown at this moment</b> and cannot be recovered.', 'two-factor-login-telegram');
            } else {
                _e('These codes allow you to log in if you don\'t have access to Telegram. <b>Save them in a safe place!</b> They will only be shown now and cannot be recovered.', 'two-factor-login-telegram');
            }
            ?>
        </div>
        <button class="copy-btn" onclick="copyRecoveryCodes()"><?php _e('Copy all codes', 'two-factor-login-telegram'); ?></button>
        <div class="recovery-codes-list" id="recovery-codes-list">
            <?php foreach ($codes as $code): ?>
                <div class="recovery-code-box"><?php echo esc_html($code); ?></div>
            <?php endforeach; ?>
        </div>
        <button class="tg-action-button button-primary" id="confirm-recovery-codes"><?php _e('I have saved the codes, continue', 'two-factor-login-telegram'); ?></button>
    </div>
</div>