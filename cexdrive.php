<?php
/*
Plugin Name: Google Drive Folder Display
Plugin URI: https://github.com/ChaosExAnima/Google-Drive-WP-Plugin
Description: A plugin to authenticate with Google Drive in order to display files and folders.
Author: Ephraim Gregor
Version: 1.1
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
function cexdrive_display()
{
	$token = get_option('cexdrive-token');
	$folder = get_option('cexdrive-folder');
	
	// Make sure we're set up first.
	if( empty($token) || empty($folder) )
	{
		return;
	}
	
	$client = cexdrive_load_lib();
	
	$service = new Google_DriveService($client);
	
	$client->setAccessToken($token); 
	
	$params = array(
		'q' => "mimeType != 'application/vnd.google-apps.folder' and '{$folder}' in parents"
	);
		
	$files = $service->files->listFiles($params);
	
	$text = '<ul class="cexdrive" style="list-style-type:none;">';
	
	foreach($files->items as $file)
	{
		$text .= '<li><img src="' . $file->iconLink . '" alt="Icon"> <a href="' . $file->embedLink . '" target="_blank">' . $file->title . '</a></li>';
	}
	
	$text .= '</ul>';
	
	return $text;
}

// Adds the shortcode hook
add_shortcode('gdrive', 'cexdrive_display');

/**
 * Creates the menu page.
 */
function cexdrive_create_menu() 
{
	// Creates a new menu under settings
	add_options_page('Google Drive Folder Display Settings', 'Google Drive Display', 'administrator', __FILE__, 'cexdrive_settings_page');

	//call register settings function
	add_action( 'admin_init', 'cexdrive_register_settings' );
}

add_action('admin_menu', 'cexdrive_create_menu');


/**
 * Registers the settings groups.
 */
function cexdrive_register_settings() 
{
	//register our settings
	register_setting( 'cexdrive-settings-group', 'cexdrive-token' );
	register_setting( 'cexdrive-settings-group', 'cexdrive-folder' );
}


/**
 * Initializes the Google SDK.
 * 
 * @param string $url The URL to redirect to.
 * @return object The Google Client object.
 */
function cexdrive_load_lib($url = '')
{
	require_once plugin_dir_path(__FILE__).'lib/Google_Client.php';
	require_once plugin_dir_path(__FILE__).'lib/contrib/Google_DriveService.php';
	
	$client = new Google_Client();
	
	$client->setRedirectUri($url);
	$client->setScopes(array('https://www.googleapis.com/auth/drive'));
	$client->setUseObjects(true);
	
	return $client;
}


/**
 * Displays the settings page.
 */
function cexdrive_settings_page() 
{
	
?>
<div class="wrap">
<h2>MET Google Drive</h2>
<?php 

$url = admin_url( '/options-general.php?page=' . basename(__DIR__) . '/cexdrive.php' ); // Sets the redirect URL

try 
{
	$client = cexdrive_load_lib($url); // Load the libraries

	$service = new Google_DriveService($client); // Create a new Drive service
}
catch(Exception $e)
{
?>
	<div class="error"><p><strong>There has been an error loading initializing the required libraries.</strong></p></div>
	<p>Information for the developer:</p>
	<pre><?php print_r($e); ?></pre>
<?php	
}

$token = get_option('cexdrive-token');
$folder = get_option('cexdrive-folder');

if( isset($_POST['folder']) )
{
	$folder = $_POST['folder'];
	update_option( 'cexdrive-folder', $folder );
}

if( empty($token) && isset($_GET['code']) )
{
	$token = $_GET['code'];
	$accessToken = $client->authenticate($token);
	update_option( 'cexdrive-token', $accessToken );
	
	$client->setAccessToken($accessToken);
	
	$dir_params = array(
		'q' => "mimeType = 'application/vnd.google-apps.folder'"
	);
		
	$dirs = $service->files->listFiles($dir_params);
?>
	<p>Next, we need to pick which folder you want to display on the front end. Check your desired folder below and hit submit:</p>
	<form action="<?php echo $url; ?>" method="post">
		<?php foreach($dirs->items as $dir): ?>
			<p><label><input type="radio" name="folder" value="<?php echo $dir->id; ?>"> <?php echo $dir->title; ?></label></p>
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
	<p>To use the plugin, put the <code>[gdrive]</code> shortcode in a page or post. Make sure you are sharing all the files to the public, or people shall be confused...!</p>
	<p>You are currently showing the folder "<strong><?php echo $current->title; ?></strong>". To change that, check your desired folder below and hit submit:</p>
<?php endif; ?>
	<form action="<?php echo $url; ?>" method="post">
		<?php foreach($dirs->items as $dir): ?>
			<p><label><input type="radio" name="folder" value="<?php echo $dir->id; ?>" <?php echo ($dir->id == $folder) ? 'checked="checked"' : ''; ?>> <?php echo $dir->title; ?></label></p>
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
