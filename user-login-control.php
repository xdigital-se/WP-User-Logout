<?php
/**
 * Plugin Name: User login control
 * Description: See online users and logout all users.
 * Version: 1.4
 * Author: xdigital
 * Author URI: https://www.xdigital.se
 * Text Domain: user-login-control
 * Domain Path: /languages/
 *
 * @package    ulf
 * @author     Amirhossein Meydani
 * @since      1.0.0
 * @license    GPLv3
 */

defined( 'ABSPATH' ) or die(); // Exit if called directly

// Define root path
defined('USER_LOGIN_CONTROL_FILE') or define( 'USER_LOGIN_CONTROL_FILE', __FILE__ );

// Define ulf text domain
defined( 'USER_LOGIN_CONTROL_TEXT_DOMAIN' ) or define('USER_LOGIN_CONTROL_TEXT_DOMAIN', 'user-login-control');

// Include class file if not already included
if ( !class_exists( 'WP_User_Login_Control' ) )
    require_once __DIR__ . '/src/class-wp-user-login-control.php';

// Initialize plugin
add_action( 'after_setup_theme', array( 'WP_User_Login_Control', 'get_instance' ) );
