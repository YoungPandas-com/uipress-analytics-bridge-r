<?php
/**
 * API Authentication Class
 *
 * Handles Google API authentication.
 *
 * @since      1.0.0
 * @package    UIPress_Analytics_Bridge
 * @subpackage UIPress_Analytics_Bridge/includes/api
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * API Authentication Class.
 *
 * Handles Google API authentication.
 *
 * @since 1.0.0
 */
class UIPress_Analytics_Bridge_API_Auth {

    /**
     * Google OAuth endpoint
     *
     * @since 1.0.0
     * @access private
     * @var string
     */
    private $google_auth_url = 'https://accounts.google.com/o/oauth2/v2/auth';

    /**
     * Google Token endpoint
     * 
     * @since 1.0.0
     * @access private
     * @var string
     */
    private $google_token_url = 'https://oauth2.googleapis.com/token';

    /**
     * Scopes needed for Analytics API
     * 
     * @since 1.0.0
     * @access private
     * @var string
     */
    private $required_scopes = 'https://www.googleapis.com/auth/analytics.readonly';

    /**
     * Constructor
     *
     * @since 1.0.0
     * @access public
     */
    public function __construct() {
        // Register AJAX handlers directly in constructor
        add_action('wp_ajax_uipress_analytics_bridge_get_auth_url', array($this, 'get_auth_url'));
        add_action('wp_ajax_uipress_analytics_bridge_verify_auth', array($this, 'verify_auth'));
        
        // Register auth callback listener
        add_action('admin_init', array($this, 'auth_callback_listener'));
    }

    /**
     * Initialize the class
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function init() {
        // This method left intentionally empty, as we're registering actions in the constructor
    }

    /**
     * Listen for authentication callbacks
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function auth_callback_listener() {
        // Check if this is an auth callback
        if (empty($_GET['uipress-analytics-bridge-auth']) || $_GET['uipress-analytics-bridge-auth'] !== 'callback') {
            return;
        }
        
        uipress_analytics_bridge_debug('Auth callback initiated');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'uipress-analytics-bridge'));
        }
        
        // Verify state parameter (same as transit token)
        $auth = new UIPress_Analytics_Bridge_Auth();
        if (empty($_GET['state']) || !$auth->validate_tt($_GET['state'])) {
            wp_die(__('Invalid security token. Please try again.', 'uipress-analytics-bridge'));
        }
        
        // Check for required parameters
        if (empty($_GET['code'])) {
            wp_die(__('Missing authorization code. Please try again.', 'uipress-analytics-bridge'));
        }
        
        $is_network = isset($_GET['network']) && $_GET['network'] === 'network';
        
        // Exchange code for access token
        $settings = $is_network 
            ? get_site_option('uipress_analytics_bridge_settings', array()) 
            : get_option('uipress_analytics_bridge_settings', array());
        
        $client_id = isset($settings['google_client_id']) ? $settings['google_client_id'] : '';
        $client_secret = isset($settings['google_client_secret']) ? $settings['google_client_secret'] : '';
        
        if (empty($client_id) || empty($client_secret)) {
            wp_die(__('API credentials are missing. Please configure your Google API credentials in the plugin settings.', 'uipress-analytics-bridge'));
        }
        
        // Build callback URL
        $callback_url = admin_url('admin.php?uipress-analytics-bridge-auth=callback');
        if ($is_network) {
            $callback_url = network_admin_url('admin.php?uipress-analytics-bridge-auth=callback');
        }
        
        // Add network parameter if needed
        if ($is_network) {
            $callback_url = add_query_arg('network', 'network', $callback_url);
        }
        
        uipress_analytics_bridge_debug('Exchanging code for token', array(
            'code_exists' => !empty($_GET['code']),
            'callback_url' => $callback_url
        ));
        
        // Exchange code for token
        $response = wp_remote_post($this->google_token_url, array(
            'body' => array(
                'code' => $_GET['code'],
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri' => $callback_url,
                'grant_type' => 'authorization_code',
            ),
            'timeout' => 15,
        ));
        
        if (is_wp_error($response)) {
            uipress_analytics_bridge_debug('Error exchanging code for token', $response->get_error_message());
            wp_die(__('Error exchanging code for access token: ', 'uipress-analytics-bridge') . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $token_data = json_decode($body, true);
        
        uipress_analytics_bridge_debug('Token exchange response', array(
            'status' => $response_code,
            'success' => isset($token_data['access_token']),
        ));
        
        if ($response_code !== 200 || empty($token_data) || !isset($token_data['access_token'])) {
            $error_message = isset($token_data['error_description']) ? $token_data['error_description'] : __('Invalid response from Google when exchanging code for token.', 'uipress-analytics-bridge');
            uipress_analytics_bridge_debug('Token exchange failed', array(
                'response' => $token_data,
                'error' => $error_message
            ));
            wp_die($error_message);
        }
        
        // Store the authentication data
        $profile = array(
            'key' => $client_id,
            'token' => $token_data['access_token'],
            'refresh_token' => isset($token_data['refresh_token']) ? $token_data['refresh_token'] : '',
            'expires_in' => isset($token_data['expires_in']) ? $token_data['expires_in'] : 3600,
            'token_created' => time(),
            'token_type' => isset($token_data['token_type']) ? $token_data['token_type'] : 'Bearer',
        );
        
        uipress_analytics_bridge_debug('Authentication successful', array(
            'profile' => array(
                'token_exists' => !empty($profile['token']),
                'refresh_token_exists' => !empty($profile['refresh_token']),
                'expires_in' => $profile['expires_in'],
            )
        ));
        
        // Create profile with authentication data
        $auth->set_analytics_profile($profile, $is_network);
        
        // Rotate the transit token for security
        $auth->rotate_tt($is_network);
        
        // Redirect to property selection page
        $redirect_url = admin_url('options-general.php?page=uipress-analytics-bridge&select_property=1');
        if ($is_network) {
            $redirect_url = network_admin_url('settings.php?page=uipress-analytics-bridge&select_property=1');
        }
        
        uipress_analytics_bridge_debug('Redirecting to property selection', $redirect_url);
        
        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Get authentication URL (AJAX handler)
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function get_auth_url() {
        // Add this line at the beginning of the function
        uipress_analytics_bridge_debug('AJAX handler get_auth_url called');
        
        // Verify nonce
        check_ajax_referer('uipress-analytics-bridge-nonce', 'nonce');
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action.', 'uipress-analytics-bridge')
            ));
        }
        
        // Get data
        $is_network = isset($_POST['network']) && $_POST['network'] === 'network';
        
        // Get settings
        $settings = $is_network 
            ? get_site_option('uipress_analytics_bridge_settings', array()) 
            : get_option('uipress_analytics_bridge_settings', array());
        
        $client_id = isset($settings['google_client_id']) ? $settings['google_client_id'] : '';
        $client_secret = isset($settings['google_client_secret']) ? $settings['google_client_secret'] : '';
        
        // Add this debug line
        uipress_analytics_bridge_debug('Client credentials', ['id_exists' => !empty($client_id), 'secret_exists' => !empty($client_secret)]);
        
        if (empty($client_id) || empty($client_secret)) {
            wp_send_json_error(array(
                'message' => __('Please enter both Google API Client ID and Client Secret.', 'uipress-analytics-bridge')
            ));
            return;
        }
        
        // Build auth URL using the new method
        $url = $this->build_auth_url('auth', $is_network);
        
        // Add this debug line before the json response
        uipress_analytics_bridge_debug('Generated auth URL', $url);
        
        wp_send_json_success(array(
            'redirect' => $url,
        ));
    }

    /**
     * Build authentication URL
     *
     * @since 1.0.0
     * @access public
     * @param string $auth_type Type of authentication
     * @param bool $is_network Whether this is a network request
     * @return string Authentication URL
     */
    public function build_auth_url($auth_type = 'auth', $is_network = false) {
        // Get settings
        $settings = $is_network 
            ? get_site_option('uipress_analytics_bridge_settings', array()) 
            : get_option('uipress_analytics_bridge_settings', array());
        
        $client_id = isset($settings['google_client_id']) ? $settings['google_client_id'] : '';
        $client_secret = isset($settings['google_client_secret']) ? $settings['google_client_secret'] : '';
        
        if (empty($client_id) || empty($client_secret)) {
            return '';
        }
        
        // Generate transit token
        $auth = new UIPress_Analytics_Bridge_Auth();
        $tt = $auth->generate_tt($is_network);
        
        // Build callback URL
        $callback_url = admin_url('admin.php?uipress-analytics-bridge-auth=callback');
        if ($is_network) {
            $callback_url = network_admin_url('admin.php?uipress-analytics-bridge-auth=callback');
        }
        
        // Add network parameter if needed
        if ($is_network) {
            $callback_url = add_query_arg('network', 'network', $callback_url);
        }
        
        // Build Google OAuth URL
        $args = array(
            'client_id' => $client_id,
            'redirect_uri' => $callback_url,
            'response_type' => 'code',
            'scope' => $this->required_scopes,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $tt,
        );
        
        // Build final URL
        return add_query_arg($args, $this->google_auth_url);
    }

    /**
     * Maybe refresh token
     *
     * @since 1.0.0
     * @access public
     * @param array $profile Authentication profile
     * @return bool|WP_Error True on success, false if not needed, WP_Error on failure
     */
    public function maybe_refresh_token($profile) {
        // Check if we need to refresh the token
        if (empty($profile) || empty($profile['token_created']) || empty($profile['expires_in']) || empty($profile['refresh_token'])) {
            return false;
        }
        
        $expiry_time = $profile['token_created'] + $profile['expires_in'] - 300; // Refresh 5 minutes before expiry
        
        if (time() > $expiry_time) {
            uipress_analytics_bridge_debug('Token needs refreshing', array(
                'created' => $profile['token_created'],
                'expires_in' => $profile['expires_in'],
                'expiry_time' => $expiry_time,
                'current_time' => time()
            ));
            
            // Token needs refresh
            $api_data = new UIPress_Analytics_Bridge_API_Data();
            return $api_data->refresh_token($profile);
        }
        
        return false;
    }

    /**
     * Verify authentication (AJAX handler)
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function verify_auth() {
        // Verify nonce
        check_ajax_referer('uipress-analytics-bridge-nonce', 'nonce');
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action.', 'uipress-analytics-bridge')
            ));
        }
        
        // Get data
        $is_network = isset($_POST['network']) && $_POST['network'] === 'network';
        
        // Get auth
        $auth = new UIPress_Analytics_Bridge_Auth();
        
        // Check if authenticated
        if (!$auth->is_authenticated($is_network)) {
            wp_send_json_error(array(
                'message' => __('Not authenticated.', 'uipress-analytics-bridge')
            ));
        }
        
        // Get profile
        $profile = $auth->get_analytics_profile(true, $is_network);
        
        // Send verification request
        $verification = $this->verify_credentials($profile);
        
        if ($verification) {
            wp_send_json_success(array(
                'message' => __('Verification successful.', 'uipress-analytics-bridge')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Verification failed.', 'uipress-analytics-bridge')
            ));
        }
    }

    /**
     * Verify credentials
     *
     * @since 1.0.0
     * @access public
     * @param array $profile Profile data
     * @return bool Whether verification was successful
     */
    public function verify_credentials($profile) {
        // Check if we have credentials
        if (empty($profile) || empty($profile['token'])) {
            return false;
        }
        
        // Verify token directly with Google
        $response = wp_remote_get('https://www.googleapis.com/oauth2/v1/tokeninfo', array(
            'body' => array(
                'access_token' => $profile['token']
            ),
            'timeout' => 15,
            'sslverify' => true,
        ));
        
        // Check for errors
        if (is_wp_error($response)) {
            return false;
        }
        
        // Check response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return false;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        // Check if response contains error
        if (isset($data['error'])) {
            return false;
        }
        
        return true;
    }
}