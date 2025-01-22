<?php
/*
Plugin Name: Gravity Forms Custom Script
Description: Adds custom JavaScript for Gravity Forms.
Version: 1.0
*/

function item_size_calculator_script() {
    if (is_page('FB-Item-Size-Calculator')) { // Replace with the correct conditional tag for your form page
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Monitor changes in the calculated field
                $(document).on('input', '#input_16_24', function() { // Replace with your form and field IDs
                    var calculatedValue = parseFloat($(this).val());
                    var stringValue = '';
                    
                    // Determine the string value based on the numeric value
                    if (calculatedValue < 2) {
                        stringValue = 'Small';
                        } 
                    else if (calculatedValue >= 12 && calculatedValue < 15) {
                        stringValue = 'Medium';
                        } 
                    else if (calculatedValue >= 15 && calculatedValue < 25) {
                            stringValue = 'Large';
                        } 
                    else if (calculatedValue >= 25 && calculatedValue < 40) {
                         stringValue = 'Bulky';
                        } else {
                            stringValue = 'Xtra Bulky'; // Add other conditions as needed
                    }
                    stringValue = 'Xtra Bulky'; // Add other conditions as needed
             }
            
            // Set the string value in the text field
            $('#input_16_25').val(); // Replace with your form and field IDs

                });
            });
        </script>
        <?php
    }
}
add_action('wp_footer', 'item_size_calculator_script');
