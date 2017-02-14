<?php
/*
Plugin Name: Spamjirut
Plugin URI: http://code.emka.web.id/index.php/wp-spamjirut
Description: SPAM is Bajirut. Simple anti-SPAM for WordPress Multisite.
Version: 0.1
Author: Luthfi Emka
Author URI: http://luthfi.emka.web.id/
*/

// Plugin version
$spamjirut_options_version = "0.1";

// Add default database settings on plugin activation
function spamjirut_add_default_data() {
	$spamjirut_options = array(
		'blocklist_keys' => '',
		'lbl_enable_disable' => 'disable',
		'remote_blocked_list' => '',
		'rbl_enable_disable' => 'disable',
		'pw_field_size' => '30',
		'tab_index' => '',
		'affiliate_msg' => '',
		'toggle_stats_update' => 'disable',
		'toggle_html' => 'disable'
		// 'spamjirut_version' => '1.5.1'
		);
	add_option('spamjirut_options', $spamjirut_options);
	add_option('spamjirut_spam_hits', '1');
}

// variable used as global to retrieve option array for functions
$wp_spamjirut_options = get_option('spamjirut_options');

// Gets Spam Blocked Count
$spamjirut_count = number_format_i18n(get_option('spamjirut_spam_hits'));

// Runs add_default_data function above when plugin activated
register_activation_hook( __FILE__, 'spamjirut_add_default_data' );

// Delete the default options from database when plugin deactivated,
// The post comment passwords can be deleted also using the following SQL statement.
// DELETE from wp_postmeta WHERE meta_key = "spamjirut_comment_form_password" ;


// Deletes all options listed in remove_default_data when plugin deactivated
// Remove // to enable an option to be deleted
function spamjirut_remove_default_data() {
// delete_option('spamjirut_options');
// delete_option('spamjirut_spam_hits');
}

// Deletes all options listed in remove_default_data when plugin deactivated
register_deactivation_hook( __FILE__, 'spamjirut_remove_default_data' );

// Checks to see if comment form password exists and if not creates one in custom fields
function spamjirut_comment_pass_exist_check() {
	global $post;
	$new_post_comment_pwd = rand(1000,9999);
	$spamjirut_pwd_exists_check = get_post_meta( $post->ID, 'spamjirut_comment_form_password', true );
	
	if( empty($spamjirut_pwd_exists_check) || !$spamjirut_pwd_exists_check  && comments_open() ) {
		update_post_meta($post->ID, 'spamjirut_comment_form_password', $new_post_comment_pwd);
	}
}
add_action('loop_start', 'spamjirut_comment_pass_exist_check', 1);

// Creates a new comment form password each time a comment is saved in the database
function spamjirut_new_comment_pass() {
	global $post;
	$new_comment_pwd = rand(1000,9999);
	$old_password = get_post_meta( $post->ID, 'spamjirut_comment_form_password', true );
	
	update_post_meta($post->ID, 'spamjirut_comment_form_password', $new_comment_pwd, $old_password);
}

// Call the function to change key 2 password to custom fields when after each new comment is saved in the database.
add_action('comment_post', 'spamjirut_new_comment_pass', 1);

// Gets the remote IP address even if behind a proxy
function get_remote_ip_address() {
	if(!empty($_SERVER['HTTP_CLIENT_IP'])) {
		$ip_address = $_SERVER['HTTP_CLIENT_IP'];
	} else if(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
	} else if(!empty($_SERVER['REMOTE_ADDR'])) {
		$ip_address = $_SERVER['REMOTE_ADDR'];
	} else {
		$ip_address = '';
	}
	return $ip_address;
}

// Returns Local Blocklist
function spamjirut_local_blocklist_check() {
	global $wp_spamjirut_options;

	// Gets IP address of commenter
	$comment_author_ip = get_remote_ip_address();

	$local_blocklist_keys = trim( $wp_spamjirut_options['blocklist_keys'] );
	if ( '' == $local_blocklist_keys )
		return false; // If blocklist keys are empty
	$local_key = explode("\n", $local_blocklist_keys );

	foreach ( (array) $local_key as $lkey ) {
		$lkey = trim($lkey);

		// Skip empty lines
		if ( empty($lkey) ) { continue; }

		// Can use '#' to comment out line in blocklist
		$lkey = preg_quote($lkey, '#');

		$pattern = "#$lkey#i";
		if (
			   preg_match($pattern, $comment_author_ip)
		 )
			return true;
	}
	return false;
}

// Returns Remote Blocklist
function spamjirut_remote_blocklist_check() {
	global $wp_spamjirut_options;
	
	// Gets IP address of commenter
	$comment_author_ip = get_remote_ip_address();
	// Retrieves remote blocklist url from database
	$rbl_url = $wp_spamjirut_options['remote_blocked_list'];
	// Uses a URL to retrieve a list of IP address in an array
	$get_remote_blocklist = wp_remote_get($rbl_url);
	
	if ( '' == $rbl_url )
		return false; // If blocklist keys are empty or url is not in the database
	$remote_key = explode("\n", $get_remote_blocklist['body'] ); // Turns blocklist array into string and lists each IP address on new line

	foreach ( (array) $remote_key as $rkey ) {
		$rkey = trim($rkey);

		// Skip empty lines
		if ( empty($rkey) ) { continue; }

		// Can use '#' to comment out line in blocklist
		$rkey = preg_quote($rkey, '#');

		$pattern = "#$rkey#i";
		if (
			   preg_match($pattern, $comment_author_ip)
		 )
			return true;
	}
	return false;
}

// Function for comments.php file
function spamjirut_options_comments_form() {
	global $wp_spamjirut_options, $post, $spamjirut_options_version, $wp_version, $spamjirut_count;
	
	
	$spamjirut_comment_form_password_var = get_post_meta( $post->ID, 'spamjirut_comment_form_password', true );
	$img_url = plugins_url( 'img.php?c='.urlencode(base64_encode(strrev($spamjirut_comment_form_password_var))), __FILE__ );
	$spamjirut_pw_field_size = $wp_spamjirut_options['pw_field_size'];
	$spamjirut_tab_index = $wp_spamjirut_options['tab_index'];

	// If the reader is logged in don't require password for comments.php
	if ( !is_user_logged_in() ) {
		// Commenter IP address
		echo "<input type='hidden' name='comment_ip' id='comment_ip' value='".get_remote_ip_address()."' />";
		// Reader must enter this password manually on the comment form
		echo "<p>* Kode Akses Komentar:
		</p>";
		echo "<p><img src='$img_url' /></p>";
		echo "<p>* Tuliskan kode akses komentar diatas:
		<input type='text' name='passthis' id='passthis' value='".$comment_passthis."' size='".$spamjirut_pw_field_size."' tabindex='".$spamjirut_tab_index."' /></p>";
		// Shows how many comment spam have been killed on the comment form
		if ($wp_spamjirut_options['toggle_stats_update'] == "enable") {
				echo '<p>' . $spamjirut_count . ' Spam Comments Blocked so far.</p>';
		} else {
				echo "";
		}
	}
}

// Function for wp-comments-post.php file located in the root Wordpress directory. The same directory as the wp-config.php file.
function spamjirut_options_comments_post() {
	global $post, $wp_spamjirut_options;
	
	$spamjirut_comment_script = get_post_meta( $post->ID, 'spamjirut_comment_form_password', true );
	echo $spamjirut_comment_script;
	
	// If the reader is logged in don't require password for wp-comments-post.php
	if ( !is_user_logged_in() ) {

		// Compares current comment form password with current password for post
		if ($_POST['passthis'] == '' || $_POST['passthis'] != $spamjirut_comment_script)
			wp_die( __('Error 1: Tuliskan kode akses berkomentar, silakan ulangi lagi.', spam_counter()) );
		
		// Compares commenter IP address to local blocklist
		if ($wp_spamjirut_options['lbl_enable_disable'] == 'enable') {
			if ($_POST['comment_ip'] == '' || $_POST['comment_ip'] == spamjirut_local_blocklist_check() )
				wp_die( __('Spam Blocked by Spamjirut (local blocklist)', spam_counter()) );
		}
		
		// Compares commenter IP address to remote blocklist
		if ($wp_spamjirut_options['rbl_enable_disable'] == 'enable') {
			if ($_POST['comment_ip'] == '' || $_POST['comment_ip'] == spamjirut_remote_blocklist_check() )
				wp_die( __('Spam Blocked by Spamjirut (remote blocklist)', spam_counter()) );
		}

	}
}

// Counts number of comment spam hits and stores in options database table
function spam_counter() {
	$s_hits = get_option('spamjirut_spam_hits');
	update_option('spamjirut_spam_hits', $s_hits+1);
}

// displays comment spam hits wherever it is called
function display_spam_hits() {
	$s_hits = get_option('spamjirut_spam_hits');
	return $s_hits;
}

// Register Admin Options Page
function register_spamjirut_options_options_page() {
	add_options_page('Spamjirut Configuration', 'Spamjirut', 'manage_options', 'spamjirut_page', 'spamjirut_options_options_page');
}

// Admin Settings Options Page function
function spamjirut_options_options_page() {

	// Check to see if user has adequate permission to access this page
	if (!current_user_can('manage_options')){
      wp_die( __('You do not have sufficient permissions to access this page.') );
    }

	global $spamjirut_options_version, $spamjirut_count;

?>
<div class="wrap">
    <h2>Spamjirut <?php echo $spamjirut_options_version; ?> Settings</h2>

	<form method="post" action="">
<?php

	echo '<table class="form-table">';
		if ($_POST['options']) {
		
		$new_wp_spamjirut_options = $_POST['wp_spamjirut_options'];
		update_option('spamjirut_options', $new_wp_spamjirut_options);

		// Display saved message when options are updated.
		$msg_status = 'Spamjirut settings saved.';
		_e('<div id="message" class="updated fade"><p>' . $msg_status . '</p></div>');
		}
		
	$wp_spamjirut_options = get_option('spamjirut_options');
		
?>

<table class="form-table">
	<tr>
		<td valign="top">		
			<h3>Local Comment Blocklist</h3>
			<p>The Local Blocklist is a list of blocked IP addresses stored in the blog database. When a comment comes from an IP address matching the Blocklist it will be blocked, which means you will never see it as waiting for approval or marked as spam. Blocked commenters will be able to view your blog, but any comments they submit will be blocked, which means not saved to the database, and they will see the message &#8220;Spam Blocked.&#8221;</p>
			<p>Enter one IP address (for example 192.168.1.1) per line. Wildcards like 192.168.1.* will not work.</p>
			<p><code>#</code> can be used to comment out an IP address.</p>
				<fieldset>
					<p>On <input type="radio" name="wp_spamjirut_options[lbl_enable_disable]" <?php echo (($wp_spamjirut_options['lbl_enable_disable'] == "enable") ? 'checked="checked"' : '') ;  ?> value="enable" />&nbsp;&nbsp; Off <input type="radio" name="wp_spamjirut_options[lbl_enable_disable]" <?php echo (($wp_spamjirut_options['lbl_enable_disable'] == "disable") ? 'checked="checked"' : '') ;  ?> value="disable" />
				</fieldset>
				<fieldset>
					<textarea name="wp_spamjirut_options[blocklist_keys]" cols='20' rows='12' ><?php echo $wp_spamjirut_options['blocklist_keys']; ?></textarea>
				</fieldset>

			<h3>Remote Comment Blocklist</h3>
			<p>The Remote Comment Blocklist accesses a text file list of IP addresses on a remote server to block comment spam. This allows a global IP address blocklist to be shared with multiple blogs. It is also possible to use the Local Comment Blocklist for blog specific blocking, and the Remote Comment Blocklist for global blocking used by mutliple blogs at the same time. Remote Comment Blocklist works exactly the same way as the Local Comment Blocklist, except it is on a remote server. The URL to the remote text file could be for example: <code>http://www.example.com/mybl/bl.txt</code></p>
			<p><code>#</code> can be used to comment out an IP address.</p>
				<fieldset>
					<p>On <input type="radio" name="wp_spamjirut_options[rbl_enable_disable]" <?php echo (($wp_spamjirut_options['rbl_enable_disable'] == "enable") ? 'checked="checked"' : '') ;  ?> value="enable" />&nbsp;&nbsp; Off <input type="radio" name="wp_spamjirut_options[rbl_enable_disable]" <?php echo (($wp_spamjirut_options['rbl_enable_disable'] == "disable") ? 'checked="checked"' : '') ;  ?> value="disable" />
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="text" size="60" name="wp_spamjirut_options[remote_blocked_list]" value="<?php echo $wp_spamjirut_options['remote_blocked_list']; ?>" />&nbsp;&nbsp; Enter URL to remote text file.</p>
				</fieldset>
				
			<h3>Password Form Customization</h3>
				<fieldset>
					<p>
					<input type="text" name="wp_spamjirut_options[pw_field_size]" size="4" value="<?php echo $wp_spamjirut_options['pw_field_size']; ?>" />&nbsp;&nbsp; Password Field Size. Default is 30.
					&nbsp;&nbsp;&nbsp;<input type="text" name="wp_spamjirut_options[tab_index]" size="4" value="<?php echo $wp_spamjirut_options['tab_index']; ?>" />&nbsp;&nbsp; Tab Index
					</p>
				</fieldset>
				
			<h3>Remove HTML from Comments</h3>
			<p>Strips the HTML from comments to render spam links as plain text. Also removes the allowed HTML tags message from below the comment box.</p>			
				<fieldset>
					<p>On <input type="radio" name="wp_spamjirut_options[toggle_html]" <?php echo (($wp_spamjirut_options['toggle_html'] == "enable") ? 'checked="checked"' : '') ;  ?> value="enable" />&nbsp;&nbsp; Off <input type="radio" name="wp_spamjirut_options[toggle_html]" <?php echo (($wp_spamjirut_options['toggle_html'] == "disable") ? 'checked="checked"' : '') ;  ?> value="disable" /></p>
				</fieldset>
		</td>
			
		<td valign="top" bgcolor="#FFFFFF">
						<div align="center"><h3>Blocked Comment Spam</h3></div>
						<p align="center"><b><big><?php echo $spamjirut_count; ?></big></b></p>
						<br />
		</td>
	</tr>
</table>

<?php

}

// Remove note after comment box that says which HTML tags can be used in comment
function spamjirut_remove_allowed_tags_field($no_allowed_tags) {
    unset($no_allowed_tags['comment_notes_after']);
    return $no_allowed_tags;
}

// Strips out html from comment form when enabled
if ($wp_spamjirut_options['toggle_html'] == "enable" && version_compare($wp_version, '3.0', '>=' )) {
	// Removes all HTML from comments and leaves it only as text
	add_filter('comment_text', 'wp_filter_nohtml_kses');
	add_filter('comment_text_rss', 'wp_filter_nohtml_kses');
	add_filter('comment_excerpt', 'wp_filter_nohtml_kses');
	// remove tags from below comment form
	add_filter('comment_form_defaults','spamjirut_remove_allowed_tags_field');
}

// Adds password field to comment form is > Wordpress 3.0 and using comment_form function to generate comment form
function do_spamjirut_options_automation() {
	global $wp_version;
	
	// run the following code only if using Wordpress 3.x or greater
	if ( version_compare($wp_version, '3.0', '>=' ) ) {

		// Calls the password form for comments.php if the comment_form function is outputting comment form fields
		add_filter('comment_form_after_fields', 'spamjirut_options_comments_form', 1);
	}
}

// Calls the Wordpress 3.x code for admin settings page
add_action('init', 'do_spamjirut_options_automation', 1);
// Calls the wp-comments-post.php authentication
add_action('pre_comment_on_post', 'spamjirut_options_comments_post', 1);
// Add Admin Options Page
add_action('admin_menu', 'register_spamjirut_options_options_page');

?>
