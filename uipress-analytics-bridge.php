<?php
/**
 * Plugin Name: UIPress Analytics Bridge
 * Plugin URI: https://yp.studio
 * Description: Enhanced Google Analytics authentication and data retrieval for UIPress Pro. Provides a more reliable connection with better error handling and diagnostics.
 * Version: 1.0.0
 * Author: Young Pandas
 * Author URI: https://yp.studio
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: uipress-analytics-bridge
 * Domain Path: /languages
 *
 * UIPress Analytics Bridge is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * UIPress Analytics Bridge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('UIPRESS_ANALYTICS_BRIDGE_VERSION', '1.0.0');
define('UIPRESS_ANALYTICS_BRIDGE_FILE', __FILE__);
define('UIPRESS_ANALYTICS_BRIDGE_PATH', plugin_dir_path(__FILE__));
define('UIPRESS_ANALYTICS_BRIDGE_URL', plugin_dir_url(__FILE__));
define('UIPRESS_ANALYTICS_BRIDGE_BASENAME', plugin_basename(__FILE__));

/**
 * Add debugging helper function to plugin
 */
function uipress_analytics_bridge_debug($message, $data = null) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('UIPress Analytics Bridge: ' . $message);
        
        if ($data !== null) {
            error_log(print_r($data, true));
        }
    }
}

/**
 * Main UIPress Analytics Bridge Class
 *
 * The main class that initiates and runs the plugin.
 *
 * @since 1.0.0
 */
final class UIPress_Analytics_Bridge {

    /**
     * Singleton instance
     *
     * @since 1.0.0
     * @var UIPress_Analytics_Bridge
     */
    private static $instance;

    /**
     * Plugin loader
     *
     * @since 1.0.0
     * @var UIPress_Analytics_Bridge_Loader
     */
    public $loader;

    /**
     * Authentication handler
     *
     * @since 1.0.0
     * @var UIPress_Analytics_Bridge_Auth
     */
    public $auth;

    /**
     * API handler
     *
     * @since 1.0.0
     * @var UIPress_Analytics_Bridge_API_Auth
     */
    public $api;

    /**
     * Main UIPress_Analytics_Bridge Instance
     *
     * Ensures only one instance of UIPress_Analytics_Bridge is loaded or can be loaded.
     *
     * @since 1.0.0
     * @static
     * @return UIPress_Analytics_Bridge - Main instance
     */
    public static function instance() {
        if (!isset(self::$instance) && !(self::$instance instanceof UIPress_Analytics_Bridge)) {
            self::$instance = new UIPress_Analytics_Bridge();
            self::$instance->setup_constants();
            self::$instance->includes();
            self::$instance->init_hooks();
            
            // Register AJAX handlers early, before 'init' action
            self::$instance->register_ajax_handlers();
        }
        return self::$instance;
    }

    /**
     * Setup plugin constants
     *
     * @since 1.0.0
     * @access private
     * @return void
     */
    private function setup_constants() {
        // Additional constants can be defined here if needed
        define('UIPRESS_ANALYTICS_BRIDGE_DEBUG', false);
    }

    /**
     * Include required files
     *
     * @since 1.0.0
     * @access private
     * @return void
     */
    private function includes() {
        // Core classes
        require_once UIPRESS_ANALYTICS_BRIDGE_PATH . 'includes/class-uipress-analytics-bridge-loader.php';
        require_once UIPRESS_ANALYTICS_BRIDGE_PATH . 'includes/class-uipress-analytics-bridge-detector.php';
        require_once UIPRESS_ANALYTICS_BRIDGE_PATH . 'includes/class-uipress-analytics-bridge-auth.php';
        require_once UIPRESS_ANALYTICS_BRIDGE_PATH . 'includes/class-uipress-analytics-bridge-data.php';
        require_once UIPRESS_ANALYTICS_BRIDGE_PATH . 'includes/class-uipress-analytics-bridge-transients.php';
        
        // API classes
        require_once UIPRESS_ANALYTICS_BRIDGE_PATH . 'includes/api/class-uipress-analytics-bridge-api-auth.php';
        require_once UIPRESS_ANALYTICS_BRIDGE_PATH . 'includes/api/class-uipress-analytics-bridge-api-data.php';
        require_once UIPRESS_ANALYTICS_BRIDGE_PATH . 'includes/api/class-uipress-analytics-bridge-account-selector.php';
        
        // Admin classes
        if (is_admin()) {
            require_once UIPRESS_ANALYTICS_BRIDGE_PATH . 'admin/class-uipress-analytics-bridge-admin.php';
        }
    }

    /**
     * Initialize plugin hooks
     *
     * @since 1.0.0
     * @access private
     * @return void
     */
    private function init_hooks() {
        // Create loader instance
        $this->loader = new UIPress_Analytics_Bridge_Loader();
        
        // Initialize components on plugins loaded to ensure WordPress is fully loaded
        add_action('plugins_loaded', array($this, 'init_components'));
        
        // Register activation/deactivation hooks
        register_activation_hook(UIPRESS_ANALYTICS_BRIDGE_FILE, array($this, 'activate'));
        register_deactivation_hook(UIPRESS_ANALYTICS_BRIDGE_FILE, array($this, 'deactivate'));
        
        // Load text domain
        add_action('init', array($this, 'load_textdomain'), 10);
    }
    
    /**
     * Register AJAX handlers early, before WordPress initialization
     *
     * @since 1.0.0
     * @access private
     * @return void
     */
    private function register_ajax_handlers() {
        // Auth AJAX handlers
        add_action('wp_ajax_uipress_analytics_bridge_get_auth_url', array($this, 'ajax_get_auth_url'));
        add_action('wp_ajax_uipress_analytics_bridge_select_property', array($this, 'ajax_select_property'));
    }

    /**
     * AJAX handler for getting auth URL
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function ajax_get_auth_url() {
        // Initialize API auth if not already initialized
        if (!isset($this->api) || !($this->api instanceof UIPress_Analytics_Bridge_API_Auth)) {
            $this->api = new UIPress_Analytics_Bridge_API_Auth();
        }
        
        // Call the API auth method directly
        $this->api->get_auth_url();
    }

    /**
     * AJAX handler for selecting property
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function ajax_select_property() {
        // Initialize auth if not already initialized
        if (!isset($this->auth) || !($this->auth instanceof UIPress_Analytics_Bridge_Auth)) {
            $this->auth = new UIPress_Analytics_Bridge_Auth();
        }
        
        // Call the auth method directly
        $this->auth->handle_property_selection();
    }

    /**
     * Initialize components
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function init_components() {
        // Check if UIPress is active
        if (!UIPress_Analytics_Bridge_Detector::is_uipress_active()) {
            add_action('admin_notices', array($this, 'admin_notice_missing_uipress'));
            return;
        }
        
        // Initialize auth component
        $this->auth = new UIPress_Analytics_Bridge_Auth();
        
        // Initialize API component
        $this->api = new UIPress_Analytics_Bridge_API_Auth();
        
        // Initialize admin if in admin area
        if (is_admin()) {
            $admin = new UIPress_Analytics_Bridge_Admin();
            $admin->init();
        }
        
        // Initialize data handler
        $data_handler = new UIPress_Analytics_Bridge_Data();
        $data_handler->init();
        
        // Register all hooks with WordPress
        $this->loader->run();
    }

    /**
     * Admin notice for missing UIPress
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function admin_notice_missing_uipress() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('UIPress Analytics Bridge requires UIPress Pro to be installed and activated.', 'uipress-analytics-bridge'); ?></p>
        </div>
        <?php
    }

    /**
     * Activation function
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function activate() {
        // Activation code
        flush_rewrite_rules();
    }

    /**
     * Deactivation function
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function deactivate() {
        // Deactivation code
        flush_rewrite_rules();
    }

    /**
     * Load plugin text domain
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function load_textdomain() {
        load_plugin_textdomain('uipress-analytics-bridge', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
}

/**
 * The main function for that returns UIPress_Analytics_Bridge
 *
 * The main function responsible for returning the one true UIPress_Analytics_Bridge
 * instance to functions everywhere.
 *
 * @since 1.0.0
 * @return UIPress_Analytics_Bridge
 */
function uipress_analytics_bridge() {
    return UIPress_Analytics_Bridge::instance();
}

// Get the plugin running
add_action('plugins_loaded', 'uipress_analytics_bridge', 9);