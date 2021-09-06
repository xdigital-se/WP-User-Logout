<?php

defined( 'ABSPATH' ) or die(); // Exit if called directly

class WP_User_Logout_Force_Handler {
    
	private $make_offline_rate = 1;

	private $destroy_others_on_login = false;

    public function __construct() {

        /** @var string current page */
        global $pagenow;

        add_action( 'init', array( $this, 'update_online_users_status' ) );
        add_action( 'init', array( $this, 'update_last_login' ) );

        // Returning if user don't have caps or page is not users page
        if ( 'users.php' !== $pagenow || ! $this->user_has_cap() )
			return;

        /** @filter load on user table wanted */
        add_filter( 'user_contactmethods', array($this, 'include_ulf_method'), 10, 1 );

        /** @filter load on user table column wanted */
        add_filter( 'manage_users_columns', array($this, 'modify_users_table'));

        /** @filter load on user table row wanted */
        add_filter( 'manage_users_custom_column', array($this, 'modify_ulf_status_row'), 10, 3 );

        add_filter( 'manage_users_sortable_columns', array( $this, 'make_ulf_field_sortable' ) );
		add_filter( 'users_list_table_query_args', array( $this, 'sort_by_login_activity' ) );

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		add_action( 'load-users.php', array( $this, 'handle_requests' ) );
		add_action( 'load-users.php', array( $this, 'handle_bulk_actions' ) );

		add_filter( 'bulk_actions-users', array( $this, 'add_bulk_action' ) );

		// Setting user options
		$this->make_offline_rate 		= get_option( 'ulf_make_offline', 1 );
		$this->destroy_others_on_login 	= get_option( 'ulf_destroy_others', false );
    }

    public function include_ulf_method( $contact_methods ) {
        $contact_methods['ulf_status'] = 'ulf_status';
        return $contact_methods;
    }

    public function modify_users_table( $column ) {
        $column['ulf_status'] = __( 'Login Activity', ULF_TEXT_DOMAIN);
        return $column;
    }

    public function modify_ulf_status_row( $value, $column_name, $user_id ) {
        
        if ( 'ulf_status' === $column_name ) {

            $is_user_online = $this->is_user_online( $user_id );

            if ( $is_user_online ) {
                $logout_link = add_query_arg(
					array(
						'action' => 'logout',
						'user'   => $user_id,
					)
				);
				$logout_link = remove_query_arg( array( 'new_role' ), $logout_link );
				$logout_link = wp_nonce_url( $logout_link, 'ulf-logout-single' );

				$value  = '<span class="online-circle">' . esc_html__( 'Online', ULF_TEXT_DOMAIN ) . '</span>';
				$value .= ' </br>';
				$value .= '<a style="color:red" href="' . esc_url( $logout_link ) . '">' . _x( 'Logout', 'The action on users list page', ULF_TEXT_DOMAIN ) . '</a>';
            } else {
				$last_login = $this->get_last_login( $user_id );
				$value      = '<span class="offline-circle">' . esc_html__( 'Offline ', ULF_TEXT_DOMAIN );
				$value     .= '</br>' . esc_html__( 'Last Login: ', ULF_TEXT_DOMAIN );
				$value     .= ! empty( $last_login ) ? $last_login . ' ago' : esc_html__( 'Never', ULF_TEXT_DOMAIN ) . '</span>';
			}
        }

        return $value;
    }

    public function make_ulf_field_sortable( $olumns ) {
        $columns['ulf_status'] = 'ulf_status';

		return $columns;
    }

    public function is_user_online( $user_id ) {

        $logged_in_users = get_transient( 'online_status' );
        
        // Online, if (s)he is in the list and last activity was less than 60 seconds ago
		return isset( $logged_in_users[ $user_id ] ) && ( $logged_in_users[ $user_id ] > ( time() - ( $this->get_make_offline_time * 60 ) ) );
    }

    public function enqueue_scripts() {
        wp_enqueue_style( 'user-logout-force', plugins_url( 'dist/css/user-logout-force.css', WP_USER_LOGOUT_FORCE_PLUGIN_FILE ), array(), ULF_ABSPATH, $media = 'all' );
		wp_enqueue_script( 'user-logout-force-js', plugins_url( 'dist/js/script.js', WP_USER_LOGOUT_FORCE_PLUGIN_FILE ), array(), ULF_ABSPATH, false );
		wp_localize_script(
			'user-logout-force-js',
			'ulf_plugins_params',
			array(
				'ajax_url'     => admin_url( 'admin-ajax.php' ),
				'review_nonce' => wp_create_nonce( 'review-notice' ),
			)
		);
    }

    /**
	 * Update online users status. Store in transient.
	 *
	 * @link  https://wordpress.stackexchange.com/a/34434/126847
	 * @return void.
	 */
	public function update_online_users_status() {

		// Get the user online status list.
		$logged_in_users = get_transient( 'online_status' );

		// Get current user ID
		$user = wp_get_current_user();
        
		// Check if the current user needs to update his online status;
		// Needs if user is not in the list.
		$no_need_to_update = isset( $logged_in_users[ $user->ID ] )

			// And if his "last activity" was less than let's say ...6 seconds ago
			&& $logged_in_users[ $user->ID ] > ( time() - ( 1 * 60 ) );

		// Update the list if needed
        
		if ( ! $no_need_to_update ) {
			$logged_in_users[ $user->ID ] = time();
			set_transient( 'online_status', $logged_in_users, $expire_in = ( 60 * 60 ) );
			// 60 mins
		}
	}

	public function handle_requests() {

		if ( isset( $_REQUEST['action'] ) && 'logout_all' === $_REQUEST['action']) {
			check_admin_referer( 'user-logout-force-nonce' );

			// Logout all users
			$this->logout_all_users();

			wp_safe_redirect( admin_url( 'users.php' ) );
			exit();
		}

		$action	= isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : false;
		$mode	= isset( $_POST['mode'] ) ? $_POST['mode'] : false;

		if ( 'list' === $mode)
			return; // Bulk requests not allowed in multisite

		if ( !empty( $action ) && 'logout' === $action ) {

			check_admin_referer( 'ulf-logout-single' );

			$this->logout_user( (isset( $_GET['user'] ) ? absint( $_GET['user'] ) : 0) );

			wp_redirect( 'users.php' );
			exit();
		}
	}

	private function logout_user( $user_id ) {

		$sessions = WP_Session_Tokens::get_instance( $user_id );

		$sessions->destroy_all();

	}

	private function logout_all_users() {
		$users = get_users();

		foreach ( $users as $user ) {

			// Get all sessions for user with ID $user_id
			$sessions = WP_Session_Tokens::get_instance( $user->ID );

			// We have got the sessions, destroy them all!
			$sessions->destroy_all();
		}
	}

	public function handle_bulk_actions() {
		
		if ( empty( $_REQUEST['users'] ) || empty( $_REQUEST['action']) )
			return;
			
		if ( 'ulf-bulk-action' !== $_REQUEST['action'] )
			return;
		
		$users = array_map( 'absint', (array) $_REQUEST['users'] );
		
		foreach ($users as $user) {
			
			$this->logout_user( $user );
		}

		return admin_url( 'users.php' );
	}

	public function add_bulk_action( $actions ) {
		$actions['ulf-bulk-action'] = esc_html__( 'Logout', ULF_TEXT_DOMAIN );
		
		return $actions;
	}

	public function sort_by_login_activity( $args ) {
		
		if ( isset( $args['orderby'] ) && 'ulf_status' == $args['orderby'] ) {

			$order = isset( $args['order'] ) && $args['order'] === 'asc' ? 'desc' : 'asc';

			$args = array_merge(
				$args,
				array(
					'meta_key' => 'last_login',
					'orderby'  => 'meta_value',
					'order'    => $order,
				)
			);
		}

		return $args;
	}

    /**
	 * Store last login info in usermeta table.
	 *
	 * @since  1.1.0
	 *
	 * @return void.
	 */
	public function update_last_login() {

		$user_id = get_current_user_id();
		update_user_meta( $user_id, 'last_login', time() );
	}

    public function get_last_login( $user_id ) {
        $last_login     = get_user_meta( $user_id, 'last_login', true );
		$the_login_date = '';

		if ( ! empty( $last_login ) && 0.00058373 != $last_login ) {
			
			$the_login_date = human_time_diff( $last_login );
		}

		return $the_login_date;
    }

	private function get_make_offline_time() {
		return $this->make_offline_rate;
	}

	private function destroy_others_on_login() {
		return $this->destroy_others_on_login;
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