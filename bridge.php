<?php
/*
Plugin Name: WP2SMFBridge
Plugin URI: https://github.com/xchwarze/WP2SMFBridge
Description: Login bridge for use SMF with WP. For a correct use the users registration and logout is from WP.
Author: DSR!
Version: 2.0.0
Author URI: https://github.com/xchwarze
License: GPL v2
*/

class WP_SMFBridge {

	/*
	 * CONFIG BLOCK! EDIT AFTER INSTALL 
	 */
	static $default_activated_value = 0;// Values: 0 Deactivate, 3 Awaiting approval
	static $is_activated_value = 1; 	// Values: 1 Activated (in this case by email), 3 Awaiting approval
	static $cookie_length = '604800';	// Default login cookies length (in seconds)
	static $localCookies = 0;			// Enable local storage of cookies
	static $globalCookies = 0;			// Use subdomain independent cookies
	static $secureCookies = 0;			// Force cookies to be secure (This only applies if you are using HTTPS - don't use otherwise!)
										//         false -> read config from this file
	static $smf_path = 'EDIT THIS';		// Forum folder
										// example 1) forum url: www.forum.com wordpress url: www.forum.com/wp config: $smf_path = '../'
										// example 2) forum url: www.forum.com/forum wordpress url: www.forum.com config: $smf_path = 'forum/'



	/***************************************************************************************
	 * DO NOT EDIT BELOW THIS LINE
	 */
	static $smf_db_prefix = '';
	static $smf_boardurl = '';
	static $smf_cookiename = '';
	static $smf_db = false;

	
	function loadConfig(){
		$settingsFile = ABSPATH . self::$smf_path . 'Settings.php';
		if (!file_exists($settingsFile)) {
			return false;
		}
		
		if (self::$smf_db) {
			return true;
		}
			
		require $settingsFile;

		self::$smf_db_prefix = $db_prefix;
		self::$smf_boardurl = $boardurl;
		self::$smf_cookiename = $cookiename;
		self::$smf_db = new wpdb($db_user, $db_passwd, $db_name, $db_server);
		
		return true;
	}
	
	function smfPassword($login, $pass){
		return sha1(strtolower($login) . $pass);
	}
	
	function smfLoadCookieConfig(){
		$config = array(
			'localCookies' => self::$localCookies,
			'globalCookies' => self::$globalCookies,
			'secureCookies' => self::$secureCookies,
		); 

		if (!self::$cookiesConfigFromSMF) {
			return $config;
		}
		
		$sql =  "SELECT variable, value " .
				"FROM " . self::$smf_db_prefix . "settings " .
				"WHERE variable = 'localCookies' OR variable = 'globalCookies' OR variable = 'secureCookies'";
		$results = self::$smf_db->get_results($sql, ARRAY_A);
		foreach ($results as $row) {
			$config[ $row['variable'] ] = $row['value'];
		}

		return $config;
	}

	function smfAddNewUser($login, $email_address, $passwd) {
		$insert = array(
			'member_name' => $login,
			'real_name' => $login,
			'email_address' => $email_address,
			'passwd' => $passwd,
			'password_salt' => substr(md5(mt_rand()), 0, 4),
			'date_registered' => (string)time(),
			'is_activated' => self::$default_activated_value,
			'pm_email_notify' => '1',
			'member_ip' => $_SERVER['REMOTE_ADDR'],
			/*
			'posts' => 0,
			'personal_text' => $modSettings['default_personal_text'],
			'pm_email_notify' => 1,
			'id_theme' => 0,
			'id_post_group' => 4,
			*/
		);

		$result = self::$smf_db->insert(self::$smf_db_prefix . 'members', $insert);
		if ($result == false) {
			return;
		}

		$sql = "REPLACE INTO " . self::$smf_db_prefix . "settings (variable, value) VALUES ('latestMember', %s), ('latestRealName', %s)";
		self::$smf_db->query( self::$smf_db->prepare($sql, self::$smf_db->insert_id, $login) );

		self::$smf_db->query("UPDATE " . self::$smf_db_prefix . "settings SET value = value + 1 WHERE variable = 'totalMembers'");
	}
	
	function smfLogoutByMember($member_name){
		if (!self::loadConfig()) {
			return;
		}
		
		$modSettings = self::smfLoadCookieConfig();
	
		$sql =  "DELETE FROM " . self::$smf_db_prefix . "log_online " .
				"WHERE id_member IN ( " .
					"SELECT id_member FROM " . self::$smf_db_prefix . "members WHERE member_name = %s" .
				") " .
				"LIMIT 1";
		self::$smf_db->query( self::$smf_db->prepare($sql, $member_name) );

		$parsed_url = self::smfURLParts($modSettings['localCookies'], $modSettings['globalCookies']);
		//setcookie('PHPSESSID', $HTTP_COOKIE_VARS['PHPSESSID'], time() - 3600, $parsed_url['path'] . '/', $parsed_url['host'], 0);  
		setcookie(self::$smf_cookiename, '', time() - 3600, $parsed_url['path'] . '/', $parsed_url['host'], 0);
		unset($_SESSION['login_' . self::$smf_cookiename]);

		$where = array('member_name' => $member_name );
		self::$smf_db->update(self::$smf_db_prefix . 'members', $update, $where);
	}
	
	//based on url_parts (SMF Subs-Auth.php)
	function smfURLParts($local, $global) {
		//global $boardurl;

		// Parse the URL with PHP to make life easier.
		//$parsed_url = parse_url($boardurl);
		$parsed_url = parse_url(self::$smf_boardurl);

		// Is local cookies off?
		if (empty($parsed_url['path']) || !$local)
			$parsed_url['path'] = '';

		// Globalize cookies across domains (filter out IP-addresses)?
		if ($global && preg_match('~^\d{1,3}(\.\d{1,3}){3}$~', $parsed_url['host']) == 0 && preg_match('~(?:[^\.]+\.)?([^\.]{2,}\..+)\z~i', $parsed_url['host'], $parts) == 1)
			$parsed_url['host'] = '.' . $parts[1];

		// We shouldn't use a host at all if both options are off.
		elseif (!$local && !$global)
			$parsed_url['host'] = '';

		// The host also shouldn't be set if there aren't any dots in it.
		elseif (!isset($parsed_url['host']) || strpos($parsed_url['host'], '.') === false)
			$parsed_url['host'] = '';

		return array($parsed_url['host'], $parsed_url['path'] . '/');
	}

	//based on setLoginCookie (SMF Subs-Auth.php) 
	function smfSetLoginCookie($id, $password, $salt) {
		$modSettings = self::smfLoadCookieConfig();
		$password = sha1($password . $salt);

		//global $cookiename, $boardurl, $modSettings;

		// If changing state force them to re-address some permission caching.
		//$_SESSION['mc']['time'] = 0;

		// The cookie may already exist, and have been set with different options.
		$cookie_state = (empty($modSettings['localCookies']) ? 0 : 1) | (empty($modSettings['globalCookies']) ? 0 : 2);
		/*if (isset($_COOKIE[self::$smf_cookiename]) && preg_match('~^a:[34]:\{i:0;(i:\d{1,6}|s:[1-8]:"\d{1,8}");i:1;s:(0|40):"([a-fA-F0-9]{40})?";i:2;[id]:\d{1,14};(i:3;i:\d;)?\}$~', $_COOKIE[self::$smf_cookiename]) === 1)
		{
			$array = safe_unserialize($_COOKIE[self::$smf_cookiename]);

			// Out with the old, in with the new!
			if (isset($array[3]) && $array[3] != $cookie_state)
			{
				$cookie_url = self::smfURLParts($array[3] & 1 > 0, $array[3] & 2 > 0);
				setcookie(self::$smf_cookiename, serialize(array(0, '', 0)), time() - 3600, $cookie_url[1], $cookie_url[0], !empty($modSettings['secureCookies']));
			}
		}*/

		// Get the data and path to set it on.
		$data = serialize(empty($id) ? array(0, '', 0) : array($id, $password, time() + self::$cookie_length, $cookie_state));
		$cookie_url = self::smfURLParts(!empty($modSettings['localCookies']), !empty($modSettings['globalCookies']));

		// Set the cookie, $_COOKIE, and session variable.
		setcookie(self::$smf_cookiename, $data, time() + self::$cookie_length, $cookie_url[1], $cookie_url[0], !empty($modSettings['secureCookies']));

		// If subdomain-independent cookies are on, unset the subdomain-dependent cookie too.
		if (empty($id) && !empty($modSettings['globalCookies']))
			setcookie(self::$smf_cookiename, $data, time() + self::$cookie_length, $cookie_url[1], '', !empty($modSettings['secureCookies']));

		// Any alias URLs?  This is mainly for use with frames, etc.
		/*if (!empty($modSettings['forum_alias_urls']))
		{
			$aliases = explode(',', $modSettings['forum_alias_urls']);

			$temp = $boardurl;
			foreach ($aliases as $alias)
			{
				// Fake the $boardurl so we can set a different cookie.
				$alias = strtr(trim($alias), array('http://' => '', 'https://' => ''));
				$boardurl = 'http://' . $alias;

				$cookie_url = self::smfURLParts(!empty($modSettings['localCookies']), !empty($modSettings['globalCookies']));

				if ($cookie_url[0] == '')
					$cookie_url[0] = strtok($alias, '/');

				setcookie(self::$smf_cookiename, $data, time() + self::$cookie_length, $cookie_url[1], $cookie_url[0], !empty($modSettings['secureCookies']));
			}

			$boardurl = $temp;
		}*/

		$_COOKIE[self::$smf_cookiename] = $data;

		// Make sure the user logs in with a new session ID.
		/*if (!isset($_SESSION['login_' . self::$smf_cookiename]) || $_SESSION['login_' . self::$smf_cookiename] !== $data)
		{
			// Backup and remove the old session.
			$oldSessionData = $_SESSION;
			$_SESSION = array();
			session_destroy();

			// Recreate and restore the new session.
			loadSession();
			session_regenerate_id();
			$_SESSION = $oldSessionData;

			// Version 4.3.2 didn't store the cookie of the new session.
			if (version_compare(PHP_VERSION, '4.3.2') === 0)
			{
				$sessionCookieLifetime = @ini_get('session.cookie_lifetime');
				setcookie(session_name(), session_id(), time() + (empty($sessionCookieLifetime) ? self::$cookie_length : $sessionCookieLifetime), $cookie_url[1], $cookie_url[0], !empty($modSettings['secureCookies']));
			}

			$_SESSION['login_' . self::$smf_cookiename] = $data;
		}*/		
	}
		
	//SMF magic
	function createUser($login, $email_address, $errors){
		if (!self::loadConfig()) {
			return;
		}
		
		//checks
		$sql =  "SELECT member_name, email_address " .
				"FROM " . self::$smf_db_prefix . "members " .
				"WHERE member_name = %s OR email_address = %s";
		$results = self::$smf_db->get_results(self::$smf_db->prepare($sql, $login, $email_address), ARRAY_A);
		foreach ($results as $row) {			
			if ($row['member_name'] === $login) {
				$errors->add('username_exists', __( '<strong>ERROR</strong>: This username is already registered. Please choose another one.'));
			}
				
			if ($row['email_address'] === $email_address) {
				$errors->add('email_exists', __( '<strong>ERROR</strong>: This email is already registered, please choose another one.'));
			}
		}
			
		if (!empty($errors->errors)) {
			return $errors;
		}

		//add
		$passwd = 'DSR!WP2SMF-Bridge'; //como no tengo el pass aun lo marco asi
		self::smfAddNewUSer($login, $email_address, $passwd);
	}

	function smfGetUserLoginInfo($login) {
		$sql =  "SELECT id_member, passwd, password_salt " .
				"FROM " . self::$smf_db_prefix . "members " .
				"WHERE member_name = %s";
		return self::$smf_db->get_row( self::$smf_db->prepare($sql, $login), ARRAY_A );
	}

	function authenticateUser($login, $pass){
		if ((empty($login) || empty($pass)) || !self::loadConfig()) {
			return;
		}
		
		$passwd = self::smfPassword($login, $pass);

		// si es el primer login actualizo el pass y el estado de la cuenta en smf para que pueda loggearse si apago el plugin
		$update = array('last_login' => (string)time(), 'passwd' => $passwd, 'is_activated' => self::$is_activated_value);
		$where = array('member_name' => $login);
		self::$smf_db->update(self::$smf_db_prefix . 'members', $update, $where);
		
		// loggeo el user
		$user = self::smfGetUserLoginInfo($login);
		if (!$user) {
			$wp_user = wp_get_current_user();
			self::smfAddNewUser($login, $wp_user->user_email, $passwd);
			$user = self::smfGetUserLoginInfo($login);
			if (!$user) {
				return;
			}
		}

		self::smfSetLoginCookie($user['id_member'], $user['passwd'], $user['password_salt']);
	}
	
	function userPassReset($user, $pass){
		if (empty($pass) || !self::loadConfig()) {
			return;
		}
		
		$update = array('passwd' => self::smfPassword($user->data->user_login, $pass));
		$where = array('member_name' => $user->data->user_login );
		self::$smf_db->update(self::$smf_db_prefix . 'members', $update, $where);
	}
	
	function userEditProfile($user_id, $old_user_data){
		if (!self::loadConfig()) {
			return;
		}

		$update = array();

		//contrastes fix
		if (!empty($_POST['user_pass'])) {
			$_POST['pass1'] = $_POST['user_pass'];
		}
		
		if (!empty($_POST['user_email'])) {
			$_POST['email'] = $_POST['user_email'];
		}

		// password change
		if (!empty($_POST['pass1'])) {
			$update['passwd'] = self::smfPassword($old_user_data->user_login, $_POST['pass1']);
		}
		
		// email change
		if ($old_user_data->user_email !== $_POST['email']) {
			$update['email_address'] = $_POST['email'];
		}

		if (empty($update)) {
			return;
		}

		$where = array('member_name' => $old_user_data->user_login);
		self::$smf_db->update(self::$smf_db_prefix . 'members', $update, $where);
	}
	
	function logoutUser(){
		$user = wp_get_current_user();
		self::smfLogoutByMember($user->data->user_login);
	}
	
	function authenticateWPCookie($cookie_elements, $user){
		if (!$user || isset($_SESSION['login_' . self::$smf_cookiename]) || !self::loadConfig()) {
			return;
		}

		$user = self::smfGetUserLoginInfo($user->data->user_login);
		if ($user) {
			self::smfSetLoginCookie($user['id_member'], $user['passwd'], $user['password_salt']);
		}
	}

	function deleteUser($id, $reassign){
		if (!self::loadConfig()) {
			return;
		}
			
		$user = new WP_User($id);

		$sql = "DELETE FROM " . self::$smf_db_prefix . "members WHERE member_name = %s";
		self::$smf_db->query( self::$smf_db->prepare($sql, $user->user_login) );

		$sql = "UPDATE " . self::$smf_db_prefix . "settings SET value = value - 1 WHERE variable = 'totalMembers'";
		self::$smf_db->query($sql);
	}
}


// Hooks!
add_action('register_post', array('WP_SMFBridge', 'createUser'), 100, 3);
add_action('wp_authenticate', array('WP_SMFBridge', 'authenticateUser'), 100, 2);
add_action('password_reset', array('WP_SMFBridge', 'userPassReset'), 100, 2);
add_action('profile_update', array('WP_SMFBridge', 'userEditProfile'), 100, 2);
add_action('wp_logout', array('WP_SMFBridge', 'logoutUser'));
add_action('auth_cookie_valid', array('WP_SMFBridge', 'authenticateWPCookie'), 100, 2);
add_action('delete_user', array('WP_SMFBridge', 'deleteUser'), 100, 2);
?>