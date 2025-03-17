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
     * @param string $metrics Metrics to retrieve
     * @param string $dimensions Dimensions to group by
     * @return array Analytics data
     */
    public function get_analytics_data($startDate, $endDate, $metrics, $dimensions = 'ga:date') {
        // Get auth
        $auth = new UIPress_Analytics_Bridge_Auth();
        $profile = $auth->get_analytics_profile();
        
        // Check if authenticated
        if (empty($profile) || empty($profile['token']) || empty($profile['v4'])) {
            return array(
                'success' => false,
                'message' => __('Not authenticated.', 'uipress-analytics-bridge'),
                'error_type' => 'no_auth',
            );
        }
        
        // Check if token needs refresh
        $api_auth = new UIPress_Analytics_Bridge_API_Auth();
        $token_refresh = $api_auth->maybe_refresh_token($profile);
        
        if ($token_refresh && !is_wp_error($token_refresh)) {
            // Token was refreshed, update the profile
            $profile = $auth->get_analytics_profile(true);
        } elseif (is_wp_error($token_refresh)) {
            return array(
                'success' => false,
                'message' => $token_refresh->get_error_message(),
                'error_type' => 'token_refresh_error',
            );
        }
        
        // Now make the actual analytics data request
        $property_id = $profile['w'];
        $access_token = $profile['token'];
        
        // Build the Google Analytics Data API v1 request
        $url = "https://analyticsdata.googleapis.com/v1beta/properties/{$property_id}:runReport";
        
        $request_body = array(
            'dateRanges' => array(
                array(
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                ),
            ),
            'dimensions' => array(
                array('name' => 'date'),
            ),
            'metrics' => array(
                array('name' => 'activeUsers'),
                array('name' => 'sessions'),
                array('name' => 'screenPageViews'),
            ),
        );
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($request_body),
            'timeout' => 15,
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
                'error_type' => 'api_error',
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return array(
                'success' => false,
                'message' => sprintf(__('API returned status code: %d', 'uipress-analytics-bridge'), $response_code),
                'error_type' => 'api_error',
            );
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        // Format data for UIPress
        $formatted_data = $this->format_ga4_data_for_uipress($data, $profile);
        
        return $formatted_data;
    }

    /**
     * Format GA4 data for UIPress
     *
     * @since 1.0.0
     * @access private
     * @param array $data Raw GA4 data
     * @param array $profile Analytics profile
     * @return array Formatted data
     */
    private function format_ga4_data_for_uipress($data, $profile) {
        // Initialize the response structure
        $response = array(
            'success' => true,
            'connected' => true,
            'data' => array(),
            'totalStats' => array(
                'users' => 0,
                'pageviews' => 0,
                'sessions' => 0,
                'change' => array(
                    'users' => 0,
                    'pageviews' => 0,
                    'sessions' => 0,
                ),
            ),
            'topContent' => array(),
            'topSources' => array(),
            'google_account' => array(
                'view' => isset($profile['viewname']) ? $profile['viewname'] : '',
                'code' => isset($profile['v4']) ? $profile['v4'] : '',
                'token' => isset($profile['token']) ? $profile['token'] : '',
            ),
            'gafour' => true,
            'property' => isset($profile['w']) ? $profile['w'] : '',
            'measurement_id' => isset($profile['v4']) ? $profile['v4'] : '',
        );
        
        // Process the rows data
        if (!empty($data) && isset($data['rows']) && !empty($data['rows'])) {
            $total_users = 0;
            $total_sessions = 0;
            $total_pageviews = 0;
            
            foreach ($data['rows'] as $row) {
                $date = $row['dimensionValues'][0]['value'];
                $formatted_date = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2);
                
                $users = isset($row['metricValues'][0]['value']) ? (int)$row['metricValues'][0]['value'] : 0;
                $sessions = isset($row['metricValues'][1]['value']) ? (int)$row['metricValues'][1]['value'] : 0;
                $pageviews = isset($row['metricValues'][2]['value']) ? (int)$row['metricValues'][2]['value'] : 0;
                
                $total_users += $users;
                $total_sessions += $sessions;
                $total_pageviews += $pageviews;
                
                $response['data'][] = array(
                    'name' => $formatted_date,
                    'value' => $users,
                    'pageviews' => $pageviews,
                    'sessions' => $sessions,
                );
            }
            
            // Set total stats
            $response['totalStats']['users'] = $total_users;
            $response['totalStats']['sessions'] = $total_sessions;
            $response['totalStats']['pageviews'] = $total_pageviews;
        }
        
        return $response;
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