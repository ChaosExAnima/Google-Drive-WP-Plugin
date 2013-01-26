<?php
/**
 * Displays the settings page.
 */
function cexdrive_settings_page() 
{
	$settings = cexdrive_get_config(); // Gets the current config
	$url = admin_url( '/options-general.php?page=' . basename(__DIR__) . '/cexdrive.php' ); // Sets the current URL
    $client = cexdrive_load_lib($url); // Loads the Google SDK
    
    
    // Account authorization handler
    if( isset($_GET['code']) )
    {
        try
        {
            // Authenticate
            $token = $client->authenticate($_GET['code']);
            $client->setAccessToken($token);
            
            // Request user info
            $req = new Google_HttpRequest("https://www.googleapis.com/oauth2/v2/userinfo", 'GET');
            $request = $client->getIo()->authenticatedRequest($req);
            
            $user = json_decode( $request->getResponseBody() );
                        
            // Data to insert
            $data = array(
                'token' => $token,
                'folders' => array(),
            );           
            
            // Store credentials
            if($user->email)
            {
                $settings = cexdrive_set_config(array($user->email => $data));
                $message = "User {$user->email} added successfully.";
            }            
        }
        catch(exception $e)
        {
            $error = 'The provided token is invalid!';
        }
    }

    // Removing a user
    if( isset($_GET['remove']) && $settings && array_key_exists($_GET['remove'], $settings) )
    {
        $name = $_GET['remove'];
        unset( $settings[ $_GET['remove'] ] );
        $settings = cexdrive_set_config($settings, FALSE);
        $message = "User {$name} was successfully removed.";
    }        
?>
<div class="wrap">
	<h2>MET Google Drive</h2>
<?php if( isset($message) ): ?>
    <div class="updated"><p><?php echo $message; ?></p></div>
<?php elseif( isset($error) ): ?>
    <div class="error"><p><strong><?php echo $error; ?></strong></p></div>    
<?php endif; ?>
	
<?php if($settings === FALSE): ?>
	<p>You have not set up any accounts yet.</p>
<?php else: ?>
	<h3>Current Accounts:</h3>
	<ol>
	<?php foreach($settings as $key => $user): ?>
	    <li><?php echo $key; ?> <a href="<?php echo $url; ?>&remove=<?php echo urlencode($key); ?>">(Remove)</a></li>
	<?php endforeach; ?>
	</ol>
<?php endif; ?>
    <p><a href="<?php echo $client->createAuthUrl(); ?>">Add a new account</a></p>
<?php if($settings): ?>
    <p>To use the plugin, put the <code>[gdrive]</code> shortcode in a page or post. Make sure you are sharing all the files to the public, or people shall be confused...!</p>
    <p>The shortcode takes two parameters: <code>[gdrive email="account@domain.com" folder="Name of folder"]</code>.</p>
    <p>The first is the email of the account that's authenticated above. The second is the exact name of the folder you want to display.</p>
    <p>Easy, huh?</p>
<?php endif; ?>
	<pre>
		<?php print_r($settings); ?>
	</pre>
</div>
<?php
}
