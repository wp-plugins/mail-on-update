=== Plugin Name ===
Contributors: kubi23
Donate link: http://www.svenkubiak.de/mail-on-update/#donate
Tested up to: 2.7
Stable tag: 2.3
Requires at least: 2.6
Tags: wordpress, plugin, mail, e-mail, notification, update, updates, notifications

Sends an E-Mail to one (i.e. WordPress admin) or multiple E-Mail Addresses if new versions of plugins are available.

== Description ==

As of Version 2.5, WordPress automaticly checks if a new update for an installed plugin is available. However, you still have to check your wp-admin to see the notification. This plugin informs you via E-mail when a new update is available.
It uses the build-in update function to periodicly check for new versions at the wordpress plugin directory. If a new version is available, the WordPress-Admin will recieve an E-mail every 12 Hours, informing him which plugins needs to be updated.

As of Version 2.0 you can set multilple Recipients and filter plugins according to your requirements.

== Installation ==

1. Download Plugin an unzip
2. Copy complete folder to your WordPress plugin-folder
3. Activate plugin via wp-admin
4. Go to Settings -> Mail On Update and set your options

Done.

== Frequently Asked Questions ==

= I don't recieve any E-mails, although updates are available =

Check you Admin E-mail settings under Settings -> E-Mail. It has to be a valid E-Mail-Address. Furthermore the Plugin is only called when the blog is accesed. So your Blog needs at least some clicks.

== Version History ==

* Version 2.3
	* Fixed Bug when sending notifications
* Version 2.2
	* Fixed Pharse Error
* Version 2.1
	* Updated language file
* Version 2.0
	* Added Option page
	* Added Option for alternative Recipients
	* Added Option to filter Plugins
	* Added Option to not inform user if a plugin is anctive
	* Update language file
	* Update readme file
* Version 1.5
	* Changed E-Mail Notification
* Version 1.4
	* Minor code cleanup
* Version 1.3
	* Fixed bug in E-Mail Notification
* Version 1.2
	* Stable Release
    * Minor code cleanup
* Version 1.1 Beta
    * Fixed: Blogname was missing
* Version 1.0 Beta
    * Initial version