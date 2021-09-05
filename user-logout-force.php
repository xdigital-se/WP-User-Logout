<?php
/**
 * Plugin Name: User logout force
 * Description: See online users and logout all users.
 * Version: 1..0
 * Author: xdigital
 * Author URI: https://www.xdigital.se
 * Text Domain: user-logout-force
 * Domain Path: /languages/
 *
 * @package    ulf
 * @author     Amirhossein Meydani
 * @since      1.0.0
 * @license    MIT
 */

defined( 'ABSPATH' ) or die(); // Exit if called directly

// Define root path
defined('WP_USER_LOGOUT_FORCE_PLUGIN_FILE') or define( 'WP_USER_LOGOUT_FORCE_PLUGIN_FILE', __FILE__ );

// Define ulf text domain
define('ULF_TEXT_DOMAIN', 'user-logout-force');

// Include class file if not already included
if ( !class_exists( 'WP_User_Logout_Force' ) )
    require_once __DIR__ . '/src/class-wp-user-logout-force.php';

// Initialize plugin
add_action( 'plugins_loaded', array( 'WP_User_Logout_Force', 'get_instance' ) );