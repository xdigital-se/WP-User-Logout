<?php
/**
 * ULF Admin options
 * 
 * @since   1.0
 * @package ulf
 * @author  Amirhosse Meydani
 * @license GPLv3
 */

defined('ABSPATH') or die(); // Exit if called directly


class WP_User_Login_Control_Options {
    
    private $options;

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_submenu_page') );
        add_action( 'admin_init', array( $this, 'settings_init') );
    }

    public function add_submenu_page() {
        add_submenu_page(
            'users.php',
            __( 'User Login Control Options', USER_LOGIN_CONTROL_TEXT_DOMAIN ),
            __( 'User Login Control Options', USER_LOGIN_CONTROL_TEXT_DOMAIN ),
            'edit_users',
            'ulf-options',
            array( $this, 'display'));
    }

    public function settings_init() {
        // Register a new setting for "wporg" page.
    register_setting( 'ulf', 'ulf_options', array( $this, 'sanitize' ) );
    register_setting( 'ulf', 'make_offline', array( $this, 'sanitize' ) );
    register_setting( 'ulf', 'destroy_all', array( $this, 'sanitize' ) );
 
    add_settings_section(
        'ulf_options_section',
        __( 'User Login Control Options', USER_LOGIN_CONTROL_TEXT_DOMAIN ),
         array( $this, 'ulf_options_section_callback' ),
        'ulf'
    );
 
    // Register a new field in the "wporg_section_developers" section, inside the "wporg" page.
    add_settings_field(
        'ulf_options_fields_offline',
        __( 'Make Offline', USER_LOGIN_CONTROL_TEXT_DOMAIN ),
        array( $this, 'ulf_options_fields_offline' ),
        'ulf',
        'ulf_options_section',
    );

    add_settings_field(
        'ulf_options_fields_destroy_other_sessions',
        __( 'Destroy other sessions', USER_LOGIN_CONTROL_TEXT_DOMAIN ),
        array( $this, 'ulf_options_fields_destroy_other_sessions' ),
        'ulf',
        'ulf_options_section',
    );

    add_settings_field(
        'ulf_options_fields_lock_login',
        __( 'Lockdown', USER_LOGIN_CONTROL_TEXT_DOMAIN ),
        array( $this, 'ulf_options_fields_lock_login' ),
        'ulf',
        'ulf_options_section',
    );

    add_settings_field(
        'ulf_options_fields_login_whitelist',
        __( 'Login Whitelist', USER_LOGIN_CONTROL_TEXT_DOMAIN ),
        array( $this, 'ulf_options_fields_login_whitelist_cb' ),
        'ulf',
        'ulf_options_section',
    );
    }

    public function ulf_options_section_callback() {
        ?>
            <p><?php _e( 'User Login Control Options and settings', USER_LOGIN_CONTROL_TEXT_DOMAIN ); ?></p>
        <?php
    }

    public function ulf_options_fields_offline() {
        ?>
            <input type="number" name="make_offline" default="1" placeholder="1" value="<?php esc_attr_e( get_option('ulf_make_offline', 1) ); ?>">
            <p><?php _e( 'How many minutes of inactivity before user should be logged out?', USER_LOGIN_CONTROL_TEXT_DOMAIN); ?></p>
        <?php
    }

    public function ulf_options_fields_destroy_other_sessions() {
        $option = get_option('ulf_destroy_others');
        
        $checked = '';
        if ( 'on' === $option)
            $checked = 'checked';
        ?>
            
            <input type="checkbox" name="destroy_others" default="no" <?php esc_attr_e( $checked ); ?>>
            <p><?php _e( 'If the user logged in with a new device or browser log out from other sessions and only keep the active session.', USER_LOGIN_CONTROL_TEXT_DOMAIN); ?></p>
        <?php
    }

    public function ulf_options_fields_lock_login() {
        $option = get_option('ulf_lock_login');
        
        $checked = '';
        if ( 'on' === $option)
            $checked = 'checked';
        ?>
            
            <input type="checkbox" name="ulf_lock_login" default="no" <?php esc_attr_e($checked) ?>>
            <p><?php _e('Lock login availability temporary so no one can login except your own defined users.</br><b>**</b> Note that by enabling this users will not be logged out, If you want all users to log out you can use the Logout All Users button.', USER_LOGIN_CONTROL_TEXT_DOMAIN); ?></p>
        <?php
    }

    public function ulf_options_fields_login_whitelist_cb() {

        // Default is Administrator
        $selected = get_option( 'ulf_login_while_list', 'Administrator' );

        ?>
            <select name="login_whitelist">
                <?php wp_dropdown_roles($selected); ?>
            </select>
            <p><?php _e( 'Users with this role can login even if Lockdown is enabled.', USER_LOGIN_CONTROL_TEXT_DOMAIN); ?></p>
        <?php
    }

    /**
     * A santization function that will take the incoming input, and make
     * sure that it's secure before saving it to the database.
     *
     * @since    1.0.0
     *
     * @param    array    $input        The name input.
     * @return   array    $new_input    The sanitized input.
     */
    public function sanitize( $input ) {
        $new_input = array();
        if( isset( $input['make_offline'] ) )
            $new_input['make_offline'] = absint( $input['make_offline'] );

        if( isset( $input['destroy_others'] ) )
            $new_input['destroy_others'] = sanitize_text_field( $input['destroy_others'] );

        if( isset( $input['ulf_lock_login'] ) )
            $new_input['ulf_lock_login'] = sanitize_text_field( $input['ulf_lock_login'] );

        if( isset( $input['login_whitelist'] ) )
            $new_input['login_whitelist'] = sanitize_text_field( $input['login_whitelist'] );

        return $new_input;

    }

    public function display() {
        if ( ! current_user_can( 'edit_users' ) )
            return;
     
        if ( !empty($_POST) ) {

            // Security check
            if ( ! check_admin_referer('ulf-options') )
                return;
            
            // Remove all sessions
            if ( 'Logout All Users' === $_POST['submit'] ) {

                WP_User_Login_Control::destroy_all_sessions();
                $_GET['updated-message'] = __( 'All users logged out successfully.', USER_LOGIN_CONTROL_TEXT_DOMAIN );

            }else {
                if ( isset( $_POST['make_offline'] ) ) {
                    if ( false === get_option( 'ulf_make_offline', false) ) {
                        add_option( 'ulf_make_offline', $this->sanitize( $_POST )['make_offline'] );
                    }else {
                        update_option( 'ulf_make_offline', $this->sanitize( $_POST )['make_offline'] );
                    }
                }
    
                if ( isset( $_POST['destroy_others']) ) {
                    add_ulf_destroy:
                        $option = get_option( 'ulf_destroy_others', false);
                        if ( false === $option ) {
                            add_option( 'ulf_destroy_others', $this->sanitize( $_POST )['destroy_others'] );
                        }else {
                            update_option( 'ulf_destroy_others', $this->sanitize( $_POST )['destroy_others'] );
                        }
                }else {
                    $_POST['destroy_others'] = 'no';
                    goto add_ulf_destroy;
                }
    
                if ( isset( $_POST['ulf_lock_login']) ) {
                    add_ulf_lock_login:
                        $option = get_option( 'ulf_lock_login', false);
                        if ( false === $option ) {
                            add_option( 'ulf_lock_login', $this->sanitize( $_POST )['ulf_lock_login'] );
                        }else {
                            update_option( 'ulf_lock_login', $this->sanitize( $_POST )['ulf_lock_login'] );
                        }
                }else {
                    $_POST['ulf_lock_login'] = 'no';
                    goto add_ulf_lock_login;
                }
    
                if ( isset( $_POST['login_whitelist'] ) ) {
                    if ( false === get_option( 'ulf_login_while_list', false) ) {
                        add_option( 'ulf_login_while_list', $this->sanitize( $_POST )['login_whitelist'] );
                    }else {
                        update_option( 'ulf_login_while_list', $this->sanitize( $_POST )['login_whitelist'] );
                    }
                }
                $_GET['updated-message'] = __( 'Settings Saved', USER_LOGIN_CONTROL_TEXT_DOMAIN );
            }

            $_GET['settings-updated'] = true;
        }
        
        $this->options = get_option( 'ulf_options' );

        if ( isset( $_GET['settings-updated'] ) ) {
            // add settings saved message with the class of "updated"
            add_settings_error( 'ulf_messages', 'ulf_message', sanitize_text_field($_GET['updated-message']), 'updated' );
        }
     
        // show error/update messages
        settings_errors( 'ulf_messages' );
        ?>
            <div class="wrap">
                <form action="users.php?page=ulf-options" method="post">
                    <?php
                    // output security fields
                    settings_fields( 'ulf' );

                    // output setting sections and their fields
                    do_settings_sections( 'ulf' );

                    // output save settings button
                    submit_button( __( 'Save Settings', USER_LOGIN_CONTROL_TEXT_DOMAIN ) );
                    submit_button( __( 'Logout All Users', USER_LOGIN_CONTROL_TEXT_DOMAIN ), 'secondary' );
                    ?>
                </form>
            </div>
        <?php
    }
}

if ( is_admin() )
    new WP_User_Login_Control_Options();
