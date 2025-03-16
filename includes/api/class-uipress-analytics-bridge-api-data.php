<?php
/**
 * API Data Class
 *
 * Handles retrieving and processing Google Analytics data.
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
 * API Data Class.
 *
 * Handles retrieving and processing Google Analytics data.
 *
 * @since 1.0.0
 */
class UIPress_Analytics_Bridge_API_Data {

    /**
     * API endpoint for data
     *
     * @since 1.0.0
     * @access private
     * @var string
     */
    private $api_endpoint = 'https://analytics.example.com/data/';

    /**
     * Constructor
     *
     * @since 1.0.0
     * @access public
     */
    public function __construct() {
        // No initialization required
    }

    /**
     * Get analytics data
     *
     * @since 1.0.0
     * @access public
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @param string $metrics Metrics to retrieve (comma-separated)
     * @param string $dimensions Dimensions to group by (comma-separated)
     * @param array $args Additional arguments
     * @return array Analytics data
     */
    public function get_analytics_data($startDate, $endDate, $metrics, $dimensions = 'ga:date', $args = array()) {
        // Get auth
        $auth = new UIPress_Analytics_Bridge_Auth();
        $profile = $auth->get_analytics_profile();
        
        // Check if authenticated
        if (empty($profile) || empty($profile['key']) || empty($profile['token']) || empty($profile['v4'])) {
            return array(
                'success' => false,
                'message' => __('Not authenticated.', 'uipress-analytics-bridge'),
                'error_type' => 'no_auth',
            );
        }
        
        // Build request
        $request_args = array(
            'start_date' => $startDate,
            'end_date' => $endDate,
            'metrics' => $metrics,
            'dimensions' => $dimensions,
            'key' => $profile['key'],
            'token' => $profile['token'],
            'v4' => $profile['v4'],
            'view' => isset($profile['viewname']) ? $profile['viewname'] : '',
            'a' => isset($profile['a']) ? $profile['a'] : '',
            'w' => isset($profile['w']) ? $profile['w'] : '',
            'p' => isset($profile['p']) ? $profile['p'] : '',
            'site' => home_url(),
            'version' => UIPRESS_ANALYTICS_BRIDGE_VERSION,
        );
        
        // Add additional args
        $request_args = array_merge($request_args, $args);
        
        // Send request
        $response = $this->send_api_request($request_args);
        
        // Process response
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
                'error_type' => 'api_error',
            );
        }
        
        // Check response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return array(
                'success' => false,
                'message' => sprintf(__('API returned status code: %d', 'uipress-analytics-bridge'), $response_code),
                'error_type' => 'api_error',
            );
        }
        
        // Parse response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Check if successful
        if (!isset($data['success']) || !$data['success']) {
            $message = isset($data['message']) ? $data['message'] : __('Unknown API error', 'uipress-analytics-bridge');
            $error_type = isset($data['error_type']) ? $data['error_type'] : 'api_error';
            
            return array(
                'success' => false,
                'message' => $message,
                'error_type' => $error_type,
            );
        }
        
        // Return data
        return $data;
    }

    /**
     * Send API request
     *
     * @since 1.0.0
     * @access private
     * @param array $args Request arguments
     * @return array|WP_Error Response or error
     */
    private function send_api_request($args) {
        // Build request URL
        $url = add_query_arg(array(), $this->api_endpoint);
        
        // Build request args
        $request_args = array(
            'body' => $args,
            'timeout' => 15,
            'sslverify' => true,
        );
        
        // Send request
        $response = wp_remote_post($url, $request_args);
        
        return $response;
    }

    /**
     * Create optimized batch request to Google Analytics
     * 
     * @since 1.0.0
     * @access public
     * @param array $required_reports List of reports needed
     * @return array Batched API response
     */
    public function batch_analytics_requests($required_reports) {
        // Get auth
        $auth = new UIPress_Analytics_Bridge_Auth();
        $profile = $auth->get_analytics_profile();
        
        // Check if authenticated
        if (empty($profile) || empty($profile['key']) || empty($profile['token']) || empty($profile['v4'])) {
            return array(
                'success' => false,
                'message' => __('Not authenticated.', 'uipress-analytics-bridge'),
                'error_type' => 'no_auth',
            );
        }
        
        // Prepare batch request
        $batch_requests = array();
        
        foreach ($required_reports as $report_key => $report_config) {
            $batch_requests[] = array(
                'metrics' => $report_config['metrics'],
                'dimensions' => $report_config['dimensions'],
                'date_ranges' => $report_config['date_ranges'],
                'report_key' => $report_key,
            );
        }
        
        // Build request
        $request_args = array(
            'batch' => json_encode($batch_requests),
            'key' => $profile['key'],
            'token' => $profile['token'],
            'v4' => $profile['v4'],
            'view' => isset($profile['viewname']) ? $profile['viewname'] : '',
            'a' => isset($profile['a']) ? $profile['a'] : '',
            'w' => isset($profile['w']) ? $profile['w'] : '',
            'p' => isset($profile['p']) ? $profile['p'] : '',
            'site' => home_url(),
            'version' => UIPRESS_ANALYTICS_BRIDGE_VERSION,
        );
        
        // Send request
        $response = $this->send_api_request($request_args);
        
        // Process response
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
                'error_type' => 'api_error',
            );
        }
        
        // Check response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return array(
                'success' => false,
                'message' => sprintf(__('API returned status code: %d', 'uipress-analytics-bridge'), $response_code),
                'error_type' => 'api_error',
            );
        }
        
        // Parse response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Return processed data
        return $this->process_batch_response($data, $required_reports);
    }

    /**
     * Process batch response
     *
     * @since 1.0.0
     * @access private
     * @param array $response API response
     * @param array $required_reports Required reports
     * @return array Processed data
     */
    private function process_batch_response($response, $required_reports) {
        // Check if successful
        if (!isset($response['success']) || !$response['success']) {
            $message = isset($response['message']) ? $response['message'] : __('Unknown API error', 'uipress-analytics-bridge');
            $error_type = isset($response['error_type']) ? $response['error_type'] : 'api_error';
            
            return array(
                'success' => false,
                'message' => $message,
                'error_type' => $error_type,
            );
        }
        
        // Process each report in the batch
        $processed = array(
            'success' => true,
            'connected' => true,
            'data' => array(),
        );
        
        foreach ($required_reports as $report_key => $report_config) {
            if (isset($response['reports'][$report_key])) {
                $processed[$report_key] = $response['reports'][$report_key];
            }
        }
        
        // Add account info
        $auth = new UIPress_Analytics_Bridge_Auth();
        $profile = $auth->get_analytics_profile();
        
        if (!empty($profile)) {
            $processed['google_account'] = array(
                'view' => isset($profile['viewname']) ? $profile['viewname'] : '',
                'code' => isset($profile['v4']) ? $profile['v4'] : '',
                'token' => isset($profile['token']) ? $profile['token'] : '',
            );
            
            $processed['gafour'] = true;
            $processed['property'] = isset($profile['w']) ? $profile['w'] : '';
            $processed['measurement_id'] = isset($profile['v4']) ? $profile['v4'] : '';
        }
        
        return $processed;
    }

    /**
     * Get common dashboard metrics for aggregate data
     *
     * @since 1.0.0
     * @access public
     * @return array Common metrics configuration
     */
    public function get_common_dashboard_metrics() {
        return array(
            'overview' => array(
                'metrics' => 'ga:users,ga:sessions,ga:pageviews',
                'dimensions' => 'ga:date',
                'date_ranges' => array(
                    'start_date' => date('Y-m-d', strtotime('-30 days')),
                    'end_date' => date('Y-m-d'),
                ),
            ),
            'topContent' => array(
                'metrics' => 'ga:pageviews,ga:uniquePageviews,ga:avgTimeOnPage,ga:bounceRate',
                'dimensions' => 'ga:pagePath,ga:pageTitle',
                'date_ranges' => array(
                    'start_date' => date('Y-m-d', strtotime('-30 days')),
                    'end_date' => date('Y-m-d'),
                ),
            ),
            'topSources' => array(
                'metrics' => 'ga:sessions,ga:bounceRate',
                'dimensions' => 'ga:source,ga:medium',
                'date_ranges' => array(
                    'start_date' => date('Y-m-d', strtotime('-30 days')),
                    'end_date' => date('Y-m-d'),
                ),
            ),
        );
    }
}