<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * This file runs when the plugin is being uninstalled to clean up any plugin data.
 * It is not triggered when the plugin is deactivated, only when it's deleted.
 *
 * @since      1.0.0
 * @package    UIPress_Analytics_Bridge
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Define plugin constants if not already defined
if (!defined('UIPRESS_ANALYTICS_BRIDGE_VERSION')) {
    define('UIPRESS_ANALYTICS_BRIDGE_VERSION', '1.0.0');
}

/**
 * Uninstall function to clean up all plugin data
 */
function uipress_analytics_bridge_uninstall() {
    // Delete options
    delete_option('uipress_analytics_bridge_profile');
    delete_option('uipress_analytics_bridge_settings');
    delete_option('uipress_analytics_bridge_tt');
    delete_option('uipress_analytics_bridge_aggregate_data');
    
    // For multisite installations
    if (is_multisite()) {
        delete_site_option('uipress_analytics_bridge_network_profile');
        delete_site_option('uipress_analytics_bridge_network_tt');
        delete_site_option('uipress_analytics_bridge_settings');
    }
    
    // Delete all transients with our prefix
    global $wpdb;
    
    // Get and delete all transients with our prefix
    $transients = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
            '_transient_uipress_analytics_bridge_%',
            '_transient_timeout_uipress_analytics_bridge_%'
        )
    );
    
    foreach ($transients as $transient) {
        if (strpos($transient, '_transient_timeout_') === 0) {
            // For timeout transients, remove the prefix
            $transient_name = str_replace('_transient_timeout_', '', $transient);
            delete_transient($transient_name);
        } else if (strpos($transient, '_transient_') === 0) {
            // For regular transients, remove the prefix
            $transient_name = str_replace('_transient_', '', $transient);
            delete_transient($transient_name);
        }
    }
    
    // For multisite, clean up site-specific options and transients
    if (is_multisite()) {
        // Get all blog IDs
        $blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
        
        foreach ($blog_ids as $blog_id) {
            switch_to_blog($blog_id);
            
            // Delete options
            delete_option('uipress_analytics_bridge_profile');
            delete_option('uipress_analytics_bridge_settings');
            delete_option('uipress_analytics_bridge_tt');
            delete_option('uipress_analytics_bridge_aggregate_data');
            
            // Delete transients
            $site_transients = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
                    '_transient_uipress_analytics_bridge_%',
                    '_transient_timeout_uipress_analytics_bridge_%'
                )
            );
            
            foreach ($site_transients as $transient) {
                if (strpos($transient, '_transient_timeout_') === 0) {
                    $transient_name = str_replace('_transient_timeout_', '', $transient);
                    delete_transient($transient_name);
                } else if (strpos($transient, '_transient_') === 0) {
                    $transient_name = str_replace('_transient_', '', $transient);
                    delete_transient($transient_name);
                }
            }
            
            restore_current_blog();
        }
    }
    
    // Clear any user meta related to this plugin
    $wpdb->query($wpdb->prepare(
        "DELETE FROM $wpdb->usermeta WHERE meta_key LIKE %s",
        'uipress_analytics_bridge_%'
    ));
}

// Run the uninstall function
uipress_analytics_bridge_uninstall();