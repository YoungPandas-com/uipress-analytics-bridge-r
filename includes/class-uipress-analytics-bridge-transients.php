<?php
/**
 * Transients Manager Class
 *
 * Handles caching of data using WordPress transients.
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
 * Transients Manager Class.
 *
 * Handles caching of data using WordPress transients with intelligent expiration.
 *
 * @since 1.0.0
 */
class UIPress_Analytics_Bridge_Transients {

    /**
     * Prefix for all transients
     *
     * @since 1.0.0
     * @access private
     * @var string
     */
    private $prefix = 'uipress_analytics_bridge_';

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
     * Get cached data
     *
     * @since 1.0.0
     * @access public
     * @param string $key Cache key
     * @return mixed|false Cached data or false if not found
     */
    public function get_cache($key) {
        return get_transient($this->prefix . $key);
    }

    /**
     * Set cached data
     *
     * @since 1.0.0
     * @access public
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param int $expiration Expiration time in seconds
     * @return bool Whether data was set
     */
    public function set_cache($key, $data, $expiration = 3600) {
        return set_transient($this->prefix . $key, $data, $expiration);
    }

    /**
     * Delete cached data
     *
     * @since 1.0.0
     * @access public
     * @param string $key Cache key
     * @return bool Whether data was deleted
     */
    public function delete_cache($key) {
        return delete_transient($this->prefix . $key);
    }

    /**
     * Delete all plugin cached data
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function delete_all_cache() {
        global $wpdb;
        
        // Get all transients with our prefix
        $transients = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s",
                '_transient_' . $this->prefix . '%'
            )
        );
        
        // Delete each transient
        foreach ($transients as $transient) {
            $key = str_replace('_transient_', '', $transient);
            delete_transient($key);
        }
    }

    /**
     * Get analytics data with intelligent cache handling
     * 
     * @since 1.0.0
     * @access public
     * @param string $start_date Start date for analytics data
     * @param string $end_date End date for analytics data
     * @param array $metrics Metrics to retrieve
     * @param array $args Additional arguments
     * @param callable $fetch_callback Callback to fetch fresh data
     * @return array Analytics data
     */
    public function get_analytics_data($start_date, $end_date, $metrics, $args = array(), $fetch_callback = null) {
        // Generate a unique cache key based on parameters
        $cache_key = 'analytics_' . md5($start_date . $end_date . serialize($metrics) . serialize($args));
        
        // Check if we have valid cached data
        $cached_data = $this->get_cache($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        // No cache or expired cache, fetch fresh data
        if (is_callable($fetch_callback)) {
            $analytics_data = call_user_func($fetch_callback, $start_date, $end_date, $metrics, $args);
            
            if (!is_wp_error($analytics_data)) {
                // Cache successful responses with appropriate expiration
                $cache_time = $this->determine_cache_expiration($start_date, $end_date);
                $this->set_cache($cache_key, $analytics_data, $cache_time);
            }
            
            return $analytics_data;
        }
        
        return array(
            'success' => false,
            'message' => __('No data fetch callback provided', 'uipress-analytics-bridge')
        );
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
     * Store aggregate analytics data for faster dashboard loading
     *
     * @since 1.0.0
     * @access public
     * @param array $data The data to store
     * @return bool Whether data was stored successfully
     */
    public function store_aggregate_data($data) {
        $aggregated = array(
            'data' => $data,
            'generated' => time(),
            'expires' => time() + (3 * HOUR_IN_SECONDS)
        );
        
        return update_option($this->prefix . 'aggregate_data', $aggregated);
    }

    /**
     * Get pre-compiled aggregate data if available
     *
     * @since 1.0.0
     * @access public
     * @return array|false Aggregate data or false if expired/not available
     */
    public function get_aggregate_data() {
        $aggregate = get_option($this->prefix . 'aggregate_data');
        
        if (!empty($aggregate) && isset($aggregate['expires']) && $aggregate['expires'] > time()) {
            return $aggregate['data'];
        }
        
        return false;
    }
}