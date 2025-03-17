<?php
/*
Plugin Name: FB Donation Capacity Manager
Author: John Ellis - NearNorthAnalytics
Description: Manages pickup capacity for donations
Version: 1.0
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
    
    // Handle form submission for new capacity
    if (isset($_POST['submit_capacity'])) {
        $new_date = sanitize_text_field($_POST['pickup_date']);
        
        // Check if date already exists
        $existing_date = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE pickup_date = %s",
            $new_date
        ));
        
        if ($existing_date > 0) {
            echo '<div class="error"><p>Error: A capacity entry already exists for ' . esc_html($new_date) . '. Please edit the existing entry instead.</p></div>';
        } else {
            $wpdb->insert($table_name, array(
                'pickup_date' => $new_date,
                'total_capacity' => intval($_POST['total_capacity'])
            ));
            echo '<div class="updated"><p>New capacity entry added successfully.</p></div>';
        }
    }
    
    // Handle capacity updates
    if (isset($_POST['update_capacity'])) {
        $id = intval($_POST['entry_id']);
        $new_capacity = intval($_POST['new_capacity']);
        $booked_capacity = intval($_POST['booked_capacity']);
        
        // Validate new capacity against booked capacity
        if ($new_capacity >= $booked_capacity) {
            $wpdb->update(
                $table_name,
                array('total_capacity' => $new_capacity),
                array('id' => $id)
            );
            echo '<div class="updated"><p>Capacity updated successfully.</p></div>';
        } else {
            echo '<div class="error"><p>Error: New capacity cannot be less than booked capacity.</p></div>';
        }
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
                <tr id="entry-<?php echo $entry->id; ?>">
                    <td><?php echo esc_html($entry->pickup_date); ?></td>
                    <td class="capacity-cell">
                        <span class="capacity-display"><?php echo esc_html($entry->total_capacity); ?></span>
                        <form class="capacity-edit-form" style="display: none;" method="post">
                            <input type="hidden" name="entry_id" value="<?php echo $entry->id; ?>">
                            <input type="hidden" name="booked_capacity" value="<?php echo $entry->booked_capacity; ?>">
                            <input type="number" name="new_capacity" value="<?php echo $entry->total_capacity; ?>" min="<?php echo $entry->booked_capacity; ?>" required>
                            <button type="submit" name="update_capacity" class="button button-small">Save</button>
                            <button type="button" class="button button-small cancel-edit">Cancel</button>
                        </form>
                    </td>
                    <td><?php echo esc_html($entry->booked_capacity); ?></td>
                    <td class="available-capacity"><?php echo esc_html($entry->total_capacity - $entry->booked_capacity); ?></td>
                    <td>
                        <button type="button" class="button button-small edit-capacity">Edit</button>
                        <a href="?page=donation-capacity&delete=<?php echo $entry->id; ?>" 
                           onclick="return confirm('Are you sure?')" 
                           class="button button-small">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Edit button click handler
        $('.edit-capacity').click(function() {
            var row = $(this).closest('tr');
            row.find('.capacity-display').hide();
            row.find('.capacity-edit-form').show();
            $(this).hide();
        });

        // Cancel button click handler
        $('.cancel-edit').click(function() {
            var row = $(this).closest('tr');
            row.find('.capacity-display').show();
            row.find('.capacity-edit-form').hide();
            row.find('.edit-capacity').show();
        });

        // Form submission handler
        $('.capacity-edit-form').submit(function() {
            var newCapacity = parseInt($(this).find('input[name="new_capacity"]').val());
            var bookedCapacity = parseInt($(this).find('input[name="booked_capacity"]').val());
            
            if (newCapacity < bookedCapacity) {
                alert('New capacity cannot be less than booked capacity');
                return false;
            }
            return true;
        });
    });
    </script>
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
            background: #ff0000;
            color: #ccc;
        }
.ui-datepicker-prev .ui-icon,
.ui-datepicker-next .ui-icon {
    background-image: none;
    text-indent: 0;
    width: auto;
    height: auto;
    font-size: 16px;
    color: #555;
}

.ui-datepicker-prev .ui-icon:before {
    content: '←';
}

.ui-datepicker-next .ui-icon:after {
    content: '→';
}

.ui-datepicker-next {
    text-align: right; /* Align text to the right */
}

.ui-datepicker-next .ui-icon {
    float: right; /* Float the icon to the right */
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
                            // Get today's date without time
                            var today = new Date();
                            today.setHours(0, 0, 0, 0);
                            
                            // If date is today or earlier, disable it
                            if (date <= today) {
                                return [false, ''];
                            }
                            
                            var dateString = $.datepicker.formatDate('yy-mm-dd', date);
                            return [response.dates.includes(dateString), ''];
                        },
                        minDate: '+1d', // Start from tomorrow
                        dateFormat: 'yy-mm-dd',
                        showOtherMonths: true,
                        selectOtherMonths: true
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