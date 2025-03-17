/**
 * All of the code for your admin-facing JavaScript source
 * should reside in this file.
 */

(function($) {
    'use strict';

    /**
     * UI Helper Functions - Define this first so it's available to other modules
     */
    var UIPressAnalyticsBridgeUI = {
        showLoader: function() {
            if ($('#uipress-analytics-bridge-loader').length === 0) {
                $('body').append('<div id="uipress-analytics-bridge-loader" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.5); z-index: 99999; display: flex; align-items: center; justify-content: center;"><div style="background-color: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);"><span class="spinner is-active" style="float: none; margin: 0 10px 0 0;"></span>Loading...</div></div>');
            } else {
                $('#uipress-analytics-bridge-loader').show();
            }
        },

        hideLoader: function() {
            $('#uipress-analytics-bridge-loader').hide();
        },

        showError: function(title, message) {
            this.showNotice(title, message, 'error');
        },

        showSuccess: function(title, message) {
            this.showNotice(title, message, 'success');
        },

        showNotice: function(title, message, type) {
            // Remove any existing notices
            $('.uipress-analytics-bridge-notice').remove();
            
            // Create notice
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible uipress-analytics-bridge-notice"><p><strong>' + title + ':</strong> ' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>');
            
            // Insert notice
            $('.wrap h1').after($notice);
            
            // Initialize dismiss button
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(300, function() {
                    $notice.remove();
                });
            });
            
            // Auto-dismiss after 5 seconds for success notices
            if (type === 'success') {
                setTimeout(function() {
                    $notice.fadeOut(300, function() {
                        $notice.remove();
                    });
                }, 5000);
            }
        }
    };

    /**
     * Auth Handler
     */
    var UIPressAnalyticsBridgeAuth = {
        init: function() {
            // Initialize auth actions
            this.initAuthButtons();
        },

        initAuthButtons: function() {
            // Authentication button
            $('.uipress-analytics-bridge-auth').on('click', function(e) {
                e.preventDefault();
                
                // Check if credentials are entered
                var clientId = $('#uipress_analytics_bridge_client_id').val();
                var clientSecret = $('#uipress_analytics_bridge_client_secret').val();
                
                if (!clientId || !clientSecret) {
                    UIPressAnalyticsBridgeUI.showError(
                        uipressAnalyticsBridgeAdmin.strings.error, 
                        'Please enter your Google API Client ID and Client Secret before connecting.'
                    );
                    return;
                }
                
                // Save settings first to ensure credentials are stored
                $('#uipress-analytics-bridge-settings-form').submit();
                
                // Add a small delay to ensure the settings are saved
                setTimeout(function() {
                    UIPressAnalyticsBridgeAuth.authenticate();
                }, 500);
            });

            // Re-authentication button
            $('.uipress-analytics-bridge-reauth').on('click', function(e) {
                e.preventDefault();
                UIPressAnalyticsBridgeAuth.reauthenticate();
            });

            // Verify button
            $('.uipress-analytics-bridge-verify-auth').on('click', function(e) {
                e.preventDefault();
                UIPressAnalyticsBridgeAuth.verify();
            });

            // Deauthentication button
            $('.uipress-analytics-bridge-deauth').on('click', function(e) {
                e.preventDefault();
                
                if (confirm(uipressAnalyticsBridgeAdmin.strings.confirmDeauth)) {
                    UIPressAnalyticsBridgeAuth.deauthenticate();
                }
            });
        },

        authenticate: function() {
            UIPressAnalyticsBridgeUI.showLoader();
            
            console.log('Sending AJAX request to get auth URL');
            
            $.ajax({
                url: uipressAnalyticsBridgeAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'uipress_analytics_bridge_get_auth_url',
                    nonce: uipressAnalyticsBridgeAdmin.nonce,
                    network: uipressAnalyticsBridgeAdmin.isNetwork,
                    auth_type: 'auth'
                },
                success: function(response) {
                    console.log('AJAX response:', response);
                    UIPressAnalyticsBridgeUI.hideLoader();
                    
                    if (response.success && response.data.redirect) {
                        if (response.data.redirect === '') {
                            UIPressAnalyticsBridgeUI.showError(
                                uipressAnalyticsBridgeAdmin.strings.error, 
                                'Unable to create authentication URL. Please check your API credentials.'
                            );
                            return;
                        }
                        
                        // Navigate directly instead of using a popup window
                        window.location.href = response.data.redirect;
                    } else {
                        UIPressAnalyticsBridgeUI.showError(
                            uipressAnalyticsBridgeAdmin.strings.error, 
                            response.data && response.data.message ? response.data.message : 'Failed to get authentication URL.'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    console.log('AJAX error:', xhr, status, error);
                    UIPressAnalyticsBridgeUI.hideLoader();
                    UIPressAnalyticsBridgeUI.showError(
                        uipressAnalyticsBridgeAdmin.strings.error, 
                        'AJAX request failed: ' + (error || status)
                    );
                }
            });
        },

        reauthenticate: function() {
            UIPressAnalyticsBridgeUI.showLoader();
            
            $.ajax({
                url: uipressAnalyticsBridgeAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'uipress_analytics_bridge_get_auth_url',
                    nonce: uipressAnalyticsBridgeAdmin.nonce,
                    network: uipressAnalyticsBridgeAdmin.isNetwork,
                    auth_type: 'reauth'
                },
                success: function(response) {
                    UIPressAnalyticsBridgeUI.hideLoader();
                    
                    if (response.success && response.data.redirect) {
                        // Navigate directly to the auth URL
                        window.location.href = response.data.redirect;
                    } else {
                        UIPressAnalyticsBridgeUI.showError(
                            uipressAnalyticsBridgeAdmin.strings.error, 
                            response.data.message || 'Failed to get re-authentication URL.'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    UIPressAnalyticsBridgeUI.hideLoader();
                    UIPressAnalyticsBridgeUI.showError(
                        uipressAnalyticsBridgeAdmin.strings.error, 
                        'AJAX request failed: ' + (error || status)
                    );
                }
            });
        },

        verify: function() {
            var $button = $('.uipress-analytics-bridge-verify-auth');
            var originalText = $button.text();
            
            $button.text(uipressAnalyticsBridgeAdmin.strings.verifying).prop('disabled', true);
            
            $.ajax({
                url: uipressAnalyticsBridgeAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'uipress_analytics_bridge_verify_auth',
                    nonce: uipressAnalyticsBridgeAdmin.nonce,
                    network: uipressAnalyticsBridgeAdmin.isNetwork
                },
                success: function(response) {
                    $button.text(originalText).prop('disabled', false);
                    
                    if (response.success) {
                        UIPressAnalyticsBridgeUI.showSuccess(
                            uipressAnalyticsBridgeAdmin.strings.success, 
                            response.data.message || 'Authentication verified successfully.'
                        );
                    } else {
                        UIPressAnalyticsBridgeUI.showError(
                            uipressAnalyticsBridgeAdmin.strings.error, 
                            response.data.message || 'Failed to verify authentication.'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    $button.text(originalText).prop('disabled', false);
                    UIPressAnalyticsBridgeUI.showError(
                        uipressAnalyticsBridgeAdmin.strings.error, 
                        'AJAX request failed: ' + (error || status)
                    );
                }
            });
        },

        deauthenticate: function() {
            UIPressAnalyticsBridgeUI.showLoader();
            
            $.ajax({
                url: uipressAnalyticsBridgeAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'uipress_analytics_bridge_deauth',
                    nonce: uipressAnalyticsBridgeAdmin.nonce,
                    network: uipressAnalyticsBridgeAdmin.isNetwork
                },
                success: function(response) {
                    UIPressAnalyticsBridgeUI.hideLoader();
                    
                    if (response.success) {
                        // Redirect to settings page with deauth parameter
                        var redirectUrl = window.location.href;
                        if (redirectUrl.indexOf('?') > -1) {
                            redirectUrl = redirectUrl.split('?')[0];
                        }
                        
                        window.location.href = redirectUrl + '?page=uipress-analytics-bridge&deauth=success';
                    } else {
                        UIPressAnalyticsBridgeUI.showError(
                            uipressAnalyticsBridgeAdmin.strings.error, 
                            response.data.message || 'Failed to deauthenticate.'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    UIPressAnalyticsBridgeUI.hideLoader();
                    UIPressAnalyticsBridgeUI.showError(
                        uipressAnalyticsBridgeAdmin.strings.error, 
                        'AJAX request failed: ' + (error || status)
                    );
                }
            });
        }
    };

    /**
     * Property Selection Handler
     */
    var UIPressAnalyticsBridgePropertySelector = {
        properties: [],
        selectedProperty: null,
        
        init: function(properties) {
            this.properties = properties;
            this.renderModal();
            this.initEvents();
        },
        
        renderModal: function() {
            // Create modal HTML
            var html = '<div id="uipress-analytics-bridge-property-modal" class="uipress-analytics-bridge-modal">';
            html += '<div class="uipress-analytics-bridge-modal-content">';
            html += '<div class="uipress-analytics-bridge-modal-header">';
            html += '<h2 class="uipress-analytics-bridge-modal-title">Select Google Analytics Property</h2>';
            html += '<span class="uipress-analytics-bridge-modal-close" data-action="close">&times;</span>';
            html += '</div>';
            html += '<div class="uipress-analytics-bridge-modal-body">';
            html += '<p>Select the Google Analytics property you want to connect:</p>';
            html += '<div class="uipress-analytics-bridge-properties-list">';
            
            // Add properties
            for (var i = 0; i < this.properties.length; i++) {
                var property = this.properties[i];
                html += '<div class="uipress-analytics-bridge-property-item" ';
                html += 'data-property-id="' + property.property_id + '" ';
                html += 'data-measurement-id="' + property.measurement_id + '" ';
                html += 'data-account-id="' + property.account_id + '">';
                html += '<div class="uipress-analytics-bridge-property-name">' + property.property_name + '</div>';
                html += '<div class="uipress-analytics-bridge-property-details">';
                html += property.account_name + ' &middot; ' + property.measurement_id;
                html += '</div>';
                html += '</div>';
            }
            
            html += '</div>';
            html += '</div>';
            html += '<div class="uipress-analytics-bridge-modal-footer">';
            html += '<button type="button" class="button button-secondary" data-action="close">Cancel</button>';
            html += '<button type="button" class="button button-primary" data-action="select" disabled>Select Property</button>';
            html += '</div>';
            html += '</div>';
            html += '</div>';
            
            // Add to document
            $('body').append(html);
        },
        
        initEvents: function() {
            var self = this;
            
            // Property selection
            $('.uipress-analytics-bridge-property-item').on('click', function() {
                $('.uipress-analytics-bridge-property-item').removeClass('selected');
                $(this).addClass('selected');
                
                self.selectedProperty = {
                    property_id: $(this).data('property-id'),
                    measurement_id: $(this).data('measurement-id'),
                    account_id: $(this).data('account-id')
                };
                
                $('.uipress-analytics-bridge-modal-footer button[data-action="select"]').prop('disabled', false);
            });
            
            // Modal buttons
            $('.uipress-analytics-bridge-modal-footer button[data-action="close"], .uipress-analytics-bridge-modal-close').on('click', function() {
                self.closeModal();
            });
            
            $('.uipress-analytics-bridge-modal-footer button[data-action="select"]').on('click', function() {
                if (self.selectedProperty) {
                    self.selectProperty(self.selectedProperty);
                }
            });
        },
        
        closeModal: function() {
            $('#uipress-analytics-bridge-property-modal').remove();
        },
        
        selectProperty: function(property) {
            UIPressAnalyticsBridgeUI.showLoader();
            
            $.ajax({
                url: uipressAnalyticsBridgeAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'uipress_analytics_bridge_select_property',
                    nonce: uipressAnalyticsBridgeAdmin.nonce,
                    network: uipressAnalyticsBridgeAdmin.isNetwork,
                    property_id: property.property_id,
                    measurement_id: property.measurement_id,
                    account_id: property.account_id
                },
                success: function(response) {
                    UIPressAnalyticsBridgeUI.hideLoader();
                    
                    if (response.success) {
                        UIPressAnalyticsBridgePropertySelector.closeModal();
                        location.reload();
                    } else {
                        UIPressAnalyticsBridgeUI.showError(
                            uipressAnalyticsBridgeAdmin.strings.error, 
                            response.data && response.data.message ? response.data.message : 'Failed to select property.'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    UIPressAnalyticsBridgeUI.hideLoader();
                    UIPressAnalyticsBridgeUI.showError(
                        uipressAnalyticsBridgeAdmin.strings.error, 
                        'AJAX request failed: ' + (error || status)
                    );
                }
            });
        }
    };

    /**
     * Settings Handler
     */
    var UIPressAnalyticsBridgeSettings = {
        init: function() {
            // Initialize settings actions
            this.initSettingsForm();
            this.initClearCache();
        },

        initSettingsForm: function() {
            $('#uipress-analytics-bridge-settings-form').on('submit', function(e) {
                e.preventDefault();
                
                var googleClientId = $('#uipress_analytics_bridge_client_id').val();
                var googleClientSecret = $('#uipress_analytics_bridge_client_secret').val();
                var settings = {
                    debug_mode: $('#uipress_analytics_bridge_debug_mode').is(':checked'),
                    cache_duration: $('#uipress_analytics_bridge_cache_duration').val(),
                    google_client_id: googleClientId,
                    google_client_secret: googleClientSecret
                };
                
                UIPressAnalyticsBridgeUI.showLoader();
                
                $.ajax({
                    url: uipressAnalyticsBridgeAdmin.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uipress_analytics_bridge_save_settings',
                        nonce: uipressAnalyticsBridgeAdmin.nonce,
                        network: uipressAnalyticsBridgeAdmin.isNetwork,
                        settings: JSON.stringify(settings)
                    },
                    success: function(response) {
                        UIPressAnalyticsBridgeUI.hideLoader();
                        
                        if (response.success) {
                            UIPressAnalyticsBridgeUI.showSuccess(
                                uipressAnalyticsBridgeAdmin.strings.success, 
                                response.data.message || 'Settings saved successfully.'
                            );
                        } else {
                            UIPressAnalyticsBridgeUI.showError(
                                uipressAnalyticsBridgeAdmin.strings.error, 
                                response.data.message || 'Failed to save settings.'
                            );
                        }
                    },
                    error: function(xhr, status, error) {
                        UIPressAnalyticsBridgeUI.hideLoader();
                        UIPressAnalyticsBridgeUI.showError(
                            uipressAnalyticsBridgeAdmin.strings.error, 
                            'AJAX request failed: ' + (error || status)
                        );
                    }
                });
            });
        },

        initClearCache: function() {
            $('.uipress-analytics-bridge-clear-cache').on('click', function(e) {
                e.preventDefault();
                
                var $button = $(this);
                var originalText = $button.text();
                
                $button.text('Clearing...').prop('disabled', true);
                
                $.ajax({
                    url: uipressAnalyticsBridgeAdmin.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uipress_analytics_bridge_clear_cache',
                        nonce: uipressAnalyticsBridgeAdmin.nonce,
                        network: uipressAnalyticsBridgeAdmin.isNetwork
                    },
                    success: function(response) {
                        $button.text(originalText).prop('disabled', false);
                        
                        if (response.success) {
                            UIPressAnalyticsBridgeUI.showSuccess(
                                uipressAnalyticsBridgeAdmin.strings.success, 
                                response.data.message || 'Cache cleared successfully.'
                            );
                        } else {
                            UIPressAnalyticsBridgeUI.showError(
                                uipressAnalyticsBridgeAdmin.strings.error, 
                                response.data.message || 'Failed to clear cache.'
                            );
                        }
                    },
                    error: function(xhr, status, error) {
                        $button.text(originalText).prop('disabled', false);
                        UIPressAnalyticsBridgeUI.showError(
                            uipressAnalyticsBridgeAdmin.strings.error, 
                            'AJAX request failed: ' + (error || status)
                        );
                    }
                });
            });
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        UIPressAnalyticsBridgeAuth.init();
        UIPressAnalyticsBridgeSettings.init();

        // Init property selection for properties page
        $('.uipress-analytics-bridge-select-property').on('click', function() {
            var propertyId = $(this).data('property-id');
            var measurementId = $(this).data('measurement-id');
            var accountId = $(this).data('account-id');
            
            UIPressAnalyticsBridgeUI.showLoader();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'uipress_analytics_bridge_select_property',
                    nonce: uipressAnalyticsBridgeAdmin.nonce,
                    network: uipressAnalyticsBridgeAdmin.isNetwork,
                    property_id: propertyId,
                    measurement_id: measurementId,
                    account_id: accountId
                },
                success: function(response) {
                    UIPressAnalyticsBridgeUI.hideLoader();
                    
                    if (response.success) {
                        window.location.href = uipressAnalyticsBridgeAdmin.settingsUrl + '&auth=success';
                    } else {
                        alert(response.data.message || 'Failed to select property.');
                    }
                },
                error: function(xhr, status, error) {
                    UIPressAnalyticsBridgeUI.hideLoader();
                    alert('AJAX request failed: ' + (error || status));
                }
            });
        });
    });

})(jQuery);