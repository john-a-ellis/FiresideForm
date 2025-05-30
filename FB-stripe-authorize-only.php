<?php
/**
 * Plugin Name: FB Gravity Forms Stripe Authorize Only
 * Plugin URI: 
 * Description: Enables authorize-only functionality for Gravity Forms with Stripe Add-On for specific form ID and feed. Works in coordination with FB Stripe Token Creation plugin.
 * Version: 2.0
 * Author: John Ellis - Near North Analytics
 * Author URI: 
 * Text Domain: FB-gf-stripe-authorize-only
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Check for conflicting plugins on form submission
 */
function FB_auth_check_conflicts($validation_result) {
    $form = $validation_result['form'];
    
    // Only check form ID 13
    if ($form['id'] !== 13) {
        return $validation_result;
    }
    
    // Check if both plugins are active
    if (is_plugin_active('FB-stripe-token-creation-only/FB-stripe-token-creation-only.php') || 
        function_exists('fb_token_create_stripe_customer_and_token')) {
        
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
add_filter('gform_validation_13', 'FB_auth_check_conflicts');

/**
 * Enable authorize-only for Form ID 13 with "Stripe Feed 1"
 */
function FB_gf_stripe_enable_authorize_only($result, $form, $feed) {
    // Check if this is form ID 13
    if($form['id'] === 13) {
        // Check for conflicts first
        if (is_plugin_active('FB-stripe-token-creation-only/FB-stripe-token-creation-only.php') || 
            function_exists('fb_token_create_stripe_customer_and_token')) {
            
            // Don't enable authorization if token creation plugin is active
            return false;
        }
        
        // Get the feed name
        $feed_name = rgars($feed, 'meta/feedName');
        
        // Check if this is "Stripe Feed 1"
        if($feed_name === 'Stripe Feed 1') {
            return true; // Enable authorization only for this specific form and feed
        }
    }
    
    return $result; // Keep default behavior for all other forms/feeds
}

// Hook into the Stripe Payment Element filter
add_filter('gform_stripe_payment_element_authorization_only', 'FB_gf_stripe_enable_authorize_only', 10, 3);

/**
 * Add note to entry when authorization is created
 */
function FB_auth_add_entry_note($entry, $form) {
    // Only process form ID 13
    if ($form['id'] !== 13) {
        return;
    }
    
    // Check for conflicts
    if (is_plugin_active('FB-stripe-token-creation-only/FB-stripe-token-creation-only.php') || 
        function_exists('fb_token_create_stripe_customer_and_token')) {
        
        error_log('FB Authorization: Conflict detected - both plugins active for entry ' . $entry['id']);
        return;
    }
    
    // Get payment amount
    $payment_amount = rgar($entry, 'payment_amount');
    $pickup_date = rgar($entry, '18');
    
    // Add note about authorization
    $note = sprintf(
        'Authorization Mode: Pre-authorization created for $%s%sPickup Date: %s%sUse Stripe dashboard to capture payment after pickup.',
        number_format($payment_amount, 2),
        "\n",
        $pickup_date,
        "\n"
    );
    
    RGFormsModel::add_note($entry['id'], wp_get_current_user()->ID, 'System', $note);
    
    error_log('FB Authorization: Authorization created for entry ' . $entry['id'] . ' - Amount: $' . $payment_amount);
}
add_action('gform_after_submission_13', 'FB_auth_add_entry_note', 10, 2);

/**
 * Add admin notice about plugin mode
 */
function FB_auth_admin_notice() {
    $screen = get_current_screen();
    
    // Show notice on forms pages
    if ($screen && (strpos($screen->id, 'forms_page_gf_') !== false || strpos($screen->id, 'toplevel_page_gf_') !== false)) {
        
        // Check if token creation plugin is also active
        if (is_plugin_active('FB-stripe-token-creation-only/FB-stripe-token-creation-only.php') || 
            function_exists('fb_token_create_stripe_customer_and_token')) {
            ?>
            <div class="notice notice-error">
                <p><strong>Plugin Conflict:</strong> Both FB Stripe Authorization and FB Stripe Token Creation plugins are active. Please deactivate one plugin to avoid form submission errors.</p>
            </div>
            <?php
        } else {
            ?>
            <div class="notice notice-success">
                <p><strong>FB Stripe Authorization Mode Active:</strong> Form 13 will create pre-authorizations for the calculated amount.</p>
            </div>
            <?php
        }
    }
}
add_action('admin_notices', 'FB_auth_admin_notice');

/**
 * Add submenu to show current mode
 */
function FB_auth_admin_menu() {
    add_submenu_page(
        'gf_edit_forms',
        'Stripe Payment Mode', 
        'Payment Mode', 
        'manage_options', 
        'fb-stripe-payment-mode', 
        'FB_auth_mode_page'
    );
}
add_action('admin_menu', 'FB_auth_admin_menu');

/**
 * Display current payment mode
 */
function FB_auth_mode_page() {
    ?>
    <div class="wrap">
        <h1>Stripe Payment Mode Status</h1>
        
        <?php
        $auth_active = is_plugin_active(plugin_basename(__FILE__));
        $token_active = is_plugin_active('FB-stripe-token-creation-only/FB-stripe-token-creation-only.php') || 
                       function_exists('fb_token_create_stripe_customer_and_token');
        ?>
        
        <div class="card" style="max-width: 600px;">
            <h2>Current Configuration</h2>
            
            <table class="form-table">
                <tr>
                    <th>FB Stripe Authorization Plugin</th>
                    <td>
                        <?php if ($auth_active): ?>
                            <span style="color: green;">✓ Active</span>
                        <?php else: ?>
                            <span style="color: gray;">○ Inactive</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>FB Stripe Token Creation Plugin</th>
                    <td>
                        <?php if ($token_active): ?>
                            <span style="color: green;">✓ Active</span>
                        <?php else: ?>
                            <span style="color: gray;">○ Inactive</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Current Mode</th>
                    <td>
                        <?php if ($auth_active && $token_active): ?>
                            <span style="color: red; font-weight: bold;">⚠ CONFLICT - Both plugins active</span>
                        <?php elseif ($auth_active): ?>
                            <span style="color: blue; font-weight: bold;">Authorization Mode</span>
                        <?php elseif ($token_active): ?>
                            <span style="color: purple; font-weight: bold;">Token Creation Mode</span>
                        <?php else: ?>
                            <span style="color: green; font-weight: bold;">Real-time Payment Mode</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="card" style="max-width: 600px; margin-top: 20px;">
            <h2>Payment Mode Descriptions</h2>
            
            <h3>Real-time Payment Mode</h3>
            <p><strong>When:</strong> Both plugins inactive<br>
            <strong>Behavior:</strong> Form processes payments immediately for the calculated amount</p>
            
            <h3>Authorization Mode</h3>
            <p><strong>When:</strong> FB Stripe Authorization plugin active, Token Creation plugin inactive<br>
            <strong>Behavior:</strong> Form creates pre-authorization for calculated amount. Use Stripe dashboard to capture payment after pickup.</p>
            
            <h3>Token Creation Mode</h3>
            <p><strong>When:</strong> FB Stripe Token Creation plugin active, Authorization plugin inactive<br>
            <strong>Behavior:</strong> Form creates customer and saves payment method. No authorization hold. Use Stripe dashboard to charge any amount after pickup.</p>
            
            <h3>Conflict Mode</h3>
            <p><strong>When:</strong> Both plugins active<br>
            <strong>Behavior:</strong> Form submission will fail with error message. Deactivate one plugin.</p>
        </div>
    </div>
    <?php
}
?>