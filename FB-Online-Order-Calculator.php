<?php
/**
 * Plugin Name: FB Online Order Calculator
 * Description: Automatically calculates size categories based on numeric input in Gravity Forms, with special handling for pagination issues
 * Author: John Ellis - NearNorthAnalytics
 * Version: 1.6
 * 
 * This plugin contains two separate calculator implementations:
 * 1. Primary implementation: Uses pure vanilla JavaScript for maximum compatibility
 * 2. Fallback implementation: Uses MutationObserver for difficult edge cases
 * 
 * Both implementations avoid jQuery dependency chains and Gravity Forms event handlers
 * that were causing pagination issues in the original implementation.
 */

/**
 * Primary calculator implementation using vanilla JavaScript.
 * Uses direct DOM methods to avoid Gravity Forms pagination issues.
 * 
 * @priority 999 - Ensures this runs after Gravity Forms has initialized
 */
add_action('wp_footer', function() {
    ?>
    <script type="text/javascript">
    /**
     * FB Size Calculator - Primary Implementation
     * 
     * This calculator converts numeric cubic feet values to size categories.
     * It uses isolated JavaScript to prevent conflicts with Gravity Forms pagination.
     */
    window.addEventListener('load', function() {
        // Self-contained calculator object with no external dependencies
        var fbCalculator = {
            // Track original value to prevent unnecessary updates
            originalValue: null,
            
            /**
             * Initialize calculator and set up event listeners
             * @return {boolean} True if initialization succeeded, false otherwise
             */
            init: function() {
                var self = this;
                
                // Find form fields using direct DOM methods (no jQuery)
                var sourceField = document.getElementById('input_16_24'); // Cubic feet input
                var targetField = document.getElementById('input_16_25'); // Size category output
                
                // Exit if fields aren't found
                if (!sourceField || !targetField) {
                    return false;
                }
                
                // Store initial value
                this.originalValue = sourceField.value;
                
                // Set up direct event handlers (avoiding jQuery)
                sourceField.onchange = function() {
                    self.calculate();
                };
                
                sourceField.oninput = function() {
                    self.calculate();
                };
                
                // Run initial calculation
                this.calculate();
                return true;
            },
            
            /**
             * Calculate size category based on cubic feet value
             * Size thresholds:
             * - Small: < 2 cubic feet
             * - Medium: 2-11.99 cubic feet
             * - Large: 12-14.99 cubic feet
             * - Bulky: 15-39.99 cubic feet
             * - Xtra Bulky: >= 40 cubic feet
             */
            calculate: function() {
                try {
                    var sourceField = document.getElementById('input_16_24');
                    var targetField = document.getElementById('input_16_25');
                    
                    if (!sourceField || !targetField) {
                        return;
                    }
                    
                    // Only update if value has changed (prevents event loops)
                    if (sourceField.value === this.originalValue) {
                        return;
                    }
                    
                    // Update tracked value
                    this.originalValue = sourceField.value;
                    
                    // Parse numeric value safely
                    var calculatedValue = 0;
                    try {
                        calculatedValue = parseFloat(sourceField.value) || 0;
                    } catch(e) {
                        calculatedValue = 0;
                    }
                    
                    // Determine size category based on value
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
                    
                    // Update target field without triggering form events
                    targetField.value = stringValue;
                } catch(e) {
                    // Silent error handling to avoid breaking page
                }
            }
        };
        
        /**
         * Safely attempt to initialize the calculator
         * @return {boolean} Success state of initialization
         */
        function tryInit() {
            try {
                return fbCalculator.init();
            } catch(e) {
                console.log("FB Calculator init failed, will retry");
                return false;
            }
        }
        
        // Initial attempt after a short delay
        setTimeout(tryInit, 1000);
        
        // Monitor for popup events or dynamic content loading
        document.addEventListener('click', function() {
            // Delayed attempt after potential popup opening
            setTimeout(tryInit, 1500);
        });
        
        // Fallback periodic check until initialization succeeds
        var checkInterval = setInterval(function() {
            if (tryInit()) {
                // Successfully initialized, reduce checking frequency
                clearInterval(checkInterval);
                
                // Set up a less frequent check for form reloads/popups
                setInterval(tryInit, 5000);
            }
        }, 2000);
        
        // Make initialization function globally available for other scripts
        window.initFBCalculator = tryInit;
    });
    </script>
    <?php
}, 999);

/**
 * Fallback calculator implementation using MutationObserver.
 * This provides a completely separate implementation to ensure the calculation works
 * even if the primary implementation fails.
 * 
 * @priority 1000 - Ensures this runs after the primary implementation
 */
add_action('wp_footer', function() {
    ?>
    <script type="text/javascript">
    /**
     * FB Size Calculator - Fallback Implementation
     * 
     * Uses MutationObserver to handle cases where the primary implementation fails.
     * This is a completely independent implementation as a safety net.
     */
    window.addEventListener('load', function() {
        // Delayed execution to ensure form is fully loaded
        setTimeout(function() {
            try {
                // Use direct DOM methods (no jQuery)
                var input = document.getElementById('input_16_24');  // Cubic feet input
                var output = document.getElementById('input_16_25'); // Size category output
                
                if (input && output) {
                    // Use MutationObserver to detect DOM changes
                    var observer = new MutationObserver(function(mutations) {
                        mutations.forEach(function(mutation) {
                            if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
                                updateValue();
                            }
                        });
                    });
                    
                    // Set up observer to watch value attribute
                    observer.observe(input, { 
                        attributes: true, 
                        attributeFilter: ['value'] 
                    });
                    
                    // Also set up standard event listeners for direct interactions
                    input.addEventListener('change', updateValue);
                    input.addEventListener('input', updateValue);
                    
                    /**
                     * Update the size category based on input value
                     * Uses same thresholds as primary implementation:
                     * - Small: < 2 cubic feet
                     * - Medium: 2-11.99 cubic feet
                     * - Large: 12-14.99 cubic feet
                     * - Bulky: 15-39.99 cubic feet
                     * - Xtra Bulky: >= 40 cubic feet
                     */
                    function updateValue() {
                        var value = parseFloat(input.value) || 0;
                        var size = '';
                        
                        if (value < 2) size = 'Small';
                        else if (value < 12) size = 'Medium';
                        else if (value < 15) size = 'Large';
                        else if (value < 40) size = 'Bulky';
                        else size = 'Xtra Bulky';
                        
                        // Update output field directly
                        output.value = size;
                    }
                    
                    // Run initial calculation
                    updateValue();
                }
            } catch(e) {
                // Silent failure to prevent breaking the page
            }
        }, 3000);
    });
    </script>
    <?php
}, 1000);
?>