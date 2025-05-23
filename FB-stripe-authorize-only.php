<?php
/**
 * Plugin Name: FB Gravity Forms Stripe Authorize Only
 * Plugin URI: 
 * Description: Enables authorize-only functionality for Gravity Forms with Stripe Add-On for specific form ID and feed.
 * Version: 1.0
 * Author: John Ellis - Near North Analytics
 * Author URI: 
 * Text Domain: FB-gf-stripe-authorize-only
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Enable authorize-only for Form ID 13 with "Stripe Feed 1"
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

// Hook into the Stripe Payment Element filter
add_filter('gform_stripe_payment_element_authorization_only', 'FB_gf_stripe_enable_authorize_only', 10, 3);