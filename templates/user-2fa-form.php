<?php
/**
 * Template for user 2FA form
 */

$is_enabled = esc_attr(get_the_author_meta('tg_wp_factor_enabled', $user->ID)) === "1";
$chat_id = esc_attr(get_the_author_meta('tg_wp_factor_chat_id', $user->ID));
$is_configured = !empty($chat_id);
?>
<h3 id="wptl"><?php
    _e('2FA with Telegram',
        'two-factor-login-telegram'); ?></h3>

<table class="form-table">
    <tr>
        <th>
            <label for="tg_wp_factor_enabled"><?php
                _e('Enable 2FA',
                    'two-factor-login-telegram'); ?>
            </label>
        </th>
        <td colspan="2">
            <input type="hidden" name="tg_wp_factor_valid"
                   id="tg_wp_factor_valid" value="<?php echo (int)$is_enabled; ?>">
            <input type="checkbox" name="tg_wp_factor_enabled"
                   id="tg_wp_factor_enabled" value="1"
                   class="regular-text" <?php echo checked($is_enabled, 1); ?> />
            
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
                _e('Current Telegram Chat ID',
                    'two-factor-login-telegram'); ?>
            </label>
        </th>
        <td>
            <input type="text" name="tg_wp_factor_chat_id_display" 
                   id="tg_wp_factor_chat_id_display" value="<?php echo $chat_id; ?>" 
                   class="regular-text input-valid" readonly style="background: #f9f9f9;"/><br/>
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
                            printf(__('Open a conversation with %s and press on <strong>Start</strong>',
                                'two-factor-login-telegram'),
                                '<a href="https://telegram.me/' . $username
                                . '" target="_blank">@' . $username . '</a>');
                            ?>
                        </li>

                        <li>
                            <?php
                            printf(__('Type command %s to obtain your Chat ID.',
                                "two-factor-login-telegram"),
                                '<code>/get_id</code>');
                            ?>
                        </li>
                        <li>
                            <?php
                            _e("The bot will reply with your <strong>Chat ID</strong> number",
                                'two-factor-login-telegram');
                            ?>
                        </li>

                        <li><?php
                            _e('Copy your Chat ID and paste it below, then press <strong>Submit code</strong>',
                                'two-factor-login-telegram'); ?></li>
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
                    _e('Telegram Chat ID',
                        'two-factor-login-telegram'); ?>
                </label></th>
            <td>
                <input type="text" name="tg_wp_factor_chat_id"
                       id="tg_wp_factor_chat_id" value="<?php
                echo esc_attr(get_the_author_meta('tg_wp_factor_chat_id',
                    $user->ID)); ?>" class="regular-text"/><br/>
                <span class="description"><?php
                    _e('Put your Telegram Chat ID',
                        'two-factor-login-telegram'); ?></span>
            </td>
            <td>
                <button class="tg-action-button" id="tg_wp_factor_chat_id_send"><?php
                    _e("Submit code",
                        "two-factor-login-telegram"); ?></button>
                <div id="chat-id-status" class="tg-status" style="display: none;"></div>
            </td>
        </tr>

        <tr id="factor-chat-confirm">
            <th>
                <label for="tg_wp_factor_chat_id_confirm"><?php
                    _e('Confirmation code',
                        'two-factor-login-telegram'); ?>
                </label></th>
            <td>
                <input type="text" name="tg_wp_factor_chat_id_confirm"
                       id="tg_wp_factor_chat_id_confirm" value=""
                       class="regular-text"/><br/>
                <span class="description"><?php
                    _e('Please enter the confirmation code you received on Telegram',
                        'two-factor-login-telegram'); ?></span>
            </td>
            <td>
                <button class="tg-action-button" id="tg_wp_factor_chat_id_check"><?php
                    _e("Validate",
                        "two-factor-login-telegram"); ?></button>
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
