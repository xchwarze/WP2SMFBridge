=== Plugin Name ===
Contributors: DSR!
Author URI: https://github.com/xchwarze
Tags: smf, forums, users, bridge, wordpress
Requires at least: 2.5
Tested up to: 4.0	
Stable tag: 1.0
License: GPLv2 or later

Login bridge from Wordpress to Simple Machine Forum.


== Description ==

WP2SMFBridge is a simple one way bridge from Wordpress to Simple Machine Forum (v2 tested). This means, this one uses databases of SMF Forum and sync to WP database every time a user performs login or logout action in WP. To get this working only use WP to register/login/logout. Also, WP and SMF must be installed in same domain, and should not be being accessed through a subdomain, though it still work. For example, if your website contains of Wordpress for news and SMF for forum, if your news is mydomain.com, then your forums should be somewhere like mydomain.com/forum.

This plugin will do these following tasks:

* If a user log in WP, then that user will be logged in SMF.
* If a user logout in WP, then that user will be logged out SMF.
* If a user created/register/change password in WP, those actions happen on SMF!
* Users that are created in SMF can be used once disabled WP plugin


== Installation ==

You must have SMF installed as a directory within your domain. Your forum and WordPress installations should not be on different domains or subdomains! Your blog on mydomain.com and forum on forum.mydomain.com will not work!

For example, if you access your blog from mydomain.com, your wordpress installation is at mydomain.com/wordpress, then it is possible that you can access your forum at mydomain.com/forum. 

1. Upload `WP2SMFBridge` to the `/wp-content/plugins/` directory
2. Uncheck "Enable local storage of cookies" and "Use subdomain independent cookie" in SMF. You can turn it off from Admin -> Configuration -> Server Settings -> Cookies and Sessions
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Visit WP2SMFBridge Settings in your Admin Settings section, enter path to your forum, then Save it. If it is accessible, then at this point, WP2SMFBridge is fully activated.


== Notes ==

Every time a user logged in SMF forum, same username (will be created if not exist) will be logged in  Wordpress. Because SMF is placed in higher order, so user in Wordpress will be changed to SMF with same role.

For example, you have an administrator named "admin" in wordpress, after WP2SMFBridge fully activated, the SMF user named "admin" can login as that administrator. So be careful!


== Frequently Asked Questions ==

= How do I active WP2SMFBridge? =

Read Installations.

= How do I know path to forum =

For example, if you access your blog from mydomain.com, your wordpress installation is at mydomain.com/wordpress, your forum at mydomain.com/forum. Then URI will be: ../forum


== Changelog ==

= 1.0 =
* First public version


== Upgrade Notice ==

Not yet


== Screenshots ==

https://raw.githubusercontent.com/xchwarze/WP2SMFBridge/master/screenshot/screenshot.jpg
https://raw.githubusercontent.com/xchwarze/WP2SMFBridge/master/screenshot/screenshot2.jpg

