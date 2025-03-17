<?php
/**
 * Admin Class
 *
 * Handles admin functionality for the plugin.
 *
 * @since      1.0.0
 * @package    UIPress_Analytics_Bridge
 * @subpackage UIPress_Analytics_Bridge/admin
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Class.
 *
 * Handles admin functionality for the plugin.
 *
 * @since 1.0.0
 */
class UIPress_Analytics_Bridge_Admin {

    /**
     * Plugin loader
     * 
     * @since 1.0.0
     * @access private
     * @var UIPress_Analytics_Bridge_Loader
     */
    private $loader;

    /**
     * Account selector instance
     * 
     * @since 1.0.0
     * @access private
     * @var UIPress_Analytics_Bridge_Account_Selector
     */
    private $account_selector;

    /**
     * Constructor.
     *
     * @since 1.0.0
     * @access public
     */
    public function __construct() {
        global $uipress_analytics_bridge;
        $this->loader = $uipress_analytics_bridge->loader;
        $this->account_selector = new UIPress_Analytics_Bridge_Account_Selector();
    }

    /**
     * Initialize the admin functionality.
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function init() {
        // Register admin menu
        add_action('admin_menu', array($this, 'register_admin_menu'));
        
        // Network admin menu
        if (is_multisite()) {
            add_action('network_admin_menu', array($this, 'register_network_admin_menu'));
        }
        
        // Register admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Register AJAX handlers
        $this->register_ajax_handlers();
        
        // Add admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
    }

    /**
     * Register admin menu.
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function register_admin_menu() {
        add_options_page(
            __('UIPress Analytics Bridge', 'uipress-analytics-bridge'),
            __('UIPress Analytics', 'uipress-analytics-bridge'),
            'manage_options',
            'uipress-analytics-bridge',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register network admin menu.
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function register_network_admin_menu() {
        add_submenu_page(
            'settings.php',
            __('UIPress Analytics Bridge', 'uipress-analytics-bridge'),
            __('UIPress Analytics', 'uipress-analytics-bridge'),
            'manage_network_options',
            'uipress-analytics-bridge',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @since 1.0.0
     * @access public
     * @param string $hook The current admin page.
     * @return void
     */
    public function enqueue_admin_assets($hook) {
        // Check if we're on our settings page
        if ($hook !== 'settings_page_uipress-analytics-bridge' && $hook !== 'options-general_page_uipress-analytics-bridge') {
            return;
        }
        
        // Enqueue styles
        wp_enqueue_style(
            'uipress-analytics-bridge-admin',
            UIPRESS_ANALYTICS_BRIDGE_URL . 'admin/css/uipress-analytics-bridge-admin.css',
            array(),
            UIPRESS_ANALYTICS_BRIDGE_VERSION
        );
        
        // Enqueue scripts
        wp_enqueue_script(
            'uipress-analytics-bridge-admin',
            UIPRESS_ANALYTICS_BRIDGE_URL . 'admin/js/uipress-analytics-bridge-admin.js',
            array('jquery'),
            UIPRESS_ANALYTICS_BRIDGE_VERSION,
            true
        );
        
        // Settings page URL for redirects
        $settings_url = admin_url('options-general.php?page=uipress-analytics-bridge');
        if (is_network_admin()) {
            $settings_url = network_admin_url('settings.php?page=uipress-analytics-bridge');
        }
        
        // Localize script
        wp_localize_script(
            'uipress-analytics-bridge-admin',
            'uipressAnalyticsBridgeAdmin',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('uipress-analytics-bridge-nonce'),
                'isNetwork' => is_network_admin() ? 'network' : 'site',
                'isAuthenticated' => $this->is_authenticated(),
                'settingsUrl' => $settings_url,
                'strings' => array(
                    'authenticate' => __('Authenticate with Google Analytics', 'uipress-analytics-bridge'),
                    'reauthenticate' => __('Re-authenticate with Google Analytics', 'uipress-analytics-bridge'),
                    'deauthenticate' => __('Disconnect Google Analytics', 'uipress-analytics-bridge'),
                    'verifying' => __('Verifying...', 'uipress-analytics-bridge'),
                    'verify' => __('Verify Connection', 'uipress-analytics-bridge'),
                    'noAccounts' => __('No accounts found', 'uipress-analytics-bridge'),
                    'noProperties' => __('No properties found', 'uipress-analytics-bridge'),
                    'noViews' => __('No views found', 'uipress-analytics-bridge'),
                    'selectAccount' => __('Select an account', 'uipress-analytics-bridge'),
                    'selectProperty' => __('Select a property', 'uipress-analytics-bridge'),
                    'selectView' => __('Select a view', 'uipress-analytics-bridge'),
                    'loadingAccounts' => __('Loading accounts...', 'uipress-analytics-bridge'),
                    'loadingProperties' => __('Loading properties...', 'uipress-analytics-bridge'),
                    'loadingViews' => __('Loading views...', 'uipress-analytics-bridge'),
                    'error' => __('Error', 'uipress-analytics-bridge'),
                    'success' => __('Success', 'uipress-analytics-bridge'),
                    'confirmDeauth' => __('Are you sure you want to disconnect Google Analytics?', 'uipress-analytics-bridge'),
                ),
            )
        );
    }

    /**
     * Register settings.
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function register_settings() {
        register_setting(
            'uipress_analytics_bridge_settings',
            'uipress_analytics_bridge_settings',
            array(
                'sanitize_callback' => array($this, 'sanitize_settings'),
            )
        );
        
        add_settings_section(
            'uipress_analytics_bridge_main_section',
            __('Google Analytics Integration', 'uipress-analytics-bridge'),
            array($this, 'render_main_section'),
            'uipress-analytics-bridge'
        );
        
        add_settings_field(
            'uipress_analytics_bridge_api_credentials',
            __('Google API Credentials', 'uipress-analytics-bridge'),
            array($this, 'render_api_credentials_field'),
            'uipress-analytics-bridge',
            'uipress_analytics_bridge_main_section',
            array('label_for' => 'uipress_analytics_bridge_client_id')
        );
        
        add_settings_field(
            'uipress_analytics_bridge_auth',
            __('Authentication', 'uipress-analytics-bridge'),
            array($this, 'render_auth_field'),
            'uipress-analytics-bridge',
            'uipress_analytics_bridge_main_section'
        );
        
        add_settings_field(
            'uipress_analytics_bridge_connection',
            __('Connection Details', 'uipress-analytics-bridge'),
            array($this, 'render_connection_field'),
            'uipress-analytics-bridge',
            'uipress_analytics_bridge_main_section'
        );
        
        add_settings_section(
            'uipress_analytics_bridge_advanced_section',
            __('Advanced Settings', 'uipress-analytics-bridge'),
            array($this, 'render_advanced_section'),
            'uipress-analytics-bridge'
        );
        
        add_settings_field(
            'uipress_analytics_bridge_debug',
            __('Debug Mode', 'uipress-analytics-bridge'),
            array($this, 'render_debug_field'),
            'uipress-analytics-bridge',
            'uipress_analytics_bridge_advanced_section'
        );
        
        add_settings_field(
            'uipress_analytics_bridge_cache',
            __('Cache Settings', 'uipress-analytics-bridge'),
            array($this, 'render_cache_field'),
            'uipress-analytics-bridge',
            'uipress_analytics_bridge_advanced_section'
        );
    }

    /**
     * Register AJAX handlers.
     *
     * @since 1.0.0
     * @access private
     * @return void
     */
    private function register_ajax_handlers() {
        // Auth handlers
        add_action('wp_ajax_uipress_analytics_bridge_get_auth_status', array($this, 'get_auth_status'));
        
        // Settings handlers
        add_action('wp_ajax_uipress_analytics_bridge_save_settings', array($this, 'save_settings'));
        add_action('wp_ajax_uipress_analytics_bridge_clear_cache', array($this, 'clear_cache'));
    }

    /**
     * Check if authenticated.
     *
     * @since 1.0.0
     * @access private
     * @return bool Whether authenticated
     */
    private function is_authenticated() {
        $auth = new UIPress_Analytics_Bridge_Auth();
        
        return $auth->is_authenticated(is_network_admin());
    }

    /**
     * Get authentication status (AJAX handler).
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function get_auth_status() {
        // Verify nonce
        check_ajax_referer('uipress-analytics-bridge-nonce', 'nonce');
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action.', 'uipress-analytics-bridge')
            ));
        }
        
        $is_network = isset($_POST['network']) && $_POST['network'] === 'network';
        
        // Get auth
        $auth = new UIPress_Analytics_Bridge_Auth();
        
        // Get profile
        $profile = $auth->get_analytics_profile(true, $is_network);
        
        // Check if authenticated
        $authenticated = $auth->is_authenticated($is_network);
        
        wp_send_json_success(array(
            'authenticated' => $authenticated,
            'profile' => $profile,
        ));
    }

    /**
     * Save settings (AJAX handler).
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function save_settings() {
        // Verify nonce
        check_ajax_referer('uipress-analytics-bridge-nonce', 'nonce');
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action.', 'uipress-analytics-bridge')
            ));
        }
        
        // Get settings
        $settings = isset($_POST['settings']) ? json_decode(stripslashes($_POST['settings']), true) : array();
        
        // Sanitize settings
        $settings = $this->sanitize_settings($settings);
        
        // Save settings
        $is_network = isset($_POST['network']) && $_POST['network'] === 'network';
        
        if ($is_network) {
            update_site_option('uipress_analytics_bridge_settings', $settings);
        } else {
            update_option('uipress_analytics_bridge_settings', $settings);
        }
        
        wp_send_json_success(array(
            'message' => __('Settings saved successfully.', 'uipress-analytics-bridge'),
            'settings' => $settings,
        ));
    }

    /**
     * Clear cache (AJAX handler).
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function clear_cache() {
        // Verify nonce
        check_ajax_referer('uipress-analytics-bridge-nonce', 'nonce');
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action.', 'uipress-analytics-bridge')
            ));
        }
        
        // Clear all transients
        $transients = new UIPress_Analytics_Bridge_Transients();
        $transients->delete_all_cache();
        
        wp_send_json_success(array(
            'message' => __('Cache cleared successfully.', 'uipress-analytics-bridge'),
        ));
    }

    /**
     * Sanitize settings.
     *
     * @since 1.0.0
     * @access public
     * @param array $settings Settings to sanitize
     * @return array Sanitized settings
     */
    public function sanitize_settings($settings) {
        $sanitized = array();
        
        // Debug mode
        $sanitized['debug_mode'] = isset($settings['debug_mode']) && $settings['debug_mode'] ? true : false;
        
        // Cache duration
        $sanitized['cache_duration'] = isset($settings['cache_duration']) ? absint($settings['cache_duration']) : 3600;
        
        // Google API credentials
        $sanitized['google_client_id'] = isset($settings['google_client_id']) ? sanitize_text_field($settings['google_client_id']) : '';
        $sanitized['google_client_secret'] = isset($settings['google_client_secret']) ? sanitize_text_field($settings['google_client_secret']) : '';
        
        return $sanitized;
    }

    /**
     * Render settings page.
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function render_settings_page() {
        // Check if UIPress is active
        if (!UIPress_Analytics_Bridge_Detector::is_uipress_active()) {
            ?>
            <div class="wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <div class="notice notice-error">
                    <p><?php _e('UIPress or UIPress Pro must be active to use this plugin.', 'uipress-analytics-bridge'); ?></p>
                </div>
            </div>
            <?php
            return;
        }
        
        // Check if UIPress is compatible
        if (!UIPress_Analytics_Bridge_Detector::is_uipress_compatible()) {
            ?>
            <div class="wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <div class="notice notice-error">
                    <p><?php _e('The installed version of UIPress is not compatible with UIPress Analytics Bridge.', 'uipress-analytics-bridge'); ?></p>
                </div>
            </div>
            <?php
            return;
        }
        
        // Check if we need to show property selection
        if (isset($_GET['select_property']) && $_GET['select_property'] == '1') {
            $this->render_property_selection_page();
            return;
        }
        
        // Process auth callback
        if (isset($_GET['auth']) && $_GET['auth'] === 'success') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Authentication successful!', 'uipress-analytics-bridge'); ?></p>
            </div>
            <?php
        }
        
        // Process deauth callback
        if (isset($_GET['deauth']) && $_GET['deauth'] === 'success') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Deauthentication successful!', 'uipress-analytics-bridge'); ?></p>
            </div>
            <?php
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="uipress-analytics-bridge-header">
                <div class="uipress-analytics-bridge-header-content">
                    <p><?php _e('Connect your Google Analytics account to enhance UIPress Pro with reliable analytics data.', 'uipress-analytics-bridge'); ?></p>
                </div>
            </div>
            
            <form id="uipress-analytics-bridge-settings-form" method="post" action="options.php">
                <?php
                settings_fields('uipress_analytics_bridge_settings');
                do_settings_sections('uipress-analytics-bridge');
                submit_button(__('Save Settings', 'uipress-analytics-bridge'));
                ?>
            </form>
            
            <div class="uipress-analytics-bridge-footer">
                <p>
                    <?php
                    printf(
                        __('UIPress Analytics Bridge v%s | %sDocumentation%s', 'uipress-analytics-bridge'),
                        UIPRESS_ANALYTICS_BRIDGE_VERSION,
                        '<a href="https://example.com/docs/uipress-analytics-bridge" target="_blank">',
                        '</a>'
                    );
                    ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render property selection page
     *
     * @since 1.0.0
     * @access private
     * @return void
     */
    private function render_property_selection_page() {
        $is_network = is_network_admin();
        
        // Get auth
        $auth = new UIPress_Analytics_Bridge_Auth();
        $profile = $auth->get_analytics_profile(true, $is_network);
        
        // Get API data handler
        $api_data = new UIPress_Analytics_Bridge_API_Data();
        
        // Check if we have a valid token
        if (empty($profile) || empty($profile['token'])) {
            ?>
            <div class="wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <div class="notice notice-error">
                    <p><?php _e('Missing authentication token. Please try authenticating again.', 'uipress-analytics-bridge'); ?></p>
                    <p><a href="<?php echo esc_url(admin_url('options-general.php?page=uipress-analytics-bridge')); ?>" class="button button-primary"><?php _e('Back to Settings', 'uipress-analytics-bridge'); ?></a></p>
                </div>
            </div>
            <?php
            return;
        }
        
        // Fetch properties
        $properties = $api_data->get_analytics_properties($profile['token']);
        
        if (is_wp_error($properties)) {
            ?>
            <div class="wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <div class="notice notice-error">
                    <p><?php echo esc_html($properties->get_error_message()); ?></p>
                    <p><a href="<?php echo esc_url(admin_url('options-general.php?page=uipress-analytics-bridge')); ?>" class="button button-primary"><?php _e('Back to Settings', 'uipress-analytics-bridge'); ?></a></p>
                </div>
            </div>
            <?php
            return;
        }
        
        // Settings page URL for redirects
        $settings_url = admin_url('options-general.php?page=uipress-analytics-bridge');
        if (is_network_admin()) {
            $settings_url = network_admin_url('settings.php?page=uipress-analytics-bridge');
        }
        
        // Enqueue property selection script
        wp_enqueue_script(
            'uipress-analytics-bridge-property-selection',
            UIPRESS_ANALYTICS_BRIDGE_URL . 'admin/js/uipress-analytics-bridge-property-selection.js',
            array('jquery'),
            UIPRESS_ANALYTICS_BRIDGE_VERSION,
            true
        );
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="uipress-analytics-bridge-header">
                <div class="uipress-analytics-bridge-header-content">
                    <p><?php _e('Select the Google Analytics property you want to connect to UIPress Pro.', 'uipress-analytics-bridge'); ?></p>
                </div>
            </div>
            
            <!-- Add nonce field for AJAX security -->
            <input type="hidden" id="uipress_analytics_bridge_nonce" value="<?php echo wp_create_nonce('uipress-analytics-bridge-nonce'); ?>">
            <input type="hidden" id="redirect_url" value="<?php echo esc_url($settings_url); ?>">
            
            <div class="uipress-analytics-bridge-properties-container">
                <h2><?php _e('Available Properties', 'uipress-analytics-bridge'); ?></h2>
                
                <?php if (empty($properties)): ?>
                    <div class="notice notice-warning">
                        <p><?php _e('No Google Analytics properties found for your account. Please make sure you have created a property in your Google Analytics account.', 'uipress-analytics-bridge'); ?></p>
                    </div>
                <?php else: ?>
                
                <div class="uipress-analytics-bridge-properties">
                    <?php foreach ($properties as $property) : ?>
                    <div class="uipress-analytics-bridge-property-card">
                        <h3><?php echo esc_html($property['property_name']); ?></h3>
                        <p class="uipress-analytics-bridge-property-account"><?php echo esc_html($property['account_name']); ?></p>
                        <p class="uipress-analytics-bridge-property-id"><?php echo esc_html($property['measurement_id']); ?></p>
                        <button type="button" class="button button-primary uipress-analytics-bridge-select-property" 
                            data-property-id="<?php echo esc_attr($property['property_id']); ?>" 
                            data-measurement-id="<?php echo esc_attr($property['measurement_id']); ?>" 
                            data-account-id="<?php echo esc_attr($property['account_id']); ?>">
                            <?php _e('Select This Property', 'uipress-analytics-bridge'); ?>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="uipress-analytics-bridge-footer">
                <p>
                    <a href="<?php echo esc_url($settings_url); ?>" class="button"><?php _e('Back to Settings', 'uipress-analytics-bridge'); ?></a>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render main section.
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function render_main_section() {
        ?>
        <p><?php _e('Configure your Google Analytics integration for UIPress Pro.', 'uipress-analytics-bridge'); ?></p>
        <?php
    }

    /**
     * Render Google API credentials field.
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function render_api_credentials_field() {
        $settings = is_network_admin() 
            ? get_site_option('uipress_analytics_bridge_settings', array()) 
            : get_option('uipress_analytics_bridge_settings', array());
        
        $client_id = isset($settings['google_client_id']) ? $settings['google_client_id'] : '';
        $client_secret = isset($settings['google_client_secret']) ? $settings['google_client_secret'] : '';
        
        // Determine callback URL for OAuth
        $callback_url = admin_url('admin.php?uipress-analytics-bridge-auth=callback');
        if (is_network_admin()) {
            $callback_url = network_admin_url('admin.php?uipress-analytics-bridge-auth=callback');
        }
        
        ?>
        <div class="uipress-analytics-bridge-field">
            <h3><?php _e('Google API Credentials', 'uipress-analytics-bridge'); ?></h3>
            <p class="description">
                <?php _e('To connect to Google Analytics, you need to create a project in the Google Cloud Console.', 'uipress-analytics-bridge'); ?>
                <a href="https://developers.google.com/analytics/devguides/reporting/data/v1/quickstart-client-libraries" target="_blank">
                    <?php _e('Learn how to create a project and get credentials', 'uipress-analytics-bridge'); ?>
                </a>
            </p>
            
            <div class="uipress-analytics-bridge-form-group">
                <label for="uipress_analytics_bridge_client_id">
                    <?php _e('Client ID', 'uipress-analytics-bridge'); ?>
                </label>
                <input type="text" 
                    id="uipress_analytics_bridge_client_id" 
                    name="uipress_analytics_bridge_settings[google_client_id]" 
                    value="<?php echo esc_attr($client_id); ?>" 
                    class="regular-text"
                    placeholder="123456789-abcdefghijklmnopqrstuvwxyz.apps.googleusercontent.com"
                >
            </div>
            
            <div class="uipress-analytics-bridge-form-group">
                <label for="uipress_analytics_bridge_client_secret">
                    <?php _e('Client Secret', 'uipress-analytics-bridge'); ?>
                </label>
                <input type="password" 
                    id="uipress_analytics_bridge_client_secret" 
                    name="uipress_analytics_bridge_settings[google_client_secret]" 
                    value="<?php echo esc_attr($client_secret); ?>" 
                    class="regular-text"
                    placeholder="GOCSPX-xxxxxxxxxxxxxxxxxxxxxxxx"
                >
            </div>
            
            <div class="uipress-analytics-bridge-form-group" style="margin-top: 20px; padding: 15px; background: #f8f8f8; border-left: 4px solid #0c5cef;">
                <h4 style="margin-top: 0;"><?php _e('Important: Google OAuth Configuration', 'uipress-analytics-bridge'); ?></h4>
                <p>
                    <?php _e('You must add this exact URL as an authorized redirect URI in your Google Cloud Console OAuth settings:', 'uipress-analytics-bridge'); ?>
                </p>
                <code style="display: block; padding: 10px; background: #fff; border: 1px solid #ddd;"><?php echo esc_url($callback_url); ?></code>
                <p>
                    <?php _e('Without this, the authentication will fail!', 'uipress-analytics-bridge'); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render advanced section.
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function render_advanced_section() {
        ?>
        <p><?php _e('Advanced settings for UIPress Analytics Bridge.', 'uipress-analytics-bridge'); ?></p>
        <?php
    }

    /**
     * Render auth field.
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function render_auth_field() {
        $auth = new UIPress_Analytics_Bridge_Auth();
        $authenticated = $auth->is_authenticated(is_network_admin());
        
        // Get auth URL directly
        $api_auth = new UIPress_Analytics_Bridge_API_Auth();
        $direct_auth_url = $api_auth->build_auth_url('auth', is_network_admin());
        
        ?>
        <div class="uipress-analytics-bridge-field">
            <div class="uipress-analytics-bridge-auth-status">
                <p>
                    <strong><?php _e('Status:', 'uipress-analytics-bridge'); ?></strong>
                    <?php if ($authenticated) : ?>
                        <span class="uipress-analytics-bridge-status uipress-analytics-bridge-status-connected">
                            <?php _e('Connected', 'uipress-analytics-bridge'); ?>
                        </span>
                    <?php else : ?>
                        <span class="uipress-analytics-bridge-status uipress-analytics-bridge-status-disconnected">
                            <?php _e('Disconnected', 'uipress-analytics-bridge'); ?>
                        </span>
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="uipress-analytics-bridge-auth-actions">
                <?php if ($authenticated) : ?>
                    <button type="button" class="button button-secondary uipress-analytics-bridge-verify-auth">
                        <?php _e('Verify Connection', 'uipress-analytics-bridge'); ?>
                    </button>
                    <button type="button" class="button button-secondary uipress-analytics-bridge-reauth">
                        <?php _e('Re-authenticate', 'uipress-analytics-bridge'); ?>
                    </button>
                    <button type="button" class="button button-secondary uipress-analytics-bridge-deauth">
                        <?php _e('Disconnect', 'uipress-analytics-bridge'); ?>
                    </button>
                <?php else : ?>
                    <button type="button" class="button button-primary uipress-analytics-bridge-auth">
                        <?php _e('Connect Google Analytics', 'uipress-analytics-bridge'); ?>
                    </button>
                    
                    <?php if (!empty($direct_auth_url)) : ?>
                    <a href="<?php echo esc_url($direct_auth_url); ?>" class="button button-secondary" style="margin-left: 10px;">
                        <?php _e('Connect Directly (Alternative)', 'uipress-analytics-bridge'); ?>
                    </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <div class="uipress-analytics-bridge-auth-description">
                <p class="description">
                    <?php _e('Connect your Google Analytics account to enable analytics data in UIPress Pro.', 'uipress-analytics-bridge'); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render connection field.
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function render_connection_field() {
        $auth = new UIPress_Analytics_Bridge_Auth();
        $profile = $auth->get_analytics_profile(false, is_network_admin());
        
        if (empty($profile)) {
            ?>
            <div class="uipress-analytics-bridge-field">
                <p><?php _e('No connection details available. Please connect your Google Analytics account.', 'uipress-analytics-bridge'); ?></p>
            </div>
            <?php
            return;
        }
        
        ?>
        <div class="uipress-analytics-bridge-field">
            <table class="form-table uipress-analytics-bridge-connection-details">
                <tbody>
                    <?php if (!empty($profile['viewname'])) : ?>
                    <tr>
                        <th scope="row"><?php _e('View Name', 'uipress-analytics-bridge'); ?></th>
                        <td><?php echo esc_html($profile['viewname']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($profile['v4'])) : ?>
                    <tr>
                        <th scope="row"><?php _e('Measurement ID', 'uipress-analytics-bridge'); ?></th>
                        <td><?php echo esc_html($profile['v4']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($profile['connection_time'])) : ?>
                    <tr>
                        <th scope="row"><?php _e('Connected On', 'uipress-analytics-bridge'); ?></th>
                        <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $profile['connection_time']); ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render debug field.
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function render_debug_field() {
        $settings = is_network_admin() 
            ? get_site_option('uipress_analytics_bridge_settings', array()) 
            : get_option('uipress_analytics_bridge_settings', array());
        
        $debug_mode = isset($settings['debug_mode']) ? $settings['debug_mode'] : false;
        
        ?>
        <div class="uipress-analytics-bridge-field">
            <label for="uipress_analytics_bridge_debug_mode">
                <input type="checkbox" id="uipress_analytics_bridge_debug_mode" name="uipress_analytics_bridge_settings[debug_mode]" value="1" <?php checked($debug_mode, true); ?>>
                <?php _e('Enable Debug Mode', 'uipress-analytics-bridge'); ?>
            </label>
            <p class="description">
                <?php _e('When enabled, debug information will be logged to the WordPress debug log.', 'uipress-analytics-bridge'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Render cache field.
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function render_cache_field() {
        $settings = is_network_admin() 
            ? get_site_option('uipress_analytics_bridge_settings', array()) 
            : get_option('uipress_analytics_bridge_settings', array());
        
        $cache_duration = isset($settings['cache_duration']) ? absint($settings['cache_duration']) : 3600;
        
        ?>
        <div class="uipress-analytics-bridge-field">
            <label for="uipress_analytics_bridge_cache_duration">
                <?php _e('Cache Duration (seconds)', 'uipress-analytics-bridge'); ?>
            </label>
            <input type="number" id="uipress_analytics_bridge_cache_duration" name="uipress_analytics_bridge_settings[cache_duration]" value="<?php echo esc_attr($cache_duration); ?>" min="0" step="1">
            <p class="description">
                <?php _e('How long to cache analytics data. Set to 0 to disable caching.', 'uipress-analytics-bridge'); ?>
            </p>
            
            <div class="uipress-analytics-bridge-cache-actions">
                <button type="button" class="button button-secondary uipress-analytics-bridge-clear-cache">
                    <?php _e('Clear Cache', 'uipress-analytics-bridge'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Admin notices.
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function admin_notices() {
        // Check if UIPress is active
        if (!UIPress_Analytics_Bridge_Detector::is_uipress_active()) {
            ?>
            <div class="notice notice-error">
                <p>
                    <?php
                    printf(
                        __('UIPress Analytics Bridge requires UIPress or UIPress Pro to be active. Please %sinstall and activate%s UIPress.', 'uipress-analytics-bridge'),
                        '<a href="' . admin_url('plugin-install.php?s=uipress&tab=search&type=term') . '">',
                        '</a>'
                    );
                    ?>
                </p>
            </div>
            <?php
            return;
        }
        
        // Check if UIPress is compatible
        if (!UIPress_Analytics_Bridge_Detector::is_uipress_compatible()) {
            ?>
            <div class="notice notice-error">
                <p>
                    <?php
                    printf(
                        __('UIPress Analytics Bridge requires UIPress %s or higher. Please update UIPress.', 'uipress-analytics-bridge'),
                        UIPress_Analytics_Bridge_Detector::$min_uipress_lite_version
                    );
                    ?>
                </p>
            </div>
            <?php
            return;
        }
    }
}