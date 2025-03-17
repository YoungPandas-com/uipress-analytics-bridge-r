<?php
/**
 * Account Selector Class
 *
 * Handles Google Analytics account, property, and view selection.
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
 * Account Selector Class.
 *
 * Handles Google Analytics account, property, and view selection.
 *
 * @since 1.0.0
 */
class UIPress_Analytics_Bridge_Account_Selector {

    /**
     * API endpoint for account listing
     *
     * @since 1.0.0
     * @access private
     * @var string
     */
    private $api_endpoint = 'https://analytics.example.com/accounts/';

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
        // Register AJAX handlers
        add_action('wp_ajax_uipress_analytics_bridge_get_accounts', array($this, 'get_accounts'));
        add_action('wp_ajax_uipress_analytics_bridge_get_properties', array($this, 'get_properties'));
        add_action('wp_ajax_uipress_analytics_bridge_get_views', array($this, 'get_views'));
    }

    /**
     * Get Google Analytics accounts (AJAX handler)
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function get_accounts() {
        // Verify nonce
        check_ajax_referer('uipress-analytics-bridge-nonce', 'nonce');
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action.', 'uipress-analytics-bridge')
            ));
        }
        
        // Get temp auth data from session
        $auth_data = get_transient('uipress_analytics_bridge_temp_auth');
        
        if (empty($auth_data) || empty($auth_data['key']) || empty($auth_data['token'])) {
            wp_send_json_error(array(
                'message' => __('Authentication data not found or expired.', 'uipress-analytics-bridge')
            ));
        }
        
        // Get accounts
        $accounts = $this->fetch_accounts($auth_data);
        
        if (is_wp_error($accounts)) {
            wp_send_json_error(array(
                'message' => $accounts->get_error_message()
            ));
        }
        
        wp_send_json_success(array(
            'accounts' => $accounts
        ));
    }

    /**
     * Get Google Analytics properties (AJAX handler)
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function get_properties() {
        // Verify nonce
        check_ajax_referer('uipress-analytics-bridge-nonce', 'nonce');
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action.', 'uipress-analytics-bridge')
            ));
        }
        
        // Get account ID
        $account_id = isset($_POST['account_id']) ? sanitize_text_field($_POST['account_id']) : '';
        
        if (empty($account_id)) {
            wp_send_json_error(array(
                'message' => __('Account ID is required.', 'uipress-analytics-bridge')
            ));
        }
        
        // Get temp auth data from session
        $auth_data = get_transient('uipress_analytics_bridge_temp_auth');
        
        if (empty($auth_data) || empty($auth_data['key']) || empty($auth_data['token'])) {
            wp_send_json_error(array(
                'message' => __('Authentication data not found or expired.', 'uipress-analytics-bridge')
            ));
        }
        
        // Get properties
        $properties = $this->fetch_properties($auth_data, $account_id);
        
        if (is_wp_error($properties)) {
            wp_send_json_error(array(
                'message' => $properties->get_error_message()
            ));
        }
        
        wp_send_json_success(array(
            'properties' => $properties
        ));
    }

    /**
     * Get Google Analytics views (AJAX handler)
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function get_views() {
        // Verify nonce
        check_ajax_referer('uipress-analytics-bridge-nonce', 'nonce');
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action.', 'uipress-analytics-bridge')
            ));
        }
        
        // Get account ID and property ID
        $account_id = isset($_POST['account_id']) ? sanitize_text_field($_POST['account_id']) : '';
        $property_id = isset($_POST['property_id']) ? sanitize_text_field($_POST['property_id']) : '';
        
        if (empty($account_id) || empty($property_id)) {
            wp_send_json_error(array(
                'message' => __('Account ID and Property ID are required.', 'uipress-analytics-bridge')
            ));
        }
        
        // Get temp auth data from session
        $auth_data = get_transient('uipress_analytics_bridge_temp_auth');
        
        if (empty($auth_data) || empty($auth_data['key']) || empty($auth_data['token'])) {
            wp_send_json_error(array(
                'message' => __('Authentication data not found or expired.', 'uipress-analytics-bridge')
            ));
        }
        
        // Get views
        $views = $this->fetch_views($auth_data, $account_id, $property_id);
        
        if (is_wp_error($views)) {
            wp_send_json_error(array(
                'message' => $views->get_error_message()
            ));
        }
        
        wp_send_json_success(array(
            'views' => $views
        ));
    }

    /**
     * Fetch Google Analytics accounts
     *
     * @since 1.0.0
     * @access private
     * @param array $auth_data Authentication data
     * @return array|WP_Error Accounts or error
     */
    private function fetch_accounts($auth_data) {
        // Build request URL
        $url = add_query_arg(array(), $this->api_endpoint);
        
        // Build request args
        $request_args = array(
            'body' => array(
                'key' => $auth_data['key'],
                'token' => $auth_data['token'],
                'site' => home_url(),
                'action' => 'accounts',
                'version' => UIPRESS_ANALYTICS_BRIDGE_VERSION,
            ),
            'timeout' => 15,
            'sslverify' => true,
        );
        
        // Send request
        $response = wp_remote_post($url, $request_args);
        
        // Check for errors
        if (is_wp_error($response)) {
            return new WP_Error('api_error', $response->get_error_message());
        }
        
        // Check response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error('api_error', sprintf(__('API returned status code: %d', 'uipress-analytics-bridge'), $response_code));
        }
        
        // Parse response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Check if successful
        if (!isset($data['success']) || !$data['success']) {
            $message = isset($data['message']) ? $data['message'] : __('Unknown API error', 'uipress-analytics-bridge');
            return new WP_Error('api_error', $message);
        }
        
        // Check if accounts are present
        if (!isset($data['accounts']) || !is_array($data['accounts'])) {
            return new WP_Error('api_error', __('No accounts found', 'uipress-analytics-bridge'));
        }
        
        return $data['accounts'];
    }

    /**
     * Fetch Google Analytics properties
     *
     * @since 1.0.0
     * @access private
     * @param array $auth_data Authentication data
     * @param string $account_id Account ID
     * @return array|WP_Error Properties or error
     */
    private function fetch_properties($auth_data, $account_id) {
        // Build request URL
        $url = add_query_arg(array(), $this->api_endpoint);
        
        // Build request args
        $request_args = array(
            'body' => array(
                'key' => $auth_data['key'],
                'token' => $auth_data['token'],
                'site' => home_url(),
                'action' => 'properties',
                'account_id' => $account_id,
                'version' => UIPRESS_ANALYTICS_BRIDGE_VERSION,
            ),
            'timeout' => 15,
            'sslverify' => true,
        );
        
        // Send request
        $response = wp_remote_post($url, $request_args);
        
        // Check for errors
        if (is_wp_error($response)) {
            return new WP_Error('api_error', $response->get_error_message());
        }
        
        // Check response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error('api_error', sprintf(__('API returned status code: %d', 'uipress-analytics-bridge'), $response_code));
        }
        
        // Parse response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Check if successful
        if (!isset($data['success']) || !$data['success']) {
            $message = isset($data['message']) ? $data['message'] : __('Unknown API error', 'uipress-analytics-bridge');
            return new WP_Error('api_error', $message);
        }
        
        // Check if properties are present
        if (!isset($data['properties']) || !is_array($data['properties'])) {
            return new WP_Error('api_error', __('No properties found', 'uipress-analytics-bridge'));
        }
        
        return $data['properties'];
    }

    /**
     * Fetch Google Analytics views
     *
     * @since 1.0.0
     * @access private
     * @param array $auth_data Authentication data
     * @param string $account_id Account ID
     * @param string $property_id Property ID
     * @return array|WP_Error Views or error
     */
    private function fetch_views($auth_data, $account_id, $property_id) {
        // Build request URL
        $url = add_query_arg(array(), $this->api_endpoint);
        
        // Build request args
        $request_args = array(
            'body' => array(
                'key' => $auth_data['key'],
                'token' => $auth_data['token'],
                'site' => home_url(),
                'action' => 'views',
                'account_id' => $account_id,
                'property_id' => $property_id,
                'version' => UIPRESS_ANALYTICS_BRIDGE_VERSION,
            ),
            'timeout' => 15,
            'sslverify' => true,
        );
        
        // Send request
        $response = wp_remote_post($url, $request_args);
        
        // Check for errors
        if (is_wp_error($response)) {
            return new WP_Error('api_error', $response->get_error_message());
        }
        
        // Check response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error('api_error', sprintf(__('API returned status code: %d', 'uipress-analytics-bridge'), $response_code));
        }
        
        // Parse response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Check if successful
        if (!isset($data['success']) || !$data['success']) {
            $message = isset($data['message']) ? $data['message'] : __('Unknown API error', 'uipress-analytics-bridge');
            return new WP_Error('api_error', $message);
        }
        
        // Check if views are present
        if (!isset($data['views']) || !is_array($data['views'])) {
            return new WP_Error('api_error', __('No views found', 'uipress-analytics-bridge'));
        }
        
        return $data['views'];
    }

    /**
     * Store temporary authentication data
     *
     * @since 1.0.0
     * @access public
     * @param array $auth_data Authentication data
     * @return bool Whether data was stored
     */
    public function store_temp_auth_data($auth_data) {
        return set_transient('uipress_analytics_bridge_temp_auth', $auth_data, 15 * MINUTE_IN_SECONDS);
    }

    /**
     * Get temporary authentication data
     *
     * @since 1.0.0
     * @access public
     * @return array|false Authentication data or false if not found
     */
    public function get_temp_auth_data() {
        return get_transient('uipress_analytics_bridge_temp_auth');
    }

    /**
     * Delete temporary authentication data
     *
     * @since 1.0.0
     * @access public
     * @return bool Whether data was deleted
     */
    public function delete_temp_auth_data() {
        return delete_transient('uipress_analytics_bridge_temp_auth');
    }

    /**
     * Render account selection UI
     *
     * @since 1.0.0
     * @access public
     * @return string HTML for account selection UI
     */
    public function render_account_selection_ui() {
        ob_start();
        ?>
        <div class="uipress-analytics-bridge-account-selector">
            <div class="uipress-analytics-bridge-step" data-step="1">
                <h3><?php _e('Select Google Analytics Account', 'uipress-analytics-bridge'); ?></h3>
                <p class="description"><?php _e('Select the Google Analytics account you want to connect.', 'uipress-analytics-bridge'); ?></p>
                <div class="uipress-analytics-bridge-accounts-list">
                    <div class="uipress-analytics-bridge-loading">
                        <span class="spinner is-active"></span>
                        <?php _e('Loading accounts...', 'uipress-analytics-bridge'); ?>
                    </div>
                    <div class="uipress-analytics-bridge-accounts-container"></div>
                </div>
                <div class="uipress-analytics-bridge-error" style="display: none;"></div>
                <div class="uipress-analytics-bridge-actions">
                    <button type="button" class="button button-primary uipress-analytics-bridge-next-step" data-next-step="2" disabled>
                        <?php _e('Next', 'uipress-analytics-bridge'); ?>
                    </button>
                </div>
            </div>
            
            <div class="uipress-analytics-bridge-step" data-step="2" style="display: none;">
                <h3><?php _e('Select Property', 'uipress-analytics-bridge'); ?></h3>
                <p class="description"><?php _e('Select the Google Analytics property you want to connect.', 'uipress-analytics-bridge'); ?></p>
                <div class="uipress-analytics-bridge-properties-list">
                    <div class="uipress-analytics-bridge-loading">
                        <span class="spinner is-active"></span>
                        <?php _e('Loading properties...', 'uipress-analytics-bridge'); ?>
                    </div>
                    <div class="uipress-analytics-bridge-properties-container"></div>
                </div>
                <div class="uipress-analytics-bridge-error" style="display: none;"></div>
                <div class="uipress-analytics-bridge-actions">
                    <button type="button" class="button uipress-analytics-bridge-prev-step" data-prev-step="1">
                        <?php _e('Back', 'uipress-analytics-bridge'); ?>
                    </button>
                    <button type="button" class="button button-primary uipress-analytics-bridge-next-step" data-next-step="3" disabled>
                        <?php _e('Next', 'uipress-analytics-bridge'); ?>
                    </button>
                </div>
            </div>
            
            <div class="uipress-analytics-bridge-step" data-step="3" style="display: none;">
                <h3><?php _e('Select View', 'uipress-analytics-bridge'); ?></h3>
                <p class="description"><?php _e('Select the Google Analytics view you want to connect.', 'uipress-analytics-bridge'); ?></p>
                <div class="uipress-analytics-bridge-views-list">
                    <div class="uipress-analytics-bridge-loading">
                        <span class="spinner is-active"></span>
                        <?php _e('Loading views...', 'uipress-analytics-bridge'); ?>
                    </div>
                    <div class="uipress-analytics-bridge-views-container"></div>
                </div>
                <div class="uipress-analytics-bridge-error" style="display: none;"></div>
                <div class="uipress-analytics-bridge-actions">
                    <button type="button" class="button uipress-analytics-bridge-prev-step" data-prev-step="2">
                        <?php _e('Back', 'uipress-analytics-bridge'); ?>
                    </button>
                    <button type="button" class="button button-primary uipress-analytics-bridge-finish" disabled>
                        <?php _e('Finish', 'uipress-analytics-bridge'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render property selection modal
     *
     * @since 1.0.0
     * @access public
     * @param array $properties Analytics properties
     * @return string HTML for property selection modal
     */
    public function render_property_selection_modal($properties) {
        ob_start();
        ?>
        <div id="uipress-analytics-bridge-property-modal" class="uipress-analytics-bridge-modal">
            <div class="uipress-analytics-bridge-modal-content">
                <div class="uipress-analytics-bridge-modal-header">
                    <h2 class="uipress-analytics-bridge-modal-title"><?php _e('Select Google Analytics Property', 'uipress-analytics-bridge'); ?></h2>
                    <span class="uipress-analytics-bridge-modal-close" data-action="close">&times;</span>
                </div>
                <div class="uipress-analytics-bridge-modal-body">
                    <p><?php _e('Select the Google Analytics property you want to connect:', 'uipress-analytics-bridge'); ?></p>
                    
                    <div class="uipress-analytics-bridge-properties-list">
                        <?php foreach ($properties as $property) : ?>
                        <div class="uipress-analytics-bridge-property-item" data-property-id="<?php echo esc_attr($property['property_id']); ?>" data-measurement-id="<?php echo esc_attr($property['measurement_id']); ?>" data-account-id="<?php echo esc_attr($property['account_id']); ?>">
                            <div class="uipress-analytics-bridge-property-name"><?php echo esc_html($property['property_name']); ?></div>
                            <div class="uipress-analytics-bridge-property-details">
                                <?php echo esc_html($property['account_name']); ?> &middot; 
                                <?php echo esc_html($property['measurement_id']); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="uipress-analytics-bridge-modal-footer">
                    <button type="button" class="button button-secondary" data-action="close"><?php _e('Cancel', 'uipress-analytics-bridge'); ?></button>
                    <button type="button" class="button button-primary" data-action="select" disabled><?php _e('Select Property', 'uipress-analytics-bridge'); ?></button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}