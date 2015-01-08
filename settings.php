<?php
echo '<div class="wrap"><h2>' . esc_html( __( 'WP2SMFBridge Settings', 'WP2SMFBridge' ) ) . '</h2>';

if (isset($_POST['action'])) {
	if ($_POST['action'] === 'save') 
		echo '<div id="message" class="updated"><p>' . esc_html( __( 'Settings saved!', 'WP2SMFBridge' ) ) . '</p></div>';
	else if ($_POST['action'] === 'sync') //TODO
		echo '<div id="message" class="updated"><p>' . esc_html( __( 'Sync databases...', 'WP2SMFBridge' ) ) . '</p></div>';
}
	
if (!self::loadConfig())
	echo '<div id="message" class="error"><p>' . esc_html( __( 'WP2SMF has not been configured properly and is not active!', 'WP2SMFBridge' ) ) . '</p></div>';
else if (self::loadConfig() && (isset($_POST['action']) && ($_POST['action'] === 'save')))
	echo '<div id="message" class="updated"><p>' . esc_html( __( 'WP2SMF is now fully activated!', 'WP2SMFBridge' ) ) . '</p></div>';
else if (get_option('WP2SMF_smfdir') && (!file_exists(ABSPATH.get_option('WP2SMF_smfdir').'Settings.php')))
	echo '<div id="message" class="error"><p>' . esc_html( __( 'Your SMF path is invalid! could not locate Settings.php', 'WP2SMFBridge' ) ) . '</p></div>';
		
$smf_settings = self::smfCheckCookieConfig();
if ($smf_settings['globalCookies'] ||$smf_settings['localCookies'])
	echo '<div id="message" class="error"><p>' . esc_html( __( 'This plugin may not work, because you did not uncheck "Enable local storage of cookies" and "Use subdomain independent cookie" in SMF settings!', 'WP2SMFBridge' ) ) . '</p></div>';
		
echo '	
<form action="' . $_SERVER['REQUEST_URI'] . '" method="POST">
<input type="hidden" name="action" value="save"/>

<p>' . esc_html( __( 'You have to uncheck "Enable local storage of cookies" and "Use subdomain independent cookie" in SMF to make this plugin works.', 'WP2SMFBridge' ) ) . '<br/>
' . esc_html( __( 'You can turn it off from Admin -> Configuration -> Server Settings -> Cookies and Sessions', 'WP2SMFBridge' ) ) . '<p>
	
<table class="form-table">
<tr>
	<th scope="row">' . esc_html( __( 'Forum Path', 'WP2SMFBridge' ) ) . '</th>
		<td><fieldset><legend class="screen-reader-text"><span>' . esc_html( __( 'Forum Path', 'WP2SMFBridge' ) ) . '</span></legend>
			<label for="smf_realpath">
				' . esc_html( __( 'Enter the forum path for read Settings.php', 'WP2SMFBridge' ) ) . '<br>
				<input name="smf_realpath" type="text" id="smf_realpath" value="' . (get_option('WP2SMF_smfdir') ? get_option('WP2SMF_smfdir') : '') . '" class="regular-text ltr" />
			</label>
		</fieldset></td>
	</tr>
</table>
	<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="' . esc_html( __( 'Save Changes', 'WP2SMFBridge' ) ) . '"  /></p>
</form>
</div>';
?>