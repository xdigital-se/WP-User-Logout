# User Logout Force

User Logout Force allows you to logout users or user from their account or see offline users last activity in your site. Also you can lock login so no one can login other thatn your chosen role with a custom message.

You can customize offline time for users so users that weren't active in that time marked as offline. Also you can manage user sessions and destroy other sessions when user login into new device or browser.

# Hooks
User Logout Force provided actions and filters to help you improve your site by customizing it.

## Filters
Filters helps you to customize behavior of User Logout Force in some situations.
### ulf_row_status
This filter used to customize each status for users row.
#### Paramteres
```
$row_content
(string) Pre-prepared row content to display
$is_user_online
(bool) User online status
$last_login
(string) Last login human readable date (2 mins ago)
```
#### Usage
```php
add_filter( 'ulf_row_status', function($row_status, $is_user_online, $last_login){
    return $row_status;
}, 10, 3);
```
### ulf_redirect_after_request
Set your custom url that redirect after handling requests (Logout user, Logout all users).
#### Paramteres
```
$redirect_url
(string) Redirect url
```
#### Usage
```php
add_filter( 'ulf_redirect_after_bulk_request', function($redirect) {
    // default is users.php
    return 'tools.php';
}, 10 , 1 );
```
### ulf_last_login
Set your custom last login format.
#### Paramteres
```
$human_readable_date
(string) Human readable date (1 hour ago)
$last_login
(int) last login timestamp
```
#### Usage
```php
add_filter( 'ulf_last_login', function($human_readable, $last_login) {
    return "It's been $human_readable";
}, 10 , 2);
```
### ulf_lockdown_message
Customize lockdown error message.
#### Paramteres
```
$message
(string) Already defined message
```
#### Usage
```php
add_filter( 'ulf_lockdown_message', function($message) {
    return 'Not a good time for login.';
}, 10, 1);
```
## Actions
### ulf_before_load
Call before loading ulf classes.
### ulf_after_load
Call after ulf initilized.
### before_ulf_status_row_out, after_ulf_status_row_out
Before or after row is going to output.
### before_ulf_handle_request
Before handling users table requests.
### before_ulf_logout_user, after_ulf_logout_user
Before or after logout single user.
### before_ulf_logout_all_users, after_ulf_logout_all_users
Before or after logout all users.
### before_ulf_update_last_login, after_ulf_update_last_login
Before after update last login for user.
### before_ulf_destroy_others, after_ulf_destroy_others
Before or after destroy other users session.
### before_ulf_lockdown, after_ulf_lockdown
Before or after lockdown executed.
### before_ulf_destroy_all_sessions_for_all_users, after_ulf_destroy_all_sessions_for_all_users
Before and after logout all sessions for all users (Executed in settings page).

# Changelog
## 1.0
Initial release

# Contribute
Feel free to contribute on languages or source code.

