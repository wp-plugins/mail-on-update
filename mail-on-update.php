<?php
/*
Plugin Name: Mail On Update
Plugin URI: http://www.svenkubiak.de/mail-on-update
Description: Sends an E-Mail to the WordPress Administrator if new versions of plugins are available.
Version: 2.0
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
define('MOUISWP25', version_compare($wp_version, '2.5', '>='));


//<-- Start of my stuff
function mailonupdate_init() {
        add_action('admin_menu', 'mailonupdate_config_page');
}

add_action('init', 'mailonupdate_init');

function mailonupdate_config_page() {
        if ( function_exists('add_submenu_page') )
                add_submenu_page('plugins.php', __('Mail On Update Configuration', 'mail-on-update'), __('Mail On Update Configuration', 'mail-on-update'), 'manage_options', 'mailonupdate-config', 'mailonupdate_conf');
}

//$sep=="\n"	:return qualified mail addresses for the form field
//$sep!="\n"	:return a $sep separated list of qualified mail addresses which are not disabled (by '-' at the end of the mail address) 
function mailonupdate_validateRecipient($maillist,$sep) {
	$hit=0;
	foreach (split("\n",$maillist) as $imail) {
		$mail=trim($imail);	
		if ( preg_match("/^[a-zäöüA-ZÄÖÜ0-9_.-]+@[a-zäöüA-ZÄÖÜ0-9-]+.[a-zäöüA-ZÄÖÜ0-9-.]+\-{0,1}$/",$mail) ) {
			if ($sep=="\n" || substr($mail,-1)!='-' ) {
				if ($hit>0) {$nmaillist.=$sep;};
					$nmaillist.=$mail;
					$hit++;
				};
			};
		};

	return $nmaillist;
}

//notifier list
function mailonupdate_listOfCommaSeparatedRecipients() {
	$list=mailonupdate_validateRecipient(get_option('mailonupdate_mailto'),',');

	if ("$list" !='') { return $list;} else {return get_option("admin_email");};
}


//radio button check
function rbc($option,$state_list,$default) {
	$checked='checked="checked"';
	$state=get_option($option);
	$hit=false;
	foreach (split(' ',$state_list) as $istate) {
		if ($state==$istate) {$res[$istate]=$checked; $hit=true; $break;};
		};

	if (!$hit) {$res["$default"]=$checked; };

return $res;
};

//plugin qualified?
function mailonupdate_pqual($plugin, $plugin_file) {
	$plugin=strtolower($plugin);
	$filtermethod=get_option('mailonupdate_filtermethod');

	if ($filtermethod=='nolist') return true;

	if (get_option("mailonupdate_exclinact")!='' && !is_plugin_active($plugin_file)) return false;
	
	if ($filtermethod=='whitelist') {$state=false;} else {$state=true;};
	foreach (split("\n",get_option('mailonupdate_filter')) as $filter) {
		$filter=trim(strtolower($filter));
		if (strpos($filter,-1)!='-') {
			if (!(strpos($plugin,$filter)===false)) {$state=!$state; break;};
			};
		};

	return $state;
}

//show qualified plugins
function mailonupdate_qualp() {
	$all_plugins=get_plugins();
	$del='';
	foreach( (array)$all_plugins as $plugin_file => $plugin_data) {	
		$plugin=wp_kses($plugin_data['Title']);
		if ($plugin!="") {
			if (is_plugin_active($plugin_file)) {$inact='';} else {$inact='-';};
			if (mailonupdate_pqual($plugin, $plugin_file)) {$flag='[x]';} else {$flag='[ ]';}
			
			$l.="$del$flag$plugin$inact";
			$del="\n";
			};		
		};

	return $l;
} 

//the configuration page
function mailonupdate_conf() {
	if ( isset($_POST['submit']) ) {
                if ( function_exists('current_user_can') && !current_user_can('manage_options') )
                        die(__('Cheatin&#8217; uh?'));

		if ( isset( $_POST['mailonupdate_mailto'] ) )
                        update_option( 'mailonupdate_mailto', mailonupdate_validateRecipient($_POST['mailonupdate_mailto'], "\n") );

		if ( isset( $_POST['mailonupdate_filter'] ) ) {
                        update_option( 'mailonupdate_filter', $_POST['mailonupdate_filter'] );
			update_option( 'mailonupdate_filtermethod', $_POST['mailonupdate_filtermethod'] );
			update_option( 'mailonupdate_exclinact', $_POST['mailonupdate_exclinact'] );
			};
		};

if ( !empty($_POST ) ) : ?>
<div id="message" class="updated fade"><p><strong><?php _e('Options saved.') ?></strong></p></div>
<?php endif; ?>
<div class="wrap">
<h2><?php _e('Mail On Update Configuration', 'mail-on-update'); ?></h2>
<div class="narrow">
	<p>
	<form action="" method="post" id="mailonupdate-conf" style="margin: auto; width: 700px; ">
	<? if (mailonupdate_validateRecipient(get_option('mailonupdate_mailto'),',')=='') { ?>
		<p>  
		<? _e('Since no mail address is specified the default', 'mail-on-update');
			print ' <a href="/wp-admin/options-general.php"><b>'.get_option("admin_email").'</b></a> ';
			_e('is assumed. Provide a list of alternative recipients to override this default.', 'mail-on-update'); ?>
		</p>
	<? }; ?>
		<table>
		<tr><td><? _e('List of alternative recipients:', 'mail-on-update'); ?></td><td colspan="2">&nbsp;</td></tr>
		<tr>
		<td>
		<textarea id="mailonupdate_mailto" name="mailonupdate_mailto" cols="40" rows="5" style="font-family: 'Courier New', Courier, mono; font-size: 1em;" ><?php echo get_option('mailonupdate_mailto'); ?></textarea>
		</td>
		<td>&nbsp;&nbsp;</td>
		<td valign="top" style="font-size: 0.8em;">
		<? _e('* each mail address has to appear on a single line', 'mail-on-update'); ?><br />
		<? _e('* invalid addresses are rejected', 'mail-on-update'); ?><br />
                <? _e('* a mail address with "-" at the end is not considered', 'mail-on-update'); ?><br />
		<? _e('* clear this field to set the default mail address<br />', 'mail-on-update'); ?>
		</td>
		</tr>
		</table>
		<p class="submit"><input type="submit" name="submit" value="<?php _e('Update options &raquo;', 'mail-on-update'); ?>" /></p>
	</form>
	</p>
	<p>
	<form action="" method="post" id="mailonupdate-conf" style="margin: auto; width: 700px;">
		<table>
		<tr><td><? _e('Filters:', 'mail-on-update'); ?></td><td colspan="2">&nbsp;</td></tr>
		<tr>
		<td valign="top">
		<textarea id="mailonupdate_filter" name="mailonupdate_filter" cols="40" rows="5" style="font-family: 'Courier New', Courier, mono; font-size: 1em;" ><?php echo get_option('mailonupdate_filter'); ?></textarea>
		</td>
		<td>&nbsp;&nbsp;</td>
		<td valign="top" style="font-size: 0.8em;">
		<? _e('* a filter has to appear on a single line', 'mail-on-update'); ?><br />
		<? _e('* a plugin is matched if the filter is a substring', 'mail-on-update'); ?><br />
		<? _e('* a filter is not case sensetive', 'mail-on-update'); ?><br />
		<? _e('* a filter is considered as a string and no regexp', 'mail-on-update'); ?><br />		
		<? _e('* a filter with "-" at the end is not considered', 'mail-on-update'); ?>
		</td>
		</tr>
		<tr>
		<td style="font-size: 0.8em;">
		<? $rval=rbc('mailonupdate_filtermethod','nolist blacklist whitelist','nolist'); ?>
                <input type="radio" name="mailonupdate_filtermethod" value="nolist" <? print $rval['nolist']; ?>" /> <? _e('don\'t filter plugins', 'mail-on-update'); ?><br />
                <input type="radio" name="mailonupdate_filtermethod" value="blacklist" <? print $rval['blacklist']; ?>" /> <? _e('black list filter (exclude a plugin)', 'mail-on-update'); ?><br />
                <input type="radio" name="mailonupdate_filtermethod" value="whitelist" <? print $rval['whitelist']; ?>" /> <? _e('white list filter (include a plugin)', 'mail-on-update'); ?><br />
                <input type="checkbox" name="mailonupdate_exclinact" value="checked" <? print get_option("mailonupdate_exclinact"); ?> /> <? _e('don\'t validate inactive plugins', 'mail-on-update'); ?>
		</td>
		<td colspan="2">&nbsp;</td>
		</tr>
		</table>
		<p class="submit"><input type="submit" name="submit" value="<?php _e('Update options &raquo;'); ?>" /></p>
	</form>
	</p>
	<p>
		<table>
		<tr><td><? _e('Resultant plugins to validate:', 'mail-on-update'); ?></td><td colspan="2">&nbsp;</td></tr>
		<tr>
		<td>
		<textarea id="mailonupdate_pluginmonitor" name="mailonupdate_pluginmonitor" readonly="readonly" cols="40" rows="5" style="font-family: 'Courier New', Courier, mono; font-size: 1em;" ><? print mailonupdate_qualp(); ?> </textarea>
		</td>
		<td>&nbsp;&nbsp;</td>
		<td valign="top" style="font-size: 0.8em;">
			[x] <? _e('denotes a plugin to validate', 'mail-on-update'); ?><br />
			[ ] <? _e('denotes a plugin not to validate', 'mail-on-update'); ?><br />
			"-" <? _e('denotes an inactive plugin', 'mail-on-update'); ?>
		</td>
		</tr>
		</table>
	</p>
</div>
</div>

<?php
}
//End of my stuff -->


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
		$message  = '';
				
		//loop through available plugin updates
		//my stuff start
		$pluginNotVaildated = '';
		//my stuff stop

		foreach ($updates->response as $pluginfile => $update)
		{
			//my stuff start	
			if (mailonupdate_pqual($plugins[$pluginfile]['Name'], $pluginfile))
			{
			//my stuff stop

			//append available updates to notification message
			$message .= sprintf( __('There is a new version (%1$s) of %2$s available.', 'mail-on-update'), $update->new_version, $plugins[$pluginfile]['Name']);
			$message .= "\n";

			//my stuff start
			}
			else
			{	
			if (is_plugin_active($pluginfile)) { $act='(active)'; } else { $act='inactive';};	
			$pluginNotVaildated .= "\n".sprintf( __('There is a new version (%1$s) of %2$s available. (%3)', 'mail-on-update'), $update->new_version, $plugins[$pluginfile]['Name'], $act);;
			};
			//my stuff stop
		}

		//my stuff start 
		if ($message!='')
		{
		//my stuff stop
		
		//append siteurl to notfication e-mail
		$message .= "\n\n";
		$message .= __('Update your Plugins at', 'mail-on-update')."\n".get_option('siteurl')."/wp-admin/plugins.php";

		//my stuff start
		if ($pluginNotVaildated!='') {
			$message.= "\n\n".__('There are also updates available for the plugins below. However, these plugins are of no concern for this notifier and the information is just for completeness.', 'mail-on-update')."\n".$pluginNotVaildated;
			};
		//my stuff stop
		
		//set mail header for notification message
		$sender 	= 'wordpress@' . preg_replace('#^www\.#', '', strtolower($_SERVER['SERVER_NAME']));
		$from 		= "From: \"$blogname\" <$sender>";	
		$headers 	= "$from\n" . "Content-Type: text/plain; charset=\"" . get_option('blog_charset') . "\"\n";
		
		//send e-mail notification to admin
		//wp_mail(get_option('admin_email'), __('WordPress Plugin Update Notification','mail-on-update'), $message, $headers);
		wp_mail(mailonupdate_listOfCommaSeparatedRecipients(), __('WordPress Plugin Update Notification','mail-on-update'), $message, $headers);	

		//my stuff start
		};
		//my stuff stop
		
		//set timestamp of last update check
		update_option('mou_lastchecked', time());
	}
}
//initallze class
if (class_exists('MailOnUpdate'))
	$mailonupdate = new MailOnUpdate();
?>