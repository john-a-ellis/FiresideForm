<?php
/**
 * Plugin Name: FB Donation Capacity Manager
 * Description: Manages pickup capacity for furniture donations by date, controls available delivery slots, and integrates with Gravity Forms
 * Author: John Ellis - NearNorthAnalytics
 * Version: 1.3
 * 
 * This plugin creates a system to manage donation pickup capacity:
 * - Creates a custom database table to track pickup dates and their capacity
 * - Provides an admin interface to manage available capacity
 * - Integrates with Gravity Forms to allow users to select only available dates
 * - Updates capacity records when forms are submitted
 * - Enhanced validation to prevent manual entry of blocked dates
 */

/**
 * Creates the donation capacity database table on plugin activation.
 * Table stores pickup dates, total capacity, and booked capacity.
 */
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

/**
 * Adds the Donation Capacity admin menu page.
 */
function dcm_admin_menu() {
    add_menu_page(
        'Donation Capacity', 
        'Donation Capacity', 
        'manage_options', 
        'donation-capacity', 
        'dcm_admin_page', 
        'dashicons-calendar-alt'
    );
}
add_action('admin_menu', 'dcm_admin_menu');

/**
 * Renders the admin interface for managing pickup capacity.
 * Allows adding, editing, and deleting capacity entries.
 */
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
        $id = intval($_GET['delete']);
        $wpdb->delete($table_name, array('id' => $id));
        echo '<div class="updated"><p>Capacity entry deleted successfully.</p></div>';
    }
    
    // Get existing entries
    $entries = $wpdb->get_results("SELECT * FROM $table_name ORDER BY pickup_date");
    
    ?>
    <div class="wrap">
        <h1><span class="dashicons dashicons-calendar-alt" style="font-size: 30px; height: 30px; padding-right: 10px;"></span> Donation Pickup Capacity Management</h1>
        <p>Use this interface to manage the available capacity for furniture donation pickups by date.</p>
        
        <!-- Add new capacity form -->
        <div class="postbox">
            <h2 class="hndle" style="padding: 10px;">Add New Capacity Date</h2>
            <div class="inside">
                <form method="post" class="form-table">
                    <table>
                        <tr>
                            <th><label for="pickup_date">Pickup Date</label></th>
                            <td><input type="date" name="pickup_date" required></td>
                        </tr>
                        <tr>
                            <th><label for="total_capacity">Total Capacity (cu ft)</label></th>
                            <td><input type="number" name="total_capacity" min="0" required></td>
                        </tr>
                    </table>
                    <p><input type="submit" name="submit_capacity" class="button button-primary" value="Add Capacity"></p>
                </form>
            </div>
        </div>
        
        <!-- Display existing entries -->
        <h2>Existing Capacity Settings</h2>
        <p>The table below shows all currently configured pickup dates. You can edit the total capacity or delete dates as needed.</p>
        
        <?php if (empty($entries)): ?>
            <div class="notice notice-warning">
                <p>No capacity entries found. Add your first capacity entry using the form above.</p>
            </div>
        <?php else: ?>
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
                               onclick="return confirm('Are you sure you want to delete this capacity entry? This cannot be undone.')" 
                               class="button button-small">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
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

        // Form submission handler with validation
        $('.capacity-edit-form').submit(function() {
            var newCapacity = parseInt($(this).find('input[name="new_capacity"]').val());
            var bookedCapacity = parseInt($(this).find('input[name="booked_capacity"]').val());
            
            if (newCapacity < bookedCapacity) {
                alert('New capacity cannot be less than booked capacity (' + bookedCapacity + ')');
                return false;
            }
            return true;
        });
    });
    </script>
    <?php
}

/**
 * Server-side validation to prevent submission with invalid dates
 * This runs when the form is submitted to validate the manually entered date
 * 
 * @param array $validation_result Array containing form validation data with keys:
 *                                - 'is_valid' (bool): Overall form validation status
 *                                - 'form' (array): Complete form configuration and field data
 * @return array Modified validation result with updated validation status and field errors
 */
function dcm_validate_pickup_date($validation_result) {
    $form = $validation_result['form'];
    
    // Only validate form 13
    if ($form['id'] !== 13) {
        return $validation_result;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'donation_capacity';
    
    // Get the submitted values
    $pickup_date = rgpost('input_18');
    $required_capacity = intval(rgpost('input_141'));
    
    if (empty($pickup_date) || $required_capacity <= 0) {
        return $validation_result; // Skip validation if no date or capacity
    }
    
    // Validate minimum lead time based on day of week
    $today = new DateTime();
    $selected_date = new DateTime($pickup_date);
    $day_of_week = intval($today->format('w')); // 0 = Sunday, 6 = Saturday
    
    // Calculate minimum allowed date
    $min_days_ahead = ($day_of_week === 5 || $day_of_week === 6) ? 6 : 5;
    $min_date = clone $today;
    $min_date->add(new DateInterval('P' . $min_days_ahead . 'D'));
    
    // Check if selected date meets minimum lead time
    if ($selected_date < $min_date) {
        $validation_result['is_valid'] = false;
        
        // Add error to the date field
        foreach ($form['fields'] as &$field) {
            if ($field->id === 18) {
                $field->failed_validation = true;
                $field->validation_message = sprintf(
                    'Please select a pickup date at least %d days in advance. The earliest available date is %s.',
                    $min_days_ahead,
                    $min_date->format('Y-m-d')
                );
                break;
            }
        }
        return $validation_result;
    }
    
    // Check if date has sufficient capacity
    $available_capacity = $wpdb->get_var($wpdb->prepare(
        "SELECT (total_capacity - booked_capacity) 
        FROM $table_name 
        WHERE pickup_date = %s",
        $pickup_date
    ));
    
    if ($available_capacity === null) {
        // Date doesn't exist in capacity table
        $validation_result['is_valid'] = false;
        
        foreach ($form['fields'] as &$field) {
            if ($field->id === 18) {
                $field->failed_validation = true;
                $field->validation_message = 'The selected pickup date is not available. Please choose a different date from the calendar.';
                break;
            }
        }
    } elseif ($available_capacity < $required_capacity) {
        // Insufficient capacity
        $validation_result['is_valid'] = false;
        
        foreach ($form['fields'] as &$field) {
            if ($field->id === 18) {
                $field->failed_validation = true;
                $field->validation_message = sprintf(
                    'The selected date does not have sufficient capacity (%d cubic feet available, %d required). Please choose a different date.',
                    $available_capacity,
                    $required_capacity
                );
                break;
            }
        }
    }
    
    return $validation_result;
}
add_filter('gform_validation_13', 'dcm_validate_pickup_date');

/**
 * Enhanced client-side validation and restrictions
 * This replaces the existing dcm_modify_date_field function
 * 
 * @param string $content The HTML content of the field to be filtered
 * @param object $field The GF_Field object containing field configuration and properties
 * @param string $value The current value of the field (may be empty for new entries)
 * @param int $entry_id The entry ID (0 for new entries, positive integer for existing entries)
 * @param int $form_id The form ID that contains this field
 * @return string Modified field content with enhanced validation JavaScript and CSS
 */
function dcm_modify_date_field($content, $field, $value, $entry_id, $form_id) {
    // Only modify the specific date field in form 13
    if ($form_id !== 13 || $field->id !== 18) {
        return $content;
    }

    $ajax_url = admin_url('admin-ajax.php');
    
    // Enhanced styles and JavaScript for the datepicker with manual entry prevention
    $script = "
    <style>
        /* Datepicker styling */
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
            text-align: right;
        }
        .ui-datepicker-next .ui-icon {
            float: right;
        }
        
        /* Error styling for invalid dates */
        .gfield_error .ginput_container input {
            border-color: #c42d2d;
        }
        .validation_error {
            color: #c42d2d;
            font-size: 12px;
            margin-top: 5px;
        }
    </style>
    <script type='text/javascript'>
    jQuery(document).ready(function($) {
        var capacityField = $('#input_13_141');
        var dateField = $('#input_13_18');
        var availableDates = [];
        var minLeadTime = 5; // Default minimum lead time
        
        /**
         * Calculate minimum allowed date based on current day of week
         */
        function getMinimumDate() {
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            var dayOfWeek = today.getDay();
            
            // If today is Friday (5) or Saturday (6), require 6+ days ahead
            var daysAhead = (dayOfWeek === 5 || dayOfWeek === 6) ? 6 : 5;
            minLeadTime = daysAhead;
            
            var minDate = new Date(today);
            minDate.setDate(today.getDate() + daysAhead);
            return minDate;
        }
        
        /**
         * Validate manually entered date
         */
        function validateManualDate(dateString) {
            if (!dateString) return { valid: true };
            
            // Check date format
            var dateRegex = /^\d{4}-\d{2}-\d{2}$/;
            if (!dateRegex.test(dateString)) {
                return { 
                    valid: false, 
                    message: 'Please enter date in YYYY-MM-DD format or use the calendar picker.' 
                };
            }
            
            var enteredDate = new Date(dateString);
            var minDate = getMinimumDate();
            
            // Check minimum lead time
            if (enteredDate < minDate) {
                return { 
                    valid: false, 
                    message: 'Please select a pickup date at least ' + minLeadTime + ' days in advance.' 
                };
            }
            
            // Check if date is in available dates list
            if (availableDates.length > 0 && !availableDates.includes(dateString)) {
                return { 
                    valid: false, 
                    message: 'The selected date is not available. Please choose from the calendar.' 
                };
            }
            
            return { valid: true };
        }
        
        /**
         * Display validation error
         */
        function showValidationError(message) {
            var fieldContainer = dateField.closest('.gfield');
            fieldContainer.addClass('gfield_error');
            
            // Remove existing error message
            fieldContainer.find('.validation_error').remove();
            
            // Add new error message
            if (message) {
                var errorDiv = $('<div class=\"validation_error\">' + message + '</div>');
                dateField.parent().append(errorDiv);
            }
        }
        
        /**
         * Clear validation error
         */
        function clearValidationError() {
            var fieldContainer = dateField.closest('.gfield');
            fieldContainer.removeClass('gfield_error');
            fieldContainer.find('.validation_error').remove();
        }
        
        /**
         * Updates the datepicker to show only dates with available capacity.
         */
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
                    availableDates = response.dates;
                    
                    if (dateField.hasClass('hasDatepicker')) {
                        dateField.datepicker('destroy');
                    }
                    
                    dateField.datepicker({
                        beforeShowDay: function(date) {
                            var minDate = getMinimumDate();
                            
                            // If date is earlier than minimum allowed date, disable it
                            if (date < minDate) {
                                return [false, 'unavailable', 'Date too soon'];
                            }
                            
                            // Check if date is in our list of available dates
                            var dateString = $.datepicker.formatDate('yy-mm-dd', date);
                            var isAvailable = availableDates.includes(dateString);
                            return [isAvailable, isAvailable ? 'available' : 'unavailable', 
                                   isAvailable ? 'Available' : 'Not available'];
                        },
                        dateFormat: 'yy-mm-dd',
                        showOtherMonths: true,
                        selectOtherMonths: true,
                        onSelect: function(dateText) {
                            // Clear any validation errors when a valid date is selected from picker
                            clearValidationError();
                        }
                    });
                    
                    // Validate current value if one exists
                    var currentValue = dateField.val();
                    if (currentValue) {
                        var validation = validateManualDate(currentValue);
                        if (!validation.valid) {
                            showValidationError(validation.message);
                        } else {
                            clearValidationError();
                        }
                    }
                }
            });
        }
        
        // Validate on manual entry (blur and change events)
        dateField.on('blur change', function() {
            var dateValue = $(this).val();
            var validation = validateManualDate(dateValue);
            
            if (!validation.valid) {
                showValidationError(validation.message);
            } else {
                clearValidationError();
            }
        });
        
        // Prevent form submission if date is invalid
        $('#gform_13').on('submit', function(e) {
            var dateValue = dateField.val();
            var validation = validateManualDate(dateValue);
            
            if (!validation.valid) {
                e.preventDefault();
                showValidationError(validation.message);
                
                // Scroll to the date field
                $('html, body').animate({
                    scrollTop: dateField.offset().top - 100
                }, 500);
                
                return false;
            }
        });
        
        // Real-time validation as user types (with debouncing)
        var typingTimer;
        dateField.on('keyup', function() {
            clearTimeout(typingTimer);
            typingTimer = setTimeout(function() {
                var dateValue = dateField.val();
                if (dateValue.length >= 10) { // Full date entered
                    var validation = validateManualDate(dateValue);
                    if (!validation.valid) {
                        showValidationError(validation.message);
                    } else {
                        clearValidationError();
                    }
                }
            }, 500);
        });
        
        // Trigger date update when capacity field changes
        capacityField.on('change keyup', function() {
            updateAvailableDates();
            
            // Re-validate current date with new capacity
            var currentValue = dateField.val();
            if (currentValue) {
                setTimeout(function() {
                    var validation = validateManualDate(currentValue);
                    if (!validation.valid) {
                        showValidationError(validation.message);
                    } else {
                        clearValidationError();
                    }
                }, 100);
            }
        });
        
        // Initial load if capacity already has a value
        if (capacityField.val()) {
            updateAvailableDates();
        }
    });
    </script>";
    
    return $content . $script;
}
add_filter('gform_field_content', 'dcm_modify_date_field', 10, 5);

/**
 * AJAX handler that returns dates with sufficient available capacity.
 * Used by the datepicker to show only valid dates for selection.
 */
function dcm_get_available_dates() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'donation_capacity';
    $required_capacity = intval($_POST['capacity']);
    
    // Get dates with sufficient remaining capacity
    $available_dates = $wpdb->get_col($wpdb->prepare(
        "SELECT pickup_date 
        FROM $table_name 
        WHERE (total_capacity - booked_capacity) >= %d 
        AND pickup_date >= CURDATE()",
        $required_capacity
    ));
    
    wp_send_json(['dates' => $available_dates]);
}
add_action('wp_ajax_get_available_dates', 'dcm_get_available_dates');
add_action('wp_ajax_nopriv_get_available_dates', 'dcm_get_available_dates');

/**
 * Updates capacity records when a form is submitted.
 * Reduces available capacity for the selected date.
 * 
 * @param array $entry The form entry data
 * @param array $form The form data
 */
function dcm_update_capacity($entry, $form) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'donation_capacity';
    
    // Get the selected date and required capacity from form submission
    $pickup_date = rgar($entry, '18');
    $required_capacity = intval(rgar($entry, '141'));
    
    if (empty($pickup_date) || $required_capacity <= 0) {
        return; // Skip if we don't have valid data
    }
    
    // Update the database to increase booked capacity
    $result = $wpdb->query($wpdb->prepare(
        "UPDATE $table_name 
        SET booked_capacity = booked_capacity + %d 
        WHERE pickup_date = %s",
        $required_capacity,
        $pickup_date
    ));
    
    // Log errors if the update fails
    if ($result === false) {
        error_log(sprintf(
            'Failed to update capacity for date %s with capacity %d: %s',
            $pickup_date,
            $required_capacity,
            $wpdb->last_error
        ));
    }
}
add_action('gform_after_submission_13', 'dcm_update_capacity', 10, 2);
?>