<?php
/**
 * UIPress Detection Class
 *
 * A class that handles detecting UIPress and its versions.
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
 * UIPress Detection class.
 *
 * Detects UIPress installation and compatibility.
 *
 * @since 1.0.0
 */
class UIPress_Analytics_Bridge_Detector {

    /**
     * Minimum supported UIPress Lite version
     * 
     * @since 1.0.0
     * @access private
     * @var string
     */
    private static $min_uipress_lite_version = '3.0.0';

    /**
     * Minimum supported UIPress Pro version
     * 
     * @since 1.0.0
     * @access private
     * @var string
     */
    private static $min_uipress_pro_version = '3.0.0';

    /**
     * Check if UIPress is active
     *
     * @since 1.0.0
     * @access public
     * @static
     * @return boolean
     */
    public static function is_uipress_active() {
        return self::is_uipress_lite_active() || self::is_uipress_pro_active();
    }

    /**
     * Check if UIPress Lite is active
     *
     * @since 1.0.0
     * @access public
     * @static
     * @return boolean
     */
    public static function is_uipress_lite_active() {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
        return is_plugin_active('uipress-lite/uipress-lite.php');
    }

    /**
     * Check if UIPress Pro is active
     *
     * @since 1.0.0
     * @access public
     * @static
     * @return boolean
     */
    public static function is_uipress_pro_active() {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
        return is_plugin_active('uipress-pro/uipress-pro.php');
    }

    /**
     * Get UIPress Lite version
     *
     * @since 1.0.0
     * @access public
     * @static
     * @return string|null
     */
    public static function get_uipress_lite_version() {
        if (!self::is_uipress_lite_active()) {
            return null;
        }

        if (defined('uip_plugin_version')) {
            return uip_plugin_version;
        }

        return null;
    }

    /**
     * Get UIPress Pro version
     *
     * @since 1.0.0
     * @access public
     * @static
     * @return string|null
     */
    public static function get_uipress_pro_version() {
        if (!self::is_uipress_pro_active()) {
            return null;
        }

        if (defined('uip_pro_plugin_version')) {
            return uip_pro_plugin_version;
        }

        return null;
    }

    /**
     * Check if UIPress Lite version is compatible
     *
     * @since 1.0.0
     * @access public
     * @static
     * @return boolean
     */
    public static function is_uipress_lite_compatible() {
        $version = self::get_uipress_lite_version();
        
        if (!$version) {
            return false;
        }
        
        return version_compare($version, self::$min_uipress_lite_version, '>=');
    }

    /**
     * Check if UIPress Pro version is compatible
     *
     * @since 1.0.0
     * @access public
     * @static
     * @return boolean
     */
    public static function is_uipress_pro_compatible() {
        $version = self::get_uipress_pro_version();
        
        if (!$version) {
            return false;
        }
        
        return version_compare($version, self::$min_uipress_pro_version, '>=');
    }

    /**
     * Check if we have a compatible version of UIPress installed
     *
     * @since 1.0.0
     * @access public
     * @static
     * @return boolean
     */
    public static function is_uipress_compatible() {
        if (self::is_uipress_pro_active() && !self::is_uipress_pro_compatible()) {
            return false;
        }
        
        if (self::is_uipress_lite_active() && !self::is_uipress_lite_compatible()) {
            return false;
        }
        
        return self::is_uipress_active();
    }

    /**
     * Check if we're on a UIPress admin page
     *
     * @since 1.0.0
     * @access public
     * @static
     * @return boolean
     */
    public static function is_uipress_admin_page() {
        if (!is_admin()) {
            return false;
        }
        
        $screen = get_current_screen();
        if (!$screen) {
            return false;
        }
        
        return (strpos($screen->id, 'uipress') !== false);
    }
}