<?php
/**
 * Plugin Name: cron script.php
 * Description: Script to manage donation capacity in Gravity Forms database, automatically run via cron job.
 * Version: 1.0.0
 * Author: John Ellis - NearNorthAnalytics
 * 
 */

// Absolute path to wp-load.php
$wp_load_path = '/home/u11-yeseazmrmjgv/www/furniturebank.org/public_html/wp-load.php';

// Log file path
$log_file = '/home/u11-yeseazmrmjgv/www/furniturebank.org/public_html/wp-content/plugins/FB-donation-capacity-manager/debug.log';

// Logging function with error reporting
function custom_log($message) {
    global $log_file;
    $full_message = date('[Y-m-d H:i:s] ') . $message . "\n";
    
    // Log to file
    file_put_contents($log_file, $full_message, FILE_APPEND);
    
    // Also output to screen for CLI debugging
    echo $full_message;
}

try {
    // Detailed file existence check
    if (!file_exists($wp_load_path)) {
        throw new Exception("wp-load.php not found at $wp_load_path");
    }

    // Verify file is readable
    if (!is_readable($wp_load_path)) {
        throw new Exception("wp-load.php is not readable");
    }

    // Explicitly define ABSPATH if not already defined
    if (!defined('ABSPATH')) {
        define('ABSPATH', dirname($wp_load_path) . '/');
    }

    require_once($wp_load_path);

    // Verify database connection
    global $wpdb;
    if (!$wpdb) {
        throw new Exception("Database connection failed");
    }

    custom_log("Database Connected. Prefix: " . $wpdb->prefix);

    function dcm_manage_capacity() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'donation_capacity';
        
        // Additional table existence check
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            throw new Exception("Table $table_name does not exist");
        }
        
        custom_log("Starting donation capacity management for table: $table_name");
        
        $earliest_date = date('Y-m-d', strtotime('-7 days'));
        $latest_date = date('Y-m-d', strtotime('+54 days'));
        custom_log("Date range: $earliest_date to $latest_date");
        
        // Detailed deletion logging
        $delete_result = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE pickup_date < %s OR pickup_date > %s", 
            $earliest_date, 
            $latest_date
        ));
        custom_log("Deleted records result: " . ($delete_result === false ? 'Failed' : $delete_result));
        
        $existing_records = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        custom_log("Existing records before insertion: $existing_records");
        
        $insertion_count = 0;
        while ($existing_records < 62) {
            $latest_existing_date = $wpdb->get_var("SELECT MAX(pickup_date) FROM $table_name");
            
            $new_date = is_null($latest_existing_date) 
                ? $earliest_date 
                : date('Y-m-d', strtotime($latest_existing_date . ' +1 day'));
            
            if (strtotime($new_date) > strtotime($latest_date)) {
                custom_log("Reached latest allowed date");
                break;
            }
            
            $insert_result = $wpdb->insert($table_name, [
                'pickup_date' => $new_date,
                'total_capacity' => 1000,
                'booked_capacity' => 0
            ]);
            
            if ($insert_result === false) {
                custom_log("Insertion failed for date: $new_date. MySQL Error: " . $wpdb->last_error);
                break;
            }
            
            $insertion_count++;
            $existing_records++;
        }
        
        custom_log("Insertion process complete. Records inserted: $insertion_count");
        custom_log("Final record count: $existing_records");
        
        return true;
    }

    // Execute and log final result
    $result = dcm_manage_capacity();
    custom_log("Script execution result: " . ($result ? 'Success' : 'Failure'));

} catch (Exception $e) {
    custom_log("Critical Error: " . $e->getMessage());
}
?>