<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$activities_table = $wpdb->prefix . 'wp2fat_activities';

// Handle clear logs action
if (isset($_POST['clear_logs']) && wp_verify_nonce($_POST['_wpnonce'], 'clear_telegram_logs')) {
    $wpdb->query("DELETE FROM $activities_table");
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Logs cleared successfully.', 'two-factor-login-telegram') . '</p></div>';
}

// Get logs from database
$logs = $wpdb->get_results("SELECT * FROM $activities_table ORDER BY timestamp DESC LIMIT 100", ARRAY_A);
?>

<div class="wrap">
    <h1><?php _e('Telegram Bot Logs', 'two-factor-login-telegram'); ?></h1>

    <form method="post">
        <?php wp_nonce_field('clear_telegram_logs'); ?>
        <input type="submit" name="clear_logs" class="button button-secondary" value="<?php _e('Clear Logs', 'two-factor-login-telegram'); ?>" onclick="return confirm('<?php _e('Are you sure you want to clear all logs?', 'two-factor-login-telegram'); ?>')">
    </form>

    <br>

    <?php if (empty($logs)): ?>
        <p><?php _e('No logs available.', 'two-factor-login-telegram'); ?></p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 15%;"><?php _e('Timestamp', 'two-factor-login-telegram'); ?></th>
                    <th style="width: 15%;"><?php _e('Action', 'two-factor-login-telegram'); ?></th>
                    <th><?php _e('Data', 'two-factor-login-telegram'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo esc_html($log['timestamp']); ?></td>
                        <td><?php echo esc_html($log['action']); ?></td>
                        <td>
                            <details>
                                <summary><?php _e('View details', 'two-factor-login-telegram'); ?></summary>
                                <pre style="background: #f1f1f1; padding: 10px; margin-top: 10px; overflow-x: auto;"><?php echo esc_html(print_r(maybe_unserialize($log['data']), true)); ?></pre>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>