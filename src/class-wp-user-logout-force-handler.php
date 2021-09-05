<?php

defined( 'ABSPATH' ) or die(); // Exit if called directly

class WP_User_Logout_Force_Handler {
    
    public function __construct() {

        /** @var string current page */
        global $pagenow;

        // Returning if user don't have caps or page is not users page
        if ( 'users.php' !== $pagenow || ! $this->user_has_cap() )
			return;

        /** @filter load on user table wanted */
        add_filter( 'user_contactmethods', array($this, 'include_ulf_method'), 10, 1 );

        /** @filter load on user table column wanted */
        add_filter( 'manage_users_columns', array($this, 'modify_users_table'));

        /** @filter load on user table row wanted */
        add_filter( 'manage_users_custom_column', array($this, 'modify_ulf_status_row'), 10, 3 );
    }

    public function include_ulf_method( $contact_methods ) {
        $contact_methods['ufl_status'] = 'ulf_status';
        return $contact_methods;
    }

    public function modify_users_table( $column ) {
        $column['ufl_status'] = __( 'ULF Status', ULF_TEXT_DOMAIN);
        return $column;
    }

    public function modify_ulf_status_row( $val, $column_name, $user_id ) {
        switch ($column_name) {
            case 'ulf_status' :
                return '—';
            default:
                return '—';
        }
        return $val;
    }

    /**
     * Checking to see user has cap to do this or not
     * 
     * @since   1.0.0
     * @return  bool
     */
    private function user_has_cap() {

        if ( is_multisite() ) {
            $blog_id    = get_current_blog_id();
            $has_cap    = current_user_can_for_blog( $blog_id, 'edit_users' );
        }else {
            $has_cap    = current_user_can( 'edit_users' );
        }

        return $has_cap;
    }
}

new WP_User_Logout_Force_Handler();