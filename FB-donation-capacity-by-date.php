<?php
/*
Plugin Name: Donation Capacity Manager
Description: Manages pickup capacity for donations
Created by: John Ellis - NearNorthAnalytics 
Version: 1.0
Requires at least: 6.7
Requires PHP: 7.4
*/

// Database table creation remains the same as it uses core WordPress functions
register_activation_hook(__FILE__, 'dcm_create_db_table');
function dcm_create_db_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'donation_capacity';
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        pickup_date date NOT NULL,
        total_capacity int NOT NULL,
        booked_capacity int DEFAULT 0,
        PRIMARY KEY (id)
    )";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
function dcm_admin_menu() {
    add_menu_page('Donation Capacity', 'Donation Capacity', 'manage_options', 'donation-capacity', 'dcm_admin_page');
}
// Add Admin interface
add_action('admin_menu', 'dcm_admin_menu');
function dcm_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'donation_capacity';
    
    // Handle form submission
    if (isset($_POST['submit_capacity'])) {
        $wpdb->insert($table_name, array(
            'pickup_date' => sanitize_text_field($_POST['pickup_date']),
            'total_capacity' => intval($_POST['total_capacity'])
        ));
    }
    
    // Handle deletion
    if (isset($_GET['delete'])) {
        $wpdb->delete($table_name, array('id' => intval($_GET['delete'])));
    }
    
    // Get existing entries
    $entries = $wpdb->get_results("SELECT * FROM $table_name ORDER BY pickup_date");
    
    ?>
    <div class="wrap">
        <h2>Set Daily Pickup Capacity</h2>
        
        <!-- Add new capacity form -->
        <form method="post" class="form-table">
            <table>
                <tr>
                    <th><label for="pickup_date">Pickup Date</label></th>
                    <td><input type="date" name="pickup_date" required></td>
                </tr>
                <tr>
                    <th><label for="total_capacity">Total Capacity (cu ft)</label></th>
                    <td><input type="number" name="total_capacity" required></td>
                </tr>
            </table>
            <p><input type="submit" name="submit_capacity" class="button button-primary" value="Add Capacity"></p>
        </form>
        
        <!-- Display existing entries -->
        <h3>Existing Capacity Settings</h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Total Capacity</th>
                    <th>Booked Capacity</th>
                    <th>Available Capacity</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $entry): ?>
                <tr>
                    <td><?php echo esc_html($entry->pickup_date); ?></td>
                    <td><?php echo esc_html($entry->total_capacity); ?></td>
                    <td><?php echo esc_html($entry->booked_capacity); ?></td>
                    <td><?php echo esc_html($entry->total_capacity - $entry->booked_capacity); ?></td>
                    <td>
                        <a href="?page=donation-capacity&delete=<?php echo $entry->id; ?>" 
                           onclick="return confirm('Are you sure?')" 
                           class="button button-small">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Updated Gravity Forms date field filter for form 13
add_filter('gform_field_content', 'dcm_modify_date_field', 10, 5);
function dcm_modify_date_field($content, $field, $value, $entry_id, $form_id) {
    if ($form_id !== 13 || $field->id !== 18) {
        return $content;
    }

    $ajax_url = admin_url('admin-ajax.php');
    
    $script = "
    <style>
        .ui-datepicker {
            background-color: #fff;
            border: 1px solid #ddd;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 10px;
        }
        .ui-datepicker-header {
            background: #f7f7f7;
            border-bottom: 1px solid #ddd;
            padding: 5px;
            margin: -10px -10px 10px;
        }
        .ui-datepicker-title {
            text-align: center;
            font-weight: bold;
        }
        .ui-datepicker-calendar th {
            padding: 5px;
            background: #f7f7f7;
        }
        .ui-datepicker-calendar td {
            padding: 2px;
            text-align: center;
        }
        .ui-datepicker-calendar .ui-state-default {
            display: block;
            padding: 5px;
            text-decoration: none;
            color: #333;
        }
        .ui-datepicker-calendar .ui-state-active {
            background: #007cba;
            color: #fff;
        }
        .ui-datepicker-calendar .ui-state-disabled {
            background: #f00000;
            color: #ccc;
        }
    </style>
    <script type='text/javascript'>
    jQuery(document).ready(function($) {
        var capacityField = $('#input_13_141');
        var dateField = $('#input_13_18');
        
        function updateAvailableDates() {
            var required_capacity = capacityField.val();
            if (!required_capacity) return;
            
            $.ajax({
                url: '{$ajax_url}',
                type: 'POST',
                data: {
                    action: 'get_available_dates',
                    capacity: required_capacity
                },
                success: function(response) {
                    if (dateField.hasClass('hasDatepicker')) {
                        dateField.datepicker('destroy');
                    }
                    
                    dateField.datepicker({
                        beforeShowDay: function(date) {
                            var dateString = $.datepicker.formatDate('yy-mm-dd', date);
                            return [response.dates.includes(dateString), ''];
                        },
                        minDate: 0,
                        dateFormat: 'yy-mm-dd'
                    });
                    
                    if (!response.dates.includes(dateField.val())) {
                        dateField.val('');
                    }
                }
            });
        }
        
        capacityField.on('change keyup', updateAvailableDates);
        
        if (capacityField.val()) {
            updateAvailableDates();
        }
    });
    </script>";
    
    return $content . $script;
}

// AJAX handler for getting available dates
add_action('wp_ajax_get_available_dates', 'dcm_get_available_dates');

add_action('wp_ajax_nopriv_get_available_dates', 'dcm_get_available_dates');
function dcm_get_available_dates() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'donation_capacity';
    $required_capacity = intval($_POST['capacity']);
    
    $available_dates = $wpdb->get_col($wpdb->prepare(
        "SELECT pickup_date 
        FROM $table_name 
        WHERE (total_capacity - booked_capacity) >= %d 
        AND pickup_date >= CURDATE()",
        $required_capacity
    ));
    
    wp_send_json(['dates' => $available_dates]);
}

// Updated form submission handler
add_action('gform_after_submission_13', 'dcm_update_capacity', 10, 2);
function dcm_update_capacity($entry, $form) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'donation_capacity';
    
    $pickup_date = rgar($entry, '18');
    $required_capacity = rgar($entry, '141');
    
    $wpdb->query($wpdb->prepare(
        "UPDATE $table_name 
        SET booked_capacity = booked_capacity + %d 
        WHERE pickup_date = %s",
        $required_capacity,
        $pickup_date
    ));
}