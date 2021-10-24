<?php
/**
 * ULF Handler class
 * 
 * @since   1.0
 * @package ulf
 * @author  Amirhosse Meydani
 * @license GPLv3
 */

defined('ABSPATH') or die(); // Exit if called directly

class WP_User_Login_Control_Handler
{

    private $make_offline_rate = 1;

    private $destroy_others_on_login = false;

    public function __construct()
    {

        /** @var string current page */
        global $pagenow;

        /** @action triggers on wordpress initilization to update online users status */
        add_action('init', array(
            $this,
            'update_online_users_status'
        ));

        /** @action triggers on wordpress initilization to update online users last_login */
        add_action('init', array(
            $this,
            'update_last_login'
        ));

        /** @action triggers on user login priority 99 params 2 */
        add_action('wp_login', array(
            $this,
            'handle_new_login'
        ) , 99, 2);

        /** @action triggers on user authenrication priority 100 params 1 */
        add_action('wp_authenticate_user', array(
            $this,
            'login_lockdown'
        ) , 100, 1);

        // Returning if user don't have caps or page is not users page
        if ('users.php' !== $pagenow || !$this->user_has_cap()) return;

        /** @filter load on user table wanted */
        add_filter('user_contactmethods', array(
            $this,
            'include_ulf_method'
        ) , 10, 1);

        /** @filter load on user table column wanted */
        add_filter('manage_users_columns', array(
            $this,
            'modify_users_table'
        ));

        /** @filter load on user table row wanted */
        add_filter('manage_users_custom_column', array(
            $this,
            'modify_ulf_status_row'
        ) , 10, 3);

        /** @filter load on table sortable columns wanted */
        add_filter('manage_users_sortable_columns', array(
            $this,
            'make_ulf_field_sortable'
        ));

        /** @filter load on sort method wanted */
        add_filter('users_list_table_query_args', array(
            $this,
            'sort_by_login_activity'
        ));

        /** @action triggers on admin enqueue scripts and loads css js */
        add_action('admin_enqueue_scripts', array(
            $this,
            'enqueue_scripts'
        ));

        /** @action triggers on users table wanted and handle request if implemented */
        add_action('load-users.php', array(
            $this,
            'handle_requests'
        ));

        /** @action triggers on users table wanted and handle bulk request actions */
        add_action('load-users.php', array(
            $this,
            'handle_bulk_actions'
        ));

        /** @filter load on bulk actions wanted and add new bulk */
        add_filter('bulk_actions-users', array(
            $this,
            'add_bulk_action'
        ));

        // Setting user options
        $this->make_offline_rate = get_option('ulf_make_offline', 1);
        $this->destroy_others_on_login = get_option('ulf_destroy_others', false);

        do_action('ulf_after_load');
    }

    /**
     * Adds new method to users table
     * 
     * @since   1.0
     * @param   array   methods
     * @return  array   modified methods
     */
    public function include_ulf_method($contact_methods)
    {
        $contact_methods['ulf_status'] = 'ulf_status';
        return $contact_methods;
    }

    /**
     * Adds new column to users table
     * 
     * @since   1.0
     * @param   array   columns
     * @return  array   modified columns
     */
    public function modify_users_table($column)
    {
        $column['ulf_status'] = __('Login Activity', USER_LOGIN_CONTROL_TEXT_DOMAIN);
        return $column;
    }

    /**
     * Adds new row for each user in users table
     * 
     * @since   1.0
     * @param   string  value
     * @param   string  column name
     * @param   int     user id
     * @return  string  value
     */
    public function modify_ulf_status_row($value, $column_name, $user_id)
    {

        if ('ulf_status' === $column_name)
        {

            $is_user_online = $this->is_user_online($user_id);
            $last_login = $this->get_last_login($user_id);

            if ($is_user_online)
            {
                $logout_link = add_query_arg(array(
                    'action' => 'logout',
                    'user' => $user_id,
                ));
                $logout_link = remove_query_arg(array(
                    'new_role'
                ) , $logout_link);
                $logout_link = wp_nonce_url($logout_link, 'ulf-logout-single');

                $value = '<span class="online-circle">' . esc_html__('Online', USER_LOGIN_CONTROL_TEXT_DOMAIN) . '</span>';
                $value .= ' </br>';
                $value .= '<a style="color:red" href="' . esc_url($logout_link) . '">' . _x('Logout', 'The action on users list page', USER_LOGIN_CONTROL_TEXT_DOMAIN) . '</a>';
            }
            else
            {
                $value = '<span class="offline-circle">' . esc_html__('Offline ', USER_LOGIN_CONTROL_TEXT_DOMAIN);
                $value .= '</br>' . esc_html__('Last Login: ', USER_LOGIN_CONTROL_TEXT_DOMAIN);
                $value .= !empty($last_login) ? $last_login . __(' ago', USER_LOGIN_CONTROL_TEXT_DOMAIN ) : esc_html__('Never', USER_LOGIN_CONTROL_TEXT_DOMAIN) . '</span>';
            }
        }

        do_action('before_ulf_status_row_out');
        $value = apply_filters( 'ulf_row_status', $value, $is_user_online, $last_login);
        do_action('after_ulf_status_row_out');

        return $value;
    }

    /**
     * Makes ulf field sortable
     * 
     * @since   1.0
     * @param   array   columns
     * @return  array   modified columns
     */
    public function make_ulf_field_sortable($columns)
    {
        $columns['ulf_status'] = 'ulf_status';

        return $columns;
    }

    /**
     * Returns is user online or not
     * 
     * @since   1.0
     * @param   int     user id
     * @return  bool    user status
     */
    public function is_user_online($user_id)
    {

        $logged_in_users = get_transient('online_status');

        // Online, if (s)he is in the list and last activity was less than 60 seconds ago
        return isset($logged_in_users[$user_id]) && ($logged_in_users[$user_id] > (time() - ($this->get_make_offline_time() * 60)));
    }

    /**
     * Enqueue scripts
     * 
     * @since   1.0
     * @return  void
     */
    public function enqueue_scripts()
    {
        wp_enqueue_style('user-login-control', plugins_url('dist/css/user-login-control.css', USER_LOGIN_CONTROL_FILE) , array() , ULF_ABSPATH, $media = 'all');
        wp_enqueue_script('user-login-control-js', plugins_url('dist/js/script.js', USER_LOGIN_CONTROL_FILE) , array() , ULF_ABSPATH, false);
        wp_localize_script('user-login-control-js', 'ulf_plugins_params', array(
            'ajax_url' => admin_url('admin-ajax.php') ,
            'review_nonce' => wp_create_nonce('review-notice') ,
        ));
    }

    /**
     * Update online users status. Store in transient.
     *
     * @since   1.0
     * @link    https://wordpress.stackexchange.com/a/34434/126847
     * @return  void.
     */
    public function update_online_users_status()
    {

        // Get the user online status list.
        $logged_in_users = get_transient('online_status');

        // Get current user ID
        $user = wp_get_current_user();

        // Check if the current user needs to update his online status;
        // Needs if user is not in the list.
        $no_need_to_update = isset($logged_in_users[$user->ID])

        // And if his "last activity" was less than let's say ...6 seconds ago
         && $logged_in_users[$user->ID] > (time() - (1 * 60));

        // Update the list if needed
        if (!$no_need_to_update)
        {
            $logged_in_users[$user->ID] = time();
            set_transient('online_status', $logged_in_users, $expire_in = (60 * 60));
            // 60 mins
            
        }
    }

    /**
     * Handles users table requests
     * 
     * @since   1.0
     * @return  void
     */
    public function handle_requests()
    {
        do_action( 'before_ulf_handle_request' );

        $redirect = apply_filters( 'ulf_redirect_after_request', 'users.php' );

        if (isset($_REQUEST['action']) && 'logout_all' === $_REQUEST['action'])
        {
            check_admin_referer('user-login-control-nonce');

            // Logout all users
            $this->logout_all_users();

            wp_safe_redirect(admin_url($redirect));
            exit();
        }

        $action = isset($_REQUEST['action']) ? sanitize_key($_REQUEST['action']) : false;
        $mode = isset($_POST['mode']) ? sanitize_key($_POST['mode']) : false;

        if ('list' === $mode) return; // Bulk requests not allowed in multisite
        if (!empty($action) && 'logout' === $action)
        {

            check_admin_referer('ulf-logout-single');

            $this->logout_user((isset($_GET['user']) ? absint($_GET['user']) : 0));

            wp_redirect($redirect);
            exit();
        }
    }

    /**
     * Logout single user with user id
     * 
     * @since   1.0
     * @param   int     user id
     * @return  void
     */
    private function logout_user($user_id)
    {
        do_action('before_ulf_logout_user');
        $sessions = WP_Session_Tokens::get_instance($user_id);

        $sessions->destroy_all();
        do_action('after_ulf_logout_user');
    }

    /**
     * Logout all users by users id
     * 
     * @since   1.0
     * @see     WP_User_Force_logout::destroy_all_sessions
     * @return  void
     */
    private function logout_all_users()
    {
        do_action('before_ulf_logout_all_users');
        $users = get_users();

        foreach ($users as $user)
        {

            // Get all sessions for user with ID $user_id
            $sessions = WP_Session_Tokens::get_instance($user->ID);

            // We have got the sessions, destroy them all!
            $sessions->destroy_all();
        }
        do_action('after_ulf_logout_all_users');
    }

    /**
     * Handles users table bulk requests
     * 
     * @since   1.0
     * @return  string  redirect url
     */
    public function handle_bulk_actions()
    {

        $redirect = apply_filters( 'ulf_redirect_after_bulk_request', 'users.php' );

        if (empty($_REQUEST['users']) || empty($_REQUEST['action'])) return;

        if ('ulf-bulk-action' !== $_REQUEST['action']) return;

        $users = array_map('absint', (array)$_REQUEST['users']);

        foreach ($users as $user)
        {

            $this->logout_user($user);
        }

        return admin_url($redirect);
    }

    /**
     * Adds nwe bulk action to users table
     * 
     * @since   1.0
     * @param   array   actions
     * @return  array   modified actions
     */
    public function add_bulk_action($actions)
    {
        $actions['ulf-bulk-action'] = esc_html__('Logout', USER_LOGIN_CONTROL_TEXT_DOMAIN);

        return $actions;
    }

    /**
     * Sorts users table by last activity
     * 
     * @since   1.0
     * @param   array   args
     * @return  array   query args
     */
    public function sort_by_login_activity($args)
    {
        if (isset($args['orderby']) && 'ulf_status' == $args['orderby'])
        {

            $order = isset($args['order']) && $args['order'] === 'asc' ? 'desc' : 'asc';

            $args = array_merge($args, array(
                'meta_key' => 'last_login',
                'orderby' => 'meta_value',
                'order' => $order,
            ));
        }

        return $args;
    }

    /**
     * Store last login info in usermeta table.
     *
     * @since  1.0
     * @return void
     */
    public function update_last_login()
    {
        do_action( 'before_ulf_update_last_login' );

        $user_id = get_current_user_id();
        update_user_meta($user_id, 'last_login', time());

        do_action( 'after_ulf_update_last_login' );
    }

    /**
     * Get last login by user id
     * 
     * @since   1.0
     * @param   int     user id
     * @return  string  login date
     */
    public function get_last_login($user_id)
    {
        $last_login = get_user_meta($user_id, 'last_login', true);
        $the_login_date = '';

        if (!empty($last_login) && 0.00058373 != $last_login)
        {
            $the_login_date = human_time_diff($last_login);
        }

        $the_login_date = apply_filters( 'ulf_last_login', $the_login_date, $last_login );

        return $the_login_date;
    }

    /**
     * Handles new login and remove other sessions if enabled on admin
     * 
     * @since   1.0
     * @param   object  user
     * @param   int     user id
     * @return  void
     */
    public function handle_new_login($user, $user_id)
    {

        if ($this->destroy_others_on_login())
        {
            do_action( 'before_ulf_destroy_others' );
            $sessions = WP_Session_Tokens::get_instance($user_id);
            $token = wp_get_session_token();

            $sessions->destroy_others($token);
            do_action( 'after_ulf_destroy_others' );
        }

    }

    /**
     * Lock downs login page so nobody can login other choosen role
     * 
     * @since   1.0
     * @see     lockdown_whitelist_check
     * @param   object  user
     * @return  WP_Error|object
     */
    public function login_lockdown($user)
    {

        if (!$this->is_login_lockdown_active()) return $user;

        if (is_wp_error($user))
        {
            return $user;
        }

        do_action( 'before_ulf_lockdown' );

        // Is user role is in white list or not
        $failure = $this->lockdown_whitelist_check($user->ID);

        if (!$failure)
        {
            return new WP_Error('ulf_login_lockdown', apply_filters( 'ulf_lockdown_message', __("Sorry, Login is disabled right now. Try again in few minutes.", USER_LOGIN_CONTROL_TEXT_DOMAIN)));
        }

        do_action( 'after_ulf_lockdown' );

        return $user;
    }

    /**
     * Is lock down activated in admin
     * 
     * @since   1.0
     * @return  bool
     */
    private function is_login_lockdown_active()
    {
        return (get_option('ulf_lock_login', 'no') === 'on') ? true : false;
    }

    /**
     * Checks white list to see user is valid to login or not
     * 
     * @since   1.0
     * @see     login_lockdown
     * @param   int     user id
     * @return  bool    status
     */
    private function lockdown_whitelist_check($user_id)
    {
        $user_meta = get_userdata($user_id);
        $user_role = $user_meta->roles;
        
        if ( $user_role === 'Administrator' )
            return true;

        $white_role = get_option('ulf_login_while_list', false);

        if (false === $white_role) return true;

        foreach ($user_role as $role) if ($white_role === $role) return true;

        return false;
    }

    /**
     * Returns make offline option value
     * 
     * @since   1.0
     * @return  int
     */
    private function get_make_offline_time()
    {
        return $this->make_offline_rate;
    }

    /**
     * Returns destroy other options value
     * 
     * @since   1.0
     * @return  bool
     */
    private function destroy_others_on_login()
    {
        return $this->destroy_others_on_login;
    }

    /**
     * Checking to see user has cap to do this or not
     *
     * @since   1.0.0
     * @return  bool
     */
    private function user_has_cap()
    {
        if (is_multisite())
        {
            $blog_id = get_current_blog_id();
            $has_cap = current_user_can_for_blog($blog_id, 'edit_users');
        }
        else
        {
            $has_cap = current_user_can('edit_users');
        }

        return $has_cap;
    }
}

new WP_User_Login_Control_Handler();

