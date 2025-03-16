/**
 * All of the code for your admin-facing JavaScript source
 * should reside in this file.
 */

(function($) {
    'use strict';

    /**
     * Auth Handler
     */
    var UIPressAnalyticsBridgeAuth = {
        init: function() {
            // Initialize auth actions
            this.initAuthButtons();
            this.initAccountSelector();
        },

        initAuthButtons: function() {
            // Authentication button
            $('.uipress-analytics-bridge-auth').on('click', function(e) {
                e.preventDefault();
                UIPressAnalyticsBridgeAuth.authenticate();
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
                    UIPressAnalyticsBridgeUI.hideLoader();
                    
                    if (response.success && response.data.redirect) {
                        // Open popup window for authentication
                        var authWindow = window.open(response.data.redirect, 'uipressAnalyticsBridgeAuth', 'width=600,height=700');
                        
                        // Check if window was opened
                        if (authWindow) {
                            // Poll for window closure
                            var pollTimer = setInterval(function() {
                                if (authWindow.closed) {
                                    clearInterval(pollTimer);
                                    
                                    // Refresh the page to show updated auth status
                                    location.reload();
                                }
                            }, 500);
                        } else {
                            UIPressAnalyticsBridgeUI.showError(uipressAnalyticsBridgeAdmin.strings.error, 'Popup window was blocked. Please allow popups for this site and try again.');
                        }
                    } else {
                        UIPressAnalyticsBridgeUI.showError(uipressAnalyticsBridgeAdmin.strings.error, response.data.message || 'Failed to get authentication URL.');
                    }
                },
                error: function() {
                    UIPressAnalyticsBridgeUI.hideLoader();
                    UIPressAnalyticsBridgeUI.showError(uipressAnalyticsBridgeAdmin.strings.error, 'AJAX request failed.');
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
                        // Open popup window for re-authentication
                        var authWindow = window.open(response.data.redirect, 'uipressAnalyticsBridgeAuth', 'width=600,height=700');
                        
                        // Check if window was opened
                        if (authWindow) {
                            // Poll for window closure
                            var pollTimer = setInterval(function() {
                                if (authWindow.closed) {
                                    clearInterval(pollTimer);
                                    
                                    // Refresh the page to show updated auth status
                                    location.reload();
                                }
                            }, 500);
                        } else {
                            UIPressAnalyticsBridgeUI.showError(uipressAnalyticsBridgeAdmin.strings.error, 'Popup window was blocked. Please allow popups for this site and try again.');
                        }
                    } else {
                        UIPressAnalyticsBridgeUI.showError(uipressAnalyticsBridgeAdmin.strings.error, response.data.message || 'Failed to get re-authentication URL.');
                    }
                },
                error: function() {
                    UIPressAnalyticsBridgeUI.hideLoader();
                    UIPressAnalyticsBridgeUI.showError(uipressAnalyticsBridgeAdmin.strings.error, 'AJAX request failed.');
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
                        UIPressAnalyticsBridgeUI.showSuccess(uipressAnalyticsBridgeAdmin.strings.success, response.data.message || 'Authentication verified successfully.');
                    } else {
                        UIPressAnalyticsBridgeUI.showError(uipressAnalyticsBridgeAdmin.strings.error, response.data.message || 'Failed to verify authentication.');
                    }
                },
                error: function() {
                    $button.text(originalText).prop('disabled', false);
                    UIPressAnalyticsBridgeUI.showError(uipressAnalyticsBridgeAdmin.strings.error, 'AJAX request failed.');
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
                        UIPressAnalyticsBridgeUI.showError(uipressAnalyticsBridgeAdmin.strings.error, response.data.message || 'Failed to deauthenticate.');
                    }
                },
                error: function() {
                    UIPressAnalyticsBridgeUI.hideLoader();
                    UIPressAnalyticsBridgeUI.showError(uipressAnalyticsBridgeAdmin.strings.error, 'AJAX request failed.');
                }
            });
        },

        initAccountSelector: function() {
            // Account selector functionality would go here
            // This will be implemented in a future version
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
                
                var settings = {
                    debug_mode: $('#uipress_analytics_bridge_debug_mode').is(':checked'),
                    cache_duration: $('#uipress_analytics_bridge_cache_duration').val()
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
                            UIPressAnalyticsBridgeUI.showSuccess(uipressAnalyticsBridgeAdmin.strings.success, response.data.message || 'Settings saved successfully.');
                        } else {
                            UIPressAnalyticsBridgeUI.showError(uipressAnalyticsBridgeAdmin.strings.error, response.data.message || 'Failed to save settings.');
                        }
                    },
                    error: function() {
                        UIPressAnalyticsBridgeUI.hideLoader();
                        UIPressAnalyticsBridgeUI.showError(uipressAnalyticsBridgeAdmin.strings.error, 'AJAX request failed.');
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
                            UIPressAnalyticsBridgeUI.showSuccess(uipressAnalyticsBridgeAdmin.strings.success, response.data.message || 'Cache cleared successfully.');
                        } else {
                            UIPressAnalyticsBridgeUI.showError(uipressAnalyticsBridgeAdmin.strings.error, response.data.message || 'Failed to clear cache.');
                        }
                    },
                    error: function() {
                        $button.text(originalText).prop('disabled', false);
                        UIPressAnalyticsBridgeUI.showError(uipressAnalyticsBridgeAdmin.strings.error, 'AJAX request failed.');
                    }
                });
            });
        }
    };

    /**
     * UI Helper Functions
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
     * Initialize on document ready
     */
    $(document).ready(function() {
        UIPressAnalyticsBridgeAuth.init();
        UIPressAnalyticsBridgeSettings.init();
    });

})(jQuery);