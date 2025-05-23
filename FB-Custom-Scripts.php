<?php
/**
 * Plugin Name: FB Gravity Forms Custom Scripts
 * Description: Custom functionality for Furniture Bank donation forms, including address copying, shipping calculations, item credits, and date formatting
 * Version: 2.0.0
 * Author: John Ellis - NearNorthAnalytics
 * 
 * This plugin contains various JavaScript enhancements for Gravity Forms:
 * - Address copying from billing to pickup
 * - Shipping charge calculation based on postal code
 * - Item credit calculations based on delivery charges
 * - Custom next button labels based on radio selection
 * - Scheduled Pickup Date formatting and persistence
 * - Product label and price formatting
 * 
 * All scripts now include conditional loading to only fire when the relevant form is present.
 */

/**
 * Helper function to check if we're on a page that likely contains Form 13
 * Customize URL patterns based on your site structure
 */
function is_form_13_page() {
    $current_url = $_SERVER['REQUEST_URI'];
    
    // Check for URL patterns that indicate Form 13 might be present
    if (strpos($current_url, 'online-quote-order-form') !== false || 
        strpos($current_url, 'online-quote-order-form') !== false ||
        strpos($current_url, 'online-quote-order-form') !== false) {
        return true;
    }
    
    return false;
}

/**
 * Helper function to check if we're on a page that likely contains Form 16
 * Customize URL patterns based on your site structure
 */
function is_form_16_page() {
    $current_url = $_SERVER['REQUEST_URI'];
    
    // Check for URL patterns that indicate Form 16 might be present
    if (strpos($current_url, 'online-quote-order-form') !== false || 
        strpos($current_url, 'online-quote-order-form') !== false ||
        strpos($current_url, 'online-quote-order-form') !== false) {
        return true;
    }
    
    return false;
}

/**
 * Copies billing address fields to pickup address fields when checkbox is checked.
 * Uses localStorage to save and display date selections across form pages.
 * Only runs when Form 13 is present on the page.
 */
function custom_gravity_forms_copy_address_script() {
    // Short-circuit for non-form pages
    if (!is_form_13_page()) {
        return;
    }
    
    ?>
    <script type="text/javascript">
        // Check if Form 13 is actually present before initializing script
        document.addEventListener('DOMContentLoaded', function() {
            var formElement = document.getElementById('gform_13');
            
            // If form doesn't exist, don't initialize the script
            if (!formElement) {
                console.log('Form 13 not found in DOM, not initializing address copy script.');
                return;
            }
            
            console.log('Form 13 found, initializing address copy script');
            
            // Monitor changes to the checkbox field for copying address
            jQuery('#choice_13_115_1').on('change', function() {
                if (jQuery(this).is(':checked')) {
                    console.log('Copying billing address to pickup address');

                    // Wait for the pickup address field to become visible
                    var checkVisibility = setInterval(function() {
                        if (jQuery('#field_13_126').is(':visible')) {
                            clearInterval(checkVisibility);

                            // Copy values from Billing Address to Pickup Address
                            jQuery('#input_13_126_1').val(jQuery('#input_13_125_1').val()); // Street Address
                            jQuery('#input_13_126_2').val(jQuery('#input_13_125_2').val()); // Address Line 2
                            jQuery('#input_13_126_3').val(jQuery('#input_13_125_3').val()); // City
                            jQuery('#input_13_126_4').val(jQuery('#input_13_125_4').val()); // State
                            jQuery('#input_13_126_5').val(jQuery('#input_13_125_5').val()); // Zip Code
                            jQuery('#input_13_126_6').val(jQuery('#input_13_125_6').val()); // Country
                        }
                    }, 100);
                }
            });

            // Store date field value when it changes
            jQuery('#input_13_18').on('change', function() {
                var dateValue = jQuery(this).val();
                localStorage.setItem('gform_13_date_18', dateValue);
                console.log('Date saved: ' + dateValue);
            });

            // Listen for page changes in the form
            jQuery(document).on('gform_post_render', function(event, formId, currentPage) {
                if (formId == 13) {
                    var storedDate = localStorage.getItem('gform_13_date_18');
                    
                    if (storedDate) {
                        var prefix = "Your Pickup is Scheduled for: ";
                        jQuery('#date-display-18').text(prefix + storedDate);
                    }
                }
            });
        });
    </script>
    <?php
}
add_action('wp_footer', 'custom_gravity_forms_copy_address_script');

/**
 * Calculates shipping charges based on postal code FSA (first three characters).
 * Updates the shipping charge field automatically when a valid postal code is entered.
 * Only runs when Form 13 is present on the page.
 */
function custom_gravity_forms_shipping_charge_script() {
    // Short-circuit for non-form pages
    if (!is_form_13_page()) {
        return;
    }
    
    ?>
    <script type="text/javascript">
        // Check if Form 13 is actually present before initializing script
        document.addEventListener('DOMContentLoaded', function() {
            var formElement = document.getElementById('gform_13');
            
            // If form doesn't exist, don't initialize the script
            if (!formElement) {
                console.log('Form 13 not found in DOM, not initializing shipping charge script.');
                return;
            }
            
            console.log('Form 13 found, initializing shipping charge script');
            
            // Form ID
            var formId = 13;
            // Field IDs
            var postalCodeFieldId = 146;
            var shippingFieldId = 128;

            // Function to update shipping charge based on FSA
            function updateShippingCharge(fsa, postalCodeElement) {
                var shippingCharge = 0;

                // Define FSAs and corresponding shipping charges
                // This lookup table maps postal code prefixes to delivery charges
                var shippingRates = {
                    'L0C':325,	'L0E':325,	'L0G':275,	'L1A':300,	'L1C':275,	'l1E':275,	'L1G':250,	
                    'L1H':250,	'L1J':250,	'L1K':250,	'L1L':250,	'L1M':225,	'L1N':225,	'L1P':225,	
                    'L1R':225,	'L1S':200,	'L1T':200,	'L1V':200,	'L1W':200,	'L1X':200,	'L1Y':200,	
                    'L1Z':200,	'L3P':225,	'L3R':225,	'L3S':225,	'L3T':200,	'L3X':275,	'L3Y':275,	
                    'L4A':250,	'L4B':225,	'L4C':225,	'L4E':225,	'L4G':250,	'L4J':200,	'L4K':200,	
                    'L4L':200,	'L4P':325,	'L4S':225,	'L4T':150,	'L4V':150,	'L4W':150,	'L4X':150,	
                    'L4Y':150,	'L4Z':150,	'L5A':150,	'L5B':150,	'L5C':150,	'L5E':150,	'L5G':150,	
                    'L5H':150,	'L5J':150,	'L5K':150,	'L5L':150,	'L5M':150,	'L5N':150,	'L5P':150,	
                    'L5R':150,	'L5S':150,	'L5T':150,	'L5V':150,	'L5W':150,	'L6A':200,	'L6B':225,	
                    'L6C':225,	'L6E':225,	'L6H':200,	'L6J':200,	'L6K':200,	'L6L':200,	'L6M':200,	
                    'L6P':150,	'L6R':150,	'L6S':150,	'L6T':150,	'L6V':150,	'L6W':150,	'L6X':150,	
                    'L6Y':150,	'L6Z':150,	'L7A':150,	'L7B':250,	'L7E':250,	'L7G':225,	'L7L':225,	
                    'L7M':225,	'L7N':225,	'L7P':225,	'L7R':225,	'L7T':225,	'L9L':300,	'L9N':275,	
                    'L9N':300,	'L9T':225,	'M1B':150,	'M1C':150,	'M1E':150,	'M1G':150,	'M1H':150,	
                    'M1J':150,	'M1K':150,	'M1L':150,	'M1M':150,	'M1N':150,	'M1P':150,	'M1R':150,	
                    'M1S':150,	'M1T':150,	'M1V':150,	'M1W':150,	'M1X':150,	'M2A':150,	'M2B':150,	
                    'M2C':150,	'M2D':150,	'M2E':150,	'M2F':150,	'M2G':150,	'M2H':150,	'M2I':150,	
                    'M2J':150,	'M2K':150,	'M2L':150,	'M2M':150,	'M2N':150,	'M2O':150,	'M2P':150,	
                    'M2Q':150,	'M2R':150,	'M2S':150,	'M2T':150,	'M2U':150,	'M2V':150,	'M2W':150,	
                    'M2X':150,	'M2Y':150,	'M2Z':150,	'M3A':150,	'M3B':150,	'M3C':150,	'M3D':150,	
                    'M3E':150,	'M3F':150,	'M3G':150,	'M3H':150,	'M3I':150,	'M3J':150,	'M3K':150,	
                    'M3L':150,	'M3M':150,	'M3N':150,	'M3O':150,	'M3P':150,	'M3Q':150,	'M3R':150,	
                    'M3S':150,	'M3T':150,	'M3U':150,	'M3V':150,	'M3W':150,	'M3X':150,	'M3Y':150,	
                    'M3Z':150,	'M4A':150,	'M4B':150,	'M4C':150,	'M4D':150,	'M4E':150,	'M4F':150,	
                    'M4G':150,	'M4H':150,	'M4I':150,	'M4J':150,	'M4K':150,	'M4L':150,	'M4M':150,	
                    'M4N':150,	'M4O':150,	'M4P':150,	'M4Q':150,	'M4R':150,	'M4S':150,	'M4T':150,	
                    'M4U':150,	'M4V':150,	'M4W':150,	'M4X':150,	'M4Y':150,	'M4Z':150,	'M5A':150,	
                    'M5B':150,	'M5C':150,	'M5D':150,	'M5E':150,	'M5F':150,	'M5G':150,	'M5H':150,	
                    'M5I':150,	'M5J':150,	'M5K':150,	'M5L':150,	'M5M':150,	'M5N':150,	'M5O':150,	
                    'M5P':150,	'M5Q':150,	'M5R':150,	'M5S':150,	'M5T':150,	'M5U':150,	'M5V':150,	
                    'M5W':150,	'M5X':150,	'M5Y':150,	'M5Z':150,	'M6A':150,	'M6B':150,	'M6C':150,	
                    'M6D':150,	'M6E':150,	'M6F':150,	'M6G':150,	'M6H':150,	'M6I':150,	'M6J':150,	
                    'M6K':150,	'M6L':150,	'M6M':150,	'M6N':150,	'M6O':150,	'M6P':150,	'M6Q':150,	
                    'M6R':150,	'M6S':150,	'M6T':150,	'M6U':150,	'M6V':150,	'M6W':150,	'M6X':150,	
                    'M6Y':150,	'M6Z':150,	'M8V':150,	'M8W':150,	'M8X':150,	'M8Y':150,	'M8Z':150,	
                    'M9V':150,	'M9W':150,	'M7A':150,	'M7R':150,	'M7Y':150,	'M9A':150,	'M9B':150,	
                    'M9C':150,	'M9L':150,	'M9M':150,	'M9N':150,	'M9P':150,	'M9R':150,  'Z9Z':10
                };

                if (shippingRates[fsa]) {
                    shippingCharge = shippingRates[fsa];
                    // Update the shipping field value
                    jQuery('#input_' + formId + '_' + shippingFieldId).val(shippingCharge);
                    console.log('Shipping charge updated: $' + shippingCharge + ' for FSA ' + fsa);
                } else {
                    // Display error message for FSAs outside the service area
                    alert('The postal code ' + postalCodeElement.val() + ' is outside our service area.');
                    // Clear the postal code field value
                    postalCodeElement.val('');
                }
            }

            // Event listener for postal code field changes
            jQuery('#input_' + formId + '_' + postalCodeFieldId).on('change', function () {
                var postalCode = jQuery(this).val().toUpperCase(); // Convert to uppercase
                jQuery(this).val(postalCode); // Write back to the postal code field
                if (postalCode.length >= 3) {
                    var fsa = postalCode.substring(0, 3);
                    updateShippingCharge(fsa, jQuery(this));
                }
            });
        });
    </script>
    <?php
}
add_action('wp_footer', 'custom_gravity_forms_shipping_charge_script');

/**
 * Calculates credit or additional charges based on delivery fee and item costs.
 * If items < delivery charge, creates a credit to offset item costs.
 * Uses hidden product field to apply the credit to the form total.
 * Only runs when Form 13 is present on the page.
 */
function custom_gravity_forms_item_credit_script() {
    // Short-circuit for non-form pages
    if (!is_form_13_page()) {
        return;
    }
    
    ?>
    <script type="text/javascript">
        // Check if Form 13 is actually present before initializing script
        document.addEventListener('DOMContentLoaded', function() {
            var formElement = document.getElementById('gform_13');
            
            // If form doesn't exist, don't initialize the script
            if (!formElement) {
                console.log('Form 13 not found in DOM, not initializing credit script.');
                return;
            }
            
            console.log('Form 13 found, initializing credit script');
            
            // Form ID
            var formId = 13;
            
            // Field IDs
            var itemFieldIds = [96, 97, 98, 100, 101]; // Item cost fields
            var deliveryChargeFieldId = 128; // Delivery charge field
            var creditFieldId = 187; // Credit field (hidden product field)
            
            // Function to calculate and apply credit
            function calculateAndApplyCredit() {
                console.log('Calculating item credit adjustment');
                
                // Get delivery charge
                var deliveryChargeField = jQuery('#input_' + formId + '_' + deliveryChargeFieldId);
                var deliveryChargeValue = deliveryChargeField.val() || '0';
                deliveryChargeValue = deliveryChargeValue.replace(/[^0-9.-]+/g, '');
                var deliveryCharge = parseFloat(deliveryChargeValue) || 0;
                
                // Calculate total item cost
                var totalItemCost = 0;
                itemFieldIds.forEach(function(fieldId) {
                    var itemField = jQuery('#input_' + formId + '_' + fieldId);
                    var itemValue = itemField.val() || '0';
                    itemValue = itemValue.replace(/[^0-9.-]+/g, '');
                    var itemCost = parseFloat(itemValue) || 0;
                    totalItemCost += itemCost;
                });
                
                // Calculate credit:
                // If no items, credit is 0
                // If items < delivery charge, credit is negative of item cost
                // If items >= delivery charge, credit is negative of delivery charge
                var credit = 0;
                
                if (totalItemCost > 0 && totalItemCost <= deliveryCharge) {
                    credit = -totalItemCost;
                } else if (totalItemCost > deliveryCharge) {
                    credit = -deliveryCharge;
                }
                
                // Format values for hidden product field
                var numericCredit = credit.toString();
                var formattedPrice = '-$ ' + Math.abs(credit).toFixed(2) + ' CAD';
                
                // Update all parts of the hidden product field
                updateHiddenProductField(creditFieldId, numericCredit, formattedPrice);
                console.log('Credit applied: ' + formattedPrice);
            }
            
            // Function to update all parts of a hidden product field
            function updateHiddenProductField(fieldId, value, formattedValue) {
                // 1. Update quantity input (input_187.3) - ALWAYS SET TO 1
                var quantityInput = jQuery('input[name="input_' + fieldId + '.3"]');
                if (quantityInput.length > 0) {
                    quantityInput.val("1");
                }
                
                // 2. Update price input (input_187.1) with the credit value
                var priceInput = jQuery('input[name="input_' + fieldId + '.1"]');
                if (priceInput.length > 0) {
                    priceInput.val(value);
                } else {
                    console.log('Credit price input not found - this may affect calculations');
                }
                
                // 3. Update base price input with formatted value (input_187.2)
                var basePriceInput = jQuery('input[name="input_' + fieldId + '.2"]');
                if (basePriceInput.length > 0) {
                    basePriceInput.val(formattedValue);
                }
                
                // 4. Additional attempt using field ID
                var ginputBasePriceElement = jQuery('#ginput_base_price_' + formId + '_' + fieldId);
                if (ginputBasePriceElement.length > 0) {
                    ginputBasePriceElement.val(formattedValue);
                }
                
                var ginputQuantityElement = jQuery('#ginput_quantity_' + formId + '_' + fieldId);
                if (ginputQuantityElement.length > 0) {
                    ginputQuantityElement.val("1");
                }
                
                // 5. Trigger form total recalculation
                if (window.gf_global && window.gf_global.gfcalc && typeof window.gf_global.gfcalc.runCalcs === 'function') {
                    // GF 2.9+ method
                    try {
                        window.gf_global.gfcalc.runCalcs(formId);
                    } catch (e) {
                        console.log('Error in GF calculation: ' + e.message);
                    }
                } else if (typeof gformCalculateTotalPrice === 'function') {
                    // Fallback to older method
                    try {
                        gformCalculateTotalPrice(formId);
                    } catch (e) {
                        console.log('Error in GF calculation: ' + e.message);
                    }
                }
                
                // 6. Extra step for GF 2.9.4: manually trigger the product price calculation
                try {
                    jQuery(document).trigger('gform_product_total_changed', [formId]);
                } catch (e) {
                    console.log('Error triggering product total changed: ' + e.message);
                }
            }
            
            // Monitor changes to item cost fields
            itemFieldIds.forEach(function(fieldId) {
                jQuery(document).on('change keyup blur', '#input_' + formId + '_' + fieldId, function() {
                    calculateAndApplyCredit();
                });
            });
            
            // Monitor changes to delivery charge field
            jQuery(document).on('change keyup blur', '#input_' + formId + '_' + deliveryChargeFieldId, function() {
                calculateAndApplyCredit();
            });
            
            // Calculate on form render
            jQuery(document).on('gform_post_render', function(event, renderedFormId, currentPage) {
                if (renderedFormId == formId) {
                    setTimeout(calculateAndApplyCredit, 500);
                }
            });
            
            // Initial calculation
            jQuery(document).ready(function() {
                setTimeout(calculateAndApplyCredit, 1000);
                setTimeout(calculateAndApplyCredit, 2500); // Additional attempt after a longer delay
            });
            
            // Final attempt after window load
            jQuery(window).on('load', function() {
                setTimeout(calculateAndApplyCredit, 1000);
            });
        });
    </script>
    <?php
}
add_action('wp_footer', 'custom_gravity_forms_item_credit_script');

/**
 * Changes the Next button label based on which radio option is selected.
 * For "Proceed to Order" vs "Request a Callback" options.
 * Only runs when Form 13 is present on the page.
 */
function custom_radio_next_button_label() {
    // Short-circuit for non-form pages
    if (!is_form_13_page()) {
        return;
    }
    
    ?>
    <script type="text/javascript">
        // Check if Form 13 is actually present before initializing script
        document.addEventListener('DOMContentLoaded', function() {
            var formElement = document.getElementById('gform_13');
            
            // If form doesn't exist, don't initialize the script
            if (!formElement) {
                console.log('Form 13 not found in DOM, not initializing button label script.');
                return;
            }
            
            console.log('Form 13 found, initializing button label script');
            
            // Form ID
            var formId = 13;
            
            // Field IDs
            var radioFieldId = 60;  // ID of the Radio Button group on page 2
            var pageBreakId = 41;   // ID of the Page Break on page 2
            var pageNumber = 2;     // The page number these elements are on
            
            // Function to update next button label based on selected radio option
            function updateNextButtonLabel() {
                // Check if we're on the correct page first
                var currentPage = getCurrentPage();
                if (currentPage !== pageNumber) {
                    return;
                }
                
                // Find the selected radio option
                var selectedRadio = jQuery('input[name="input_' + radioFieldId + '"]:checked');
                
                if (selectedRadio.length > 0) {
                    // Get the value directly from the radio button
                    var radioValue = selectedRadio.val() || '';
                    
                    // Determine which button label to use based on the radio value content
                    var newButtonLabel = '';
                    
                    if (radioValue.indexOf('Proceed to Order') >= 0) {
                        newButtonLabel = 'Step 3: When are we picking up? >';
                    } else if (radioValue.indexOf('Request a Callback') >= 0) {
                        newButtonLabel = 'Step 3: Where do we call you? >';
                    }
                    
                    // If we determined a new label, update the next button
                    if (newButtonLabel) {
                        // Find the next button - for page 2 specifically
                        var nextButton = jQuery('#gform_next_button_' + formId + '_' + pageBreakId);
                        
                        if (nextButton.length > 0) {
                            nextButton.val(newButtonLabel);
                            console.log('Next button updated: ' + newButtonLabel);
                        } else {
                            // Try alternative selectors for page 2
                            var alternativeButtons = [
                                jQuery('#gform_page_' + formId + '_' + pageNumber + ' .gform_next_button'),
                                jQuery('#gform_page_' + formId + '_' + pageNumber + ' input[type="button"][value^="Next"]'),
                                jQuery('#gform_page_' + formId + '_' + pageNumber + ' input[type="button"][value^="Step"]')
                            ];
                            
                            for (var i = 0; i < alternativeButtons.length; i++) {
                                var button = alternativeButtons[i];
                                if (button.length > 0) {
                                    button.val(newButtonLabel);
                                    console.log('Next button updated (alt selector): ' + newButtonLabel);
                                    break;
                                }
                            }
                        }
                    }
                }
            }
            
            // Helper function to determine current page number
            function getCurrentPage() {
                // Method 1: Check for visible page
                var visiblePage = jQuery('#gform_page_' + formId + '_' + pageNumber + ':visible');
                if (visiblePage.length > 0) {
                    return pageNumber;
                }
                
                // Method 2: GF 2.9.4 specific - check current_page in gf_global
                if (window['gf_global'] && window['gf_global']['current_page'] && window['gf_global']['current_page'][formId]) {
                    return parseInt(window['gf_global']['current_page'][formId]);
                }
                
                // Method 3: Check for current page marker in URL
                var urlParams = new URLSearchParams(window.location.search);
                var pageParam = urlParams.get('gf_page');
                if (pageParam) {
                    return parseInt(pageParam);
                }
                
                // Default to 1 if we can't determine the page
                return 1;
            }
            
            // Monitor radio button changes (specific to page 2)
            jQuery(document).on('change', 'input[name="input_' + radioFieldId + '"]', function() {
                updateNextButtonLabel();
            });
            
            // The key event for multi-page forms - when a new page is loaded
            jQuery(document).on('gform_page_loaded', function(event, eventFormId, currentPage) {
                if (parseInt(eventFormId) === formId && parseInt(currentPage) === pageNumber) {
                    // Run immediately and after a short delay to ensure everything is loaded
                    updateNextButtonLabel();
                    setTimeout(updateNextButtonLabel, 500);
                }
            });
            
            // Check on form render
            jQuery(document).on('gform_post_render', function(event, eventFormId, currentPage) {
                setTimeout(function() {
                    updateNextButtonLabel();
                }, 500);
            });
            
            // Initial check
            jQuery(document).ready(function() {
                setTimeout(updateNextButtonLabel, 1000);
            });
        });
    </script>
    <?php
}
add_action('wp_footer', 'custom_radio_next_button_label');

/**
 * Function to format a date field value and store it in a target single line text field
 * Using hardcoded form and field IDs.
 * Only runs when Form 13 is present on the page.
 */
function custom_date_formatter_script() {
    // Short-circuit for non-form pages
    if (!is_form_13_page()) {
        return;
    }
    
    ?>
    <script type="text/javascript">
        // Check if Form 13 is actually present before initializing script
        document.addEventListener('DOMContentLoaded', function() {
            var formElement = document.getElementById('gform_13');
            
            // If form doesn't exist, don't initialize the script
            if (!formElement) {
                console.log('Form 13 not found in DOM, not initializing date formatter script.');
                return;
            }
            
            console.log('Form 13 found, initializing date formatter script');
            
            // Hardcoded IDs
            var formId = 13;            // Form ID
            var dateFieldId = 18;       // Date field ID
            var targetFieldId = 188;    // Target single line text field ID
            
            console.log('Initializing date formatter with hardcoded IDs: form=' + formId + 
                        ', date field=' + dateFieldId + 
                        ', target field=' + targetFieldId);
            
            var formattedDateValue = ''; // Store the formatted value for cross-page persistence
            
            // Function to format date from YYYY-MM-DD to Month DD, YYYY
            function formatDate(dateString) {
                // Check if we have a valid date string
                if (!dateString || dateString.trim() === '') {
                    console.log('No valid date found');
                    return '';
                }
                
                try {
                    // Parse the date
                    var dateParts = dateString.split('-');
                    if (dateParts.length !== 3) {
                        // Try other common formats
                        var dateObj = new Date(dateString);
                        if (isNaN(dateObj.getTime())) {
                            console.log('Could not parse date: ' + dateString);
                            return dateString;
                        }
                    } else {
                        // Create date from parts (year, month-1, day)
                        var year = parseInt(dateParts[0]);
                        var month = parseInt(dateParts[1]) - 1; // JS months are 0-11
                        var day = parseInt(dateParts[2]);
                        var dateObj = new Date(year, month, day);
                    }
                    
                    // Format the date
                    var months = [
                        'January', 'February', 'March', 'April', 'May', 'June', 
                        'July', 'August', 'September', 'October', 'November', 'December'
                    ];
                    
                    var formattedDate = months[dateObj.getMonth()] + ' ' + 
                                        dateObj.getDate() + ', ' + 
                                        dateObj.getFullYear();
                    
                    console.log('Formatted date: ' + formattedDate);
                    return formattedDate;
                } catch (e) {
                    console.error('Error formatting date:', e);
                    return dateString;
                }
            }
            
            // Function to read the date and store the formatted version
            function processDateField() {
                console.log('Processing date field');
                
                // Get the date value from the source field
                var dateField = jQuery('#input_' + formId + '_' + dateFieldId);
                if (dateField.length === 0) {
                    console.log('Date field not found - might be on a different page');
                    return;
                }
                
                var dateValue = dateField.val();
                console.log('Original date value: ' + dateValue);
                
                if (!dateValue) {
                    console.log('No date value to format');
                    return;
                }
                
                // Format the date and store it for later use
                formattedDateValue = formatDate(dateValue);
                console.log('Stored formatted date value: ' + formattedDateValue);
                
                // Try to update target field if it exists on the current page
                updateTargetField();
                
                // Store in sessionStorage for cross-page persistence
                try {
                    sessionStorage.setItem('gform_formatted_date_' + formId + '_' + dateFieldId, formattedDateValue);
                    console.log('Saved formatted date to sessionStorage');
                } catch (e) {
                    console.error('Error saving to sessionStorage:', e);
                }
            }
            
            // Function to update the target field with the formatted date
            function updateTargetField() {
                console.log('Attempting to update target field');
                
                // If we don't have a formatted value yet, try to get it from sessionStorage
                if (!formattedDateValue) {
                    try {
                        formattedDateValue = sessionStorage.getItem('gform_formatted_date_' + formId + '_' + dateFieldId);
                        console.log('Retrieved formatted date from sessionStorage: ' + formattedDateValue);
                    } catch (e) {
                        console.error('Error retrieving from sessionStorage:', e);
                    }
                }
                
                if (!formattedDateValue) {
                    console.log('No formatted date value available yet');
                    return;
                }
                
                // Look for the target single line text field
                var targetField = jQuery('#input_' + formId + '_' + targetFieldId);
                if (targetField.length > 0) {
                    targetField.val(formattedDateValue);
                    targetField.trigger('change');
                    console.log('Updated target field with: ' + formattedDateValue);
                } else {
                    console.log('Target field not found on current page');
                }
            }
            
            // Monitor for changes to the date field
            jQuery(document).on('change', '#input_' + formId + '_' + dateFieldId, function() {
                console.log('Date field changed');
                processDateField();
            });
            
            // Monitor for keyup/blur events on date field for more responsive updates
            jQuery(document).on('keyup blur', '#input_' + formId + '_' + dateFieldId, function() {
                console.log('Date field keyup/blur');
                setTimeout(processDateField, 200);
            });
            
            // The key event for multi-page forms - when a new page is loaded
            jQuery(document).on('gform_page_loaded', function(event, loadedFormId, currentPage) {
                if (parseInt(loadedFormId) === formId) {
                    console.log('Form page ' + currentPage + ' loaded');
                    
                    // First check if the date field is on this page
                    var dateField = jQuery('#input_' + formId + '_' + dateFieldId);
                    if (dateField.length > 0) {
                        console.log('Date field found on current page');
                        processDateField();
                    } else {
                        console.log('Date field not on current page');
                    }
                    
                    // Then try to update the target field
                    setTimeout(updateTargetField, 500);
                }
            });
            
            // Check when the form is rendered
            jQuery(document).on('gform_post_render', function(event, renderedFormId) {
                if (parseInt(renderedFormId) === formId) {
                    console.log('Form rendered');
                    
                    // Try both processing the date and updating the target field
                    setTimeout(function() {
                        processDateField();
                        updateTargetField();
                    }, 500);
                }
            });
            
            // Initial processing
            jQuery(document).ready(function() {
                console.log('Document ready - initial processing');
                setTimeout(function() {
                    processDateField();
                    updateTargetField();
                }, 1000);
                
                // Additional check after a longer delay
                setTimeout(function() {
                    processDateField();
                    updateTargetField();
                }, 2500);
            });
            
            // Try again after everything is loaded
            jQuery(window).on('load', function() {
                console.log('Window loaded');
                setTimeout(function() {
                    processDateField();
                    updateTargetField();
                }, 1000);
            });
            
            // Also intercept form submission to ensure the target field is populated
            jQuery('#gform_' + formId).on('submit', function() {
                console.log('Form submission detected, ensuring target field is populated');
                updateTargetField();
                return true; // Allow the form submission to continue
            });
        });
    </script>
    <?php
}
add_action('wp_footer', 'custom_date_formatter_script');

/**
 * Default Empty Quantity Fields to Zero
 * 
 * This function adds JavaScript that automatically converts any empty quantity
 * fields to '0' when the second page of Form ID 13 is displayed.
 * Only runs when Form 13 is present on the page.
 */
function custom_gravity_forms_quantity_default_script() {
    // Short-circuit for non-form pages
    if (!is_form_13_page()) {
        return;
    }
    
    ?>
    <script type="text/javascript">
        // Check if Form 13 is actually present before initializing script
        document.addEventListener('DOMContentLoaded', function() {
            var formElement = document.getElementById('gform_13');
            
            // If form doesn't exist, don't initialize the script
            if (!formElement) {
                console.log('Form 13 not found in DOM, not initializing quantity default script.');
                return;
            }
            
            console.log('Form 13 found, initializing quantity default script');
            
            jQuery(document).ready(function($) {
                // Configuration
                const formId = 13;
                const targetPageNumber = 2;
                
                // Field mapping: field_id.3 => actual DOM id suffix pattern
                const fieldMapping = {
                    '34.3': '34_1',
                    '36.3': '36_1',
                    '38.3': '38_1',
                    '37.3': '37_1',
                    '39.3': '39_1'
                };
                
                /**
                 * Set empty quantity fields to zero
                 * Uses the correct selector pattern based on actual DOM inspection
                 */
                function setEmptyFieldsToZero() {
                    console.log('Setting empty quantity fields to zero');
                    let processedCount = 0;
                    
                    // Process each field using the corrected selector pattern
                    Object.entries(fieldMapping).forEach(function([fieldName, idSuffix]) {
                        // Create the proper input ID based on DOM inspection
                        const inputId = 'input_' + formId + '_' + idSuffix;
                        console.log('Looking for field:', inputId);
                        
                        // Find the field
                        const field = document.getElementById(inputId);
                        
                        if (field) {
                            console.log('Field found:', inputId);
                            
                            // Check if empty and set to 0
                            if (field.value === '' || field.value === null) {
                                console.log('Setting field ' + inputId + ' to 0');
                                field.value = '0';
                                processedCount++;
                                
                                // Trigger change event to ensure calculations update
                                $(field).trigger('change');
                            } else {
                                console.log('Field has value:', field.value);
                            }
                        } else {
                            console.log('Field not found:', inputId);
                        }
                    });
                    
                    console.log('Processed ' + processedCount + ' empty fields');
                }
                
                /**
                 * Primary event handler for Gravity Forms page loads
                 */
                $(document).on('gform_page_loaded', function(event, loadedFormId, currentPage) {
                    console.log('Page loaded - Form:', loadedFormId, 'Page:', currentPage);
                    
                    if (loadedFormId == formId && currentPage == targetPageNumber) {
                        console.log('Target page detected, setting empty quantity fields to zero');
                        
                        // Small delay to ensure everything is fully loaded
                        setTimeout(setEmptyFieldsToZero, 300);
                    }
                });
                
                /**
                 * Backup method to check initial page load
                 * This handles direct links and page refreshes
                 */
                function checkCurrentPage() {
                    // Check if we're on form 13
                    if ($('#gform_' + formId).length === 0) {
                        return;
                    }
                    
                    // Different ways to detect page 2
                    const currentPage = $('#gform_source_page_number_' + formId).val();
                    const page2Visible = $('#gform_page_' + formId + '_' + targetPageNumber).is(':visible');
                    
                    console.log('Current page detection - Form page:', currentPage, 'Page 2 visible:', page2Visible);
                    
                    if (currentPage == targetPageNumber || page2Visible) {
                        console.log('Already on page 2, processing fields');
                        setTimeout(setEmptyFieldsToZero, 500);
                    }
                }
                
                // Check current page on initial load
                checkCurrentPage();
                
                // Also run a final check after a longer delay for slower page loads
                setTimeout(checkCurrentPage, 1500);
                
                // Add global function for testing if needed
                window.fbSetQuantityFieldsToZero = setEmptyFieldsToZero;
            });
        });
    </script>
    <?php
}
add_action('wp_footer', 'custom_gravity_forms_quantity_default_script', 999);

/**
 * Product Label and Price Modifier
 * 
 * Restructures product labels to show description with product name and combines item price with subtotal.
 * Only runs when Form 13 is present on the page.
 */
function custom_product_label_price_modifier() {
    // Short-circuit for non-form pages
    if (!is_form_13_page()) {
        return;
    }
    
    ?>
    <script type="text/javascript">
        // Check if Form 13 is actually present before initializing script
        document.addEventListener('DOMContentLoaded', function() {
            var formElement = document.getElementById('gform_13');
            
            // If form doesn't exist, don't initialize the script
            if (!formElement) {
                console.log('Form 13 not found in DOM, not initializing product label script.');
                return;
            }
            
            console.log('Form 13 found, initializing product label script');
            
            // Flag to track if we're currently updating to prevent recursive updates
            var isUpdating = false;
            // Flag to determine if elements have been initialized
            var isInitialized = false;
            
            // Product field ID to extended price field ID mapping
            var fieldMapping = {
                'field_13_34': 'field_13_96',  // Small Item -> Extended Price -Small
                'field_13_36': 'field_13_97',  // Medium Item -> Extended Price -Medium
                'field_13_38': 'field_13_98',  // Large Item -> Extended Price -Large
                'field_13_37': 'field_13_100', // Bulky Item -> Extended Price -Bulky
                'field_13_39': 'field_13_101'  // Extra Bulky Item -> Extended Price -Extra Bulky
            };
            
            // Store references to quantity fields and their associated display elements
            var quantityFieldsMap = {};
            
            // Check if we're on page 2 (only run the script on the appropriate page)
            function isOnRelevantPage() {
                // Check if any of our target product fields is visible
                for (var fieldId in fieldMapping) {
                    var field = document.getElementById(fieldId);
                    if (field && field.offsetParent !== null) {
                        return true;
                    }
                }
                return false;
            }
            
            function modifyProductLabels() {
                // Don't proceed if we're not on the relevant page
                if (!isOnRelevantPage()) {
                    return;
                }
                
                console.log('Running product label modifications');
                
                // Process each product field
                Object.keys(fieldMapping).forEach(function(productFieldId) {
                    try {
                        // Get the product field
                        var productField = document.getElementById(productFieldId);
                        if (!productField || productField.offsetParent === null) {
                            // Skip if not visible
                            return;
                        }
                        
                        // Find label and description elements
                        var labelElement = productField.querySelector('.gfield_label_product');
                        var descriptionElement = productField.querySelector('.gfield_description');
                        
                        // Modify product label with description
                        if (labelElement && descriptionElement) {
                            // Clean up label text (removing trailing '<' if present)
                            // Check if the label already contains our modified content
                            if (!labelElement.querySelector('.product-description')) {
                                // Get the original product name without any previous modifications
                                var originalLabel = labelElement.textContent.trim().replace(/<$/, '').trim();
                                
                                // If the description text is already in the label, remove it to avoid duplication
                                originalLabel = originalLabel.split(' - ')[0].trim();
                                
                                var description = descriptionElement.textContent.trim();
                                
                                // Create new label format with the description in a span
                                labelElement.innerHTML = originalLabel + ' - <span class="FB-product-description">' + description + '</span>';
                                
                                // Hide the original description element
                                descriptionElement.style.display = 'none';
                            }
                        }
                        
                        // Get price elements
                        var priceLabel = productField.querySelector('.ginput_product_price_label');
                        var priceValue = productField.querySelector('.ginput_product_price');
                        
                        // Change price label
                        if (priceLabel) {
                            priceLabel.textContent = 'Item Price:';
                        }
                        
                        // Get corresponding extended price field
                        var extendedPriceFieldId = fieldMapping[productFieldId];
                        var extendedPriceField = document.getElementById(extendedPriceFieldId);
                        
                        // Find quantity field for this product
                        var quantityField = productField.querySelector('.ginput_quantity');
                        
                        if (extendedPriceField && priceValue) {
                            // Find the extended price input
                            var extendedPriceInput = extendedPriceField.querySelector('input[type="text"]');
                            
                            if (extendedPriceInput) {
                                // Check if subtotal span already exists
                                var existingSubtotal = productField.querySelector('.extended-price-display');
                                
                                if (!existingSubtotal) {
                                    // Create a new element for subtotal
                                    var subtotalSpan = document.createElement('span');
                                    subtotalSpan.className = 'extended-price-display';
                                    subtotalSpan.innerHTML = ' | Subtotal: <span class="ginput_product_subtotal">' + 
                                                            extendedPriceInput.value + '</span>';
                                    
                                    // Insert after price value
                                    if (priceValue.parentNode) {
                                        priceValue.parentNode.insertBefore(subtotalSpan, priceValue.nextSibling);
                                    }
                                    
                                    // Hide the extended price field
                                    extendedPriceField.style.display = 'none';
                                } else {
                                    // Only update if the value has actually changed
                                    var subtotalDisplay = existingSubtotal.querySelector('.ginput_product_subtotal');
                                    if (subtotalDisplay && subtotalDisplay.textContent !== extendedPriceInput.value) {
                                        subtotalDisplay.textContent = extendedPriceInput.value;
                                    }
                                }
                                
                                // Store mapping of quantity field to relevant elements for updates
                                if (quantityField) {
                                    var fieldId = quantityField.id;
                                    quantityFieldsMap[fieldId] = {
                                        productField: productField,
                                        extendedPriceInput: extendedPriceInput,
                                        lastValue: extendedPriceInput.value // Track last value to avoid unnecessary updates
                                    };
                                    
                                    // Add direct event listener to quantity field
                                    if (!quantityField.hasAttribute('data-has-listener')) {
                                        quantityField.addEventListener('input', function() {
                                            // Debounce the update so it doesn't fire too frequently
                                            if (quantityField.updateTimeout) {
                                                clearTimeout(quantityField.updateTimeout);
                                            }
                                            quantityField.updateTimeout = setTimeout(function() {
                                                updateSubtotal(fieldId);
                                            }, 300);
                                        });
                                        
                                        quantityField.addEventListener('change', function() {
                                            // Debounce the update so it doesn't fire too frequently
                                            if (quantityField.updateTimeout) {
                                                clearTimeout(quantityField.updateTimeout);
                                            }
                                            quantityField.updateTimeout = setTimeout(function() {
                                                updateSubtotal(fieldId);
                                            }, 300);
                                        });
                                        
                                        // Mark as having listeners to avoid duplicates
                                        quantityField.setAttribute('data-has-listener', 'true');
                                    }
                                }
                            }
                        }
                    } catch(e) {
                        console.error('Error modifying product field:', productFieldId, e);
                    }
                });
                
                isInitialized = true;
            }
            
            // Function to update a specific subtotal display
            function updateSubtotal(quantityFieldId) {
                // Prevent recursive updates
                if (isUpdating) return;
                isUpdating = true;
                
                try {
                    var mapping = quantityFieldsMap[quantityFieldId];
                    if (!mapping) {
                        isUpdating = false;
                        return;
                    }
                    
                    var productField = mapping.productField;
                    var extendedPriceInput = mapping.extendedPriceInput;
                    var currentValue = extendedPriceInput.value;
                    
                    // Only update if the value has changed
                    if (mapping.lastValue !== currentValue) {
                        var subtotalDisplay = productField.querySelector('.ginput_product_subtotal');
                        if (subtotalDisplay) {
                            console.log('Updating subtotal for', quantityFieldId, 'to', currentValue);
                            subtotalDisplay.textContent = currentValue;
                            mapping.lastValue = currentValue;
                        }
                    }
                } catch (e) {
                    console.error('Error in updateSubtotal:', e);
                } finally {
                    isUpdating = false;
                }
            }
            
            // Function to update all subtotals
            function updateAllSubtotals() {
                // Don't proceed if we're not on the relevant page
                if (!isOnRelevantPage()) {
                    return;
                }
                
                // Prevent recursive updates
                if (isUpdating) return;
                isUpdating = true;
                
                try {
                    Object.keys(quantityFieldsMap).forEach(function(fieldId) {
                        var mapping = quantityFieldsMap[fieldId];
                        var currentValue = mapping.extendedPriceInput.value;
                        
                        // Only update if the value has changed
                        if (mapping.lastValue !== currentValue) {
                            var subtotalDisplay = mapping.productField.querySelector('.ginput_product_subtotal');
                            if (subtotalDisplay) {
                                subtotalDisplay.textContent = currentValue;
                                mapping.lastValue = currentValue;
                            }
                        }
                    });
                } catch (e) {
                    console.error('Error in updateAllSubtotals:', e);
                } finally {
                    isUpdating = false;
                }
            }
            
            // Set up a single, debounced mutation observer to avoid rapid firing
            var observerTimeout = null;
            var formObserver = new MutationObserver(function(mutations) {
                // Clear any existing timeout
                if (observerTimeout) {
                    clearTimeout(observerTimeout);
                }
                
                // Set a new timeout to debounce the updates
                observerTimeout = setTimeout(function() {
                    // If we're not initialized yet or not on the right page, try initializing first
                    if (!isInitialized || !isOnRelevantPage()) {
                        modifyProductLabels();
                    } else {
                        updateAllSubtotals();
                    }
                }, 500);
            });
            
            // Function to start/stop observers based on current page
            function manageObservers() {
                if (isOnRelevantPage()) {
                    // We're on the relevant page, initialize if needed
                    if (!isInitialized) {
                        modifyProductLabels();
                    }
                    
                    // Observe the form for changes
                    var formWrapper = document.querySelector('.gform_wrapper');
                    if (formWrapper) {
                        formObserver.observe(formWrapper, {
                            childList: true,
                            subtree: true,
                            attributes: true,
                            attributeFilter: ['value']
                        });
                    }
                } else {
                    // We're not on the relevant page, disconnect observer
                    formObserver.disconnect();
                    isInitialized = false;
                }
            }
            
            // Run on page load with a delay to let Gravity Forms initialize
            setTimeout(manageObservers, 500);
            
            // Also run after Gravity Forms renders or updates the form
            if (typeof jQuery !== 'undefined') {
                jQuery(document).on('gform_post_render', function() {
                    setTimeout(manageObservers, 200);
                });
                
                // Listen for page changes
                jQuery(document).on('gform_page_loaded', function() {
                    setTimeout(manageObservers, 200);
                });
            }
        });
    </script>
    <?php
}
add_action('wp_footer', 'custom_product_label_price_modifier', 999);

/**
 * FB Order Calculator - Sized for form 16
 * 
 * Only runs when Form 16 is present on the page. 
 */
function custom_fb_order_calculator_script() {
    // Short-circuit for non-form pages
    if (!is_form_16_page()) {
        return;
    }
    
    ?>
    <script type="text/javascript">
        // Check if Form 16 is actually present before initializing script
        document.addEventListener('DOMContentLoaded', function() {
            var formElement = document.getElementById('gform_16');
            
            // If form doesn't exist, don't initialize the script
            if (!formElement) {
                console.log('Form 16 not found in DOM, not initializing calculator script.');
                return;
            }
            
            console.log('Form 16 found, initializing calculator script');
            
            // Create a completely isolated function that won't trigger Gravity Forms pagination
            var fbCalculator = {
                // Store original field value
                originalValue: null,
                
                // Initialize calculator
                init: function() {
                    var self = this;
                    
                    // Find the source field using pure DOM methods
                    var sourceField = document.getElementById('input_16_24');
                    var targetField = document.getElementById('input_16_25');
                    
                    if (!sourceField || !targetField) {
                        return false;
                    }
                    
                    // Store original value
                    this.originalValue = sourceField.value;
                    
                    // Use direct event handler to avoid jQuery and Gravity Forms
                    sourceField.onchange = function() {
                        self.calculate();
                    };
                    
                    sourceField.oninput = function() {
                        self.calculate();
                    };
                    
                    // Initial calculation
                    this.calculate();
                    return true;
                },
                
                // Calculate size
                calculate: function() {
                    try {
                        var sourceField = document.getElementById('input_16_24');
                        var targetField = document.getElementById('input_16_25');
                        
                        if (!sourceField || !targetField) {
                            return;
                        }
                        
                        // Only proceed if value has changed
                        if (sourceField.value === this.originalValue) {
                            return;
                        }
                        
                        // Store new value
                        this.originalValue = sourceField.value;
                        
                        // Parse value safely
                        var calculatedValue = 0;
                        try {
                            calculatedValue = parseFloat(sourceField.value) || 0;
                        } catch(e) {
                            calculatedValue = 0;
                        }
                        
                        // Determine size category
                        var stringValue = '';
                        if (calculatedValue < 2) {
                            stringValue = 'Small';
                        } else if (calculatedValue >= 2 && calculatedValue < 12) {
                            stringValue = 'Medium';
                        } else if (calculatedValue >= 12 && calculatedValue < 15) {
                            stringValue = 'Large';
                        } else if (calculatedValue >= 15 && calculatedValue < 40) {
                            stringValue = 'Bulky';
                        } else if (calculatedValue >= 40) {
                            stringValue = 'Xtra Bulky';
                        }
                        
                        // Directly set value without using jQuery or triggering events
                        targetField.value = stringValue;
                    } catch(e) {
                        // Silent error handling to avoid breaking page
                        console.error('Error in FB calculator:', e);
                    }
                }
            };
            
            // Function to safely try initialization
            function tryInit() {
                try {
                    return fbCalculator.init();
                } catch(e) {
                    console.error('Error initializing FB calculator:', e);
                    return false;
                }
            }
            
            // Main page load attempt
            setTimeout(tryInit, 1000);
            
            // Look for popup events using native DOM
            document.addEventListener('click', function(e) {
                // Wait for possible popup to open
                setTimeout(tryInit, 1500);
            });
            
            // Periodic check as a fallback
            var checkInterval = setInterval(function() {
                if (tryInit()) {
                    // Successfully initialized, reduce checking frequency
                    clearInterval(checkInterval);
                    
                    // Set a longer interval check for when popup reopens
                    setInterval(tryInit, 5000);
                }
            }, 2000);
            
            // Expose global initialization function
            window.initFBCalculator = tryInit;
        });
    </script>
    <?php
}
add_action('wp_footer', 'custom_fb_order_calculator_script', 999);

/**
 * Changes the label of the credit field to be more user-friendly.
 */
function custom_price_field_label_alt($label, $form_id, $field) {
    if ($form_id == 13 && $field->id == 187 && $label == 'Additional Costs') {
        return 'Delivery Credit Applied';
    }
    return $label;
}
add_filter('gform_field_label', 'custom_price_field_label_alt', 10, 3);

/**
 * Override Gravity Forms validation to allow negative numbers in credit field.
 */
function allow_negative_credit_values($result, $value, $form, $field) {
    // Always return valid for this specific field
    return array(
        'is_valid' => true,
        'message' => ''
    );
}
add_filter('gform_field_validation_13_187', 'allow_negative_credit_values', 10, 4);