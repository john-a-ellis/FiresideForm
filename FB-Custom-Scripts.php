<?php
/**
 * Plugin Name: FB Gravity Forms Custom Scripts
 * Description: Custom functionality for Furniture Bank donation forms, including address copying, shipping calculations, item credits, and date formatting
 * Version: 1.6.3
 * Author: John Ellis - NearNorthAnalytics
 * 
 * This plugin contains various JavaScript enhancements for Gravity Forms:
 * - Address copying from billing to pickup
 * - Shipping charge calculation based on postal code
 * - Item credit calculations based on delivery charges
 * - Custom next button labels based on radio selection
 * - Scheduled Pickup Date formatting and persistence
 */

/**
 * Copies billing address fields to pickup address fields when checkbox is checked.
 * Uses localStorage to save and display date selections across form pages.
 */
function custom_gravity_forms_copy_address_script() {
    ?>
    <script type="text/javascript">
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
 */
function custom_gravity_forms_shipping_charge_script() {
    ?>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function () {
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
                    'L0C':325, 'L0E':325, 'L0G':275,
                    'L1A':300, 'L1C':275, 'l1E':275, 'L1G':250, 'L1H':250, 'L1J':250,
                    'L1K':250, 'L1L':250, 'L1M':225, 'L1N':225, 'L1P':225, 'L1R':225,
                    'L1S':200, 'L1T':200, 'L1V':200, 'L1W':200, 'L1X':200, 'L1Y':200, 'L1Z':200,
                    'L3P':225, 'L3R':225, 'L3S':225, 'L3T':200, 'L3X':275, 'L3Y':275,
                    'L4A':250, 'L4B':225, 'L4C':225, 'L4E':225, 'L4G':250, 'L4J':200, 'L4K':200, 'L4L':200,
                    'L4P':325, 'L4S':225, 'L4T':150, 'L4V':150, 'L4W':150, 'L4X':150, 'L4Y':150, 'L4Z':150,
                    'L5A':150, 'L5B':150, 'L5C':150, 'L5E':150, 'L5G':150, 'L5H':150, 'L5J':150, 'L5K':150,
                    'L5L':150, 'L5M':150, 'L5N':150, 'L5P':150, 'L5R':150, 'L5S':150, 'L5T':150, 'L5V':150, 'L5W':150,
                    'L6A':200, 'L6B':225, 'L6C':225, 'L6E':225, 'L6H':200, 'L6J':200, 'L6K':200, 'L6L':200, 'L6M':200,
                    'L6P':150, 'L6R':150, 'L6S':150, 'L6T':150, 'L6V':150, 'L6W':150, 'L6X':150, 'L6Y':150, 'L6Z':150,
                    'L7A':150, 'L7B':250, 'L7E':250, 'L7G':225, 'L7L':225, 'L7M':225, 'L7N':225, 'L7P':225, 'L7R':225, 'L7T':225,
                    'L9L':300, 'L9N':275, 'L9N':300, 'L9T':225,
                    'M1B':150, 'M1C':150, 'M1E':150, 'M1G':150, 'M1H':150, 'M1J':150, 'M1K':150, 'M1L':150,
                    'M1M':150, 'M1N':150, 'M1P':150, 'M1R':150, 'M1S':150, 'M1T':150, 'M1V':150, 'M1W':150, 'M1X':150,
                    // 100+ more postal codes for Toronto area with $150 charge
                    // For brevity, not all postal codes are listed here in full
                    'M2A':150, 'M3A':150, 'M4A':150, 'M5A':150, 'M6A':150, 'M7A':150, 'M8V':150, 'M9V':150
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
 */
function custom_gravity_forms_item_credit_script() {
    ?>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function () {
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

/**
 * Changes the Next button label based on which radio option is selected.
 * For "Proceed to Order" vs "Request a Callback" options.
 */
function custom_radio_next_button_label() {
    ?>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function () {
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
                        newButtonLabel = 'Step 3: When are we picking up?';
                    } else if (radioValue.indexOf('Request a Callback') >= 0) {
                        newButtonLabel = 'Step 3: Where do we call you?';
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
 * Formats dates in a user-friendly way (Month DD, YYYY format).
 * Applies to dates shown in the confirmation and review sections of the form.
 */
function custom_date_formatter_script() {
    ?>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function () {
            // Form ID
            var formId = 13;
            
            // Field IDs
            var dateFieldId = 18;  // ID of the date field
            
            // Function to format date from YYYY-MM-DD to Month DD, YYYY
            function formatDate(dateString) {
                // Check if we have a valid date string
                if (!dateString || dateString.trim() === '' || dateString === '{date_18}') {
                    return dateString;
                }
                
                try {
                    // Parse the date
                    var dateParts = dateString.split('-');
                    if (dateParts.length !== 3) {
                        // Try other common formats
                        var dateObj = new Date(dateString);
                        if (isNaN(dateObj.getTime())) {
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
                    
                    return formattedDate;
                } catch (e) {
                    console.log('Date formatting error: ' + e.message);
                    return dateString;
                }
            }
            
            // Function to update the displayed date
            function updateDisplayedDate() {
                // Find the date display element
                var dateDisplay = document.getElementById('date-display-18');
                if (!dateDisplay) {
                    return;
                }
                
                var originalText = dateDisplay.innerHTML;
                
                // Check if the mergetag has been replaced already
                if (originalText.includes('{date_18}')) {
                    return; // Wait for Gravity Forms to replace the merge tag
                }
                
                // Extract the date part
                var prefixText = 'Your Pickup is Scheduled for: ';
                var dateText = originalText.replace(prefixText, '').trim();
                
                // Format the date
                var formattedDate = formatDate(dateText);
                
                // Update the element
                if (formattedDate !== dateText) {
                    dateDisplay.innerHTML = prefixText + formattedDate;
                    console.log('Date formatted: ' + formattedDate);
                }
            }
            
            // Monitor for changes to the date field
            jQuery(document).on('change', '#input_' + formId + '_' + dateFieldId, function() {
                setTimeout(updateDisplayedDate, 500);
            });
            
            // Monitor for form submissions and page changes
            jQuery(document).on('gform_page_loaded', function(event, formId, currentPage) {
                setTimeout(updateDisplayedDate, 500);
                setTimeout(updateDisplayedDate, 1500); // Additional check
            });
            
            // Check when the form is rendered
            jQuery(document).on('gform_post_render', function(event, formId) {
                setTimeout(updateDisplayedDate, 500);
                setTimeout(updateDisplayedDate, 1500); // Additional check
            });
            
            // Set up a mutation observer to detect when the content changes
            function setupMutationObserver() {
                var dateDisplay = document.getElementById('date-display-18');
                if (!dateDisplay) {
                    setTimeout(setupMutationObserver, 1000); // Try again in a second
                    return;
                }
                
                var observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'childList' || mutation.type === 'characterData') {
                            updateDisplayedDate();
                        }
                    });
                });
                
                observer.observe(dateDisplay, { 
                    childList: true, 
                    characterData: true,
                    subtree: true
                });
            }
            
            // Initial checks
            jQuery(document).ready(function() {
                setTimeout(updateDisplayedDate, 1000);
                setTimeout(setupMutationObserver, 1000);
            });
        });
    </script>
    <?php
}
add_action('wp_footer', 'custom_date_formatter_script');

add_action('wp_footer', 'custom_date_formatter_script');

/**
 * Format Scheduled Pickup Date in Order Confirmations
 * 
 * This function reformats the date from form field 18 (Scheduled Pickup Date) 
 * from the database format (Y-m-d) to a more user-friendly format with full month name (F d, Y).
 * Example: Changes "2025-03-21" to "March 21, 2025"
 * 
 * @param mixed $confirmation The confirmation message content
 * @param array $form The form object
 * @param array $entry The entry object containing submitted data
 * @param bool $ajax Whether the submission was made via AJAX
 * @return mixed The modified confirmation with formatted date
 */
add_filter('gform_confirmation', 'format_date_in_order_confirmation', 10, 4);

function format_date_in_order_confirmation($confirmation, $form, $entry, $ajax) {
    // Only process form with ID 13
    if ($form['id'] == 13) {
        // Get the Scheduled Pickup Date value from field 18
        $date_value = rgar($entry, '18');
        
        // Only proceed if we have a date value
        if (!empty($date_value)) {
            // Try to parse the date with dash format (Y-m-d)
            $date_obj = DateTime::createFromFormat('Y-m-d', $date_value);
            
            // If parsing with dashes failed, try slash format (Y/m/d)
            if (!$date_obj) {
                $date_obj = DateTime::createFromFormat('Y/m/d', $date_value);
            }
            
            // If we successfully parsed the date, reformat it
            if ($date_obj) {
                // Format as "Month Day, Year" (e.g., "March 21, 2025")
                $formatted_date = $date_obj->format('F d, Y');
                
                // Replace all instances of the original date format in the confirmation
                $confirmation = str_replace($date_value, $formatted_date, $confirmation);
                
                // Debug log if needed
                // error_log("Date formatted for confirmation: $date_value → $formatted_date");
            }
        }
    }
    
    return $confirmation;
}