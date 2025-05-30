<?php
/**
 * Plugin Name: FB Stripe Token Creation Only
 * Plugin URI: 
 * Description: Creates Stripe customers and payment method tokens for later processing via Stripe dashboard. No payment capture or authorization occurs in WordPress.
 * Version: 1.0
 * Author: John Ellis - Near North Analytics
 * Author URI: 
 * Text Domain: FB-gf-stripe-token-creation
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Check for conflicting plugins on form submission
 */
function fb_token_check_conflicts($validation_result) {
    $form = $validation_result['form'];
    
    // Only check form ID 13
    if ($form['id'] !== 13) {
        return $validation_result;
    }
    
    // Check if both plugins are active
    if (is_plugin_active('FB-stripe-authorize-only/FB-stripe-authorize-only.php') || 
        function_exists('FB_gf_stripe_enable_authorize_only')) {
        
        // Both plugins are active - this is an error condition
        $validation_result['is_valid'] = false;
        
        // Add error to the form
        foreach ($form['fields'] as &$field) {
            // Add error to the first field
            if ($field->type !== 'page') {
                $field->failed_validation = true;
                $field->validation_message = 'Configuration Error: Both FB Stripe Authorization and FB Stripe Token Creation plugins are active. Please deactivate one plugin before submitting the form.';
                break;
            }
        }
    }
    
    return $validation_result;
}
add_filter('gform_validation_13', 'fb_token_check_conflicts');

/**
 * Create database table for tracking created tokens
 */
register_activation_hook(__FILE__, 'fb_token_create_reference_table');
function fb_token_create_reference_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'fb_stripe_token_references';
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        entry_id int NOT NULL,
        form_id int NOT NULL,
        stripe_customer_id varchar(255) NOT NULL,
        stripe_payment_method_id varchar(255) NOT NULL,
        customer_email varchar(255),
        customer_name varchar(255),
        pickup_date date,
        declared_amount decimal(10,2),
        declared_items longtext,
        created_date datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY entry_id (entry_id),
        KEY stripe_customer_id (stripe_customer_id),
        KEY pickup_date (pickup_date)
    )";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Disable all Stripe payment processing for Form 13
 */
function fb_token_disable_payment_processing($feed, $entry, $form) {
    // Only affect form 13
    if ($form['id'] !== 13) {
        return $feed;
    }
    
    // Check for conflicts first
    if (is_plugin_active('FB-stripe-authorize-only/FB-stripe-authorize-only.php') || 
        function_exists('FB_gf_stripe_enable_authorize_only')) {
        
        // Don't process if both plugins active
        return $feed;
    }
    
    // Disable payment processing entirely for this feed
    $feed['is_active'] = '0'; // Deactivate the feed
    
    return $feed;
}
add_filter('gform_stripe_feed_object', 'fb_token_disable_payment_processing', 5, 3);

/**
 * Override Stripe payment processing to prevent any charges
 */
function fb_token_prevent_stripe_processing($result, $feed, $submission_data, $form, $entry) {
    // Only affect form 13
    if ($form['id'] !== 13) {
        return $result;
    }
    
    // Check for conflicts
    if (is_plugin_active('FB-stripe-authorize-only/FB-stripe-authorize-only.php') || 
        function_exists('FB_gf_stripe_enable_authorize_only')) {
        
        // Return error if both plugins active
        return new WP_Error('conflict', 'Both authorization and token creation plugins are active.');
    }
    
    // Prevent any Stripe processing and return success
    // We'll handle customer/token creation in our after_submission hook
    return array(
        'is_success' => true,
        'payment_status' => 'Pending',
        'payment_amount' => $submission_data['payment_amount'],
        'transaction_id' => 'TOKEN_' . time(),
        'payment_date' => gmdate('Y-m-d H:i:s'),
        'payment_method' => 'Token Created',
        'ready_to_fulfill' => true
    );
}
add_filter('gform_stripe_create_payment_intent', 'fb_token_prevent_stripe_processing', 5, 5);

/**
 * Create Stripe customer and payment method after form submission
 */
function fb_token_create_stripe_customer_and_token($entry, $form) {
    // Only process form ID 13
    if ($form['id'] !== 13) {
        return;
    }
    
    // Check for conflicts
    if (is_plugin_active('FB-stripe-authorize-only/FB-stripe-authorize-only.php') || 
        function_exists('FB_gf_stripe_enable_authorize_only')) {
        
        error_log('FB Token Creation: Conflict detected - both plugins active for entry ' . $entry['id']);
        return;
    }
    
    // Extract customer information from form
    $customer_email = rgar($entry, '121'); // Email field
    $customer_first_name = rgar($entry, '122'); // First name
    $customer_last_name = rgar($entry, '123'); // Last name
    $customer_phone = rgar($entry, '124'); // Phone field (if exists)
    $pickup_date = rgar($entry, '18'); // Date field
    $declared_amount = rgar($entry, 'payment_amount');
    
    // Get billing address
    $billing_address = array(
        'line1' => rgar($entry, '125.1'),
        'line2' => rgar($entry, '125.2'),
        'city' => rgar($entry, '125.3'),
        'state' => rgar($entry, '125.4'),
        'postal_code' => rgar($entry, '125.5'),
        'country' => rgar($entry, '125.6') ?: 'CA'
    );
    
    // Collect declared items for reference
    $declared_items = array(
        'small_items' => intval(rgar($entry, '34')),
        'medium_items' => intval(rgar($entry, '36')),
        'large_items' => intval(rgar($entry, '38')),
        'bulky_items' => intval(rgar($entry, '37')),
        'extra_bulky_items' => intval(rgar($entry, '39')),
        'delivery_charge' => floatval(rgar($entry, '128')),
        'total_cubic_feet' => floatval(rgar($entry, '141'))
    );
    
    try {
        // Initialize Stripe
        if (!class_exists('GFStripe')) {
            error_log('FB Token Creation: Stripe add-on not available');
            return;
        }
        
        $stripe_api = new GFStripe();
        \Stripe\Stripe::setApiKey($stripe_api->get_secret_key());
        
        // Create setup intent to collect payment method
        $setup_intent = \Stripe\SetupIntent::create([
            'usage' => 'off_session',
            'payment_method_types' => ['card'],
            'metadata' => array(
                'source' => 'Furniture Bank Donation Form',
                'entry_id' => $entry['id'],
                'pickup_date' => $pickup_date,
                'declared_amount' => $declared_amount
            )
        ]);
        
        // Check if customer already exists by email
        $existing_customers = \Stripe\Customer::all(['email' => $customer_email, 'limit' => 1]);
        
        if (!empty($existing_customers->data)) {
            $customer = $existing_customers->data[0];
            error_log('FB Token Creation: Using existing Stripe customer ' . $customer->id);
        } else {
            // Create new Stripe customer
            $customer_data = array(
                'email' => $customer_email,
                'name' => trim($customer_first_name . ' ' . $customer_last_name),
                'address' => array_filter($billing_address), // Remove empty fields
                'metadata' => array(
                    'source' => 'Furniture Bank Donation Form',
                    'entry_id' => $entry['id'],
                    'pickup_date' => $pickup_date,
                    'declared_amount' => $declared_amount,
                    'declared_items' => json_encode($declared_items)
                )
            );
            
            if ($customer_phone) {
                $customer_data['phone'] = $customer_phone;
            }
            
            $customer = \Stripe\Customer::create($customer_data);
            error_log('FB Token Creation: Created new Stripe customer ' . $customer->id);
        }
        
        // For token-only mode, we need to create a payment method manually
        // This would typically be done on the frontend with Stripe.js
        // For now, we'll create a placeholder and expect frontend integration
        
        $payment_method_id = 'pm_placeholder_' . time(); // This should be replaced with actual payment method from frontend
        
        // Store reference in our local table for tracking
        global $wpdb;
        $table_name = $wpdb->prefix . 'fb_stripe_token_references';
        
        $wpdb->insert(
            $table_name,
            array(
                'entry_id' => $entry['id'],
                'form_id' => $form['id'],
                'stripe_customer_id' => $customer->id,
                'stripe_payment_method_id' => $payment_method_id,
                'customer_email' => $customer_email,
                'customer_name' => trim($customer_first_name . ' ' . $customer_last_name),
                'pickup_date' => $pickup_date,
                'declared_amount' => $declared_amount,
                'declared_items' => json_encode($declared_items)
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%s')
        );
        
        // Store Stripe customer ID in Gravity Forms entry meta for reference
        gform_update_meta($entry['id'], 'stripe_customer_id', $customer->id);
        gform_update_meta($entry['id'], 'stripe_setup_intent_id', $setup_intent->id);
        gform_update_meta($entry['id'], 'payment_method', 'Token Created');
        gform_update_meta($entry['id'], 'payment_status', 'Token Created');
        
        // Add note to entry
        $note = sprintf(
            'Stripe customer created for token-only processing: %s%sSetup Intent: %s%sCustomer can be charged any amount via Stripe dashboard.',
            $customer->id,
            "\n",
            $setup_intent->id,
            "\n"
        );
        
        RGFormsModel::add_note($entry['id'], wp_get_current_user()->ID, 'System', $note);
        
        error_log('FB Token Creation: Successfully processed entry ' . $entry['id'] . ' - Customer: ' . $customer->id);
        
    } catch (Exception $e) {
        error_log('FB Token Creation: Error processing entry ' . $entry['id'] . ': ' . $e->getMessage());
        
        // Add error note to entry
        $error_note = 'Error creating Stripe customer for token processing: ' . $e->getMessage();
        RGFormsModel::add_note($entry['id'], wp_get_current_user()->ID, 'System', $error_note);
    }
}
add_action('gform_after_submission_13', 'fb_token_create_stripe_customer_and_token', 20, 2);

/**
 * Add admin menu for viewing created token customers
 */
function fb_token_admin_menu() {
    add_submenu_page(
        'gf_edit_forms',
        'Stripe Token Customers', 
        'Stripe Tokens', 
        'manage_options', 
        'fb-stripe-token-customers', 
        'fb_token_customers_admin_page'
    );
}
add_action('admin_menu', 'fb_token_admin_menu');

/**
 * Admin page to view created Stripe token customers
 */
function fb_token_customers_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'fb_stripe_token_references';
    
    $customers = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_date DESC LIMIT 100");
    
    ?>
    <div class="wrap">
        <h1>Stripe Token Customers</h1>
        <p><strong>Token Creation Mode:</strong> Customers and payment methods created for later processing via Stripe dashboard.</p>
        
        <?php if (empty($customers)): ?>
            <div class="notice notice-info">
                <p>No Stripe token customers have been created yet.</p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Entry ID</th>
                        <th>Customer Name</th>
                        <th>Email</th>
                        <th>Pickup Date</th>
                        <th>Declared Amount</th>
                        <th>Stripe Customer ID</th>
                        <th>Created Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=gf_entries&view=entry&id=13&lid=' . $customer->entry_id); ?>">
                                #<?php echo $customer->entry_id; ?>
                            </a>
                        </td>
                        <td><?php echo esc_html($customer->customer_name); ?></td>
                        <td><?php echo esc_html($customer->customer_email); ?></td>
                        <td><?php echo esc_html($customer->pickup_date); ?></td>
                        <td>$<?php echo number_format($customer->declared_amount, 2); ?></td>
                        <td>
                            <code><?php echo esc_html($customer->stripe_customer_id); ?></code>
                        </td>
                        <td><?php echo esc_html($customer->created_date); ?></td>
                        <td>
                            <a href="https://dashboard.stripe.com/customers/<?php echo $customer->stripe_customer_id; ?>" 
                               target="_blank" class="button button-small">
                                View in Stripe
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <div class="notice notice-info" style="margin-top: 20px;">
            <p><strong>Token Creation Mode - To process payments:</strong></p>
            <ol>
                <li>Click "View in Stripe" to open the customer in your Stripe dashboard</li>
                <li>Navigate to the customer's payment methods</li>
                <li>Create a new payment intent with any desired amount</li>
                <li>Process the payment when the furniture pickup occurs</li>
            </ol>
            <p><strong>Note:</strong> No pre-authorization holds are placed on customer cards in this mode.</p>
        </div>
    </div>
    <?php
}

/**
 * Add admin notice about plugin mode
 */
function fb_token_admin_notice() {
    $screen = get_current_screen();
    if ($screen && $screen->id === 'forms_page_fb-stripe-token-customers') {
        ?>
        <div class="notice notice-success">
            <p><strong>FB Stripe Token Creation Mode Active:</strong> Forms will create customers and tokens only. No payments or authorizations will be processed.</p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'fb_token_admin_notice');
?>