/**
 * Property selection page JavaScript
 */
(function($) {
    'use strict';

    // Initialize property selection buttons
    $(document).ready(function() {
        console.log('Property selection page loaded');
        
        $('.uipress-analytics-bridge-select-property').on('click', function(e) {
            e.preventDefault();
            console.log('Property selection button clicked');
            
            var $button = $(this);
            var propertyId = $button.data('property-id');
            var measurementId = $button.data('measurement-id');
            var accountId = $button.data('account-id');
            
            console.log('Property details:', {
                property_id: propertyId,
                measurement_id: measurementId,
                account_id: accountId
            });
            
            // Disable button and show loading state
            $button.prop('disabled', true).text('Selecting...').addClass('disabled');
            
            // Simple loading overlay
            $('body').append('<div id="uipress-loading-overlay" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:9999; display:flex; align-items:center; justify-content:center;"><div style="background:white; padding:20px; border-radius:5px;"><span class="spinner is-active" style="float:none; margin-right:10px;"></span> Selecting property...</div></div>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'uipress_analytics_bridge_select_property',
                    nonce: $('#uipress_analytics_bridge_nonce').val(),
                    property_id: propertyId,
                    measurement_id: measurementId,
                    account_id: accountId
                },
                success: function(response) {
                    console.log('AJAX response:', response);
                    
                    if (response.success) {
                        // Show success message before redirect
                        $('#uipress-loading-overlay').html('<div style="background:white; padding:20px; border-radius:5px;"><p style="color:green;">✓ Property selected successfully! Redirecting...</p></div>');
                        
                        // Redirect to settings page
                        setTimeout(function() {
                            window.location.href = $('#redirect_url').val() + '&auth=success';
                        }, 1000);
                    } else {
                        // Show error message
                        $('#uipress-loading-overlay').remove();
                        $button.prop('disabled', false).text('Select This Property').removeClass('disabled');
                        
                        $('<div class="notice notice-error is-dismissible"><p>' + 
                          (response.data && response.data.message ? response.data.message : 'Failed to select property.') + 
                          '</p></div>').insertAfter('.uipress-analytics-bridge-header');
                    }
                },
                error: function(xhr, status, error) {
                    console.log('AJAX error:', xhr, status, error);
                    
                    // Show error message
                    $('#uipress-loading-overlay').remove();
                    $button.prop('disabled', false).text('Select This Property').removeClass('disabled');
                    
                    $('<div class="notice notice-error is-dismissible"><p>AJAX request failed: ' + 
                      (error || status) + '</p></div>').insertAfter('.uipress-analytics-bridge-header');
                }
            });
        });
    });

})(jQuery);