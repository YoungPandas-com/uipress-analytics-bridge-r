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
     * Auth relay endpoint
     *
     * @since 1.0.0
     * @access private
     * @var string
     */
    private $relay_endpoint = 'https://auth.example.com/analytics-relay/';

    /**
     * Authentication endpoint
     * 
     * @since 1.0.0
     * @access private
     * @var string
     */
    private $auth_endpoint = 'auth';

    /**
     * Re-authentication endpoint
     * 
     * @since 1.0.0
     * @access private
     * @var string
     */
    private $reauth_endpoint = 'reauth';

    /**
     * Verification endpoint
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
        
        // Verify transit token
        $auth = new UIPress_Analytics_Bridge_Auth();
        if (empty($_GET['tt']) || !$auth->validate_tt($_GET['tt'])) {
            wp_die(__('Invalid security token.', 'uipress-analytics-bridge'));
        }
        
        // Check for required parameters
        if (
            empty($_GET['key']) ||
            empty($_GET['token']) ||
            empty($_GET['miview']) ||
            empty($_GET['a']) ||
            empty($_GET['w']) ||
            empty($_GET['p']) ||
            empty($_GET['v4'])
        ) {
            wp_die(__('Missing required parameters.', 'uipress-analytics-bridge'));
        }
        
        // Create profile
        $profile = array(
            'key' => sanitize_text_field($_GET['key']),
            'token' => sanitize_text_field($_GET['token']),
            'viewname' => sanitize_text_field($_GET['miview']),
            'a' => sanitize_text_field($_GET['a']),
            'w' => sanitize_text_field($_GET['w']),
            'p' => sanitize_text_field($_GET['p']),
            'v4' => sanitize_text_field($_GET['v4']),
            'siteurl' => home_url(),
        );
        
        // Add measurement protocol secret if provided
        if (!empty($_GET['mp'])) {
            $profile['measurement_protocol_secret'] = sanitize_text_field($_GET['mp']);
        }
        
        // Save profile
        $is_network = isset($_GET['network']) && $_GET['network'] === 'network';
        $auth->set_analytics_profile($profile, $is_network);
        
        // Rotate transit token
        $auth->rotate_tt($is_network);
        
        // Redirect to settings page
        $redirect_url = admin_url('options-general.php?page=uipress-analytics-bridge&auth=success');
        if ($is_network) {
            $redirect_url = network_admin_url('settings.php?page=uipress-analytics-bridge&auth=success');
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
        
        // Determine endpoint
        $endpoint = $this->auth_endpoint;
        if ($auth_type === 'reauth') {
            $endpoint = $this->reauth_endpoint;
        } elseif ($auth_type === 'verify') {
            $endpoint = $this->verify_endpoint;
        }
        
        // Build URL
        $callback_url = admin_url('admin.php?uipress-analytics-bridge-auth=callback');
        if ($is_network) {
            $callback_url = network_admin_url('admin.php?uipress-analytics-bridge-auth=callback');
        }
        
        $args = array(
            'tt' => $tt,
            'site' => home_url(),
            'network' => $is_network ? 'network' : 'site',
            'callback' => $callback_url,
            'version' => UIPRESS_ANALYTICS_BRIDGE_VERSION,
        );
        
        // For reauth, add existing credentials
        if ($auth_type === 'reauth') {
            $profile = $auth->get_analytics_profile(false, $is_network);
            
            if (!empty($profile)) {
                $args['key'] = $profile['key'];
                $args['token'] = $profile['token'];
            }
        }
        
        // Build final URL
        $url = add_query_arg($args, $this->relay_endpoint . $endpoint);
        
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
        if (empty($profile) || empty($profile['key']) || empty($profile['token'])) {
            return false;
        }
        
        // Build verification request
        $url = $this->relay_endpoint . $this->verify_endpoint;
        
        $args = array(
            'body' => array(
                'key' => $profile['key'],
                'token' => $profile['token'],
                'site' => home_url(),
                'version' => UIPRESS_ANALYTICS_BRIDGE_VERSION,
            ),
            'timeout' => 15,
            'sslverify' => true,
        );
        
        // Send request
        $response = wp_remote_post($url, $args);
        
        // Check for errors
        if (is_wp_error($response)) {
            return false;
        }
        
        // Check response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return false;
        }
        
        // Parse response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Check if successful
        if (!isset($data['success']) || !$data['success']) {
            return false;
        }
        
        return true;
    }
}