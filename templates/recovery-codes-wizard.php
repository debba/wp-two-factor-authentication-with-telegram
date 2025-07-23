<?php
if (!defined('ABSPATH'))
    exit;

$is_profile = (defined('IS_PROFILE_PAGE') && IS_PROFILE_PAGE);
?>
<style>
    body.login div#login h1 a {
        background-image: url("<?php echo esc_url($plugin_logo); ?>");
    }

    /* Recovery Codes Modal Styles for Login Page */
    .tg-modal-recovery {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0,0,0,0.35);
        animation: tg-modal-fadein 0.2s;
    }
    .tg-modal-recovery-bg {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.35);
        z-index: 1;
    }
    .tg-modal-recovery-content {
        position: relative;
        z-index: 2;
        box-shadow: 0 4px 24px rgba(0,0,0,0.13);
        border-radius: 8px;
        background: #fff;
        max-width: 480px;
        width: 95vw;
        margin: 0 auto;
        padding: 32px 28px 24px 28px;
        text-align: center;
    }
    .tg-modal-close {
        position: absolute;
        top: 12px;
        right: 16px;
        background: none;
        border: none;
        font-size: 2em;
        color: #888;
        cursor: pointer;
        z-index: 3;
        line-height: 1;
    }
    .tg-modal-close:hover {
        color: #222;
    }
    @keyframes tg-modal-fadein {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .tg-modal-recovery h2 {
        color: #333;
        font-size: 24px;
        margin: 0 0 20px 0;
        font-weight: 600;
    }

    .notice-warning {
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        border-radius: 6px;
        padding: 15px;
        margin: 15px 0 20px 0;
        color: #856404;
        font-size: 14px;
        line-height: 1.5;
    }

    .copy-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: white;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 500;
        font-size: 14px;
        margin-bottom: 20px;
        transition: all 0.2s ease;
    }

    .copy-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .recovery-codes-list {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 20px;
        margin: 20px 0;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 12px;
    }

    .recovery-code-box {
        background: #ffffff;
        border: 2px solid #dee2e6;
        border-radius: 6px;
        padding: 12px 8px;
        font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
        font-size: 13px;
        font-weight: 600;
        text-align: center;
        color: #495057;
        transition: all 0.2s ease;
        cursor: pointer;
        user-select: all;
    }

    .recovery-code-box:hover {
        border-color: #667eea;
        background: #f8f9ff;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(102, 126, 234, 0.1);
    }

    .recovery-code-box:active {
        background: #e7f0ff;
        border-color: #5a6fd8;
    }

    .plugin-logo {
        margin-bottom: 20px;
    }

    .plugin-logo img {
        width: 64px;
        height: 64px;
        border-radius: 50%;
    }

    .tg-action-button.button-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: white;
        padding: 12px 24px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 500;
        font-size: 16px;
        margin-top: 20px;
        transition: all 0.2s ease;
        width: 100%;
    }

    .tg-action-button.button-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    /* Responsive adjustments for recovery codes */
    @media (max-width: 768px) {
        .recovery-codes-list {
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            padding: 15px;
        }

        .recovery-code-box {
            font-size: 12px;
            padding: 10px 6px;
        }

        .tg-modal-recovery-content {
            padding: 24px 20px;
            margin: 20px;
        }
    }
</style>
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
<script>
    window.openRecoveryCodesModal = function(url, redirect_to, html) {
        var oldModal = document.getElementById('tg-modal-recovery');
        if (oldModal) oldModal.remove();
        if (html) {
            var div = document.createElement('div');
            div.innerHTML = html;
            var modalElement = div.querySelector('#tg-modal-recovery') || div.firstElementChild;
            if (modalElement) {
                document.body.appendChild(modalElement);
                var btn = document.getElementById('confirm-recovery-codes');
                if (btn) {
                    btn.onclick = function() {
                        window.location.href = redirect_to;
                    };
                }
            }
            return;
        }

        fetch(url)
            .then(r => r.text())
            .then(html => {
                var div = document.createElement('div');
                div.innerHTML = html;
                document.body.appendChild(div.firstElementChild);
                var btn = document.getElementById('confirm-recovery-codes');
                if (btn) {
                    btn.onclick = function() {
                        window.location.href = redirect_to;
                    };
                }
            });

    }
    window.closeRecoveryModal = function() {
        var modal = document.getElementById('tg-modal-recovery');
        if (modal) modal.remove();
    }
    window.copyRecoveryCodes = function() {
        let codes = Array.from(document.querySelectorAll('.recovery-code-box')).map(e => e.textContent).join('\n');
        navigator.clipboard.writeText(codes).then(function() {
            alert('Codes copied to clipboard!');
        }).catch(function() {
            alert('Failed to copy codes to clipboard');
        });
    }

</script>
