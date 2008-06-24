<?php
/*
Plugin Name: Mail On Update
Plugin URI: http://www.svenkubiak.de/mail-on-update
Description: Sends an E-Mail to the WordPress Administrator if new versions of plugins are available.
Version: 1.4
Author: Sven Kubiak
Author URI: http://www.svenkubiak.de

Copyright 2008 Sven Kubiak

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/
global $wp_version;
define('MOUISWP25', version_compare($wp_version, '2.4', '>='));

class MailOnUpdate {

	function mailonupdate()
	{		
		//load language file
		if (function_exists('load_plugin_textdomain'))
			load_plugin_textdomain('mail-on-update', PLUGINDIR.'/mail-on-update');
			
		//is wordpress at least version 2.5?
		if (!MOUISWP25){
			add_action('admin_notices', array(&$this, 'wpVersionFailed'));
			return;
		}			
	
		add_option('mou_lastchecked', 0, '', 'yes');
	
		add_action('wp_footer', array(&$this, 'checkPlugins'));
		add_action('deactivate_mail-on-update/mail-on-update.php', array(&$this, 'deactivate'));
	}
	
	function deactivate()
	{
		delete_option('mou_lastchecked');			
	}
	
	function wpVersionFailed()
	{
		echo "<div id='message' class='error fade'><p>".__('Mail On Update requires at least WordPress 2.5!','mail-on-update')."</p></div>";	
	}	

	function checkPlugins()
	{			
		//is last check more than 12 hours ago?
		if (time() < get_option('mou_lastchecked') + 43200)
			return;		
		
		//inlcude wordpress update functions
		@require_once ( ABSPATH . 'wp-admin/includes/update.php' );
		@require_once ( ABSPATH . 'wp-admin/admin-functions.php' );			
			
		//call the wordpress update function
		wp_update_plugins();		
			
		//get a list of plugins to update
		$updates = get_option('update_plugins');

		//are plugin updates available?
		if (empty($updates->response))
			return; 

		//get all plugin
		$plugins = get_plugins();
		
		//set blogname for notification e-mail
		$blogname = get_option('blogname');

		//start message for the notification e-mail
		$message  = sprintf( __('New updates at %1$s','mail-on-update'), $blogname);
		$message .= "\n\n";
		
		//loop through available plugin updates
		foreach ($updates->response as $pluginfile => $update)
		{
			//append available updates to notification message
			$message .= sprintf( __('There is a new version (%1$s) of %2$s available.', 'mail-on-update'), $update->new_version, $plugins[$pluginfile]['Name']);
			$message .= "\n";
		}
		
		//append siteurl to notfication e-mail
		$message .= "\n\n";
		$message .= __('Update your Plugins at', 'mail-on-update')."\n".get_option('siteurl')."/wp-admin/plugins.php";
			
		//set mail header for notification message
		$sender 	= 'wordpress@' . preg_replace('#^www\.#', '', strtolower($_SERVER['SERVER_NAME']));
		$from 		= "From: \"$blogname\" <$sender>";	
		$headers 	= "$from\n" . "Content-Type: text/plain; charset=\"" . get_option('blog_charset') . "\"\n";
		
		//send e-mail notification to admin
		wp_mail(get_option('admin_email'), __('WordPress Plugin Update Notification','mail-on-update'), $message, $headers);
		
		//set timestamp of last update check
		update_option('mou_lastchecked', time());
	}
}
//initallze class
if (class_exists('MailOnUpdate'))
	$mailonupdate = new MailOnUpdate();
?>