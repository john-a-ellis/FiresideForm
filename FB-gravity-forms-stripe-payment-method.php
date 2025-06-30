<?php
/**
 * Plugin Name: FB Gravity Forms Stripe Customer Creator
 * Plugin URI: 
 * Description: Creates Stripe customers and payment methods and authorizes payments for Form ID 13.
 * Version: 2.0
 * Author: John Ellis - Near North Analytics
 * Author URI: 
 * Text Domain: FB-gf-stripe-customer-creator
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Enable authorize-only mode while keeping setup_future_usage
 * This creates an authorization (not a payment) but still saves the payment method
 */
function FB_gf_stripe_enable_authorize_only($result, $form, $feed) {
    // Check if this is form ID 13
    if($form['id'] === 13) {
        // Get the feed name
        $feed_name = rgars($feed, 'meta/feedName');
        
        // Check if this is "Stripe Feed 1"
        if($feed_name === 'Stripe Feed 1') {
            return true; // Enable authorization only for this specific form and feed
        }
    }
    
    return $result; // Keep default behavior for all other forms/feeds
}

// Enable authorization for this form
add_filter('gform_stripe_payment_element_authorization_only', 'FB_gf_stripe_enable_authorize_only', 10, 3);

/**
 * Extract customer data from form entry
 */
function FB_extract_customer_data($entry, $form) {
    $customer_data = array();
    
    // Map form fields to customer data
    // You'll need to adjust these field IDs based on your actual form structure
    
    // Email (required for Stripe customer)
    $email = rgar($entry, '59'); // Email field
    if (empty($email)) {
        return false; // Email is required
    }
    $customer_data['email'] = $email;
    
    // Name fields
    $first_name = rgar($entry, '102'); // First name field
    $last_name = rgar($entry, '103');  // Last name field
    
    if (!empty($first_name) || !empty($last_name)) {
        $customer_data['name'] = trim($first_name . ' ' . $last_name);
    }
    
    // Phone
    $phone = rgar($entry, '53'); // Phone field
    if (!empty($phone)) {
        $customer_data['phone'] = $phone;
    }
    
    // Address - you may want to extract from your address fields
    $address_street = rgar($entry, '125.1'); // Based on your billing address field
    $address_city = rgar($entry, '125.3');
    $address_state = rgar($entry, '125.4');
    $address_zip = rgar($entry, '125.5');
    $address_country = rgar($entry, '125.6');
    
    if (!empty($address_street)) {
        $customer_data['address'] = array(
            'line1' => $address_street,
            'city' => $address_city,
            'state' => $address_state,
            'postal_code' => $address_zip,
            'country' => $address_country ?: 'CA' // Default to Canada
        );
    }
    
    // Add metadata for reference
    $customer_data['metadata'] = array(
        'gravity_forms_entry_id' => $entry['id'],
        'gravity_forms_form_id' => $entry['form_id'],
        'source' => 'Furniture Bank Donation Form'
    );
    
    return $customer_data;
}

/**
 * Set up payment intent for future usage with extended authorization
 * This must be set at the Payment Intent level, not just the charge level
 */
function FB_setup_payment_intent_for_future_use($intent_information, $feed, $form) {
    // Only process for Form ID 13
    if (rgar($form, 'id') != 13) {
        return $intent_information;
    }
    
    // Get the feed name to ensure we're working with the right feed
    $feed_name = rgars($feed, 'meta/feedName');
    if ($feed_name !== 'Stripe Feed 1') {
        return $intent_information;
    }
    
    // Log for debugging
    gf_stripe()->log_debug(__METHOD__ . '(): Adding setup_future_usage and extended authorization to payment intent for form 13');
    
    // Get the form total from field 135 and convert to cents
    $form_total = 0;
    if (isset($_POST['input_135'])) {
        $form_total_value = sanitize_text_field($_POST['input_135']);
        // Remove any currency symbols and convert to float
        $form_total_value = preg_replace('/[^0-9.]/', '', $form_total_value);
        $form_total = floatval($form_total_value);
        // Convert to cents (multiply by 100 and round)
        $form_total = round($form_total * 100);
    }
    
    // Fallback to 50 cents if no total is found or total is 0
    if ($form_total <= 0) {
        $form_total = 50;
    }
    
    $intent_information['amount'] = $form_total;
    
    // Set setup_future_usage to save the payment method for future payments
    $intent_information['setup_future_usage'] = 'off_session';
    
    // Set capture method to manual for authorization
    $intent_information['capture_method'] = 'manual';
    
    // Request extended authorization (31 days instead of 7) - set at payment intent level
    $intent_information['request_extended_authorization'] = true;
    
    return $intent_information;
}

// Hook into payment intent creation to set future usage
add_filter('gform_stripe_payment_element_initial_payment_information', 'FB_setup_payment_intent_for_future_use', 10, 3);

/**
 * Also ensure setup_future_usage is set in the payment intent args
 * This is a backup to ensure the setting is preserved
 */
function FB_modify_payment_intent_args($payment_intent_args, $form, $entry, $feed) {
    // Only process for Form ID 13
    if (rgar($form, 'id') != 13) {
        return $payment_intent_args;
    }
    
    // Get the feed name to ensure we're working with the right feed
    $feed_name = rgars($feed, 'meta/feedName');
    if ($feed_name !== 'Stripe Feed 1') {
        return $payment_intent_args;
    }
    
    // Ensure setup_future_usage is set
    $payment_intent_args['setup_future_usage'] = 'off_session';
    
    // Log for debugging
    gf_stripe()->log_debug(__METHOD__ . '(): Ensuring setup_future_usage is off_session in payment intent args');
    
    return $payment_intent_args;
}

// Hook into payment intent creation args
add_filter('gform_stripe_payment_intent_pre_create', 'FB_modify_payment_intent_args', 10, 4);

/**
 * Add setup_future_usage to charge metadata - REQUIRED to match payment intent
 * This must match what's set in the payment intent to avoid mismatch errors
 */
function FB_add_charge_metadata($charge_meta, $feed, $submission_data, $form, $entry) {
    // Only process for Form ID 13
    if (rgar($form, 'id') != 13) {
        return $charge_meta;
    }
    
    // Get the feed name to ensure we're working with the right feed
    $feed_name = rgars($feed, 'meta/feedName');
    if ($feed_name !== 'Stripe Feed 1') {
        return $charge_meta;
    }
    
    // Log for debugging
    gf_stripe()->log_debug(__METHOD__ . '(): Adding setup_future_usage to charge for feed ' . rgars($feed, 'meta/feedName'));
    
    // CRITICAL: Set setup_future_usage to match the payment intent
    $charge_meta['setup_future_usage'] = 'off_session';
    
    // Store customer information in metadata for reference
    $customer_data = FB_extract_customer_data($entry, $form);
    if ($customer_data) {
        $charge_meta['metadata'] = array_merge(
            $charge_meta['metadata'] ?? array(),
            array(
                'gravity_forms_entry_id' => $entry['id'],
                'gravity_forms_form_id' => $form['id'],
                'customer_email' => $customer_data['email'],
                'customer_name' => $customer_data['name'] ?? '',
                'source' => 'Furniture Bank Donation Form'
            )
        );
    }
    
    return $charge_meta;
}

// Hook into charge creation to add metadata
add_filter('gform_stripe_charge_pre_create', 'FB_add_charge_metadata', 10, 5);

/**
 * Create Stripe customer
 */
function FB_create_stripe_customer($stripe_api, $customer_data) {
    try {
        $customer = $stripe_api->create_customer($customer_data);
        return $customer;
    } catch (Exception $e) {
        error_log('Stripe customer creation failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Store Stripe customer and payment method information after successful charge
 */
function FB_store_stripe_customer_info($entry, $action) {
    // Only process for Form ID 13
    if ($entry['form_id'] != 13) {
        return;
    }
    
    // Check if this was a successful transaction
    if ($action['type'] !== 'complete_payment' || !$action['is_success']) {
        return;
    }
    
    // Get transaction data
    $transaction_id = rgar($action, 'transaction_id');
    $customer_id = rgar($action, 'customer_id');
    $payment_method = rgar($action, 'payment_method');
    
    if ($customer_id) {
        gform_update_meta($entry['id'], 'stripe_customer_id', $customer_id);
        error_log("Stored Stripe customer ID {$customer_id} for entry {$entry['id']}");
    }
    
    if ($payment_method) {
        gform_update_meta($entry['id'], 'stripe_payment_method_id', $payment_method);
        error_log("Stored Stripe payment method {$payment_method} for entry {$entry['id']}");
    }
    
    if ($transaction_id) {
        gform_update_meta($entry['id'], 'stripe_transaction_id', $transaction_id);
        error_log("Stored Stripe transaction ID {$transaction_id} for entry {$entry['id']}");
    }
}

// Hook to capture Stripe data after processing
add_action('gform_post_payment_action', 'FB_store_stripe_customer_info', 10, 2);
?>