<?php
/**
 * Data Handling Class
 *
 * Handles formatting and processing of analytics data.
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
 * Data Handling Class.
 *
 * Handles formatting and processing of analytics data.
 *
 * @since 1.0.0
 */
class UIPress_Analytics_Bridge_Data {

    /**
     * Transients manager
     *
     * @since 1.0.0
     * @access private
     * @var UIPress_Analytics_Bridge_Transients
     */
    private $transients;

    /**
     * API data handler
     *
     * @since 1.0.0
     * @access private
     * @var UIPress_Analytics_Bridge_API_Data
     */
    private $api_data;

    /**
     * Constructor
     *
     * @since 1.0.0
     * @access public
     */
    public function __construct() {
        $this->transients = new UIPress_Analytics_Bridge_Transients();
        $this->api_data = new UIPress_Analytics_Bridge_API_Data();
    }

    /**
     * Initialize the class
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function init() {
        // Intercept UIPress analytics data requests
        add_action('wp_ajax_uip_get_analytics_data', array($this, 'intercept_analytics_data'), 1);
        
        // Register our own AJAX handlers
        add_action('wp_ajax_uipress_analytics_bridge_get_data', array($this, 'get_analytics_data'));
    }

    /**
     * Intercept UIPress analytics data requests
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function intercept_analytics_data() {
        // Check if we should intercept
        if (!defined('UIPRESS_ANALYTICS_BRIDGE_DISABLE_INTERCEPT') || !UIPRESS_ANALYTICS_BRIDGE_DISABLE_INTERCEPT) {
            // Verify nonce
            check_ajax_referer('uip-security-nonce', 'security');
            
            // Get parameters
            $startDate = isset($_POST['dateRange']['startDate']) ? sanitize_text_field($_POST['dateRange']['startDate']) : date('Y-m-d', strtotime('-30 days'));
            $endDate = isset($_POST['dateRange']['endDate']) ? sanitize_text_field($_POST['dateRange']['endDate']) : date('Y-m-d');
            $dimensions = isset($_POST['dimensions']) ? sanitize_text_field($_POST['dimensions']) : 'ga:date';
            $metrics = isset($_POST['metrics']) ? sanitize_text_field($_POST['metrics']) : 'ga:users,ga:sessions,ga:pageviews';
            
            // Get auth
            $auth = new UIPress_Analytics_Bridge_Auth();
            $profile = $auth->get_analytics_profile();
            
            // Check if authenticated
            if (empty($profile) || empty($profile['key']) || empty($profile['v4'])) {
                wp_send_json_error(array(
                    'error' => true,
                    'message' => __('You need to connect a Google Analytics account to display data', 'uipress-analytics-bridge'),
                    'error_type' => 'no_google',
                ));
            }
            
            // Get analytics data
            $data = $this->get_data($startDate, $endDate, $metrics, $dimensions);
            
            // Send response
            if (isset($data['success']) && $data['success']) {
                wp_send_json_success($data);
            } else {
                wp_send_json_error($data);
            }
        }
    }

    /**
     * Get analytics data (AJAX handler)
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function get_analytics_data() {
        // Verify nonce
        check_ajax_referer('uipress-analytics-bridge-nonce', 'nonce');
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action.', 'uipress-analytics-bridge')
            ));
        }
        
        // Get parameters
        $startDate = isset($_POST['startDate']) ? sanitize_text_field($_POST['startDate']) : date('Y-m-d', strtotime('-30 days'));
        $endDate = isset($_POST['endDate']) ? sanitize_text_field($_POST['endDate']) : date('Y-m-d');
        $metrics = isset($_POST['metrics']) ? sanitize_text_field($_POST['metrics']) : 'ga:users,ga:sessions,ga:pageviews';
        $dimensions = isset($_POST['dimensions']) ? sanitize_text_field($_POST['dimensions']) : 'ga:date';
        
        // Get data
        $data = $this->get_data($startDate, $endDate, $metrics, $dimensions);
        
        // Send response
        if (isset($data['success']) && $data['success']) {
            wp_send_json_success($data);
        } else {
            wp_send_json_error($data);
        }
    }

    /**
     * Get analytics data
     *
     * @since 1.0.0
     * @access public
     * @param string $startDate Start date
     * @param string $endDate End date
     * @param string $metrics Metrics to retrieve
     * @param string $dimensions Dimensions to group by
     * @return array Analytics data
     */
    public function get_data($startDate, $endDate, $metrics, $dimensions) {
        // Get cached data
        $cache_key = md5($startDate . $endDate . $metrics . $dimensions);
        $cached_data = $this->transients->get_cache($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        // Get data from API
        $data = $this->api_data->get_analytics_data($startDate, $endDate, $metrics, $dimensions);
        
        // If successful, cache the data
        if (isset($data['success']) && $data['success']) {
            $cache_expiration = $this->determine_cache_expiration($startDate, $endDate);
            $this->transients->set_cache($cache_key, $data, $cache_expiration);
        }
        
        return $data;
    }

    /**
     * Get fallback/default data
     *
     * @since 1.0.0
     * @access public
     * @return array Default data structure
     */
    public function get_default_data() {
        return array(
            'success' => true,
            'connected' => false,
            'data' => array(),
            'totalStats' => array(
                'users' => 0,
                'pageviews' => 0,
                'sessions' => 0,
                'change' => array(
                    'users' => 0,
                    'pageviews' => 0,
                    'sessions' => 0
                )
            ),
            'topContent' => array(),
            'topSources' => array(),
            'gafour' => true,
            'message' => __('No data available', 'uipress-analytics-bridge')
        );
    }

    /**
     * Format analytics data for UIPress compatibility
     *
     * @since 1.0.0
     * @access public
     * @param array $data Raw data from API
     * @return array Formatted data
     */
    public function format_data_for_uipress($data) {
        // Start with the default structure
        $formatted = $this->get_default_data();
        
        // If we have valid data, format it
        if (isset($data['success']) && $data['success'] && !empty($data['data'])) {
            $formatted['connected'] = true;
            $formatted['data'] = $data['data'];
            
            // Set total stats
            if (isset($data['totalStats'])) {
                $formatted['totalStats'] = $data['totalStats'];
            }
            
            // Set top content
            if (isset($data['topContent'])) {
                $formatted['topContent'] = $data['topContent'];
            }
            
            // Set top sources
            if (isset($data['topSources'])) {
                $formatted['topSources'] = $data['topSources'];
            }
            
            // Add account info
            $auth = new UIPress_Analytics_Bridge_Auth();
            $profile = $auth->get_analytics_profile();
            
            if (!empty($profile)) {
                $formatted['google_account'] = array(
                    'view' => isset($profile['viewname']) ? $profile['viewname'] : '',
                    'code' => isset($profile['v4']) ? $profile['v4'] : '',
                    'token' => isset($profile['token']) ? $profile['token'] : '',
                );
                
                $formatted['property'] = isset($profile['w']) ? $profile['w'] : '';
                $formatted['measurement_id'] = isset($profile['v4']) ? $profile['v4'] : '';
            }
        }
        
        return $formatted;
    }

    /**
     * Determine appropriate cache expiration time based on date range
     *
     * @since 1.0.0
     * @access private
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return int Cache time in seconds
     */
    private function determine_cache_expiration($start_date, $end_date) {
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $end = new DateTime($end_date, new DateTimeZone('UTC'));
        $diff = $now->diff($end);
        
        // For today's data: cache for just 30 minutes
        if ($diff->days === 0) {
            return 30 * MINUTE_IN_SECONDS;
        }
        
        // For yesterday's data: cache for 3 hours
        if ($diff->days === 1) {
            return 3 * HOUR_IN_SECONDS;
        }
        
        // For this week's data: cache for 12 hours
        if ($diff->days < 7) {
            return 12 * HOUR_IN_SECONDS;
        }
        
        // For older data: cache for 24 hours
        return DAY_IN_SECONDS;
    }

    /**
     * Check for mismatched timezones between site and Google Analytics
     *
     * @since 1.0.0
     * @access public
     * @param string $ga_timezone Google Analytics timezone
     * @return bool|array False if no mismatch, or array with timezone info
     */
    public function check_timezone_mismatch($ga_timezone) {
        try {
            $wp_timezone = wp_timezone_string();
            
            // Convert to comparable formats
            $ga_tz_obj = new DateTimeZone($ga_timezone);
            $wp_tz_obj = new DateTimeZone($wp_timezone);
            
            $ga_offset = $ga_tz_obj->getOffset(new DateTime('now'));
            $wp_offset = $wp_tz_obj->getOffset(new DateTime('now'));
            
            // If offsets don't match, return mismatch data
            if ($ga_offset !== $wp_offset) {
                return array(
                    'ga_timezone' => $ga_timezone,
                    'wp_timezone' => $wp_timezone,
                    'ga_offset' => $ga_offset,
                    'wp_offset' => $wp_offset,
                    'detected' => time()
                );
            }
        } catch (Exception $e) {
            // In case of date/timezone errors
            return false;
        }
        
        return false;
    }
}