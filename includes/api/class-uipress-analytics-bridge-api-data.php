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
     * API endpoints for data
     *
     * @since 1.0.0
     * @access private
     * @var string
     */
    private $api_endpoint = 'https://analyticsdata.googleapis.com/v1beta';

    /**
     * API endpoint for account listing
     *
     * @since 1.0.0
     * @access private
     * @var string
     */
    private $admin_api_endpoint = 'https://analyticsadmin.googleapis.com/v1beta';

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
    public function get_analytics_data($startDate, $endDate, $metrics, $dimensions = 'date') {
        // Get auth
        $auth = new UIPress_Analytics_Bridge_Auth();
        $profile = $auth->get_analytics_profile();
        
        // Check if authenticated
        if (empty($profile) || empty($profile['token']) || empty($profile['w'])) {
            return array(
                'success' => false,
                'message' => __('Not authenticated or missing property ID.', 'uipress-analytics-bridge'),
                'error_type' => 'no_auth',
            );
        }
        
        // Check if token needs refresh
        if (!empty($profile['token_created']) && !empty($profile['expires_in'])) {
            $expiry_time = $profile['token_created'] + $profile['expires_in'] - 300; // Refresh 5 minutes before expiry
            
            if (time() > $expiry_time && !empty($profile['refresh_token'])) {
                // Token needs refresh
                uipress_analytics_bridge_debug('Token expired, refreshing', array(
                    'expires_at' => date('Y-m-d H:i:s', $expiry_time),
                    'now' => date('Y-m-d H:i:s', time())
                ));
                
                $refresh_result = $this->refresh_token($profile);
                
                if (is_wp_error($refresh_result)) {
                    return array(
                        'success' => false,
                        'message' => $refresh_result->get_error_message(),
                        'error_type' => 'token_refresh_error',
                    );
                }
                
                // Get updated profile
                $profile = $auth->get_analytics_profile(true);
                
                if (empty($profile) || empty($profile['token'])) {
                    return array(
                        'success' => false,
                        'message' => __('Failed to refresh authentication token.', 'uipress-analytics-bridge'),
                        'error_type' => 'refresh_failed',
                    );
                }
            }
        }
        
        // Now make the actual analytics data request
        $property_id = $profile['w']; // Property ID
        $access_token = $profile['token'];
        
        // Build the Google Analytics Data API v1beta request
        $url = "https://analyticsdata.googleapis.com/v1beta/properties/{$property_id}:runReport";
        
        // Parse metrics from comma-separated string
        $metrics_array = array();
        foreach (explode(',', $metrics) as $metric) {
            // Strip 'ga:' prefix if present
            $metric_name = str_replace('ga:', '', trim($metric));
            
            // Map GA Universal Analytics metrics to GA4 metrics
            switch ($metric_name) {
                case 'users':
                    $metrics_array[] = array('name' => 'activeUsers');
                    break;
                case 'sessions':
                    $metrics_array[] = array('name' => 'sessions');
                    break;
                case 'pageviews':
                    $metrics_array[] = array('name' => 'screenPageViews');
                    break;
                default:
                    // Just use the metric as is
                    $metrics_array[] = array('name' => $metric_name);
                    break;
            }
        }
        
        // Parse dimensions
        $dimensions_array = array();
        foreach (explode(',', $dimensions) as $dimension) {
            // Strip 'ga:' prefix if present
            $dimension_name = str_replace('ga:', '', trim($dimension));
            
            // Map GA Universal Analytics dimensions to GA4 dimensions
            switch ($dimension_name) {
                case 'date':
                    $dimensions_array[] = array('name' => 'date');
                    break;
                default:
                    // Just use the dimension as is
                    $dimensions_array[] = array('name' => $dimension_name);
                    break;
            }
        }
        
        $request_body = array(
            'dateRanges' => array(
                array(
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                ),
            ),
            'dimensions' => $dimensions_array,
            'metrics' => $metrics_array,
        );
        
        uipress_analytics_bridge_debug('Sending Analytics API request', array(
            'url' => $url,
            'property_id' => $property_id,
            'metrics' => $metrics_array,
            'dimensions' => $dimensions_array,
        ));
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($request_body),
            'timeout' => 15,
        ));
        
        if (is_wp_error($response)) {
            uipress_analytics_bridge_debug('API Error', $response->get_error_message());
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
                'error_type' => 'api_error',
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['error']['message']) 
                ? $error_data['error']['message'] 
                : sprintf(__('API returned status code: %d', 'uipress-analytics-bridge'), $response_code);
            
            uipress_analytics_bridge_debug('API Error Response', array(
                'code' => $response_code,
                'body' => $body,
                'error' => $error_message
            ));
            
            return array(
                'success' => false,
                'message' => $error_message,
                'error_type' => 'api_error',
                'details' => $body,
            );
        }
        
        $data = json_decode($body, true);
        
        // For debugging - log basic structure without all rows
        $data_structure = $data;
        if (isset($data_structure['rows']) && count($data_structure['rows']) > 2) {
            $data_structure['rows'] = array(
                'first_row' => $data_structure['rows'][0],
                'total_rows' => count($data['rows'])
            );
        }
        uipress_analytics_bridge_debug('API Response Structure', $data_structure);
        
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
            
            // Get index of each metric type
            $metric_indices = array();
            if (isset($data['metricHeaders']) && is_array($data['metricHeaders'])) {
                foreach ($data['metricHeaders'] as $index => $header) {
                    $metric_name = $header['name'];
                    
                    // Map GA4 metrics to GA Universal Analytics metrics
                    switch ($metric_name) {
                        case 'activeUsers':
                            $metric_indices['users'] = $index;
                            break;
                        case 'sessions':
                            $metric_indices['sessions'] = $index;
                            break;
                        case 'screenPageViews':
                            $metric_indices['pageviews'] = $index;
                            break;
                        default:
                            $metric_indices[$metric_name] = $index;
                            break;
                    }
                }
            }
            
            // Get dimension index for date
            $date_dimension_index = null;
            if (isset($data['dimensionHeaders']) && is_array($data['dimensionHeaders'])) {
                foreach ($data['dimensionHeaders'] as $index => $header) {
                    if ($header['name'] === 'date') {
                        $date_dimension_index = $index;
                        break;
                    }
                }
            }
            
            // Process each row
            foreach ($data['rows'] as $row) {
                // Get date
                $date = null;
                if ($date_dimension_index !== null && isset($row['dimensionValues'][$date_dimension_index]['value'])) {
                    $date_raw = $row['dimensionValues'][$date_dimension_index]['value'];
                    // Format date from YYYYMMDD to YYYY-MM-DD
                    if (strlen($date_raw) === 8) {
                        $date = substr($date_raw, 0, 4) . '-' . substr($date_raw, 4, 2) . '-' . substr($date_raw, 6, 2);
                    } else {
                        // Use as is if not in expected format
                        $date = $date_raw;
                    }
                } else {
                    // Skip rows without date
                    continue;
                }
                
                // Get metric values
                $users = isset($metric_indices['users']) && isset($row['metricValues'][$metric_indices['users']]['value']) 
                    ? (int)$row['metricValues'][$metric_indices['users']]['value'] 
                    : 0;
                    
                $sessions = isset($metric_indices['sessions']) && isset($row['metricValues'][$metric_indices['sessions']]['value']) 
                    ? (int)$row['metricValues'][$metric_indices['sessions']]['value'] 
                    : 0;
                    
                $pageviews = isset($metric_indices['pageviews']) && isset($row['metricValues'][$metric_indices['pageviews']]['value']) 
                    ? (int)$row['metricValues'][$metric_indices['pageviews']]['value'] 
                    : 0;
                
                $total_users += $users;
                $total_sessions += $sessions;
                $total_pageviews += $pageviews;
                
                $response['data'][] = array(
                    'name' => $date,
                    'value' => $users,
                    'pageviews' => $pageviews,
                    'sessions' => $sessions,
                );
            }
            
            // Set total stats
            $response['totalStats']['users'] = $total_users;
            $response['totalStats']['sessions'] = $total_sessions;
            $response['totalStats']['pageviews'] = $total_pageviews;
            
            // Calculate percentage changes
            // This would be done by comparing to previous period
            // For now, set to 0 as placeholder
            $response['totalStats']['change']['users'] = 0;
            $response['totalStats']['change']['sessions'] = 0;
            $response['totalStats']['change']['pageviews'] = 0;
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
     * Get Google Analytics properties
     *
     * @since 1.0.0
     * @access public
     * @param string $access_token Access token
     * @return array|WP_Error Analytics properties or error
     */
    public function get_analytics_properties($access_token) {
        if (empty($access_token)) {
            return new WP_Error('missing_token', __('Access token is required', 'uipress-analytics-bridge'));
        }
        
        // Use the Google Analytics Admin API to get properties
        $url = 'https://analyticsadmin.googleapis.com/v1beta/properties';
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
            ),
            'timeout' => 15,
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $error_body = wp_remote_retrieve_body($response);
            $error_data = json_decode($error_body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : sprintf(__('API returned status code: %d', 'uipress-analytics-bridge'), $response_code);
            
            uipress_analytics_bridge_debug('API Error in get_analytics_properties', array(
                'code' => $response_code,
                'response' => $error_body
            ));
            
            return new WP_Error('api_error', $error_message);
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        // For debugging
        uipress_analytics_bridge_debug('Properties API Response', $data);
        
        // GA4 response structure
        if (empty($data) || !isset($data['properties'])) {
            return array(); // Return empty array instead of error for no properties
        }
        
        $properties = array();
        
        foreach ($data['properties'] as $property) {
            // Extract property ID from name (format: "properties/123456789")
            $property_id = isset($property['name']) ? str_replace('properties/', '', $property['name']) : '';
            
            // Get account ID from parent if available (format: "accounts/123456789")
            $account_id = '';
            $account_name = '';
            if (isset($property['parent'])) {
                $account_id = str_replace('accounts/', '', $property['parent']);
                // We don't have account name in this response, we'll use property name as fallback
                $account_name = isset($property['displayName']) ? $property['displayName'] . ' Account' : '';
            }
            
            // For GA4, we need to check dataStreams for measurement ID
            $measurement_id = isset($property['measurementId']) ? $property['measurementId'] : '';
            
            // If no measurement ID in property, try to use property ID with G- prefix
            if (empty($measurement_id)) {
                $measurement_id = 'G-' . $property_id;
            }
            
            $properties[] = array(
                'property_id' => $property_id,
                'property_name' => isset($property['displayName']) ? $property['displayName'] : '',
                'account_id' => $account_id,
                'account_name' => $account_name,
                'measurement_id' => $measurement_id,
            );
        }
        
        return $properties;
    }

    /**
     * Refresh access token
     *
     * @since 1.0.0
     * @access public
     * @param array $profile Authentication profile
     * @return bool|WP_Error True on success or error
     */
    public function refresh_token($profile) {
        if (empty($profile['refresh_token'])) {
            return new WP_Error('missing_refresh_token', __('Refresh token is missing', 'uipress-analytics-bridge'));
        }
        
        if (empty($profile['key'])) {
            return new WP_Error('missing_client_id', __('Client ID is missing', 'uipress-analytics-bridge'));
        }
        
        // Get client secret from settings
        $is_network = is_network_admin();
        $settings = $is_network 
            ? get_site_option('uipress_analytics_bridge_settings', array()) 
            : get_option('uipress_analytics_bridge_settings', array());
        
        $client_secret = isset($settings['google_client_secret']) ? $settings['google_client_secret'] : '';
        
        if (empty($client_secret)) {
            return new WP_Error('missing_client_secret', __('Client secret is missing', 'uipress-analytics-bridge'));
        }
        
        uipress_analytics_bridge_debug('Refreshing token', array(
            'client_id' => $profile['key'],
            'refresh_token_exists' => !empty($profile['refresh_token']),
        ));
        
        // Make request to Google for new token
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'client_id' => $profile['key'],
                'client_secret' => $client_secret,
                'refresh_token' => $profile['refresh_token'],
                'grant_type' => 'refresh_token',
            ),
        ));
        
        if (is_wp_error($response)) {
            uipress_analytics_bridge_debug('Token refresh error', $response->get_error_message());
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            uipress_analytics_bridge_debug('Token refresh failed', array(
                'status' => $response_code,
                'body' => $body
            ));
            
            return new WP_Error(
                'refresh_error',
                sprintf(__('Token refresh failed with status code: %d', 'uipress-analytics-bridge'), $response_code)
            );
        }
        
        $data = json_decode($body, true);
        
        if (empty($data) || !isset($data['access_token'])) {
            uipress_analytics_bridge_debug('Invalid response when refreshing token', $data);
            return new WP_Error('invalid_response', __('Invalid response when refreshing token', 'uipress-analytics-bridge'));
        }
        
        // Update profile with new token
        $auth = new UIPress_Analytics_Bridge_Auth();
        $updated_profile = $profile;
        $updated_profile['token'] = $data['access_token'];
        $updated_profile['token_created'] = time();
        $updated_profile['expires_in'] = isset($data['expires_in']) ? $data['expires_in'] : 3600;
        
        // If we got a new refresh token, update it
        if (!empty($data['refresh_token'])) {
            $updated_profile['refresh_token'] = $data['refresh_token'];
        }
        
        uipress_analytics_bridge_debug('Token refreshed successfully', array(
            'token_exists' => !empty($data['access_token']),
            'expires_in' => isset($data['expires_in']) ? $data['expires_in'] : 3600,
        ));
        
        $auth->set_analytics_profile($updated_profile, $is_network);
        
        return true;
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