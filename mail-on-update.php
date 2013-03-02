<?php
/*
 Plugin Name: Mail On Update
Plugin URI: http://www.svenkubiak.de/mail-on-update
Description: Sends an E-Mail Notification to one or multiple E-Mail-Addresses if new versions of plugins are available.
Version: 5.0.0
Author: Sven Kubiak, Matthias Kindler, Heiko Adams
Author URI: http://www.svenkubiak.de
Donate link: https://flattr.com/thing/7653/Mail-On-Update-WordPress-Plugin

Copyright 2008-2013 Sven Kubiak, Matthias Kindler, Heiko Adams

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
define('MOUISWP30', version_compare($wp_version, '3.0', '>='));
define('MOUISWP33', version_compare($wp_version, '3.3', '>='));
define('WPVER', $wp_version);

if (!class_exists('MailOnUpdate'))
{
	class MailOnUpdate {

		var $mou_lastchecked;
		var $mou_lastmessage;
		var $mou_singlenotification;
		var $mou_mailto;
		var $mou_exclinact;
		var $mou_filtermethod;
		var $mou_filter;

		function mailonupdate() {
			//load language file
			if (function_exists('load_plugin_textdomain')) {
				load_plugin_textdomain('mail-on-update', false, dirname( plugin_basename( __FILE__ ) ) );
			}
			//is wordpress at least version 3.0?
			if (!MOUISWP30) {
				add_action('admin_notices', array(&$this, 'wpVersionFailed'));
				return false;
			}

			//load nospamnx options
			$this->getOptions();
				
			//add wordpress aktions
			add_action('wp_footer', array(&$this, 'checkPlugins'));
			add_action('admin_menu', array(&$this, 'mouAdminMenu'));
				
			//tell wp what to do when plugin is activated and deactivated
			if (function_exists('register_activation_hook')) {
				register_activation_hook(__FILE__, array(&$this, 'activate'));
			}
			if (function_exists('register_uninstall_hook')) {
				register_uninstall_hook(__FILE__, 'deactivate');
			}
			if (function_exists('register_deactivation_hook')) {
				register_deactivation_hook(__FILE__, array(&$this, 'deactivate'));
			}
		}

		function activate() {
			$options = array(
					'mou_lastchecked' 	=> 0,
					'mou_singlenotification' => '',
					'mou_lastmessage'	=> '',
					'mou_mailto'		=> '',
					'mou_exclinact'		=> '',
					'mou_filtermethod'	=> '',
					'mou_filter'		=> '',
			);
			add_option('mailonupdate', $options, '', 'yes');
			$this->checkPlugins();
		}

		static function deactivate() {
			delete_option('mailonupdate');
		}

		function getOptions() {
			$options = get_option('mailonupdate');
				
			$this->mou_lastchecked 	= $options['mou_lastchecked'];
			$this->mou_lastmessage = $options['mou_lastmessage'];
			$this->mou_singlenotification = $options['mou_singlenotification'];
			$this->mou_mailto		= $options['mou_mailto'];
			$this->mou_exclinact	= $options['mou_exclinact'];
			$this->mou_filtermethod	= $options['mou_filtermethod'];
			$this->mou_filter		= $options['mou_filter'];
		}

		function setOptions() {
			$options = array(
					'mou_lastchecked'	=> $this->mou_lastchecked,
					'mou_lastmessage' => $this->mou_lastmessage,
					'mou_singlenotification' => $this->mou_singlenotification,
					'mou_mailto'		=> $this->mou_mailto,
					'mou_exclinact'		=> $this->mou_exclinact,
					'mou_filtermethod'	=> $this->mou_filtermethod,
					'mou_filter'		=> $this->mou_filter,
			);
				
			update_option('mailonupdate', $options);
		}

		function wpVersionFailed() {
			echo "<div id='message' class='error fade'><p>".__('Your WordPress is to old. Mail On Update requires at least WordPress 3.0!','mail-on-update')."</p></div>";
		}

		function checkPlugins() {
			//is last check more than 12 hours ago?
			if (!WP_DEBUG and time() < $this->mou_lastchecked + 43200) {
				return false;
			}
				
			//inlcude wordpress update functions
			@require_once ( ABSPATH . 'wp-admin/includes/update.php' );
			@require_once ( ABSPATH . 'wp-admin/admin-functions.php' );

			//call the wordpress update function
			if (MOUISWP30) {
				wp_plugin_update_rows();
				$updates = get_site_transient('update_plugins');

				wp_theme_update_rows();
				$themes = get_site_transient('update_themes');
			}
			else {
				wp_update_plugins();
				$updates = get_transient('update_plugins');

				wp_update_themes();
				$themes = get_site_transient('update_themes');
			}

			$update_wordpress = get_core_updates( array('dismissed' => false, 'available' => true));
			//are plugin or theme updates available?
			if (empty($updates->response) and empty($themes) and empty( $update_wordpress )){
				return false;
			}

			//get all plugin
			$plugins = get_plugins();
			$blogname = get_option('blogname');
			$message  = '';
			$pluginNotVaildated = '';
				
			//loop through available plugin updates
			foreach ($updates->response as $pluginfile => $update) {
				if ($this->mailonupdate_pqual($plugins[$pluginfile]['Name'], $pluginfile)) {
					//append available updates to notification message
					$message .= sprintf( __('A new version of %1$s is available.', 'mail-on-update'), trim($plugins[$pluginfile]['Name']));
					$message .= "\n";
					$message .= sprintf( __('- Installed: %1$s, Current: %2$s', 'mail-on-update'), $plugins[$pluginfile]['Version'], $update->new_version);
					$message .= "\n\n";
				}
				else {
					(is_plugin_active($pluginfile)) ? $act = __('active', 'mail-on-update') : $act = __('inactive', 'mail-on-update');
					$pluginNotVaildated .= "\n".sprintf( __('A new version (%1$s) of %2$s is available. (%3s)', 'mail-on-update'), $update->new_version, $plugins[$pluginfile]['Name'], $act);
				};
			}
				
			//loop through available theme updates
			foreach ($themes->response as $theme => $update) {
				if ($this->mailonupdate_pqual($themes[$theme]['Name'], $theme)) {
					//append available updates to notification message
					$message .= sprintf( __('A new version of %1$s is available.', 'mail-on-update'), trim($themes[$theme]['Name']));
					$message .= "\n";
					$message .= sprintf( __('- Installed: %1$s, Current: %2$s', 'mail-on-update'), $themes[$theme]['Version'], $update->new_version);
					$message .= "\n\n";
				}
				else {
					(is_plugin_active($theme)) ? $act = __('active', 'mail-on-update') : $act = __('inactive', 'mail-on-update');
					$pluginNotVaildated .= "\n".sprintf( __('A new version (%1$s) of %2$s is available. (%3s)', 'mail-on-update'), $update->new_version, $themes[$theme]['Name'], $act);
				};
			}

			if (MOUISWP28 && !empty( $update_wordpress ) && ! in_array( $update_wordpress[0]->response, array('development', 'latest'))){
				$message .= sprintf( __('A new version of %1$s is available.', 'mail-on-update'), 'WordPress');
				$message .= "\n";
				$message .= sprintf( __('- Installed: %1$s, Current: %2$s', 'mail-on-update'), WPVER, $update_wordpress[0]->current);
				$message .= "\n\n";
			}

			if ($message!='' && ($this->mou_singlenotification == '' || ($message != $this->mou_lastmessage && $this->mou_singlenotification != ''))) {
				$this->mou_lastmessage = $message;

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
				$subject = sprintf(__('[%s] Update Notification','mail-on-update'), $blogname);
				wp_mail($this->mailonupdate_listOfCommaSeparatedRecipients(), $subject, $message, $headers);
			};
				
			//set timestamp of last update check
			$this->mou_lastchecked = time();
			$this->setOptions();
		}

		function mouAdminMenu() {
			add_options_page('Mail On Update', 'Mail On Update', 'manage_options', 'mail-on-update', array(&$this, 'mailonupdateConf'));
		}

		//$sep=="\n"	:return qualified mail addresses for the form field
		//$sep!="\n"	:return a $sep separated list of qualified mail addresses which are not disabled (by '-' at the end of the mail address)
		function mailonupdate_validateRecipient($maillist,$sep) {
			$nmaillist = '';
			$hit=0;
			foreach (split("\n",$maillist) as $imail) {
				$mail=trim($imail);

				if ( preg_match("/^[a-z&auml;&ouml;&uuml;A-Z&Auml;&Ouml;&Uuml;0-9_.-]+@[a-z&auml;&ouml;&uuml;A-Z&Auml;&Ouml;&Uuml;0-9-]+.[a-z&auml;&auml;&uuml;A-Z&Auml;&Ouml;&Uuml;0-9-.]+\-{0,1}$/",$mail) ) {
					if ($sep=="\n" || substr($mail,-1)!='-' ) {
						if ($hit>0) {
							$nmaillist.=$sep;
						};
						if($this->mailonupdate_isAllowedReceipent($mail)){
							$nmaillist.=$mail;
							$hit++;
						}
					};
				};
			};

			return $nmaillist;
		}

		function  mailonupdate_isAllowedReceipent($mail){
			$user = get_user_by('email', $mail);

			if(is_object($user)){
				if(MOUISWP30){
					return user_can($user, 'update_core') or user_can($user, 'update_plugins') or user_can($user, 'update_themes');
				} else {
					return user_can($user, 'update_plugins') or user_can($user, 'update_themes');
				}
			} else {
				return false;
			}
		}

		//notifier list
		function mailonupdate_listOfCommaSeparatedRecipients() {
			$list = $this->mailonupdate_validateRecipient($this->mou_mailto,',');

			if ("$list" !='') {
				return $list;
			} else {
				return get_option("admin_email");
			}
		}

		//radio button check
		function rbc($option,$state_list,$default) {
			$checked = 'checked="checked"';
			$state = $this->mou_filtermethod;
			$hit = false;
			$res = array();

			foreach (explode(' ',$state_list) as $istate){
				if ($state==$istate){
					$res[$istate] = $checked;
					$hit=true;
					break;
				}
			}

			(!$hit) ? $res["$default"] = $checked : false;

			return $res;
		}

		//plugin qualified?
		function mailonupdate_pqual($plugin, $plugin_file) {
			$plugin			= strtolower($plugin);
			$filtermethod 	= $this->mou_filtermethod;

			if ($filtermethod == 'nolist') {
				return true;
			}

			if ($this->mou_exclinact != '' && !is_plugin_active($plugin_file)) {
				return false;
			}
				
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
		function mailonupdate_qualp() {
			$l = '';
			$all_plugins = get_plugins();
			$del		 = '';
			foreach( (array)$all_plugins as $plugin_file => $plugin_data) {
				$plugin=wp_kses($plugin_data['Title'],array());
				if ($plugin!="") {
					(is_plugin_active($plugin_file)) ? $inact='' : $inact=" (".__('inactive', 'mail-on-update').")";
					($this->mailonupdate_pqual($plugin, $plugin_file)) ? $flag='[x] ' : $flag='[ ] ';
						
					$l 	.= "$del$flag$plugin$inact";
					$del = "\n";
				};
			};

			return $l;
		}

		//the configuration page
		function mailonupdateConf() {
			if (!current_user_can('manage_options')) {
				wp_die(__('Sorry, but you have no permissions to change settings.','mail-on-update'));
			}

			if (isset($_POST['submit'])){
				if (isset($_POST['mailonupdate_mailto'])) {
					$this->mou_mailto = $this->mailonupdate_validateRecipient($_POST['mailonupdate_mailto'], "\n");
				}
				$this->mou_singlenotification = $_POST['mailonupdate_singlenotification'];

				if (isset( $_POST['mailonupdate_filter'])){
					$this->mou_filter 			= $_POST['mailonupdate_filter'];
					$this->mou_filtermethod 	= $_POST['mailonupdate_filtermethod'];
					$this->mou_exclinact		= $_POST['mailonupdate_exclinact'];
				};

				$this->setOptions();
				echo '<div id="message" class="updated fade"><p><strong>'. __('Mail On Update settings succsesfully saved.', 'mail-on-update') .'</strong></p></div>';
			};
				
			?>

<div class="wrap">
	<div id="icon-options-general" class="icon32"></div>
	<h2>
		<?php echo __('Mail On Update Settings', 'mail-on-update'); ?>
	</h2>

	<div id="poststuff" class="ui-sortable">
		<div class="postbox opened">
			<h3>
				<?php echo __('List of alternative recipients', 'mail-on-update'); ?>
			</h3>
			<div class="inside">
				<form action="options-general.php?page=mail-on-update" method="post"
					id="mailonupdate-conf">
					<table class="form-table">
						<tr>
							<td><?php 
			
							if ($this->mailonupdate_validateRecipient($this->mou_mailto,',') == '') {
			
										echo "<p>";
										printf (__('Since no alternative recipients are specified, the default address %s is assumed. Provide a list of alternative recipients to override.'
		,'mail-on-update')
		, '<b>'.get_option("admin_email").'</b>'
		);
											
										echo "</p>";
									}

									?></td>
							<td><script type="text/javascript">
										/* <![CDATA[ */
										    (function() {
										        var s = document.createElement('script'), t = document.getElementsByTagName('script')[0];
										        s.type = 'text/javascript';
										        s.async = true;
										        s.src = 'http://api.flattr.com/js/0.6/load.js?mode=auto';
										        t.parentNode.insertBefore(s, t);
										    })();
										/* ]]> */
									</script> <a class="FlattrButton" style="display: none;"
								href="http://www.svenkubiak.de/mail-on-update/"></a>
							</td>
						</tr>
						<tr>
							<td width="10"><textarea id="mailonupdate_mailto"
									name="mailonupdate_mailto" cols="40" rows="5">
									<?php echo $this->mou_mailto; ?>
								</textarea></td>
							<td valign="top"><?php echo __('* Each E-Mail-Address has to appear on a single line', 'mail-on-update'); ?><br />
								<?php echo __('* Invalid E-Mail-Addresses will be rejected', 'mail-on-update'); ?><br />
								<?php echo __('* An E-Mail-Address with "-" at the end is not considered', 'mail-on-update'); ?><br />
								<?php echo __('* Clear this field to set the default E-Mail-Address', 'mail-on-update'); ?>
							</td>
						</tr>
						<tr>
							<td valign="top"><label><input type="checkbox"
									name="mailonupdate_singlenotification" value="checked"
									<?php print $this->mou_singlenotification; ?> /> <?php echo __('Send only one notification per Update', 'mail-on-update'); ?>
							</label>
							</td>
						</tr>
					</table>
					<p class="submit">
						<input type="submit" class='button-primary' name="submit"
							value="<?php echo __('Save', 'mail-on-update'); ?>" />
					</p>
				</form>
			</div>
		</div>
	</div>

	<div id="poststuff" class="ui-sortable">
		<div class="postbox opened">
			<h3>
				<?php echo __('Filters', 'mail-on-update'); ?>
			</h3>
			<div class="inside">
				<form action="options-general.php?page=mail-on-update" method="post"
					id="mailonupdate-conf">
					<table class="form-table">
						<tr>
							<td width="10"><textarea id="mailonupdate_filter"
									name="mailonupdate_filter" cols="40" rows="5">
									<?php echo $this->mou_filter; ?>
								</textarea></td>
							<td valign="top"><?php echo __('* A plugin is matched if the filter is a substring', 'mail-on-update'); ?><br />
								<?php echo __('* A filter has to appear on a single line', 'mail-on-update'); ?><br />
								<?php echo __('* A filter is not case sensetive', 'mail-on-update'); ?><br />
								<?php echo __('* A filter is considered as a string and no regexp', 'mail-on-update'); ?><br />
								<?php echo __('* A filter with "-" at the end is not considered', 'mail-on-update'); ?>
								<?php $rval = $this->rbc('mailonupdate_filtermethod','nolist blacklist whitelist','nolist'); ?>
							</td>
						</tr>
						<tr>
							<td valign="top"><input type="radio"
								name="mailonupdate_filtermethod" value="nolist"
								<?php print $rval['nolist']; ?> /> <?php echo __('Don\'t filter plugins', 'mail-on-update'); ?><br />
								<input type="radio" name="mailonupdate_filtermethod"
								value="blacklist" <?php print $rval['blacklist']; ?> /> <?php echo __('Blacklist filter (exclude plugins)', 'mail-on-update'); ?><br />
								<input type="radio" name="mailonupdate_filtermethod"
								value="whitelist" <?php print $rval['whitelist']; ?> /> <?php echo __('Whitelist filter (include plugins)', 'mail-on-update'); ?><br />
								<input type="checkbox" name="mailonupdate_exclinact"
								value="checked" <?php print $this->mou_exclinact; ?> /> <?php echo __('Don\'t validate inactive plugins', 'mail-on-update'); ?>
							</td>
						</tr>
					</table>
					<p class="submit">
						<input type="submit" class='button-primary' name="submit"
							value="<?php echo __('Save', 'mail-on-update'); ?>" />
					</p>
				</form>
			</div>
		</div>
	</div>

	<div id="poststuff" class="ui-sortable">
		<div class="postbox opened">
			<h3>
				<?php echo __('Plugins to validate', 'mail-on-update'); ?>
			</h3>
			<div class="inside">
				<table class="form-table">
					<tr>
						<td><textarea id="mailonupdate_pluginmonitor"
								name="mailonupdate_pluginmonitor" class="large-text code"
								readonly="readonly" cols="50" rows="10">
								<?php print $this->mailonupdate_qualp(); ?>
							</textarea></td>
					</tr>
					<tr>
						<td>[x] <?php echo __('Plugin will be validated', 'mail-on-update'); ?><br />
							[ ] <?php echo __('Plugin will not be validated', 'mail-on-update'); ?><br />
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
	$mou = new MailOnUpdate();
}
?>