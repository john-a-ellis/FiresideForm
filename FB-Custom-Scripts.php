<?php
/**
 * Plugin Name: FB Gravity Forms Custom Scripts
 * Description: Custom script for copying Billing Address to Pickup Address, updating Shipping Charge, and copying date values in Gravity Forms.
 * Version: 1.5 Author: John Ellis - NearNorthAnalytics
 */

function custom_gravity_forms_copy_address_script() {
    ?>
    <script type="text/javascript">
        console.log('Initializing address copy script');

        document.addEventListener('DOMContentLoaded', function () {
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
                console.log('Date field changed - Element:', this);
                var dateValue = jQuery(this).val();
                console.log('Date value before storage:', dateValue);
                localStorage.setItem('gform_13_date_18', dateValue);
                
                // Verify the storage worked
                var verifyStorage = localStorage.getItem('gform_13_date_18');
                console.log('Verification - Retrieved from storage:', verifyStorage);
            });

            // Listen for page changes in the form
            jQuery(document).on('gform_post_render', function(event, formId, currentPage) {
                if (formId == 13) {
                    console.log('Form rendered - Current Page:', currentPage);
                    
                    var storedDate = localStorage.getItem('gform_13_date_18');
                    console.log('Checking storage - Stored Date:', storedDate);
                    
                    if (storedDate) {
                        var prefix ="Your Pickup is Scheduled for: "
                        console.log('Found stored date, attempting to update display');
                        jQuery('#date-display-18').text(prefix + storedDate);
                    } else {
                        console.log('No stored date found in localStorage');
                    }
                }
            });
        });
    </script>
    <?php
}
add_action('wp_footer', 'custom_gravity_forms_copy_address_script');

function custom_gravity_forms_shipping_charge_script() {
    ?>
    <script type="text/javascript">
        console.log('Initializing shipping charge script');

        document.addEventListener('DOMContentLoaded', function () {
            // Form ID
            var formId = 13;
            // Field IDs
            var postalCodeFieldId = 146;
            var shippingFieldId = 128;

            // Function to update shipping charge based on FSA
            function updateShippingCharge(fsa, postalCodeElement) {
                var shippingCharge = 0;

                // Define your FSAs and corresponding shipping charges here
                var shippingRates = {
                    'L0C':325,
                    'L0E':325,
                    'L0G':275,
                    'L1A':300,
                    'L1C':275,
                    'l1E':275,
                    'L1G':250,
                    'L1H':250,
                    'L1J':250,
                    'L1K':250,
                    'L1L':250,
                    'L1M':225,
                    'L1N':225,
                    'L1P':225,
                    'L1R':225,
                    'L1S':200,
                    'L1T':200,
                    'L1V':200,
                    'L1W':200,
                    'L1X':200,
                    'L1Y':200,
                    'L1Z':200,
                    'L3P':225,
                    'L3R':225,
                    'L3S':225,
                    'L3T':200,
                    'L3X':275,
                    'L3Y':275,
                    'L4A':250,
                    'L4B':225,
                    'L4C':225,
                    'L4E':225,
                    'L4G':250,
                    'L4J':200,
                    'L4K':200,
                    'L4L':200,
                    'L4P':325,
                    'L4S':225,
                    'L4T':150,
                    'L4V':150,
                    'L4W':150,
                    'L4X':150,
                    'L4Y':150,
                    'L4Z':150,
                    'L5A':150,
                    'L5B':150,
                    'L5C':150,
                    'L5E':150,
                    'L5G':150,
                    'L5H':150,
                    'L5J':150,
                    'L5K':150,
                    'L5L':150,
                    'L5M':150,
                    'L5N':150,
                    'L5P':150,
                    'L5R':150,
                    'L5S':150,
                    'L5T':150,
                    'L5V':150,
                    'L5W':150,
                    'L6A':200,
                    'L6B':225,
                    'L6C':225,
                    'L6E':225,
                    'L6H':200,
                    'L6J':200,
                    'L6K':200,
                    'L6L':200,
                    'L6M':200,
                    'L6P':150,
                    'L6R':150,
                    'L6S':150,
                    'L6T':150,
                    'L6V':150,
                    'L6W':150,
                    'L6X':150,
                    'L6Y':150,
                    'L6Z':150,
                    'L7A':150,
                    'L7B':250,
                    'L7E':250,
                    'L7G':225,
                    'L7L':225,
                    'L7M':225,
                    'L7N':225,
                    'L7P':225,
                    'L7R':225,
                    'L7T':225,
                    'L9L':300,
                    'L9N':275,
                    'L9N':300,
                    'L9T':225,
                    'M1B':150,
                    'M1C':150,
                    'M1E':150,
                    'M1G':150,
                    'M1H':150,
                    'M1J':150,
                    'M1K':150,
                    'M1L':150,
                    'M1M':150,
                    'M1N':150,
                    'M1P':150,
                    'M1R':150,
                    'M1S':150,
                    'M1T':150,
                    'M1V':150,
                    'M1W':150,
                    'M1X':150,
                    'M2A':150,
                    'M2B':150,
                    'M2C':150,
                    'M2D':150,
                    'M2E':150,
                    'M2F':150,
                    'M2G':150,
                    'M2H':150,
                    'M2I':150,
                    'M2J':150,
                    'M2K':150,
                    'M2L':150,
                    'M2M':150,
                    'M2N':150,
                    'M2O':150,
                    'M2P':150,
                    'M2Q':150,
                    'M2R':150,
                    'M2S':150,
                    'M2T':150,
                    'M2U':150,
                    'M2V':150,
                    'M2W':150,
                    'M2X':150,
                    'M2Y':150,
                    'M2Z':150,
                    'M3A':150,
                    'M3B':150,
                    'M3C':150,
                    'M3D':150,
                    'M3E':150,
                    'M3F':150,
                    'M3G':150,
                    'M3H':150,
                    'M3I':150,
                    'M3J':150,
                    'M3K':150,
                    'M3L':150,
                    'M3M':150,
                    'M3N':150,
                    'M3O':150,
                    'M3P':150,
                    'M3Q':150,
                    'M3R':150,
                    'M3S':150,
                    'M3T':150,
                    'M3U':150,
                    'M3V':150,
                    'M3W':150,
                    'M3X':150,
                    'M3Y':150,
                    'M3Z':150,
                    'M4A':150,
                    'M4B':150,
                    'M4C':150,
                    'M4D':150,
                    'M4E':150,
                    'M4F':150,
                    'M4G':150,
                    'M4H':150,
                    'M4I':150,
                    'M4J':150,
                    'M4K':150,
                    'M4L':150,
                    'M4M':150,
                    'M4N':150,
                    'M4O':150,
                    'M4P':150,
                    'M4Q':150,
                    'M4R':150,
                    'M4S':150,
                    'M4T':150,
                    'M4U':150,
                    'M4V':150,
                    'M4W':150,
                    'M4X':150,
                    'M4Y':150,
                    'M4Z':150,
                    'M5A':150,
                    'M5B':150,
                    'M5C':150,
                    'M5D':150,
                    'M5E':150,
                    'M5F':150,
                    'M5G':150,
                    'M5H':150,
                    'M5I':150,
                    'M5J':150,
                    'M5K':150,
                    'M5L':150,
                    'M5M':150,
                    'M5N':150,
                    'M5O':150,
                    'M5P':150,
                    'M5Q':150,
                    'M5R':150,
                    'M5S':150,
                    'M5T':150,
                    'M5U':150,
                    'M5V':150,
                    'M5W':150,
                    'M5X':150,
                    'M5Y':150,
                    'M5Z':150,
                    'M6A':150,
                    'M6B':150,
                    'M6C':150,
                    'M6D':150,
                    'M6E':150,
                    'M6F':150,
                    'M6G':150,
                    'M6H':150,
                    'M6I':150,
                    'M6J':150,
                    'M6K':150,
                    'M6L':150,
                    'M6M':150,
                    'M6N':150,
                    'M6O':150,
                    'M6P':150,
                    'M6Q':150,
                    'M6R':150,
                    'M6S':150,
                    'M6T':150,
                    'M6U':150,
                    'M6V':150,
                    'M6W':150,
                    'M6X':150,
                    'M6Y':150,
                    'M6Z':150,
                    'M8V':150,
                    'M8W':150,
                    'M8X':150,
                    'M8Y':150,
                    'M8Z':150,
                    'M9V':150,
                    'M9W':150,
                    'M7A':150,
                    'M7R':150,
                    'M7Y':150,
                    'M9A':150,
                    'M9B':150,
                    'M9C':150,
                    'M9L':150,
                    'M9M':150,
                    'M9N':150,
                    'M9P':150,
                    'M9R':150
                };

                if (shippingRates[fsa]) {
                    shippingCharge = shippingRates[fsa];
                    // Update the shipping field value
                    jQuery('#input_' + formId + '_' + shippingFieldId).val(shippingCharge);
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

function custom_gravity_forms_item_credit_script() {
    ?>
    <script type="text/javascript">
        console.log('Initializing item credit calculation script - GF 2.9.4 with fixed quantity');

        document.addEventListener('DOMContentLoaded', function () {
            // Form ID
            var formId = 13;
            
            // Field IDs
            var itemFieldIds = [96, 97, 98, 100, 101]; // Item cost fields
            var deliveryChargeFieldId = 128; // Delivery charge field
            var creditFieldId = 187; // Credit field (hidden product field)
            
            // Function to calculate and apply credit
            function calculateAndApplyCredit() {
                console.log('Calculating and applying credit');
                
                // Log the current form total before changes
                logFormTotal('Before credit calculation');
                
                // Get delivery charge
                var deliveryChargeField = jQuery('#input_' + formId + '_' + deliveryChargeFieldId);
                var deliveryChargeValue = deliveryChargeField.val() || '0';
                deliveryChargeValue = deliveryChargeValue.replace(/[^0-9.-]+/g, '');
                var deliveryCharge = parseFloat(deliveryChargeValue) || 0;
                console.log('Delivery charge value:', deliveryCharge);
                
                // Calculate total item cost
                var totalItemCost = 0;
                itemFieldIds.forEach(function(fieldId) {
                    var itemField = jQuery('#input_' + formId + '_' + fieldId);
                    var itemValue = itemField.val() || '0';
                    itemValue = itemValue.replace(/[^0-9.-]+/g, '');
                    var itemCost = parseFloat(itemValue) || 0;
                    console.log('Item field ' + fieldId + ' cost:', itemCost);
                    totalItemCost += itemCost;
                });
                console.log('Total item cost:', totalItemCost);
                
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
                
                console.log('Final credit calculation:', credit);
                
                // Format values for hidden product field
                var numericCredit = credit.toString();
                var formattedPrice = '-$ ' + Math.abs(credit).toFixed(2) + ' CAD';
                console.log('Numeric credit:', numericCredit, 'Formatted price:', formattedPrice);
                
                // Update all parts of the hidden product field
                updateHiddenProductField(creditFieldId, numericCredit, formattedPrice);
                
                // Wait a moment and log the form total after changes
                setTimeout(function() {
                    logFormTotal('After credit calculation');
                }, 500);
            }
            
            // Function to log the current form total
            function logFormTotal(label) {
                // Try different selectors for the form total
                var totalSelectors = [
                    '#gform_review_' + formId + ' .ginput_total_' + formId,  // Review page total
                    '.ginput_total_' + formId,                               // Standard total field
                    '#gform_wrapper_' + formId + ' .ginput_total',           // Generic total field
                    '#gfield_total_' + formId + '_total',                    // Total field element
                    '.gform_total'                                           // Any total field
                ];
                
                var totalFound = false;
                
                totalSelectors.forEach(function(selector) {
                    var totalElement = jQuery(selector);
                    if (totalElement.length > 0) {
                        var totalValue = totalElement.text() || totalElement.val();
                        console.log(label + ' - Form total (' + selector + '):', totalValue);
                        totalFound = true;
                    }
                });
                
                // Try to get total from gform_product_total global variable
                if (window.gform_product_total) {
                    console.log(label + ' - Global product total:', window.gform_product_total);
                    totalFound = true;
                }
                
                if (!totalFound) {
                    console.log(label + ' - Could not find form total element');
                    
                    // Log all form inputs for debugging
                    console.log('All form inputs:');
                    jQuery('#gform_' + formId + ' input').each(function() {
                        var input = jQuery(this);
                        var name = input.attr('name');
                        var id = input.attr('id');
                        var value = input.val();
                        if (name && name.includes('total') || id && id.includes('total')) {
                            console.log('Potential total field:', name || id, 'Value:', value);
                        }
                    });
                }
                
                // Log credit field values for reference
                var creditQuantity = jQuery('input[name="input_' + creditFieldId + '.3"]').val();
                var creditPrice = jQuery('input[name="input_' + creditFieldId + '.1"]').val();
                var creditFormatted = jQuery('input[name="input_' + creditFieldId + '.2"]').val();
                
                console.log(label + ' - Credit field values:', {
                    quantity: creditQuantity,
                    price: creditPrice,
                    formatted: creditFormatted
                });
            }
            
            // Function to update all parts of a hidden product field
            function updateHiddenProductField(fieldId, value, formattedValue) {
                // Based on your HTML, we need to update three hidden inputs:
                
                // 1. Update quantity input (input_187.3) - ALWAYS SET TO 1
                var quantityInput = jQuery('input[name="input_' + fieldId + '.3"]');
                if (quantityInput.length > 0) {
                    quantityInput.val("1");
                    console.log('Updated quantity input with fixed value: 1');
                } else {
                    console.error('Quantity input not found');
                }
                
                // 2. Update price input (input_187.1) with the credit value
                var priceInput = jQuery('input[name="input_' + fieldId + '.1"]');
                if (priceInput.length > 0) {
                    priceInput.val(value);
                    console.log('Updated price input with value:', value);
                } else {
                    console.error('Price input not found');
                }
                
                // 3. Update base price input with formatted value (input_187.2)
                var basePriceInput = jQuery('input[name="input_' + fieldId + '.2"]');
                if (basePriceInput.length > 0) {
                    basePriceInput.val(formattedValue);
                    console.log('Updated base price input with value:', formattedValue);
                } else {
                    console.error('Base price input not found');
                }
                
                // 4. Additional attempt using field ID
                var ginputBasePriceElement = jQuery('#ginput_base_price_' + formId + '_' + fieldId);
                if (ginputBasePriceElement.length > 0) {
                    ginputBasePriceElement.val(formattedValue);
                    console.log('Updated ginput_base_price element with value:', formattedValue);
                }
                
                var ginputQuantityElement = jQuery('#ginput_quantity_' + formId + '_' + fieldId);
                if (ginputQuantityElement.length > 0) {
                    ginputQuantityElement.val("1");
                    console.log('Updated ginput_quantity element with fixed value: 1');
                }
                
                // 5. Trigger form total recalculation using GF 2.9.4 methods
                if (window.gf_global && window.gf_global.gfcalc && typeof window.gf_global.gfcalc.runCalcs === 'function') {
                    // GF 2.9+ method
                    try {
                        window.gf_global.gfcalc.runCalcs(formId);
                        console.log('Triggered gf_global.gfcalc.runCalcs');
                    } catch (e) {
                        console.error('Error triggering gf_global.gfcalc.runCalcs:', e);
                    }
                } else if (typeof gformCalculateTotalPrice === 'function') {
                    // Fallback to older method
                    try {
                        gformCalculateTotalPrice(formId);
                        console.log('Triggered gformCalculateTotalPrice');
                    } catch (e) {
                        console.error('Error triggering gformCalculateTotalPrice:', e);
                    }
                }
                
                // 6. Extra step for GF 2.9.4: manually trigger the product price calculation
                try {
                    jQuery(document).trigger('gform_product_total_changed', [formId]);
                    console.log('Triggered gform_product_total_changed event');
                } catch (e) {
                    console.error('Error triggering product total changed event:', e);
                }
            }
            
            // Monitor changes to item cost fields
            itemFieldIds.forEach(function(fieldId) {
                jQuery(document).on('change keyup blur', '#input_' + formId + '_' + fieldId, function() {
                    console.log('Item field ' + fieldId + ' changed');
                    calculateAndApplyCredit();
                });
            });
            
            // Monitor changes to delivery charge field
            jQuery(document).on('change keyup blur', '#input_' + formId + '_' + deliveryChargeFieldId, function() {
                console.log('Delivery charge field changed');
                calculateAndApplyCredit();
            });
            
            // Calculate on form render
            jQuery(document).on('gform_post_render', function(event, renderedFormId, currentPage) {
                if (renderedFormId == formId) {
                    console.log('Form rendered - calculating credit');
                    setTimeout(calculateAndApplyCredit, 500);
                }
            });
            
            // Listen for price calculation events
            jQuery(document).on('gform_price_change', function(event, formEventId, fieldId) {
                console.log('Price change detected on form', formEventId, 'field', fieldId);
                if (formEventId == formId) {
                    setTimeout(calculateAndApplyCredit, 100);
                }
            });
            
            // Listen for total change events
            jQuery(document).on('gform_product_total_changed', function(event, formEventId) {
                console.log('Product total changed on form', formEventId);
                if (formEventId == formId) {
                    setTimeout(function() {
                        logFormTotal('After total changed event');
                    }, 200);
                }
            });
            
            // Initial calculation
            jQuery(document).ready(function() {
                console.log('Document ready - initial credit calculation');
                setTimeout(calculateAndApplyCredit, 1000);
                
                // Additional attempt after a longer delay
                setTimeout(calculateAndApplyCredit, 2500);
                
                // Final attempt after page is fully loaded
                jQuery(window).on('load', function() {
                    setTimeout(calculateAndApplyCredit, 1000);
                });
            });
        });
    </script>
    <?php
}
add_action('wp_footer', 'custom_gravity_forms_item_credit_script');

// Set the label of the User Defined Product field
add_filter('gform_field_label', 'custom_price_field_label_alt', 10, 3);
function custom_price_field_label_alt($label, $form_id, $field) {
    error_log("Label filter triggered - Form ID: $form_id, Field ID: {$field->id}, Label: $label");
    
    if ($form_id == 13 && $field->id == 187 && $label == 'Additional Costs') {
    return 'Delivery Credit Applied';
    }
    
    return $label;
}
// Override Gravity Forms validation to allow negative numbers in credit field
add_filter('gform_field_validation_13_187', 'allow_negative_credit_values', 10, 4);
function allow_negative_credit_values($result, $value, $form, $field) {
    // Always return valid for this specific field
    return array(
        'is_valid' => true,
        'message' => ''
    );
}
?>