<?php
/*
Plugin Name: pMailer Contact Importer
Plugin URI: http://www.pmailer.co.za/
Description: Exports email addresses from Wordpress and imports them into pMailer.
Version: 0.3
Author: pMailer
Author URI: http://www.prefix.co.za
License: GPL
*/

// check if classes have already been included by another pmailer plugin.

/**
 * Include required files.
 */
if ( class_exists('PMailerSubscriptionApiV1_0') === false )
{
    require_once 'pmailer_api.php';
}

/* Runs when plugin is activated */
register_activation_hook(__FILE__, 'pmailer_imp_install');

/* Runs on plugin deactivation*/
register_deactivation_hook(__FILE__, 'pmailer_imp_remove');

/**
 * Runs code when Pmailer widget is activated.
 */
function pmailer_imp_install()
{
    // Add database options
    add_option('pmailer_imp_valid', '', '', 'no');
    add_option('pmailer_imp_url', '', '', 'no');
    add_option('pmailer_imp_api_key', '', '', 'no');
    add_option('pmailer_imp_lists', '', '', 'no');
    add_option('pmailer_imp_request_double_opt_in', 'no', 'no');

}

/**
 * Runs clean-up code when pmailer is de-activated.
 */
function pmailer_imp_remove()
{
    // Remove database options
    delete_option('pmailer_imp_valid');
    delete_option('pmailer_imp_url');
    delete_option('pmailer_imp_api_key');
    delete_option('pmailer_imp_lists');
    delete_option('pmailer_imp_request_double_opt_in');

}

// create custom plugin settings menu
add_action('admin_menu', 'pmailer_imp_create_menu');

function pmailer_imp_create_menu()
{
    // create new top-level menu
    add_options_page('pMailer', 'pMailer Importer', 'manage_options', 'pmailer-importer', 'pmailer_imp_settings_page');

}

function pmailer_imp_save_api_settings()
{
    // Update API details
    if ( isset($_POST['pmailer_imp_api_details']) === true )
    {
        // save details
        update_option('pmailer_imp_url', $_POST['pmailer_imp_url']);
        update_option('pmailer_imp_api_key', $_POST['pmailer_imp_api_key']);
        $pmailerApi = new PMailerSubscriptionApiV1_0($_POST['pmailer_imp_url'], $_POST['pmailer_imp_api_key']);
        try
        {
            $lists = $pmailerApi->getLists();
        }
        catch ( PMailerSubscriptionException $e )
        {
            echo '<div class="error"><p>'.$e->getMessage().'</p></div>';
            return;
        }

        update_option('pmailer_imp_lists', serialize($lists));
        update_option('pmailer_imp_valid', 'yes');
        echo '<div class="updated"><p>API details successfully updated.</p></div>';

    }
}

function pmailer_imp_reset_enterprise_settings()
{
    // Reset details
    if ( isset($_POST['pmailer_imp_reset_details']) === true )
    {
        update_option('pmailer_imp_valid', '');
    }
}

function pmailer_imp_refresh_lists()
{
    if ( isset($_POST['pmailer_imp_refresh_lists']) === true )
    {
        $pmailerApi = new PMailerSubscriptionApiV1_0(get_option('pmailer_imp_url'), get_option('pmailer_imp_api_key'));
        try
        {
            $lists = $pmailerApi->getLists();
        }
        catch ( PMailerSubscriptionException $e )
        {
            echo '<div class="error"><p>'.$e->getMessage().'</p></div>';
            return;
        }
        update_option('pmailer_imp_lists', serialize($lists));
        echo '<div class="updated"><p>Successfully refreshed lists.</p></div>';
    }
}

function pmailer_imp_import_contacts()
{
	global $wpdb;

	if ( !is_admin() )
	{
		return;
	}

    if ( isset($_POST['pmailer_imp_form_details']) === true )
    {
    	// set defaults
    	$contacts = $users = $comments = array();
    	// import users if option was checked
    	$pmailer_imp_user_limit = ( isset($_POST['pmailer_imp_user_limit']) === true ) ? (int)$_POST['pmailer_imp_user_limit'] : 0;
    	if ( isset($_POST['pmailer_imp_users']) === true )
    	{
    		$users = $wpdb->get_results("SELECT user_email AS contact_email FROM $wpdb->users LIMIT {$pmailer_imp_user_limit}, 25", ARRAY_A);
    	}

        // import comments if option was checked
        $pmailer_imp_comment_limit = ( isset($_POST['pmailer_imp_comment_limit']) === true ) ? (int)$_POST['pmailer_imp_comment_limit'] : 0;
        if ( isset($_POST['pmailer_imp_comments']) === true )
        {
            $comments = $wpdb->get_results("SELECT comment_author_email AS contact_email FROM $wpdb->comments WHERE comment_author_email != '' LIMIT {$pmailer_imp_comment_limit}, 25", ARRAY_A);
        }

        $contacts = array_merge($users, $comments);

        // if there is nothing to import stop the import
        if ( empty($contacts) === true )
        {
        	$response['status'] = 'complete';
            $response['message'] = 'Import complete';
            die(json_encode($response));
        }

        $lists = array();
        foreach ( $_POST['pmailer_imp_selected_lists'] as $list => $list_id )
        {
        	$lists[] = $list_id;
        }

        $pmailerApi = new PMailerSubscriptionApiV1_0(get_option('pmailer_imp_url'), get_option('pmailer_imp_api_key'));
        try
        {
            if ( isset($_POST['pmailer_imp_request_double_opt_in']) === true
            	&& $_POST['pmailer_imp_request_double_opt_in'] == 'yes' )
            {
                $response = $pmailerApi->batchSubscribe($contacts, $lists, 'unconfirmed');
            }
            else
            {
                $response = $pmailerApi->batchSubscribe($contacts, $lists, 'subscribed');
            }
        }
        catch ( PMailerSubscriptionException $e )
        {
        	$response['status'] = 'error';
        	$response['message'] = $e->getMessage();
        }

        // increment fetch limits
        $response['pmailer_imp_user_limit'] = $pmailer_imp_user_limit + 25;
        $response['pmailer_imp_comment_limit'] = $pmailer_imp_comment_limit + 25;

        die(json_encode($response));

    }

}

add_action('init', 'pmailer_imp_import_contacts');

function pmailer_imp_settings_page()
{
	global $wpdb;
	$user_count = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(ID) FROM $wpdb->users;"));
	$comment_count = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(comment_ID) FROM $wpdb->comments WHERE comment_author_email != ''"));
    if ( is_admin() )
    {
        pmailer_imp_save_api_settings();
        pmailer_imp_reset_enterprise_settings();
        pmailer_imp_refresh_lists();
    }

?>
<div class="wrap">
<div class="icon32" id="icon-options-general"><br>
</div>
<h2>pMailer contact importer</h2>
    <?php
    $valid = get_option('pmailer_imp_valid');
    if ( empty($valid) === true ):
    ?>
    <div style="background-color:white; padding:10px;">
    <strong>Please enter your pMailer details:</strong>
    <form method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
        <table class="form-table">
            <tr valign="top">
                <th scope="row">pMailer URL</th>
                <td><input type="text" name="pmailer_imp_url" value="<?php echo get_option('pmailer_imp_url'); ?>" /> - e.g. live.pmailer.co.za</td>
            </tr>
            <tr valign="top">
                <th scope="row">API key</th>
                <td><input type="text" name="pmailer_imp_api_key" size="40" value="<?php echo get_option('pmailer_imp_api_key'); ?>" /></td>
            </tr>
        </table>
        <input type="hidden" name="pmailer_imp_api_details" value="Y">
        <p class="submit"><input type="submit" class="button-primary" value="<?php _e('Next') ?>" /></p>
    </form>
    </div>
    <?php
    endif;
    ?>

    <?php
    if ( $valid === 'yes' ):
    ?>
<div style="background-color:white; padding:10px;">
<form method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
    <strong>Enterprise details:</strong><br />
    Enterprise URL: <i><?php echo get_option('pmailer_imp_url'); ?></i><br />
    API key: <i><?php echo get_option('pmailer_imp_api_key'); ?></i> <input type="hidden" name="pmailer_imp_reset_details" value="Y">
    <br />
    <p class="submit"><input type="submit" class="button-primary" value="<?php _e('Change enterprise & API details') ?>" /></p>
    <h3>How to use:</h3>
    1) Select the list(s) that you would like users/comments to be imported into.<br />
    2) Select what must be imported (users/comments)<br />
    3) Click the "Import into pMailer button"<br /><br />
    Additional information:<br />
    <i>This import can be run as many times as desired, any duplicate email addresses found will be ignored.<br />
    And any new email addresses will be added to the selected lists.</i>
</form>
</div>
<br />
</div>

<div style="background-color:white; padding:10px;" id="pmailer_imp_options">
<p><strong>Import settings:</strong></p>

<form method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
    <input type="hidden" name="pmailer_imp_refresh_lists" value="yes">
    <input type="submit" class="button-primary" value="<?php _e('Refresh lists') ?>" />
</form>
<form method="post" id="pmailer_imp_form_settings" name="pmailer_imp_form_settings" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
<input type="hidden" name="pmailer_imp_form_details" value="Y">
<input type="hidden" name="pmailer_imp_url" id="pmailer_imp_url" value="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
<input type="hidden" name="pmailer_imp_total_users" id="pmailer_imp_total_users" value="<?php echo $user_count; ?>">
<input type="hidden" name="pmailer_imp_total_comments" id="pmailer_imp_total_comments" value="<?php echo $comment_count; ?>">

<table class="form-table">
    <tr valign="top">
        <th scope="row">Please select the list(s) that contacts will be imported to.
        </th>
        <td>
          <div id="pmailer_imp_selected_lists_error" style="color:red;"></div>
          <select name="pmailer_imp_selected_lists[]" id="pmailer_imp_selected_lists" multiple="multiple" style="height: 110px">
                <?php
                $available_lists = get_option('pmailer_imp_lists');
                if ( is_array($available_lists) === false )
                {
                    $available_lists = unserialize($available_lists);
                }
                foreach ( $available_lists['data'] as $key => $list ):
                ?>
                  <option value="<?php echo $list['list_id']; ?>"><?php echo $list['list_name']; ?></option>
                <?php
                endforeach;
                ?>
            </select>
        </td>
    </tr>

    <tr valign="top">
        <th scope="row">What must be imported?</th>
        <td>
          <div id="pmailer_imp_selected_options_error" style="color:red;"></div>
          <label id="pmailer_imp_users_label" for="pmailer_imp_users"><input type="checkbox" name="pmailer_imp_users" id="pmailer_imp_users" value="users" />&nbsp;Import users (<?php echo $user_count; ?>)</label><br />
          <label id="pmailer_imp_comments_label" for="pmailer_imp_comments"><input type="checkbox" name="pmailer_imp_comments" id="pmailer_imp_comments" value="comments" />&nbsp;Import Comments (<?php echo $comment_count; ?>)</label>
        </td>
    </tr>
    <tr valign="top">
        <th scope="row">Request double opt in?</th>
        <td>
          <label id="pmailer_imp_request_double_opt_in_yes" for="pmailer_imp_request_double_opt_in"><input type="radio" name="pmailer_imp_request_double_opt_in" id="pmailer_imp_request_double_opt_in_yes" value="yes" />&nbsp;Yes</label>
          <label id="pmailer_imp_request_double_opt_in_no" for="pmailer_imp_request_double_opt_in"><input type="radio" name="pmailer_imp_request_double_opt_in" id="pmailer_imp_request_double_opt_in_no" value="no" />&nbsp;No</label>
        </td>
    </tr>
</table>
<p class="submit"><input id="pmailer_imp_import_process" type="submit" class="button-primary" value="<?php _e('Import to pMailer') ?>" /></p>
</form>
</div>
<script type="text/javascript" src="<?php echo WP_PLUGIN_URL . '/pmailer-importer/js/pmailer_importer.js'; ?>"></script>

<br />
<style>
#pmailer_imp_show_summary:hover
{
	cursor:pointer;
}
</style>
<div style="background-color:white; padding:10px; display:none;" id="pmailer_imp_progress">
    <h2>Import progress</h2>
    <h3 id="pmailer_imp_import_status">Starting import...</h3>
    <div id="pmailer_imp_message"></div>
    <div><a id="pmailer_imp_show_summary">► Show details</a></div>
    <div id="pmailer_imp_summary" style="height: 150px; width:500px; overflow: scroll; display:none;"></div>
</div>

    <?php
    endif;
    ?>
<?php
}

?>