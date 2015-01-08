<?php
/*
Plugin Name: WP2SMFBridge
Plugin URI: https://github.com/xchwarze/WP2SMFBridge
Description: Login bridge for use SMF with WP. For a correct use the users registration and logout is from WP.
Author: DSR!
Version: 1.0
Author URI: https://github.com/xchwarze
License: GPL2 or later.
*/

define('WP_SMFBRIDGE_MYSQLI_DRIVER', function_exists('mysqli_connect'));
class WP_SMFBridge {
	static $smf_db_user = '';
	static $smf_db_passwd = '';
	static $smf_db_server = '';
	static $smf_db_prefix = '';
	static $smf_db_name = '';
	static $smf_boardurl = '';
	static $smf_cookiename = '';
	static $bridge_loaded = false;
	static $is_activated_value = '';
	static $cookie_length = '';
	
	//db functions
	function dsr_db_open($db_host, $db_user, $db_password, $db_name){
		if (WP_SMFBRIDGE_MYSQLI_DRIVER)
			return mysqli_connect($db_host, $db_user, $db_password, $db_name);
		else {
			$link = mysql_connect($db_host, $db_user, $db_password);
			mysql_select_db($db_name, $link);
			return $link;
		}
	}

	function dsr_db_query($db, $query){
		if (WP_SMFBRIDGE_MYSQLI_DRIVER)
			return mysqli_query($db, $query);
		else
			return mysql_query($query, $db);
	}

	function dsr_db_fetch_assoc($result){
		if (WP_SMFBRIDGE_MYSQLI_DRIVER)
			return mysqli_fetch_assoc($result);
		else
			return mysql_fetch_assoc($result);
	}
	
	function dsr_db_fetch_array($result){
		if (WP_SMFBRIDGE_MYSQLI_DRIVER)
			return mysqli_fetch_array($result);
		else
			return mysql_fetch_array($result);
	}
	
	function dsr_db_fetch_row($result){
		if (WP_SMFBRIDGE_MYSQLI_DRIVER)
			return mysqli_fetch_row($result);
		else
			return mysql_fetch_row($result);
	}

	function dsr_db_affected_rows($result) {
		if (WP_SMFBRIDGE_MYSQLI_DRIVER)
			return mysqli_affected_rows($result);
		else
			return mysql_affected_rows($result);
	}

	function dsr_db_insert_id($result) {
		if (WP_SMFBRIDGE_MYSQLI_DRIVER)
			return mysqli_insert_id($result);
		else
			return mysql_insert_id();
	}

	function dsr_db_close($db){
		if (WP_SMFBRIDGE_MYSQLI_DRIVER)
			return mysqli_close($db);
		else
			return mysql_close($db);
	}
	
	//misc
	function loadConfig(){
		$smf_dir = ABSPATH . get_option('WP2SMF_smfdir');
		if (!file_exists("{$smf_dir}Settings.php"))
			return false;
		
		if (self::$bridge_loaded)
			return true;
			
		require_once "{$smf_dir}Settings.php";
		self::$smf_db_user = $db_user;
		self::$smf_db_passwd = $db_passwd;
		self::$smf_db_server = $db_server;
		self::$smf_db_prefix = $db_prefix;
		self::$smf_db_name = $db_name;
		self::$smf_boardurl = $boardurl;
		self::$smf_cookiename = $cookiename;
		
		self::$bridge_loaded = true;
		self::$is_activated_value = 1; // Values: 1 Activated (in this case by email), 3 Awaiting approval
		self::$cookie_length = 3600;
		
		return true;
	}
	
	function smfPassword($login, $pass){
		return sha1(strtolower($login) . $pass);
	}
	
	function smfCheckCookieConfig(){
		if (!self::loadConfig())
			return false;
		
		$link = self::dsr_db_open(self::$smf_db_server, self::$smf_db_user, self::$smf_db_passwd, self::$smf_db_name);
		$query = self::dsr_db_query($link, "SELECT variable, value FROM " . self::$smf_db_prefix . "settings WHERE variable = 'localCookies' OR variable = 'globalCookies'");
		while ($row = self::dsr_db_fetch_row($query))
			$cookies[ $row[0] ] = $row[1];
		
		self::dsr_db_close($link);
		return $cookies;
	}
	
	function smfLogoutByMember($member_name){
		if (!self::loadConfig())
			return false;
		
		$localCookies = false;
		$globalCookies = false;	
		
		$link = self::dsr_db_open(self::$smf_db_server, self::$smf_db_user, self::$smf_db_passwd, self::$smf_db_name);
		self::dsr_db_query($link, "DELETE FROM " . self::$smf_db_prefix . "log_online WHERE id_member IN (SELECT id_member FROM " . self::$smf_db_prefix . "members WHERE member_name = '{$member_name}') LIMIT 1");

		$parsed_url = self::smfURLParts($localCookies, $globalCookies);
		//setcookie('PHPSESSID', $HTTP_COOKIE_VARS['PHPSESSID'], time() - 3600, $parsed_url['path'] . '/', $parsed_url['host'], 0);  
		setcookie(self::$smf_cookiename, '', time() - 3600, $parsed_url['path'] . '/', $parsed_url['host'], 0);
		unset($_SESSION['login_' . self::$smf_cookiename]);
		
		self::dsr_db_query($link, "UPDATE " . self::$smf_db_prefix . "members SET pssword_salt = '" . substr(md5(mt_rand()), 0, 4) . "' WHERE member_name = '{$member_name}'");
		self::dsr_db_close($link);
	}
	
	//based on url_parts (SMF Subs-Auth.php)
	function smfURLParts($local, $global) {
		$parsed_url = parse_url(self::$smf_boardurl);

		if (empty($parsed_url['path']) || !$local)
			$parsed_url['path'] = '';

		if ($global && preg_match('~^\d{1,3}(\.\d{1,3}){3}$~', $parsed_url['host']) == 0 && preg_match('~(?:[^\.]+\.)?([^\.]{2,}\..+)\z~i', $parsed_url['host'], $parts) == 1)
				$parsed_url['host'] = '.' . $parts[1];
		else if (!$local && !$global)
			$parsed_url['host'] = '';
		else if (!isset($parsed_url['host']) || strpos($parsed_url['host'], '.') === false)
			$parsed_url['host'] = '';

		return array($parsed_url['host'], $parsed_url['path'] . '/');
	}

	//based on setLoginCookie (SMF Subs-Auth.php) 
	function smfSetLoginCookie($id, $password, $salt) {
		$localCookies = false;
		$globalCookies = false;

		$data = serialize(array($id, sha1($password . $salt), time() + self::$cookie_length));
		$parsed_url = self::smfURLParts($localCookies, $globalCookies);

		setcookie(self::$smf_cookiename, $data, time() + self::$cookie_length, $parsed_url['path'] . '/', $parsed_url['host'], 0);
		
		//deleted...
			// If subdomain-independent cookies are on, unset the subdomain-dependent cookie too.
			// Any alias URLs?  This is mainly for use with frames, etc.
		
		$_COOKIE[self::$smf_cookiename] = $data;
		$_SESSION['login_' . self::$smf_cookiename] = $data;
	}
	

	function addInACP() {
		add_submenu_page('options-general.php', 'WP2SMFBridge Settings', 'WP2SMFBridge', 8, __FILE__, array('WP_SMFBridge', 'settings'));
	}

	function settings() {
		load_plugin_textdomain('WP2SMFBridge', false, 'WP2SMFBridge/languages/');
		
		if (isset($_POST['action'])) {
			switch ($_POST['action']) {
				case "save":
					if (substr($_POST['smf_realpath'], -1) != '/')
						$_POST['smf_realpath'] .= '/';
					
					if (get_option('WP2SMF_smfdir') === False)
						add_option('WP2SMF_smfdir', $_POST['smf_realpath']);
					else
						update_option('WP2SMF_smfdir', $_POST['smf_realpath']);
					
					break;
					
				case "sync":
					//TODO					
					break;
			}			
		}

		require dirname(__FILE__) . '/settings.php';
	}
		
	//SMF magic
	function createUser($login, $email_address, $errors){
		if (!self::loadConfig())
			return false;
		
		$link = self::dsr_db_open(self::$smf_db_server, self::$smf_db_user, self::$smf_db_passwd, self::$smf_db_name);
		
		//checks
		$result = self::dsr_db_query($link, "SELECT member_name, email_address FROM " . self::$smf_db_prefix . "members WHERE member_name = '{$login}' OR email_address = '{$email_address}'");
		while ($row = self::dsr_db_fetch_array($result)){
			if ($row['member_name'] === $login)
				$errors->add('username_exists', __( '<strong>ERROR</strong>: This username is already registered. Please choose another one.'));
				
			if ($row['email_address'] === $email_address)
				$errors->add('email_exists', __( '<strong>ERROR</strong>: This email is already registered, please choose another one.'));
		}
			
		if (!empty($errors->errors))
			return $errors;

		//add
		$register_vars = array(
			'member_name' => "'{$login}'",
			'real_name' => "'{$login}'",
			'email_address' => "'" . addslashes($user_email) . "'",
			'passwd' => "'DSR!WP2SMF-Bridge'",
			'password_salt' => "'" . substr(md5(mt_rand()), 0, 4) . "'",
			'date_registered' => (string)time(),
			'is_activated' => '0',
			'pm_email_notify' => '1',
			'member_ip' => "'{$_SERVER['HTTP_REFERER']}'",
			/*
			'posts' => 0,
			'personal_text' => $modSettings['default_personal_text'],
			'pm_email_notify' => 1,
			'id_theme' => 0,
			'id_post_group' => 4,
			*/
		);

		self::dsr_db_query($link, "INSERT INTO " . self::$smf_db_prefix . "members (" . implode(', ', array_keys($register_vars)) . ") VALUES (" . implode(', ', $register_vars) . ")");
		//self::dsr_db_query($link, "INSERT INTO " . self::$smf_db_prefix . "members (" . implode(', ', array_keys($register_vars)) . ") VALUES (" . implode(', ', $register_vars) . ") ON DUPLICATE KEY UPDATE passwd = 'DSR!WP2SMF-Bridge', email_address = '" . addslashes($user_email) . "'");
		self::dsr_db_query($link, "REPLACE INTO " . self::$smf_db_prefix . "settings (variable, value) VALUES ('latestMember', " . self::dsr_db_insert_id($link) . "), ('latestRealName', '{$login}')");
		self::dsr_db_query($link, "UPDATE " . self::$smf_db_prefix . "settings SET value = value + 1 WHERE variable = 'totalMembers' LIMIT 1");
		self::dsr_db_close($link);
	}

	function authenticateUser($login, $pass){
		if ((empty($login) || empty($pass)) || !self::loadConfig())
			return false;
		
		$passwd = self::smfPassword($login, $pass);
		$link = self::dsr_db_open(self::$smf_db_server, self::$smf_db_user, self::$smf_db_passwd, self::$smf_db_name);
		self::dsr_db_query($link, "UPDATE " . self::$smf_db_prefix . "members SET is_activated = '" . self::$is_activated_value . "', passwd = '{$passwd}' WHERE member_name = '{$login}' AND passwd = 'DSR!WP2SMF-Bridge' LIMIT 1");
			
		//Oh my God, that's the funky sh...
		$user = self::dsr_db_fetch_assoc(self::dsr_db_query($link, "SELECT id_member, passwd, password_salt FROM " . self::$smf_db_prefix . "members WHERE member_name = '{$login}' AND passwd = '{$passwd}' LIMIT 1"));
		self::dsr_db_close($link);
		self::smfSetLoginCookie($user['id_member'], $user['passwd'], $user['password_salt']);
	}
	
	function passReset($login, $pass){
		$link = self::dsr_db_open(self::$smf_db_server, self::$smf_db_user, self::$smf_db_passwd, self::$smf_db_name);
		$ret = self::dsr_db_query($link, "UPDATE " . self::$smf_db_prefix . "members SET passwd = '" . self::smfPassword($login, $pass) . "' WHERE member_name = '{$login}' LIMIT 1");		
		self::dsr_db_close($link);
		return $ret;
	}
	
	function userPassReset($user, $pass){
		if (empty($pass) || !self::loadConfig())
			return false;
		
		self::passReset($user->data->user_login, $pass);
	}
	
	function userEditProfile($user_id, $old_user_data){
		if (empty($_POST['pass1']) || !self::loadConfig())
			return false;
			
		self::passReset($old_user_data->user_login, $_POST['pass1']);
	}
	
	function logoutUser(){
		$user = wp_get_current_user();
		self::smfLogoutByMember($user->data->user_login);
	}
	
	function authenticateWPCookie($cookie_elements, $user){
		if (!self::loadConfig() || isset($_SESSION['login_' . self::$smf_cookiename]))
			return false;
		
		$link = self::dsr_db_open(self::$smf_db_server, self::$smf_db_user, self::$smf_db_passwd, self::$smf_db_name);
		$user = self::dsr_db_fetch_assoc(self::dsr_db_query($link, "SELECT id_member, passwd, password_salt FROM " . self::$smf_db_prefix . "members WHERE member_name = '{$user->data->user_login}' LIMIT 1"));
		self::dsr_db_close($link);
		self::smfSetLoginCookie($user['id_member'], $user['passwd'], $user['password_salt']);
	}
}


// Hooks!
add_action('admin_menu', array('WP_SMFBridge', 'addInACP'));
add_action('register_post', array('WP_SMFBridge', 'createUser'), 100, 3);
add_action('wp_authenticate', array('WP_SMFBridge', 'authenticateUser'), 100, 2);
add_action('password_reset', array('WP_SMFBridge', 'userPassReset'), 100, 2);
add_action('profile_update', array('WP_SMFBridge', 'userEditProfile'), 100, 2);
add_action('wp_logout', array('WP_SMFBridge', 'logoutUser'));
add_action('auth_cookie_valid', array('WP_SMFBridge', 'authenticateWPCookie'), 100, 2);
?>