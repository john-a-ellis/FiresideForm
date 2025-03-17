<?php
/*
Plugin Name: FB Online Order Calculator - Pagination Fix
Author: John Ellis - NearNorthAnalytics
Description: Fixed calculator for Gravity Forms with pagination error protection.
Version: 1.5
*/

// Add script to footer
add_action('wp_footer', function() {
    ?>
    <script type="text/javascript">
    /* FB Calculator with pagination fix */
    
    // Wait for window load
    window.addEventListener('load', function() {
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
                }
            }
        };
        
        // Function to safely try initialization
        function tryInit() {
            try {
                return fbCalculator.init();
            } catch(e) {
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
}, 999);

// Add a safety fallback that doesn't use jQuery at all
add_action('wp_footer', function() {
    ?>
    <script type="text/javascript">
    // Fallback script that runs after page is fully loaded
    window.addEventListener('load', function() {
        // Wait an extra 3 seconds for everything to stabilize
        setTimeout(function() {
            // Direct DOM manipulation without any framework dependencies
            try {
                var input = document.getElementById('input_16_24');
                var output = document.getElementById('input_16_25');
                
                if (input && output) {
                    // Create a MutationObserver to watch for value changes
                    var observer = new MutationObserver(function(mutations) {
                        mutations.forEach(function(mutation) {
                            if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
                                updateValue();
                            }
                        });
                    });
                    
                    // Watch for attribute changes
                    observer.observe(input, { 
                        attributes: true, 
                        attributeFilter: ['value'] 
                    });
                    
                    // Also set up regular events
                    input.addEventListener('change', updateValue);
                    input.addEventListener('input', updateValue);
                    
                    // Function to update the value
                    function updateValue() {
                        var value = parseFloat(input.value) || 0;
                        var size = '';
                        
                        if (value < 2) size = 'Small';
                        else if (value < 12) size = 'Medium';
                        else if (value < 15) size = 'Large';
                        else if (value < 40) size = 'Bulky';
                        else size = 'Xtra Bulky';
                        
                        // Set output value directly
                        output.value = size;
                    }
                    
                    // Initial update
                    updateValue();
                }
            } catch(e) {
                // Silent failure
            }
        }, 3000);
    });
    </script>
    <?php
}, 1000); // Even higher priority