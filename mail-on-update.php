<?php
/*
Plugin Name: Mail On Update
Plugin URI: http://www.svenkubiak.de/mail-on-update
Description: Sends an E-Mail to one (e.g. WordPress admin) or multiple E-Mail-Addresses if new versions of plugins are available.
Version: 3.1
Author: Sven Kubiak, Matthias Kindler
Author URI: http://www.svenkubiak.de

Copyright 2008-2009 Sven Kubiak

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
define('MOUISWP26', version_compare($wp_version, '2.6', '>='));
define('MOUISWP28', version_compare($wp_version, '2.8', '>='));

class MailOnUpdate {
	
	var $mou_lastchecked;
	var $mou_mailto;
	var $mou_exclinact;
	var $mou_filtermethod;
	var $mou_filter;

	function mailonupdate()
	{		
		//load language file
		if (function_exists('load_plugin_textdomain'))
			load_plugin_textdomain('mail-on-update', PLUGINDIR.'/mail-on-update');
			
		//is wordpress at least version 2.6?
		if (!MOUISWP26){
			add_action('admin_notices', array(&$this, 'wpVersionFailed'));
			return false;
		}			
		
		//add wordpress aktions
		add_action('wp_footer', array(&$this, 'checkPlugins'));
		add_action('admin_menu', array(&$this, 'mouAdminMenu'));
		
		//tell wp what to do when plugin is activated and deactivated
		register_activation_hook(__FILE__, array(&$this, 'activate'));
		register_deactivation_hook(__FILE__, array(&$this, 'deactivate'));		

		//load nospamnx options
		$this->loadOptions();		
	}
	
	function activate()
	{	
		//add mailonupdate options
		$options = array(
			'mou_lastchecked' 	=> 0,
			'mou_mailto'		=> '',
			'mou_exclinact'		=> '',
			'mou_filtermethod'	=> '',
			'mou_filter'		=> '',
		);
		
		add_option('mailonupdate', $options, '', 'yes');
	}	
	
	function deactivate()
	{
		delete_option('mailonupdate');	
	}	
	
	function loadOptions()
	{
		$options = get_option('mailonupdate');
		
		$this->mou_lastchecked 	= $options['mou_lastchecked'];
		$this->mou_mailto		= $options['mou_mailto'];
		$this->mou_exclinact	= $options['mou_exclinact'];
		$this->mou_filtermethod	= $options['mou_filtermethod'];
		$this->mou_filter		= $options['mou_filter'];
	}	
	
	function updateOptions()
	{
		$options = array(
			'mou_lastchecked'	=> $this->mou_lastchecked,
			'mou_mailto'		=> $this->mou_mailto,
			'mou_exclinact'		=> $this->mou_exclinact,
			'mou_filtermethod'	=> $this->mou_filtermethod,
			'mou_filter'		=> $this->mou_filter,
		);
		
		update_option('mailonupdate', $options);
	}	
	
	function wpVersionFailed()
	{
		echo "<div id='message' class='error fade'><p>".__('Your WordPress is to old. Mail On Update requires at least WordPress 2.6.','mail-on-update')."</p></div>";	
	}	

	function checkPlugins()
	{			
		//is last check more than 12 hours ago?
		if (time() < $this->mou_lastchecked + 43200)
			return false;
		
		//inlcude wordpress update functions
		@require_once ( ABSPATH . 'wp-admin/includes/update.php' );
		@require_once ( ABSPATH . 'wp-admin/admin-functions.php' );			
			
		//call the wordpress update function
		wp_update_plugins();		
			
		//get a list of plugins to update		
		if (MOUISWP28)
			$updates = get_transient('update_plugins');
		else 
			$updates = get_option('update_plugins');
			
		//are plugin updates available?
		if (empty($updates->response)){
			return false; 
		}
			
		//get all plugin
		$plugins = get_plugins();
		
		//set blogname for notification e-mail
		$blogname = get_option('blogname');

		//start message for the notification e-mail
		$message  = '';
				
		//loop through available plugin updates
		$pluginNotVaildated = '';

		foreach ($updates->response as $pluginfile => $update)
		{	
			if ($this->mailonupdate_pqual($plugins[$pluginfile]['Name'], $pluginfile))
			{
				//append available updates to notification message
				$message .= sprintf( __('A new version of %1$s is available.', 'mail-on-update'), trim($plugins[$pluginfile]['Name']));
				$message .= "\n";
				$message .= sprintf( __('- Installed: %1$s, Current: %2$s', 'mail-on-update'), $plugins[$pluginfile]['Version'], $update->new_version);
				$message .= "\n\n";
			}
			else
			{	
				(is_plugin_active($pluginfile)) ? $act = __('active', 'mail-on-update') : $act = __('inactive', 'mail-on-update');
				$pluginNotVaildated .= "\n".sprintf( __('A new version (%1$s) of %2$s is available. (%3s)', 'mail-on-update'), $update->new_version, $plugins[$pluginfile]['Name'], $act);
			};
		}

		if ($message!='')
		{	
			//append siteurl to notfication e-mail
			$message .= __('Update your Plugins at', 'mail-on-update')."\n".get_option('siteurl')."/wp-admin/plugins.php";
	
			if ($pluginNotVaildated!='') {
				$message.= "\n\n".__('There are also updates available for the plugins below. However, these plugins are of no concern for this notifier and the information is just for completeness.', 'mail-on-update')."\n".$pluginNotVaildated;
			};
			
			//set mail header for notification message
			$sender 	= 'wordpress@' . preg_replace('#^www\.#', '', strtolower($_SERVER['SERVER_NAME']));
			$from 		= "From: \"$sender\" <$sender>";	
			$headers 	= "$from\n" . "Content-Type: text/plain; charset=\"" . get_option('blog_charset') . "\"\n";
			
			//send e-mail notification to admin or multiple recipienes
			$subject = sprintf(__('[%s] Plugin Update Notification','mail-on-update'), $blogname);
			wp_mail($this->mailonupdate_listOfCommaSeparatedRecipients(), $subject, $message, $headers);	

		};
		
		//set timestamp of last update check
		$this->mou_lastchecked = time();
		$this->updateOptions();
	}
	
	function mouAdminMenu()
	{
		add_options_page('Mail On Update', 'Mail On Update', 8, 'mail on update', array(&$this, 'mailonupdateConf'));           
	}

	//$sep=="\n"	:return qualified mail addresses for the form field
	//$sep!="\n"	:return a $sep separated list of qualified mail addresses which are not disabled (by '-' at the end of the mail address) 
	function mailonupdate_validateRecipient($maillist,$sep)
	{
		$hit=0;
		foreach (split("\n",$maillist) as $imail) {
			$mail=trim($imail);	
			
			if ( preg_match("/^[a-z&auml;&ouml;&uuml;A-Z&Auml;&Ouml;&Uuml;0-9_.-]+@[a-z&auml;&ouml;&uuml;A-Z&Auml;&Ouml;&Uuml;0-9-]+.[a-z&auml;&auml;&uuml;A-Z&Auml;&Ouml;&Uuml;0-9-.]+\-{0,1}$/",$mail) ) {
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
	function mailonupdate_listOfCommaSeparatedRecipients()
	{
		$list = $this->mailonupdate_validateRecipient($this->mou_mailto,',');
	
		if ("$list" !='')
			return $list;
		else
			return get_option("admin_email");
	}


	//radio button check
	function rbc($option,$state_list,$default)
	{
			$checked = 'checked="checked"';
			$state = $this->mou_filtermethod;
			$hit = false;
			
			foreach (split(' ',$state_list) as $istate){
				if ($state==$istate){
					$res[$istate] = $checked;
					$hit=true;
					$break;
				}
			}
		
			(!$hit) ? $res["$default"] = $checked : false;
		
		return $res;
	}

	//plugin qualified?
	function mailonupdate_pqual($plugin, $plugin_file)
	{
		$plugin			= strtolower($plugin);
		$filtermethod 	= $this->mou_filtermethod;
	
		if ($filtermethod == 'nolist')
			return true;
	
		if ($thsis->mou_exclinact != '' && !is_plugin_active($plugin_file))
			return false;
		
		($filtermethod=='whitelist') ? $state  =false : $state = true;
		
		foreach (split("\n",$this->mou_filter) as $filter) {
			$filter=trim(strtolower($filter));
			if (!empty($filter)){
				if (strpos($filter,-1)!='-') {
					if (!(strpos($plugin,$filter)===false)){
						$state=!$state;
						break;					
					}
				}
			}
		}
	
		return $state;
	}

	//show qualified plugins
	function mailonupdate_qualp()
	{
		$all_plugins = get_plugins();
		$del		 = '';
		foreach( (array)$all_plugins as $plugin_file => $plugin_data) {	
			$plugin=wp_kses($plugin_data['Title'],array());
			if ($plugin!="") {
				(is_plugin_active($plugin_file)) ? $inact='' : $inact='-';
				($this->mailonupdate_pqual($plugin, $plugin_file)) ? $flag='[x]' : $flag='[ ]';
				
				$l 	.= "$del$flag$plugin$inact";
				$del = "\n";
			};		
		};
	
		return $l;
	} 

	//the configuration page 
	function mailonupdateConf()
	{
		if (!current_user_can('manage_options'))
			wp_die(__('Sorry, but you have no permissions to change settings.','mail-on-update'));
			
		if (isset($_POST['submit'])){
			if (isset($_POST['mailonupdate_mailto']))
	        	$this->mou_mailto = $this->mailonupdate_validateRecipient($_POST['mailonupdate_mailto'], "\n");
	
			if (isset( $_POST['mailonupdate_filter'])){
	            $this->mou_filter 			= $_POST['mailonupdate_filter'];
				$this->mou_filtermethod 	= $_POST['mailonupdate_filtermethod']; 
				$this->mou_exclinact		= $_POST['mailonupdate_exclinact'];
			};
			
			$this->updateOptions();
			echo '<div id="message" class="updated fade"><p><strong>'. __('Mail On Update settings succsesfully saved.', 'mail-on-update') .'</strong></p></div>';
		};
		
		?>
		
		<div class="wrap">
			<div id="icon-options-general" class="icon32"></div>
			<h2><?php echo __('Mail On Update Settings', 'mail-on-update'); ?></h2>
		
			<div id="poststuff" class="ui-sortable">
				<div class="postbox opened">
					<h3><?php echo __('List of alternative recipients', 'mail-on-update'); ?></h3>
					<div class="inside">
						<form action="options-general.php?page=mail on update" method="post" id="mailonupdate-conf">
					    <table class="form-table">
					    	<tr>
								<td colspan="2">
								<?php 
								
								if ($this->mailonupdate_validateRecipient($this->mou_mailto,',') == '') {
								
									echo "<p>";
									printf (__('Since no alternative recipients are specified, the default address %s is assumed. Provide a list of alternative recipients to override.'
										,'mail-on-update')
						            	, '<b>'.get_option("admin_email").'</b>'
						            );
						            
									echo "</p>";
								}
								
								?>
							</tr>
							<tr>	
								<td width="10"><textarea id="mailonupdate_mailto" name="mailonupdate_mailto" cols="40" rows="5"><?php echo $this->mou_mailto; ?></textarea></td>
								<td valign="top">
								<?php echo __('* Each E-Mail-Address has to appear on a single line', 'mail-on-update'); ?><br />
								<?php echo __('* Invalid E-Mail-Addresses will be rejected', 'mail-on-update'); ?><br />
	        					<?php echo __('* An E-Mail-Address with "-" at the end is not considered', 'mail-on-update'); ?><br />
								<?php echo __('* Clear this field to set the default E-Mail-Address', 'mail-on-update'); ?>
								</td>
							</tr>
						</table>
						<p class="submit"><input type="submit" class='button-primary' name="submit" value="<?php echo __('Save', 'mail-on-update'); ?>" /></p>	
						</form>				
					</div>
				</div>
			</div>	
			
			<div id="poststuff" class="ui-sortable">
				<div class="postbox opened">
					<h3><?php echo __('Filters', 'mail-on-update'); ?></h3>
					<div class="inside">
						<form action="options-general.php?page=mail on update" method="post" id="mailonupdate-conf">
					    <table class="form-table">
							<tr>
								<td width="10"><textarea id="mailonupdate_filter" name="mailonupdate_filter" cols="40" rows="5"><?php echo $this->mou_filter; ?></textarea></td>
								<td valign="top">
								<?php echo __('* A plugin is matched if the filter is a substring', 'mail-on-update'); ?><br />
								<?php echo __('* A filter has to appear on a single line', 'mail-on-update'); ?><br />
								<?php echo __('* A filter is not case sensetive', 'mail-on-update'); ?><br />
								<?php echo __('* A filter is considered as a string and no regexp', 'mail-on-update'); ?><br />		
								<?php echo __('* A filter with "-" at the end is not considered', 'mail-on-update'); ?>								
								<?php $rval = $this->rbc('mailonupdate_filtermethod','nolist blacklist whitelist','nolist'); ?>
								</td> 
							</tr>
							<tr>
								<td valign="top">
	                			<input type="radio" name="mailonupdate_filtermethod" value="nolist" <?php print $rval['nolist']; ?>" /> <?php echo __('Don\'t filter plugins', 'mail-on-update'); ?><br />
	                			<input type="radio" name="mailonupdate_filtermethod" value="blacklist" <?php print $rval['blacklist']; ?>" /> <?php echo __('Blacklist filter (exclude plugins)', 'mail-on-update'); ?><br />
	                			<input type="radio" name="mailonupdate_filtermethod" value="whitelist" <?php print $rval['whitelist']; ?>" /> <?php echo __('Whitelist filter (include plugins)', 'mail-on-update'); ?><br />
	                			<input type="checkbox" name="mailonupdate_exclinact" value="checked" <?php print $this->mou_exclinact; ?> /> <?php echo __('Don\'t validate inactive plugins', 'mail-on-update'); ?>							
								</td>
							</tr>
						</table>	
						<p class="submit"><input type="submit" class='button-primary' name="submit" value="<?php echo __('Save', 'mail-on-update'); ?>" /></p>
						</form>				
					</div>
				</div>
			</div>

			<div id="poststuff" class="ui-sortable">
				<div class="postbox opened">
					<h3><?php echo __('Plugins to validate', 'mail-on-update'); ?></h3>
					<div class="inside">
					    <table class="form-table">
					    	<tr>
								<td width="10"><textarea id="mailonupdate_pluginmonitor" name="mailonupdate_pluginmonitor" readonly="readonly" cols="40" rows="5" /><?php print $this->mailonupdate_qualp(); ?></textarea></td>
								<td valign="top" align="left">
								[x] <?php echo __('Plugin will be validated', 'mail-on-update'); ?><br />
								[ ] <?php echo __('Plugin will not be validated', 'mail-on-update'); ?><br />
								"-" <?php echo __('Inactive plugin', 'mail-on-update'); ?>								
								</td>
							</tr>
						</table>			
					</div>
				</div>
			</div>			
			
		</div>				
	
	<?php

	}
}
//initallze class
if (class_exists('MailOnUpdate'))
	$mou = new MailOnUpdate();
?>