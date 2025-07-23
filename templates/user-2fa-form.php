<?php
/**
 * Template for user 2FA form
 */

$is_enabled = esc_attr(get_the_author_meta('tg_wp_factor_enabled', $user->ID)) === "1";
$chat_id = esc_attr(get_the_author_meta('tg_wp_factor_chat_id', $user->ID));
$is_configured = !empty($chat_id);
?>
<h3 id="wptl"><?php
_e(
    '2FA with Telegram',
    'two-factor-login-telegram'
); ?></h3>

<table class="form-table">
    <tr>
        <th>
            <label for="tg_wp_factor_enabled"><?php
            _e(
                'Enable 2FA',
                'two-factor-login-telegram'
            ); ?>
            </label>
        </th>
        <td colspan="2">
            <input type="hidden" name="tg_wp_factor_valid" id="tg_wp_factor_valid"
                value="<?php echo (int) $is_enabled; ?>">
            <input type="checkbox" name="tg_wp_factor_enabled" id="tg_wp_factor_enabled" value="1" class="regular-text"
                <?php echo checked($is_enabled, 1); ?> />

            <?php if ($is_configured && $is_enabled): ?>
                <span class="tg-status success" style="display: inline-flex; margin-left: 10px;">
                    ✅ <?php _e('2FA is active', 'two-factor-login-telegram'); ?>
                </span>
            <?php endif; ?>
        </td>
    </tr>

    <?php if ($is_configured && $is_enabled): ?>
        <tr class="tg-configured-row">
            <th>
                <label for="tg_wp_factor_chat_id_display"><?php
                _e(
                    'Current Telegram Chat ID',
                    'two-factor-login-telegram'
                ); ?>
                </label>
            </th>
            <td>
                <input type="text" name="tg_wp_factor_chat_id_display" id="tg_wp_factor_chat_id_display"
                    value="<?php echo $chat_id; ?>" class="regular-text input-valid" readonly
                    style="background: #f9f9f9;" /><br />
                <span class="description">
                    <?php _e('Your 2FA is configured with this Chat ID. Change it to reconfigure.', 'two-factor-login-telegram'); ?>
                </span>
            </td>
            <td>
                <button type="button" class="button tg-edit-button" id="tg-edit-chat-id">
                    <?php _e('Change', 'two-factor-login-telegram'); ?>
                </button>
            </td>
        </tr>
    <?php endif; ?>
</table>

<div id="tg-2fa-configuration" style="display: none;">
    <table class="form-table">

        <tr>
            <td colspan="3">
                <?php
                $username = $this->telegram->get_me()->username;
                ?>

                <div class="tg-setup-steps">
                    <h4 style="margin-top: 0;"><?php _e('🚀 Setup Steps', 'two-factor-login-telegram'); ?></h4>
                    <ol>
                        <li>
                            <?php
                            printf(
                                __(
                                    'Open a conversation with %s and press on <strong>Start</strong>',
                                    'two-factor-login-telegram'
                                ),
                                '<a href="https://telegram.me/' . $username
                                . '" target="_blank">@' . $username . '</a>'
                            );
                            ?>
                        </li>

                        <li>
                            <?php
                            printf(
                                __(
                                    'Type command %s to obtain your Chat ID.',
                                    "two-factor-login-telegram"
                                ),
                                '<code>/get_id</code>'
                            );
                            ?>
                        </li>
                        <li>
                            <?php
                            _e(
                                "The bot will reply with your <strong>Chat ID</strong> number",
                                'two-factor-login-telegram'
                            );
                            ?>
                        </li>

                        <li><?php
                        _e(
                            'Copy your Chat ID and paste it below, then press <strong>Submit code</strong>',
                            'two-factor-login-telegram'
                        ); ?></li>
                    </ol>
                </div>

                <!-- Progress indicator -->
                <div class="tg-progress">
                    <div class="tg-progress-bar" id="tg-progress-bar"></div>
                </div>
            </td>
        </tr>

        <tr>
            <th>
                <label for="tg_wp_factor_chat_id"><?php
                _e(
                    'Telegram Chat ID',
                    'two-factor-login-telegram'
                ); ?>
                </label>
            </th>
            <td>
                <input type="text" name="tg_wp_factor_chat_id" id="tg_wp_factor_chat_id" value="<?php
                echo esc_attr(get_the_author_meta(
                    'tg_wp_factor_chat_id',
                    $user->ID
                )); ?>" class="regular-text" /><br />
                <span class="description"><?php
                _e(
                    'Put your Telegram Chat ID',
                    'two-factor-login-telegram'
                ); ?></span>
            </td>
            <td>
                <button class="tg-action-button" id="tg_wp_factor_chat_id_send"><?php
                _e(
                    "Submit code",
                    "two-factor-login-telegram"
                ); ?></button>
                <div id="chat-id-status" class="tg-status" style="display: none;"></div>
            </td>
        </tr>

        <tr id="factor-chat-confirm">
            <th>
                <label for="tg_wp_factor_chat_id_confirm"><?php
                _e(
                    'Confirmation code',
                    'two-factor-login-telegram'
                ); ?>
                </label>
            </th>
            <td>
                <input type="text" name="tg_wp_factor_chat_id_confirm" id="tg_wp_factor_chat_id_confirm" value=""
                    class="regular-text" /><br />
                <span class="description"><?php
                _e(
                    'Please enter the confirmation code you received on Telegram',
                    'two-factor-login-telegram'
                ); ?></span>
            </td>
            <td>
                <button class="tg-action-button" id="tg_wp_factor_chat_id_check"><?php
                _e(
                    "Validate",
                    "two-factor-login-telegram"
                ); ?></button>
                <div id="validation-status" class="tg-status" style="display: none;"></div>
            </td>
        </tr>
        <tr id="factor-chat-response">
            <td colspan="3">
                <div class="wpft-notice wpft-notice-warning">
                    <p></p>
                </div>
            </td>
        </tr>
    </table>
</div>

<?php if ($is_configured && $is_enabled): ?>
    <hr style="margin:32px 0 18px 0;">
    <div id="tg-recovery-codes-section" style="margin-bottom:32px;">
        <h4 style="margin-top:0;">
            <?php _e('Recovery Codes', 'two-factor-login-telegram'); ?>
        </h4>
        <?php
        $recovery_codes_plain = isset($GLOBALS['tg_recovery_codes_plain']) ? $GLOBALS['tg_recovery_codes_plain'] : null;
        $recovery_codes = get_user_meta($user->ID, 'tg_wp_factor_recovery_codes', true);
        $just_regenerated = is_array($recovery_codes_plain) && count($recovery_codes_plain) > 0;
        ?>
        <?php if ($just_regenerated): ?>
            <div class="notice-warning" style="margin-bottom:16px;">
                <?php _e('These are your new Recovery Codes. Save them now: they won\'t be visible again!', 'two-factor-login-telegram'); ?>
            </div>
            <div class="recovery-codes-list" style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:18px;">
                <?php foreach ($recovery_codes_plain as $code): ?>
                    <div class="recovery-code-box"
                        style="background:#f9f9f9;border:1px solid #e1e1e1;border-radius:4px;padding:10px 16px;font-family:monospace;font-size:1.1em;letter-spacing:2px;">
                        <?php echo esc_html($code); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="notice-info" style="margin-bottom:12px;">
                <?php _e('You have already generated Recovery Codes. You can regenerate them if needed, but the old ones will be invalidated.', 'two-factor-login-telegram'); ?>
            </div>
        <?php endif; ?>
        <div style="margin-top:10px;">
            <button type="button" class="button tg-action-button" id="tg-recovery-codes-btn"
                    data-nonce="<?php echo wp_create_nonce('tg_regenerate_recovery_codes_' . $user->ID); ?>">
                <?php _e('Regenerate Recovery Codes', 'two-factor-login-telegram'); ?>
            </button>
            <span id="tg-recovery-spinner" style="display:none;margin-left:10px;vertical-align:middle;">⌛</span>
            <span id="tg-recovery-msg" style="margin-left:10px;color:#d00;"></span>
        </div>
    </div>
<?php endif; ?>

<script>
    jQuery(document).ready(function ($) {
        var $btn = $('#tg-recovery-codes-btn');
        var $spinner = $('#tg-recovery-spinner');
        var $msg = $('#tg-recovery-msg');

        $btn.on('click', function (e) {
            e.preventDefault();

            if (!confirm('<?php echo esc_js(__('Are you sure you want to regenerate Recovery Codes? The old ones will no longer be valid.', 'two-factor-login-telegram')); ?>')) return;

            $spinner.show();
            $msg.text('');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'regenerate_recovery_codes',
                    _wpnonce: $btn.data('nonce')
                },
                success: function (res) {
                    $spinner.hide();
                    if (res.success && res.data && res.data.html) {
                        openRecoveryCodesModal(null, window.location.href, res.data.html);
                    } else {
                        $msg.text(res.data && res.data.message ? res.data.message : '<?php echo esc_js(__('Error occurred', 'two-factor-login-telegram')); ?>');
                    }
                },
                error: function (xhr, status, error) {
                    $spinner.hide();
                    $msg.text('<?php echo esc_js(__('Network error', 'two-factor-login-telegram')); ?>: ' + error);
                }
            });
        });
    });
</script>

