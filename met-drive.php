<?php
/*
Plugin Name: MET Google Drive
Plugin URI: http://www.mindseyesociety.org
Description: A plugin to authenticate with Google Drive in order to display files and folders.
Author: Ephraim Gregor
Version: 1.0
Author URI: http://ephraimgregor.com/
License: GPL3
*/

/*  
Copyright 2013  Ephraim Gregor  (email : ephraim@ephraimgregor.com)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


/**
 * Loads and displays the files from Google Drive using the shortcode.
 */
function metdrive_display()
{
	$token = get_option('metdrive-token');
	$folder = get_option('metdrive-folder');
	
	// Make sure we're set up first.
	if( empty($token) || empty($folder) )
	{
		return;
	}
	
	require_once plugin_dir_path(__FILE__).'lib/Google_Client.php';
	require_once plugin_dir_path(__FILE__).'lib/contrib/Google_DriveService.php';
	
	$client = new Google_Client();
	
	$client->setClientId('895728636120-il1j8fajf720s6d8q7apq4dfc1ks18d1.apps.googleusercontent.com');
	$client->setClientSecret('AMmBSg_3yMpIoCIsRMppRRY7');
	$client->setRedirectUri( admin_url('/options-general.php?page=met-drive%2Fmet-drive.php') );
	$client->setScopes(array('https://www.googleapis.com/auth/drive'));
	$client->setUseObjects(true);
	
	$service = new Google_DriveService($client);
	
	$client->setAccessToken($token); 
	
	$params = array(
		'q' => "mimeType != 'application/vnd.google-apps.folder' and '{$folder}' in parents"
	);
		
	$files = $service->files->listFiles($params);
	
	$text = '<ul class="metdrive" style="list-style-type:none;">';
	
	foreach($files->items as $file)
	{
		$text .= '<li><img src="' . $file->iconLink . '" alt="Icon"> <a href="' . $file->embedLink . '" target="_blank">' . $file->title . '</a></li>';
	}
	
	$text .= '</ul>';
	
	return $text;
}

// Adds the shortcode hook
add_shortcode('metdrive', 'metdrive_display');

/**
 * Creates the menu page.
 */
function metdrive_create_menu() 
{
	// Creates a new menu under settings
	add_options_page('MET Google Drive Settings', 'MET Google Drive', 'administrator', __FILE__, 'metdrive_settings_page');

	//call register settings function
	add_action( 'admin_init', 'metdrive_register_settings' );
}

add_action('admin_menu', 'metdrive_create_menu');


/**
 * Registers the settings groups.
 */
function metdrive_register_settings() 
{
	//register our settings
	register_setting( 'metdrive-settings-group', 'metdrive-token' );
	register_setting( 'metdrive-settings-group', 'metdrive-folder' );
}


/**
 * Displays the settings page.
 */
function metdrive_settings_page() 
{
	require_once plugin_dir_path(__FILE__).'lib/Google_Client.php';
	require_once plugin_dir_path(__FILE__).'lib/contrib/Google_DriveService.php';
	
	$client = new Google_Client();
	
	$client->setClientId('895728636120-il1j8fajf720s6d8q7apq4dfc1ks18d1.apps.googleusercontent.com');
	$client->setClientSecret('AMmBSg_3yMpIoCIsRMppRRY7');
	$client->setRedirectUri( admin_url('/options-general.php?page=met-drive%2Fmet-drive.php') );
	$client->setScopes(array('https://www.googleapis.com/auth/drive'));
?>
<div class="wrap">
<h2>MET Google Drive</h2>
<?php 

$service = new Google_DriveService($client);

$token = get_option('metdrive-token');
$folder = get_option('metdrive-folder');

if( isset($_POST['folder']) )
{
	$folder = $_POST['folder'];
	update_option( 'metdrive-folder', $folder );
}

if( empty($token) && isset($_GET['code']) )
{
	$token = $_GET['code'];
	$accessToken = $client->authenticate($token);
	update_option( 'metdrive-token', $accessToken );
	
	$client->setAccessToken($accessToken);
	
	$dir_params = array(
		'q' => "mimeType = 'application/vnd.google-apps.folder'"
	);
		
	$dirs = $service->files->listFiles($dir_params);
?>
	<p>Next, we need to pick which folder you want to display on the front end. Check your desired folder below and hit submit:</p>
	<form action="<?php echo admin_url('/options-general.php?page=met-drive%2Fmet-drive.php'); ?>" method="post">
		<?php foreach($dirs['items'] as $dir): ?>
			<p><label><input type="radio" name="metdrive-folder" value="<?php echo $dir['id']; ?>"> <?php echo $dir['title']; ?></label></p>
		<?php endforeach; ?>
		<?php echo submit_button(); ?>
	</form>
<?php 
}
elseif( empty($token) ) { // We don't have the token stored!
?>
	<p>You need to authenticate with Google before using this plugin:</p>
	<p><a href="<?php echo $client->createAuthUrl(); ?>">Click here to authenticate.</a></p>
<?php 
} 
else
{
	$client->setAccessToken($token); 
	
	$dir_params = array(
		'q' => "mimeType = 'application/vnd.google-apps.folder'"
	);
		
	$dirs = $service->files->listFiles($dir_params);
	
	if($folder)
	{
		$current = $service->files->get($folder);
	}	
?>
<?php if( empty($folder) ): ?>
	<p>Next, we need to pick which folder you want to display on the front end. Check your desired folder below and hit submit:</p>
<?php else: ?>
	<p>To use the plugin, put the <code>[metdrive]</code> shortcode in a page or post.</p>
	<p>You are currently showing the folder "<strong><?php echo $current['title']; ?></strong>". To change that, check your desired folder below and hit submit:</p>
<?php endif; ?>
	<form action="<?php echo admin_url('/options-general.php?page=met-drive%2Fmet-drive.php'); ?>" method="post">
		<?php foreach($dirs['items'] as $dir): ?>
			<p><label><input type="radio" name="folder" value="<?php echo $dir['id']; ?>" <?php echo ($dir['id'] == $folder) ? 'checked="checked"' : ''; ?>> <?php echo $dir['title']; ?></label></p>
		<?php endforeach; ?>
		<?php echo submit_button(); ?>
	</form>
<?php 	
} 
?>
</div>
<?php 
}
?>
