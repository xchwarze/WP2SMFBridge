WP2SMFBridge
====

WP2SMFBridge is a simple one way bridge from Wordpress to Simple Machine Forum (v2 tested). This means, this one uses databases of SMF Forum and sync to WP database every time a user performs login or logout action in WP. To get this working only use WP to register/login/logout. Also, WP and SMF must be installed in same domain, and should not be being accessed through a subdomain, though it still work. For example, if your website contains of Wordpress for news and SMF for forum, if your news is mydomain.com, then your forums should be somewhere like mydomain.com/forum.

This plugin will do these following tasks:

* If a user log in WP, then that user will be logged in SMF.
* If a user logout in WP, then that user will be logged out SMF.
* If a user created/register/change password in WP, those actions happen on SMF! 
* Users that are created in SMF can be used once disabled WP plugin


Images
-----------

![alt text](http://oi58.tinypic.com/34j5dmt.jpg "WP2SMFBridge - Settings")
