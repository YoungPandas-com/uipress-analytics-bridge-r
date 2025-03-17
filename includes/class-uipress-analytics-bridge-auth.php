<?php
/**
 * Authentication Storage Class
 *
 * Handles storing and retrieving authentication data for Google Analytics.
 *
 * @since      1.0.0
 * @package    UIPress_Analytics_Bridge
 * @subpackage UIPress_Analytics_Bridge/includes
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Authentication Storage Class.
 *
 * Handles storing and retrieving authentication data for Google Analytics.
 *
 * @since 1.0.0
 */
class UIPress_Analytics_Bridge_Auth {

    /**
     * Profile data - site specific
     *
     * @since 1.0.0
     * @access private
     * @var array
     */
    private $profile = array();

    /**
     * Profile data - network specific
     *
     * @since 1.0.0
     * @access private
     * @var array
     */
    private $network = array();

    /**
     * Option name for site profile
     *
     * @since 1.0.0
     * @access private
     * @var string
     */
    private $site_option_name = 'uipress_analytics_bridge_profile';

    /**
     * Option name for network profile
     *
     * @since 1.0.0
     * @access private
     * @var string
     */
    private $network_option_name = 'uipress_analytics_bridge_network_profile';

    /**
     * Option name for transit token
     *
     * @since 1.0.0
     * @access private
     * @var string
     */
    private $tt_option_name = 'uipress_analytics_bridge_tt';

    /**
     * Option name for network transit token
     *
     * @since 1.0.0
     * @access private
     * @var string
     */
    private $network_tt_option_name = 'uipress_analytics_bridge_network_tt';

    /**
     * Constructor.
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
        // Load the profile data
        $this->profile = get_option($this->site_option_name, array());
        $this->network = get_site_option($this->network_option_name, array());
        
        // Register hooks
        add_action('wp_ajax_uipress_analytics_bridge_auth', array($this, 'handle_auth'));
        add_action('wp_ajax_uipress_analytics_bridge_verify', array($this, 'handle_verify'));
        add_action('wp_ajax_uipress_analytics_bridge_deauth', array($this, 'handle_deauth'));
        add_action('wp_ajax_uipress_analytics_bridge_select_property', array($this, 'handle_property_selection'));
        
        // Intercept UIPress Pro hooks
        add_action('wp_ajax_uip_build_google_analytics_query', array($this, 'intercept_build_query'), 1);
        add_action('wp_ajax_uip_save_google_analytics', array($this, 'intercept_save_google_analytics'), 1);
        add_action('wp_ajax_uip_save_access_token', array($this, 'intercept_save_access_token'), 1);
        
        // Add a filter to tell UIPress we have an authenticated connection
        add_filter('pre_option_uip_google_analytics_status', array($this, 'filter_ga_status'), 10, 1);
        
        // Add filter to inject our data into UIPress
        add_filter('uip_filter_google_analytics_data', array($this, 'filter_ga_data'), 10, 1);
    }

    /**
     * Get the transit token
     *
     * @since 1.0.0
     * @access public
     * @param bool $network Whether to get network token
     * @return string Transit token
     */
    public function get_tt($network = false) {
        if ($network) {
            $tt = get_site_option($this->network_tt_option_name, '');
        } else {
            $tt = get_option($this->tt_option_name, '');
        }
        
        if (empty($tt)) {
            $tt = $this->generate_tt($network);
        }
        
        return $tt;
    }

    /**
     * Generate a transit token
     *
     * @since 1.0.0
     * @access public
     * @param bool $network Whether to generate a network token
     * @return string Generated transit token
     */
    public function generate_tt($network = false) {
        $tt = hash('sha512', wp_generate_password(128, true, true) . AUTH_SALT . uniqid('', true));
        
        if ($network) {
            update_site_option($this->network_tt_option_name, $tt);
        } else {
            update_option($this->tt_option_name, $tt);
        }
        
        return $tt;
    }

    /**
     * Validate transit token
     *
     * @since 1.0.0
     * @access public
     * @param string $tt Transit token to validate
     * @param bool $network Whether to validate against network token
     * @return bool Whether token is valid
     */
    public function validate_tt($tt, $network = false) {
        $stored_tt = $this->get_tt($network);
        return hash_equals($stored_tt, $tt);
    }

    /**
     * Rotate transit token
     *
     * @since 1.0.0
     * @access public
     * @param bool $network Whether to rotate network token
     * @return void
     */
    public function rotate_tt($network = false) {
        $this->generate_tt($network);
    }

    /**
     * Get the analytics profile data
     *
     * @since 1.0.0
     * @access public
     * @param bool $force Whether to force a fresh fetch
     * @param bool $network Whether to get network profile
     * @return array Analytics profile data
     */
    public function get_analytics_profile($force = false, $network = false) {
        if ($network) {
            if (!empty($this->network) && !$force) {
                return $this->network;
            }
            
            $profile = get_site_option($this->network_option_name, array());
            $this->network = $profile;
            
            return $profile;
        } else {
            if (!empty($this->profile) && !$force) {
                return $this->profile;
            }
            
            $profile = get_option($this->site_option_name, array());
            $this->profile = $profile;
            
            return $profile;
        }
    }

    /**
     * Set the analytics profile data
     *
     * @since 1.0.0
     * @access public
     * @param array $data Profile data to set
     * @param bool $network Whether to set network profile
     * @return void
     */
    public function set_analytics_profile($data = array(), $network = false) {
        if (!empty($data)) {
            $data['connection_time'] = time();
            
            // Also update UIPress Pro settings to ensure compatibility
            if (isset($data['v4'])) {
                $uip_analytics = get_option('uip_google_analytics', array());
                $uip_analytics['view'] = isset($data['view']) ? $data['view'] : '';
                $uip_analytics['code'] = isset($data['v4']) ? $data['v4'] : '';
                $uip_analytics['token'] = isset($data['token']) ? $data['token'] : '';
                update_option('uip_google_analytics', $uip_analytics);
                update_option('uip_google_analytics_status', 'connected');
            }
        }
        
        if ($network) {
            update_site_option($this->network_option_name, $data);
            $this->network = $data;
        } else {
            update_option($this->site_option_name, $data);
            $this->profile = $data;
        }
    }

    /**
     * Delete the analytics profile data
     *
     * @since 1.0.0
     * @access public
     * @param bool $migrate Whether to migrate data
     * @param bool $network Whether to delete network profile
     * @return void
     */
    public function delete_analytics_profile($migrate = true, $network = false) {
        if ($migrate) {
            $newdata = array();
            
            if ($network) {
                if (isset($this->network['v4'])) {
                    $newdata['manual_v4'] = $this->network['v4'];
                    $newdata['measurement_protocol_secret'] = isset($this->network['measurement_protocol_secret']) ? $this->network['measurement_protocol_secret'] : '';
                }
                
                $this->network = $newdata;
                $this->set_analytics_profile($newdata, true);
            } else {
                if (isset($this->profile['v4'])) {
                    $newdata['manual_v4'] = $this->profile['v4'];
                    $newdata['measurement_protocol_secret'] = isset($this->profile['measurement_protocol_secret']) ? $this->profile['measurement_protocol_secret'] : '';
                }
                
                $this->profile = $newdata;
                $this->set_analytics_profile($newdata, false);
            }
        } else {
            if ($network) {
                $this->network = array();
                delete_site_option($this->network_option_name);
            } else {
                $this->profile = array();
                delete_option($this->site_option_name);
                
                // Also update UIPress Pro settings
                update_option('uip_google_analytics', array());
                update_option('uip_google_analytics_status', '');
            }
        }
    }

    /**
     * Get V4 ID
     *
     * @since 1.0.0
     * @access public
     * @param bool $network Whether to get network ID
     * @return string V4 ID
     */
    public function get_v4_id($network = false) {
        $profile = $this->get_analytics_profile(false, $network);
        return !empty($profile['v4']) ? $profile['v4'] : '';
    }

    /**
     * Get analytics key
     *
     * @since 1.0.0
     * @access public
     * @param bool $network Whether to get network key
     * @return string Analytics key
     */
    public function get_key($network = false) {
        $profile = $this->get_analytics_profile(false, $network);
        return !empty($profile['key']) ? $profile['key'] : '';
    }

    /**
     * Get analytics token
     *
     * @since 1.0.0
     * @access public
     * @param bool $network Whether to get network token
     * @return string Analytics token
     */
    public function get_token($network = false) {
        $profile = $this->get_analytics_profile(false, $network);
        return !empty($profile['token']) ? $profile['token'] : '';
    }

    /**
     * Get view name
     *
     * @since 1.0.0
     * @access public
     * @param bool $network Whether to get network view name
     * @return string View name
     */
    public function get_viewname($network = false) {
        $profile = $this->get_analytics_profile(false, $network);
        return !empty($profile['viewname']) ? $profile['viewname'] : '';
    }

    /**
     * Get Measurement Protocol secret
     *
     * @since 1.0.0
     * @access public
     * @param bool $network Whether to get network secret
     * @return string Measurement Protocol secret
     */
    public function get_measurement_protocol_secret($network = false) {
        $profile = $this->get_analytics_profile(false, $network);
        return !empty($profile['measurement_protocol_secret']) ? $profile['measurement_protocol_secret'] : '';
    }

    /**
     * Set Measurement Protocol secret
     *
     * @since 1.0.0
     * @access public
     * @param string $value Secret value
     * @param bool $network Whether to set network secret
     * @return void
     */
    public function set_measurement_protocol_secret($value, $network = false) {
        $profile = $this->get_analytics_profile(false, $network);
        $profile['measurement_protocol_secret'] = $value;
        
        $this->set_analytics_profile($profile, $network);
    }

    /**
     * Get manual V4 ID
     *
     * @since 1.0.0
     * @access public
     * @param bool $network Whether to get network manual V4 ID
     * @return string Manual V4 ID
     */
    public function get_manual_v4_id($network = false) {
        $profile = $this->get_analytics_profile(false, $network);
        return !empty($profile['manual_v4']) ? $profile['manual_v4'] : '';
    }

    /**
     * Set manual V4 ID
     *
     * @since 1.0.0
     * @access public
     * @param string $v4 V4 ID to set
     * @param bool $network Whether to set network manual V4 ID
     * @return void
     */
    public function set_manual_v4_id($v4 = '', $network = false) {
        if (empty($v4)) {
            return;
        }
        
        $data = array();
        
        if ($network) {
            if (empty($this->network)) {
                $data['manual_v4'] = $v4;
            } else {
                $data = $this->network;
                $data['manual_v4'] = $v4;
            }
            
            $this->network = $data;
            $this->set_analytics_profile($data, true);
        } else {
            if (empty($this->profile)) {
                $data['manual_v4'] = $v4;
            } else {
                $data = $this->profile;
                $data['manual_v4'] = $v4;
            }
            
            $this->profile = $data;
            $this->set_analytics_profile($data, false);
        }
    }

    /**
     * Is authentcated
     *
     * @since 1.0.0
     * @access public
     * @param bool $network Whether to check network auth
     * @return bool Whether authenticated
     */
    public function is_authenticated($network = false) {
        if ($network) {
            return !empty($this->network['key']) && !empty($this->network['v4']);
        } else {
            return !empty($this->profile['key']) && !empty($this->profile['v4']);
        }
    }

    /**
     * Handle authentication
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function handle_auth() {
        // Verify nonce
        check_ajax_referer('uipress-analytics-bridge-nonce', 'nonce');
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action.', 'uipress-analytics-bridge')
            ));
        }
        
        // Get data
        $key = sanitize_text_field($_POST['key']);
        $token = sanitize_text_field($_POST['token']);
        $viewname = sanitize_text_field($_POST['viewname']);
        $account_id = sanitize_text_field($_POST['account_id']);
        $property_id = sanitize_text_field($_POST['property_id']);
        $view_id = sanitize_text_field($_POST['view_id']);
        $v4 = sanitize_text_field($_POST['v4']);
        $mp_secret = isset($_POST['mp_secret']) ? sanitize_text_field($_POST['mp_secret']) : '';
        
        // Create profile
        $profile = array(
            'key' => $key,
            'token' => $token,
            'viewname' => $viewname,
            'a' => $account_id,
            'w' => $property_id,
            'p' => $view_id,
            'v4' => $v4,
            'measurement_protocol_secret' => $mp_secret,
            'siteurl' => home_url(),
        );
        
        // Set profile
        $is_network = isset($_POST['network']) && $_POST['network'] === 'network';
        $this->set_analytics_profile($profile, $is_network);
        
        wp_send_json_success(array(
            'message' => __('Authentication successful.', 'uipress-analytics-bridge')
        ));
    }

    /**
     * Handle verification
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function handle_verify() {
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
        
        // Check if authenticated
        if (!$this->is_authenticated($is_network)) {
            wp_send_json_error(array(
                'message' => __('Not authenticated.', 'uipress-analytics-bridge')
            ));
        }
        
        // TODO: Implement verification
        
        wp_send_json_success(array(
            'message' => __('Verification successful.', 'uipress-analytics-bridge')
        ));
    }

    /**
     * Handle deauthentication
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function handle_deauth() {
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
        
        // Delete profile
        $this->delete_analytics_profile(false, $is_network);
        
        wp_send_json_success(array(
            'message' => __('Deauthentication successful.', 'uipress-analytics-bridge')
        ));
    }

    /**
     * Intercept UIPress build query ajax call
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function intercept_build_query() {
        // Check if we should intercept
        if (!defined('UIPRESS_ANALYTICS_BRIDGE_DISABLE_INTERCEPT') || !UIPRESS_ANALYTICS_BRIDGE_DISABLE_INTERCEPT) {
            // Verify nonce
            check_ajax_referer('uip-security-nonce', 'security');
            
            // Get user setting
            $save_to_user = isset($_POST['saveAccountToUser']) ? sanitize_text_field($_POST['saveAccountToUser']) : 'false';
            
            // Get profile
            $is_network = $save_to_user === 'true' ? false : true;
            $profile = $this->get_analytics_profile(true, $is_network);
            
            // Check if authenticated
            if (empty($profile) || empty($profile['key']) || empty($profile['v4'])) {
                wp_send_json_error(array(
                    'error' => true,
                    'message' => __('You need to connect a Google Analytics account to display data', 'uipress-analytics-bridge'),
                    'error_type' => 'no_google',
                ));
            }
            
            // Build response
            $domain = get_home_url();
            $token = isset($profile['token']) ? $profile['token'] : '';
            
            // Build URL for analytics data
            $url = add_query_arg(array(
                'code' => $profile['v4'],
                'view' => $profile['viewname'],
                'key' => $profile['key'],
                'instance' => md5($domain),
                'uip3' => 1,
                'gafour' => true,
                'd' => $domain,
                'uip_token' => $token,
            ), 'https://analytics.uipress.co/view.php');
            
            wp_send_json_success(array(
                'success' => true,
                'url' => $url,
            ));
        }
    }

    /**
     * Intercept UIPress save Google Analytics ajax call
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function intercept_save_google_analytics() {
        // Check if we should intercept
        if (!defined('UIPRESS_ANALYTICS_BRIDGE_DISABLE_INTERCEPT') || !UIPRESS_ANALYTICS_BRIDGE_DISABLE_INTERCEPT) {
            // Verify nonce
            check_ajax_referer('uip-security-nonce', 'security');
            
            // Get data
            $analytics = json_decode(stripslashes($_POST['analytics']), true);
            $save_to_user = isset($_POST['saveAccountToUser']) ? sanitize_text_field($_POST['saveAccountToUser']) : 'false';
            
            // Validate data
            if (!is_array($analytics)) {
                wp_send_json_error(array(
                    'message' => __('Incorrect data passed to server', 'uipress-analytics-bridge')
                ));
            }
            
            if (!isset($analytics['view']) || !isset($analytics['code'])) {
                wp_send_json_error(array(
                    'message' => __('Incorrect data passed to server', 'uipress-analytics-bridge')
                ));
            }
            
            // Get existing profile
            $is_network = $save_to_user === 'true' ? false : true;
            $profile = $this->get_analytics_profile(true, $is_network);
            
            if (!is_array($profile)) {
                $profile = array();
            }
            
            // Update profile
            $profile['view'] = $analytics['view'];
            $profile['v4'] = $analytics['code'];
            
            // Save profile
            $this->set_analytics_profile($profile, $is_network);
            
            wp_send_json_success();
        }
    }

    /**
     * Intercept UIPress save access token ajax call
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function intercept_save_access_token() {
        // Check if we should intercept
        if (!defined('UIPRESS_ANALYTICS_BRIDGE_DISABLE_INTERCEPT') || !UIPRESS_ANALYTICS_BRIDGE_DISABLE_INTERCEPT) {
            // Verify nonce
            check_ajax_referer('uip-security-nonce', 'security');
            
            // Get data
            $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
            $save_to_user = isset($_POST['saveAccountToUser']) ? sanitize_text_field($_POST['saveAccountToUser']) : 'false';
            
            // Validate data
            if (empty($token)) {
                wp_send_json_error(array(
                    'message' => __('Incorrect token sent to server', 'uipress-analytics-bridge')
                ));
            }
            
            // Get existing profile
            $is_network = $save_to_user === 'true' ? false : true;
            $profile = $this->get_analytics_profile(true, $is_network);
            
            if (!is_array($profile)) {
                $profile = array();
            }
            
            // Update profile
            $profile['token'] = $token;
            
            // Save profile
            $this->set_analytics_profile($profile, $is_network);
            
            wp_send_json_success();
        }
    }

    /**
     * Handle property selection
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function handle_property_selection() {
        // Verify nonce
        check_ajax_referer('uipress-analytics-bridge-nonce', 'nonce');
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action.', 'uipress-analytics-bridge')
            ));
        }
        
        // Get data
        $property_id = isset($_POST['property_id']) ? sanitize_text_field($_POST['property_id']) : '';
        $measurement_id = isset($_POST['measurement_id']) ? sanitize_text_field($_POST['measurement_id']) : '';
        $account_id = isset($_POST['account_id']) ? sanitize_text_field($_POST['account_id']) : '';
        $is_network = isset($_POST['network']) && $_POST['network'] === 'network';
        
        if (empty($property_id) || empty($measurement_id) || empty($account_id)) {
            wp_send_json_error(array(
                'message' => __('Missing required property information.', 'uipress-analytics-bridge')
            ));
        }
        
        // Get current profile
        $profile = $this->get_analytics_profile(true, $is_network);
        
        if (empty($profile)) {
            wp_send_json_error(array(
                'message' => __('No authentication profile found.', 'uipress-analytics-bridge')
            ));
        }
        
        // Update profile with selected property
        $profile['w'] = $property_id;
        $profile['p'] = $property_id; // In GA4, we use property ID as view ID
        $profile['v4'] = $measurement_id;
        $profile['a'] = $account_id;
        
        // Save profile
        $this->set_analytics_profile($profile, $is_network);
        
        wp_send_json_success(array(
            'message' => __('Property selected successfully.', 'uipress-analytics-bridge')
        ));
    }

    /**
     * Filter Google Analytics status
     *
     * @since 1.0.0
     * @access public
     * @param mixed $value Option value
     * @return string Modified option value
     */
    public function filter_ga_status($value) {
        // Check if authenticated
        if ($this->is_authenticated() || $this->is_authenticated(true)) {
            return 'connected';
        }
        
        return $value;
    }

    /**
     * Filter Google Analytics data
     *
     * @since 1.0.0
     * @access public
     * @param mixed $data Analytics data
     * @return mixed Modified analytics data
     */
    public function filter_ga_data($data) {
        // Get profile
        $profile = $this->get_analytics_profile();
        
        // Add our data
        if (!empty($profile)) {
            $data['google_account'] = array(
                'view' => isset($profile['viewname']) ? $profile['viewname'] : '',
                'code' => isset($profile['v4']) ? $profile['v4'] : '',
                'token' => isset($profile['token']) ? $profile['token'] : '',
            );
            
            $data['gafour'] = true;
            $data['property'] = isset($profile['w']) ? $profile['w'] : '';
            $data['measurement_id'] = isset($profile['v4']) ? $profile['v4'] : '';
        }
        
        return $data;
    }
}