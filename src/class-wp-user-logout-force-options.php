<?php

class WP_User_Logout_Force_Options {
    
    private $options;

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_submenu_page') );
        add_action( 'admin_init', array( $this, 'settings_init') );
    }

    public function add_submenu_page() {
        add_submenu_page(
            'users.php',
            'User Logout Force Options',
            'User Logout Force Options',
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
        __( 'User Logout Force Options', ULF_TEXT_DOMAIN ),
         array( $this, 'ulf_options_section_callback' ),
        'ulf'
    );
 
    // Register a new field in the "wporg_section_developers" section, inside the "wporg" page.
    add_settings_field(
        'ulf_options_fields_offline',
        __( 'Make Offline', ULF_TEXT_DOMAIN ),
        array( $this, 'ulf_options_fields_offline' ),
        'ulf',
        'ulf_options_section',
    );

    add_settings_field(
        'ulf_options_fields_destroy_other_sessions',
        __( 'Destroy other sessions', ULF_TEXT_DOMAIN ),
        array( $this, 'ulf_options_fields_destroy_other_sessions' ),
        'ulf',
        'ulf_options_section',
    );
    }

    public function ulf_options_section_callback() {
        ?>
            <p>User logout force options and settings</p>
        <?php
    }

    public function ulf_options_fields_offline() {
        ?>
            <input type="number" name="make_offline" default="1" placeholder="1" value="<?php echo esc_attr( get_option('make_offline') ); ?>">
            <p>Make user offline in how much time of inactivity?</p>
        <?php
    }

    public function ulf_options_fields_destroy_other_sessions() {
        ?>
            <input type="checkbox" name="destroy_others" value="<?php echo esc_attr( get_option('destroy_others') ); ?>">
            <p>If user logged in with new device or browser remove other sessions and keep only active session.</p>
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
        print_r($input);
        $new_input = array();
        if( isset( $input['make_offline'] ) )
            $new_input['make_offline'] = absint( $input['make_offline'] );

        if( isset( $input['destroy_others'] ) )
            $new_input['destroy_others'] = absint( $input['destroy_others'] );

        return $new_input;

    }

    public function display() {
        if ( ! current_user_can( 'edit_users' ) )
            return;
     
        $this->options = get_option( 'ulf_options' );

        if ( isset( $_GET['settings-updated'] ) ) {
            // add settings saved message with the class of "updated"
            add_settings_error( 'ulf_messages', 'ulf_message', __( 'Settings Saved', ULF_TEXT_DOMAIN ), 'updated' );
        }
     
        // show error/update messages
        settings_errors( 'ulf_messages' );
        ?>
            <div class="wrap">
                <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
                <form action="options.php" method="post">
                    <?php
                    // output security fields
                    settings_fields( 'ulf' );

                    // output setting sections and their fields
                    do_settings_sections( 'ulf' );

                    // output save settings button
                    submit_button( 'Save Settings' );
                    ?>
                </form>
            </div>
        <?php
    }
}

new WP_User_Logout_Force_Options();