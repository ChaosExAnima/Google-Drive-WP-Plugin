<?php
/*
Plugin Name: Google Drive Folder Display
Plugin URI: https://github.com/ChaosExAnima/Google-Drive-WP-Plugin
Description: A plugin to authenticate with Google Drive in order to display files and folders.
Author: Ephraim Gregor
Version: 1.2
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

// The Admin pages
require "cexadmin.php";


/**
 * Loads and displays the files from Google Drive using the shortcode.
 */
function cexdrive_display($attrs)
{
	// Make sure we have both attributes
	if( !isset($attrs['email']) || !isset($attrs['folder']) )
    {
        return;
    }
    
    $email = $attrs['email'];
    $folder = $attrs['folder'];
    
	$settings = cexdrive_get_config();
    
    // Make sure we have this account enabled
    if( !array_key_exists($email, $settings) )
    {
        return;
    }
    
    $user = $settings[$email];
    
    // Get the access token
    try
    {
        $client = cexdrive_load_lib();
        $service = new Google_DriveService($client);
        $client->setAccessToken($user['token']);     
    }
    catch(exception $e)
    {
        // Silently fail and log the error if it all goes to hell
        error_log($e);
        return;
    }
    
    // Get the folder id
    $folder_id = isset( $user['folders'][$folder] ) ? $user['folders'][$folder] : FALSE;
    
    // Check if we're provided the subfolder ID in the url
    if( isset($_GET['folder']) )
    {
        $folder_id = $_GET['folder'];
    }
    
    if(!$folder_id)
    {
        $params = array(
          'q' => "mimeType = 'application/vnd.google-apps.folder' and title = '{$folder}'"  
        );
        
        $response = $service->files->listFiles($params);
        
        // Did we get a match?
        if( count($response->items) > 0 )
        {
            $folder_id = $response->items[0]->id;
            
            $user['folders'][$folder] = $folder_id;
            
            cexdrive_set_config( array( $email => $user ) );
        }
        else
        {
            // Exit if there's no folder by that name
            error_log("CEXDrive: Could not find a folder by the name '{$folder}'.");
            return;
        }
    }
    	
	$params = array(
		'q' => "'{$folder_id}' in parents"
	);
		
	$files = $service->files->listFiles($params);
	
    $text = '<ul class="cexdrive" style="list-style-type:none;">';
	
	foreach($files->items as $file)
	{
	    // If this is a subfolder, set the embed link to something else
	    $target = '_blank';
	    if($file->mimeType == 'application/vnd.google-apps.folder')
        {
            $file->embedLink = cexdrive_subfolder_url($file->id);
            $target = '_self';
        }
        
		$text .= '<li><img src="' . $file->iconLink . '" alt="Icon"> <a href="' . $file->embedLink . '" target="' . $target . '">' . $file->title . '</a></li>';
	}
	
	$text .= '</ul>';
	
    // If no files are found
    if( count($files->items) == 0 )
    {
        $text = '<p class="cexdrive"><em>No files are found.</em></p>';
    }
         
    // Add a "Go Back" link 
    if( isset($_GET['folder']) )
    {
        $text .= '<p><a href="' . cexdrive_subfolder_url() . '">Click here to go back.</a></p>';
    }    
        
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
	register_setting( 'cexdrive-settings-group', 'cexdrive-config' );
}


/**
 * Gets a subfolder access URL.
 * 
 * @param string $id The subfolder id to access.
 * @return string The URL
 */
function cexdrive_subfolder_url($id = FALSE)
{
    if($id !== FALSE)
    {
        $query = '?' . http_build_query( array_merge($_GET, array('folder' => $id)) );
    }
    else
    {
        $query = '';
    }
    
    $url = empty($_SERVER['HTTPS']) ? 'http://' : 'https://';
    $url .= $_SERVER["HTTP_HOST"] . parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
    $url .= $query;
        
    return $url;
}


/**
 * Parses the registered options.
 * 
 * @return array|bool An array of options, or FALSE if not set up.
 */
function cexdrive_get_config()
{
	$settings = unserialize( get_option('cexdrive-config') );
	if(empty($settings) || count($settings) == 0)
	{
		return FALSE;
	}
	return $settings;
}


/**
 * Sets a config option.
 * 
 * @param array An array that will be merged in the existing options.
 * @param bool Whether to merge the data or override.
 * @return array The new data array.
 */
function cexdrive_set_config($data = array(), $merge = TRUE)
{
	$current = cexdrive_get_config();
	if($current !== FALSE && $merge === TRUE)
	{
		$data = array_merge($current, $data);
	}
    update_option('cexdrive-config', serialize($data));
	return $data;
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
	$client->setScopes(array('https://www.googleapis.com/auth/drive', 'https://www.googleapis.com/auth/userinfo.email'));
	$client->setUseObjects(true);
	
	return $client;
}
?>
