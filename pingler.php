<?php
/*
Plugin Name: Pingler
Plugin URI: http://pingler.com/downloads/wordpress/
Description: Pingler automatic pinging of new posts. <a href="/wp-admin/options-general.php?page=pingler-options">Click here to add your API Key!</a> (once activated)
Version: 1.5.0
Author: Pingler
Author URI: http://pingler.com/
License: GPL2
*/

/*
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

//SETTING UP THE OPTIONS PAGE
function pingler_connect($url) {
	if (function_exists('curl_init')) {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		@curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
		return curl_exec($ch);
	} else {
		return file_get_contents($url);
	}
}

add_action('admin_menu', 'pingler_menu');

function pingler_menu() {
	add_options_page('Pingler Options', 'Pingler', 'manage_options', 'pingler-options', 'pingler_options_page');
}

function pingler_options_page() {
	if (!current_user_can('manage_options'))  {
		wp_die( __('You do not have sufficient permissions to access this page.') );
	}
	//FORM CODE STARTS HERE
?>
<div class="wrap">

<?php echo pingler_connect('http://api.pingler.com/announce.php'); ?>

<h2>Pingler</h2>
<p>Thank you for using our Wordpress Plugin to submit your pings. To keep inline with the Wordpress methodology of keeping it simple, here are a whole 2 options.</p>
<p><b>*Pingler API Key:</b> If you have an account with us at Pingler enter your API Key to increase your Ping Allowance, if you are not registered yet, leave this blank.</p>
<p><b>*Pingler Category:</b> Select the most relevant category for your Blog here so your pings get the targetted traffic they deserve.</p>

<form method="post" action="options.php">
<?php wp_nonce_field('update-options');
$PINGKEY = get_option('pingler_apikey');
?>

<table class="form-table">
<tr valign="top">
<th scope="row"><label for="pingler_apikey">Pingler API Key:</label></th>
<td><input type="text" name="pingler_apikey" value="<?php echo $PINGKEY; ?>" class="regular-text" /> <small>(Can be found in your account at <a href="http://pingler.com/">pingler.com</a>)</small></td>
</tr>
<?php if(!empty($PINGKEY)) { ?>
<tr valign="top">
<th scope="row"><label for="pingler_category">Pingler Category:</label></th>
<td><select name="pingler_category" class="regular-text postform" /><option value="">Please Select</option><?php pingler_categories($PINGKEY); ?></select></td>
</tr>
<?php } ?>
</table>

<input type="hidden" name="action" value="update" />
<input type="hidden" name="page_options" value="pingler_apikey,pingler_category" />
<p class="submit">
<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
</p>
</form> 

<?php if(!empty($PINGKEY)) { ?>
	<h2>Ping Multiple Posts</h2>
	<p>Here you can add all the posts on your blog to Pingler, if you have added your API key above they will go into your account. You can choose to select the posts you want to add using the "Let me choose" button.</p>
	<p><b>*API Key:</b> If you have entered your account API Key above then the posts will be added to your account.</p>
	<p><b>*No Account:</b> If you don't have a pingler account the posts will be Live Pinged, this can take quite a while.</p>

	<?php if(!isset($_POST['pingChoose']) && !isset($_POST['pingChosen']) && !isset($_POST['pingAll'])) { ?>
	<form method="post" action="">
	<p class="submit">
	<input type="submit" name="pingAll" class="button-primary" value="<?php _e('Ping All') ?>" />
	<input type="submit" name="pingChoose" class="button-primary" value="<?php _e('Let me choose') ?>" />
	</p>
	</form>
	<?php } ?>
<?php } ?>

<?php if(isset($_POST['pingChoose'])) { ?>
<form method="post" action="">
<p class="submit">
<input type="submit" name="pingChosen" class="button-primary" value="<?php _e('Ping Selected') ?>" />
</p>
<table class="widefat post fixed" cellspacing="0">

	<thead>
	<tr>
	<th scope="col" id="cb" class="manage-column column-cb check-column" style=""><input type="checkbox" /></th>
	<th scope="col" id="title" class="manage-column column-title" style="">Title</th>
	</tr>
	</thead>

	<tbody>
	<?php
	global $post;
	$thisCount = 1;
	$myposts = get_posts('numberposts=-1');
	foreach($myposts as $post) :
		setup_postdata($post);
	?>
		<tr class='alternate author-self status-draft iedit' valign="top">
		<th scope="row" class="check-column"><input type="checkbox" name="post[]" value="<?php echo $post->ID; ?>" /></th>
		<td><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></td>
		</tr>
	<?php endforeach; ?>

	</tbody>
</table> 

<p class="submit">
<input type="submit" name="pingChosen" class="button-primary" value="<?php _e('Ping Selected') ?>" />
</p>
</form> 
<?php } ?>

<?php
if($_POST['pingAll']) {
	pingler_multiple();
} elseif($_POST['pingChosen']) {
	pingler_multiple($_POST['post']);
}
?>

</div>
<?php
	//FORM CODE ENDS HERE
}

function pingler_multiple($posts = NULL) {
	if($posts != NULL) {
		foreach($posts as $post) {
			$pinglerURL = pingler_build_url($post);
			$pinglerMSG = pingler_connect($pinglerURL);
			echo "<p>".$pinglerMSG."</p>";
		}
	} else {
		global $post;
		$thisCount = 1;
		$myposts = get_posts('numberposts=-1');
		foreach($myposts as $post) :
			setup_postdata($post);
			$pinglerURL = pingler_build_url($post->ID);
			$pinglerMSG = pingler_connect($pinglerURL);
			echo "<p>".$pinglerMSG."</p>";
		endforeach;
	}
}

function pingler_categories($PINGKEY = NULL) {
	$pinglerCATS = pingler_connect('http://api.pingler.com/?key='.$PINGKEY.'&act=cats');

	preg_match_all('#<id>(.*?)</id>#is', $pinglerCATS, $PINFO[id], PREG_SET_ORDER);
	preg_match_all('#<name>(.*?)</name>#is', $pinglerCATS, $PINFO[name], PREG_SET_ORDER);

	foreach($PINFO[id] as $key => $value) {
		if(get_option('pingler_category') === $PINFO[id][$key][1]) {
			echo '<option value="'.$PINFO[id][$key][1].'" selected=selected>'.$PINFO[name][$key][1].'</option>';
		} else {
			echo '<option value="'.$PINFO[id][$key][1].'">'.$PINFO[name][$key][1].'</option>';
		}
	}
}

//ACTUALLY PING THE PUBLISHED POST TO PINGLER
function pingler_build_url($post_ID) {
$thisPost = get_post($post_ID, ARRAY_A);
	$pinglerOPT = '&key='.get_option('pingler_apikey');
	$pinglerOPT .= '&url='.get_permalink( $post_ID );
	$pinglerOPT .= '&title='.urlencode($thisPost['post_title']);
	$pinglerOPT .= '&category_id='.get_option('pingler_category');

	return 'http://api.pingler.com/?act=add'.$pinglerOPT;	
}

function pingler_submit_post($post_ID) {
	if(get_permalink( $post_ID ) != NULL) {
		$pinglerURL = pingler_build_url($post_ID);
		$pinglerMSG = pingler_connect($pinglerURL);
	}
	update_option('pingler_message', $pinglerMSG);
}

function pingler_display_message() {
	if(get_option('pingler_message')) {
		echo '<div id="message" class="updated"><p>'.get_option('pingler_message').'</p></div>';
		update_option('pingler_message', '');
	}
}

add_action ('publish_post', 'pingler_submit_post');
add_action ('admin_footer', 'pingler_display_message');
?>
