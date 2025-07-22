<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Telegram_Logs_List_Table extends WP_List_Table
{
    private $table_data;

    public function __construct()
    {
        parent::__construct([
            'singular' => __('Log', 'two-factor-login-telegram'),
            'plural'   => __('Logs', 'two-factor-login-telegram'),
            'ajax'     => false
        ]);
    }

    public function get_columns()
    {
        return [
            'cb'        => '<input type="checkbox" />',
            'timestamp' => __('Timestamp', 'two-factor-login-telegram'),
            'action'    => __('Action', 'two-factor-login-telegram'),
            'data'      => __('Data', 'two-factor-login-telegram')
        ];
    }

    public function get_sortable_columns()
    {
        return [
            'timestamp' => ['timestamp', true],
            'action'    => ['action', false]
        ];
    }

    public function get_bulk_actions()
    {
        return [
            'delete' => __('Delete', 'two-factor-login-telegram')
        ];
    }

    public function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="log_ids[]" value="%s" />',
            $item['id']
        );
    }

    public function column_timestamp($item)
    {
        // Format timestamp using WordPress date and time settings
        $timestamp = $item['timestamp'];
        $date_format = get_option('date_format');
        $time_format = get_option('time_format');
        $formatted_timestamp = wp_date($date_format . ' ' . $time_format, strtotime($timestamp));
        
        $delete_url = wp_nonce_url(
            admin_url('options-general.php?page=tg-conf&tab=logs&action=delete&log_id=' . $item['id']),
            'delete_log_' . $item['id']
        );

        $actions = [
            'delete' => sprintf(
                '<a href="%s" onclick="return confirm(\'%s\')">%s</a>',
                $delete_url,
                __('Are you sure you want to delete this log entry?', 'two-factor-login-telegram'),
                __('Delete', 'two-factor-login-telegram')
            )
        ];

        return sprintf('%1$s %2$s', esc_html($formatted_timestamp), $this->row_actions($actions));
    }

    public function column_action($item)
    {
        return esc_html($item['action']);
    }

    public function column_data($item)
    {
        $data = maybe_unserialize($item['data']);
        $formatted_data = print_r($data, true);
        
        return sprintf(
            '<details><summary>%s</summary><pre style="background: #f1f1f1; padding: 10px; margin-top: 10px; overflow-x: auto; font-size: 11px;">%s</pre></details>',
            __('View details', 'two-factor-login-telegram'),
            esc_html($formatted_data)
        );
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'timestamp':
            case 'action':
            case 'data':
                return $item[$column_name];
            default:
                return print_r($item, true);
        }
    }

    public function prepare_items()
    {
        global $wpdb;

        $per_page = 10;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        $table_name = $wpdb->prefix . 'wp2fat_activities';

        // Handle sorting
        $orderby = (!empty($_GET['orderby'])) ? $_GET['orderby'] : 'timestamp';
        $order = (!empty($_GET['order'])) ? $_GET['order'] : 'DESC';

        // Sanitize sorting parameters
        $allowed_orderby = ['timestamp', 'action'];
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'timestamp';
        }
        
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        // Get total items count
        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");

        // Get data
        $this->table_data = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY $orderby $order LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),
            ARRAY_A
        );

        // Set up columns
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        // Set up pagination
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);

        $this->items = $this->table_data;
    }

    public function process_bulk_action()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wp2fat_activities';

        if ('delete' === $this->current_action()) {
            if (isset($_POST['log_ids']) && is_array($_POST['log_ids'])) {
                $log_ids = array_map('intval', $_POST['log_ids']);
                if (!empty($log_ids)) {
                    $placeholders = implode(',', array_fill(0, count($log_ids), '%d'));
                    $wpdb->query(
                        $wpdb->prepare(
                            "DELETE FROM $table_name WHERE id IN ($placeholders)",
                            ...$log_ids
                        )
                    );
                    
                    echo '<div class="notice notice-success is-dismissible"><p>' . 
                         sprintf(__('Deleted %d log entries.', 'two-factor-login-telegram'), count($log_ids)) . 
                         '</p></div>';
                }
            }
        }

        // Handle single delete action
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['log_id'])) {
            $log_id = intval($_GET['log_id']);
            if (wp_verify_nonce($_GET['_wpnonce'], 'delete_log_' . $log_id)) {
                $wpdb->delete($table_name, ['id' => $log_id], ['%d']);
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                     __('Log entry deleted.', 'two-factor-login-telegram') . 
                     '</p></div>';
            }
        }
    }

    public function no_items()
    {
        _e('No logs found.', 'two-factor-login-telegram');
    }
}