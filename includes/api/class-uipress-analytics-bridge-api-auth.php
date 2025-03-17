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
     * Auth relay endpoint (legacy)
     *
     * @since 1.0.0
     * @access private
     * @var string
     */
    private $relay_endpoint = 'https://auth.example.com/analytics-relay/';

    /**
     * Authentication endpoint (legacy)
     * 
     * @since 1.0.0
     * @access private
     * @var string
     */
    private $auth_endpoint = 'auth';

    /**
     * Re-authentication endpoint (legacy)
     * 
     * @since 1.0.0
     * @access private
     * @var string
     */
    private $reauth_endpoint = 'reauth';

    /**
     * Verification endpoint (legacy)
     * 
     * @since 1.0.0
     * @access private
     * @var string
     */
    private $verify_endpoint = 'verify';

    /**
     * Constructor
     *
     * @since 1.0.0
     * @access public
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
    }

    /**
     * Initialize the class
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function init() {
        // Register auth callback listener
        add_action('admin_init', array($this, 'auth_callback_listener'));
        
        // Register AJAX handlers
        add_action('wp_ajax_uipress_analytics_bridge_get_auth_url', array($this, 'get_auth_url'));
        add_action('wp_ajax_uipress_analytics_bridge_verify_auth', array($this, 'verify_auth'));
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
        
        // Check if user can manage options
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'uipress-analytics-bridge'));
        }
        
        // Verify state parameter (same as transit token)
        $auth = new UIPress_Analytics_Bridge_Auth();
        if (empty($_GET['state']) || !$auth->validate_tt($_GET['state'])) {
            wp_die(__('Invalid security token.', 'uipress-analytics-bridge'));
        }
        
        // Check for required parameters
        if (empty($_GET['code'])) {
            wp_die(__('Missing authorization code.', 'uipress-analytics-bridge'));
        }
        
        $is_network = isset($_GET['network']) && $_GET['network'] === 'network';
        
        // Exchange code for access token
        $settings = $is_network 
            ? get_site_option('uipress_analytics_bridge_settings', array()) 
            : get_option('uipress_analytics_bridge_settings', array());
        
        $client_id = isset($settings['google_client_id']) ? $settings['google_client_id'] : '';
        $client_secret = isset($settings['google_client_secret']) ? $settings['google_client_secret'] : '';
        
        if (empty($client_id) || empty($client_secret)) {
            wp_die(__('API credentials are missing.', 'uipress-analytics-bridge'));
        }
        
        // Build callback URL
        $callback_url = admin_url('admin.php?uipress-analytics-bridge-auth=callback');
        if ($is_network) {
            $callback_url = network_admin_url('admin.php?uipress-analytics-bridge-auth=callback');
        }
        
        // Exchange code for token
        $response = wp_remote_post($this->google_token_url, array(
            'body' => array(
                'code' => $_GET['code'],
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri' => $callback_url,
                'grant_type' => 'authorization_code',
            ),
        ));
        
        if (is_wp_error($response)) {
            wp_die(__('Error exchanging code for access token: ', 'uipress-analytics-bridge') . $response->get_error_message());
        }
        
        $token_data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($token_data) || !isset($token_data['access_token'])) {
            wp_die(__('Invalid response from Google when exchanging code for token.', 'uipress-analytics-bridge'));
        }
        
        // Store the temporary authentication data
        $temp_auth = array(
            'access_token' => $token_data['access_token'],
            'refresh_token' => $token_data['refresh_token'] ?? '',
            'expires_in' => $token_data['expires_in'] ?? 3600,
            'token_created' => time(),
            'key' => $client_id,
            'token' => $token_data['access_token'],
        );
        
        // Create profile with temporary data
        $auth->set_analytics_profile($temp_auth, $is_network);
        
        // Redirect to property selection page
        $redirect_url = admin_url('options-general.php?page=uipress-analytics-bridge&select_property=1');
        if ($is_network) {
            $redirect_url = network_admin_url('settings.php?page=uipress-analytics-bridge&select_property=1');
        }
        
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
        $auth_type = isset($_POST['auth_type']) ? sanitize_text_field($_POST['auth_type']) : 'auth';
        
        // Generate URL
        $url = $this->build_auth_url($auth_type, $is_network);
        
        wp_send_json_success(array(
            'redirect' => $url,
        ));
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
     * Build authentication URL
     *
     * @since 1.0.0
     * @access public
     * @param string $auth_type Type of authentication (auth, reauth, verify)
     * @param bool $is_network Whether this is a network request
     * @return string Authentication URL
     */
    public function build_auth_url($auth_type = 'auth', $is_network = false) {
        // Get auth
        $auth = new UIPress_Analytics_Bridge_Auth();
        
        // Generate transit token
        $tt = $auth->generate_tt($is_network);
        
        // Get client credentials
        $settings = $is_network 
            ? get_site_option('uipress_analytics_bridge_settings', array()) 
            : get_option('uipress_analytics_bridge_settings', array());
        
        $client_id = isset($settings['google_client_id']) ? $settings['google_client_id'] : '';
        
        if (empty($client_id)) {
            return '';
        }
        
        // Build callback URL
        $callback_url = admin_url('admin.php?uipress-analytics-bridge-auth=callback');
        if ($is_network) {
            $callback_url = network_admin_url('admin.php?uipress-analytics-bridge-auth=callback');
        }
        
        // Add network parameter to callback URL if needed
        if ($is_network) {
            $callback_url = add_query_arg(array('network' => 'network'), $callback_url);
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
        $url = add_query_arg($args, $this->google_auth_url);
        
        return $url;
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

    /**
     * Refresh access token
     *
     * @since 1.0.0
     * @access public
     * @param string $refresh_token Refresh token
     * @param bool $is_network Whether this is a network request
     * @return array|WP_Error Token data or error
     */
    public function refresh_access_token($refresh_token, $is_network = false) {
        // Get credentials
        $settings = $is_network 
            ? get_site_option('uipress_analytics_bridge_settings', array()) 
            : get_option('uipress_analytics_bridge_settings', array());
        
        $client_id = isset($settings['google_client_id']) ? $settings['google_client_id'] : '';
        $client_secret = isset($settings['google_client_secret']) ? $settings['google_client_secret'] : '';
        
        if (empty($client_id) || empty($client_secret)) {
            return new WP_Error('missing_credentials', __('API credentials are missing.', 'uipress-analytics-bridge'));
        }
        
        // Request new access token
        $response = wp_remote_post($this->google_token_url, array(
            'body' => array(
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $refresh_token,
                'grant_type' => 'refresh_token',
            ),
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $token_data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($token_data) || !isset($token_data['access_token'])) {
            return new WP_Error('invalid_response', __('Invalid response when refreshing token.', 'uipress-analytics-bridge'));
        }
        
        // Update the profile with the new token
        $auth = new UIPress_Analytics_Bridge_Auth();
        $profile = $auth->get_analytics_profile(true, $is_network);
        
        if (!empty($profile)) {
            $profile['token'] = $token_data['access_token'];
            $profile['token_created'] = time();
            $profile['expires_in'] = $token_data['expires_in'] ?? 3600;
            
            $auth->set_analytics_profile($profile, $is_network);
        }
        
        return $token_data;
    }

    /**
     * Check if access token needs refreshing
     *
     * @since 1.0.0
     * @access public
     * @param array $profile Analytics profile
     * @param bool $is_network Whether this is a network profile
     * @return bool|array False if no refresh needed, or new token data
     */
    public function maybe_refresh_token($profile, $is_network = false) {
        if (empty($profile) || empty($profile['token']) || empty($profile['refresh_token'])) {
            return false;
        }
        
        // Check if token is expired or about to expire (within 5 minutes)
        $token_created = isset($profile['token_created']) ? $profile['token_created'] : 0;
        $expires_in = isset($profile['expires_in']) ? $profile['expires_in'] : 3600;
        
        if (time() > ($token_created + $expires_in - 300)) {
            // Token is expired or about to expire, refresh it
            return $this->refresh_access_token($profile['refresh_token'], $is_network);
        }
        
        return false;
    }
}