<?php
/**
 * ULF Final class file
 * 
 * @since   1.0
 * @package ulf
 * @author  Amirhosse Meydani
 * @license MIT
 */

defined( 'ABSPATH' ) or die(); // Exit if called directly

final class WP_User_Logout_Force {
    
    /** @var string ULF version */
    public $version = '1.0.0';

    /** @var object instance of class */
    protected static $instance = null;

    /**
     * Returns an instance of class
     * 
     * @since   1.0.0
     * @return  object instance of this class
     */
    public static function get_instance() {

        if ( is_null( self::$instance ) )
            self::$instance = new self();

        return self::$instance;

    }

    /**
     * Preventing from cloning class
     * 
     * @since 1.0.0
     */
    public function __clone() {
        _doing_it_wrong( __FUNCTION__, __( 'You cannot clone an instance of WP_User_Logout_Force', ULF_TEXT_DOMAIN ), '1.0.0' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Preventing of unserializing instances of this class
     * 
     * @since 1.0.0
     */
    public function __wakeup() {
        _doing_it_wrong( __FUNCTION__, __( 'You cannot clone an instance of WP_User_Logout_Force', ULF_TEXT_DOMAIN ), '1.0.0' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * ULF Constructor.
     * 
     * @since 1.0.0
     */
    public function __construct() {

        add_action( 'init', array($this, 'load_plugin_textdomain' ) );

        $this->define_constants();
        $this->includes();

        do_action( 'user_logout_force_loaded' );
    }

    /**
     * Define main constants
     * 
     * @since 1.0.0
     */
    private function define_constants() {
        $this->define( 'ULF_ABSPATH', dirname( WP_USER_LOGOUT_FORCE_PLUGIN_FILE ) . '/' );
        $this->define( 'ULF_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
        $this->define( 'ULF_VERSION', $this->version );
    }

    /**
     * Defining constant if not already defined
     * 
     * @since   1.0.0
     * @param   string      $name   name of constant
     * @param   string|bool $value  value of constant
     */
    private function define( $name, $value ) {
        if ( ! defined( $name ) ) {
            define( $name, $value );
        }
    }

    /**
     * Determining type of request
     * 
     * @since   1.0.0
     * @param   string  $type type of request
     */
    private function is_request( $type ) {
        switch ( $type ) {
            case 'admin':
                return is_admin();
            case 'ajax':
                return defined( 'DOING_AJAX' );
            case 'cron':
                return defined( 'DOING_CRON' );
            case 'frontend':
                return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
        }
    }

    /**
	 * Load Localisation files.
	 *
	 * Note: the first-loaded translation file overrides any following ones if the same translation is present.
	 *
	 * Locales found in:
	 *      - WP_LANG_DIR/wp-force-logout/wp-force-logout-LOCALE.mo
	 *      - WP_LANG_DIR/plugins/wp-force-logout-LOCALE.mo
	 */
    public function load_plugin_textdomain() {
        $locale = apply_filters( 'plugin_locale', get_locale(), 'user-logout-force' );

        load_textdomain( 'user-logout-force', WP_LANG_DIR . '/user-logout-force/user-logout-force-' . $locale . '.mo' );
        load_plugin_textdomain( 'user-logout-force', false, plugin_basename( dirname( WP_USER_LOGOUT_FORCE_PLUGIN_FILE ) ) . '/languages' );
    }

    /**
     * Destroy all sessions for all users ( Admin login require after this action)
     * 
     * @since   1.0
     * @return  void
     */
    public static function destroy_all_sessions() {
        WP_Session_Tokens::destroy_all_for_all_users();
    }

    /**
     * Including Handler
     * 
     * @since   1.0.0
     */
    private function includes() {
        include_once dirname( __FILE__ ) . '/class-wp-user-logout-force-handler.php';
        include_once dirname( __FILE__ ). '/class-wp-user-logout-force-options.php';
    }
}
