=== Plugin Name ===
Contributors: kubi23
Donate link: http://www.svenkubiak.de/mail-on-update/#donate
Tested up to: 3.0
Stable tag: 4.1
Requires at least: 2.8
Tags: wordpress, plugin, mail, e-mail, notification, update, updates, notifications, mail-on-update

Sends an E-Mail to one (i.e. WordPress admin) or multiple E-Mail Addresses if new versions of plugins are available.

== Description ==
 
As of WordPress Version 2.5, WordPress automaticly checks if a new update for an installed plugin is available. However, you still have to check your wp-admin to see the notification. This plugin informs you via E-mail when a new update is available.
It uses the build-in update function to periodicly check for new versions at the wordpress plugin directory. If a new version is available, the WordPress-Admin will recieve an E-mail every 12 Hours, informing him which plugins needs to be updated.

As of Plugin Version 2.0 you can set multilple Recipients and filter plugins according to your requirements.

= Available Languages  =

* German
* English
* French

== Installation ==

1. Download Plugin an unzip
2. Copy complete folder to your WordPress plugin-folder
3. Activate plugin via wp-admin
4. Go to Settings -> Mail On Update and set your options (if required)

Done.

== Frequently Asked Questions ==

= I don't recieve any E-mails, although updates are available =

Check you Admin E-mail settings under Settings -> E-mail. It has to be a valid E-mail-adress. Furthermore the Plugin is only called when the blog is accesed. So your Blog needs at least one click.
 

== Changelog ==

= 4.1 =
* Fixed Bug with WordPress 3.0
* Plugin requires now at least WordPress 2.8
* Updated language files

= 4.0 =
* Added compatibility to WordPress 3.0
* Updated language files

= 3.4 =
* Code-Cleanup
* Update language files

= 3.3 =
* Removed debug informations which made it in the release (sorry)
* Change Subject of notifcation E-Mails
* Added new WordPress Plugins Changelog

= 3.2 =
*  WordPress-Plugin SVN Error, which did not allow 3.2 commit?!

= 3.1 =
* Fixed incompatibility with WordPress 2.8

= 3.0 =
* Changed handling of options
* Code cleanup and improvements
* New style for settings page
* Update language file

= 2.7 =
* Added current and new version to notification mail
* Update language file

= 2.6 =
* Fixed Bug when using filter
* Added French translation

= 2.5 =
* Fixed Bug with umlaut
* Fixed Bug when checke WordPress Version

= 2.4 =
* Fixed Bug when validating E-Mail-Adresses
* Fixed Bug with UTF-8 encoding
* Fixed Bug when validating if a plugin is active or not
* Updated language file
	
= 2.3 =
* Fixed Bug when sending notifications
	
= 2.2 =
* Fixed Pharse Error

= 2.1 =
* Updated language file

= 2.0 =
* Added Option page
* Added Option for alternative Recipients
* Added Option to filter Plugins
* Added Option to not inform user if a plugin is anctive
* Update language file
* Update readme file

= 1.5 =
* Changed E-Mail Notification
	
= 1.4 =
* Minor code cleanup
	
= 1.3 =
* Fixed bug in E-Mail Notification
	
= 1.2 =
* Stable Release
* Minor code cleanup
    
= 1.1 Beta =
* Fixed: Blogname was missing
    
= 1.0 Beta =
* Initial version