<?php

/**
 * Plugin Name: u3a SiteWorks Contact Form
 * Description: Provides shortcodes to create a secure contact form for any email recipient
 * Version: 1.1.2
 * Author: u3a SiteWorks team
 * Author URI: https://siteworks.u3a.org.uk/
 * Plugin URI: https://siteworks.u3a.org.uk/
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) exit;

define('U3A_SITEWORKS_CONTACT_FORM_VERSION', '1.1.2'); // Set to current plugin version number

// Use the plugin update service on SiteWorks update server

require 'inc/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$u3acfUpdateChecker = PucFactory::buildUpdateChecker(
    'https://siteworks.u3a.org.uk/wp-update-server/?action=get_metadata&slug=u3a-siteworks-contact-form', //Metadata URL
    __FILE__, //Full path to the main plugin file or functions.php.
    'u3a-siteworks-contact-form'
);

// set the page which will hold the contact form.
// Ideally we should set this in the plugin's settings

define('U3A_CONTACT_PAGE_SLUG', 'u3a-contact-form');

require_once 'class-u3a-contact-form-table.php';
require_once "class-u3a-contact-form-log.php";
U3aContactFormLog::initialise(__FILE__);

register_activation_hook(__FILE__, 'u3a_contact_form_activation');
function u3a_contact_form_activation()
{
    U3aEmailContactsTable::create_table();
    u3a_contact_form_create_page();
}
register_deactivation_hook(__FILE__, 'u3a_contact_form_deactivation');
function u3a_contact_form_deactivation()
{
    /* de-register the shortcodes */
    remove_shortcode('u3a_contact_form');
    remove_shortcode('u3a_contact');
    $timestamp = wp_next_scheduled( 'u3a_cf_cron_hook' );
    wp_unschedule_event( $timestamp, 'u3a_cf_cron_hook' );  // unschedules all future events
}
register_uninstall_hook(__FILE__, 'u3a_contact_form_uninstall');
function u3a_contact_form_uninstall()
{
    U3aEmailContactsTable::delete_table();
    U3aContactFormLog::delete_table();
    u3a_contact_form_delete_page();
    $timestamp = wp_next_scheduled( 'u3a_cf_cron_hook' );
    wp_unschedule_event( $timestamp, 'u3a_cf_cron_hook' );  // unschedules all future events
}

/**
 * The cron job - deletes old database records.
 */
function u3a_cf_cron_exec()
{
    U3aEmailContactsTable::clear_old_contact_instances(2);
    U3aContactFormLog::clear_old_messages(90);
}
add_action('u3a_cf_cron_hook', 'u3a_cf_cron_exec');

/**
 * Schedule the cron job for 4am tomorrow, if the job is not currently scheduled.
 * The job will be repeated daily at 4am.
 */
function u3a_cf_schedule_cron()
{
    $timestamp = wp_next_scheduled('u3a_cf_cron_hook');
    if ($timestamp) {  // there is a scheduled event
         return;
    }
    // schedule event for 4am tomorrow
    $date = new DateTime();
    $date->setTimestamp(time());
    // Reset hours, minutes and seconds to zero.
    $date->setTime(0, 0, 0);
    $tomorrow_4am = $date->getTimestamp() + 86400 + (4*3600);
    wp_schedule_event($tomorrow_4am, 'daily', 'u3a_cf_cron_hook' );
}
add_action('init','u3a_cf_schedule_cron');


/* Register the shortcodes */
add_shortcode('u3a_contact_form', 'u3a_contact_form_shortcode');
add_shortcode('u3a_contact', 'u3a_contact_shortcode');

// Add post state to the contact form page
add_filter('display_post_states', 'u3a_contact_form_set_states', 10, 2);

// Load CSS for the contact form on the front of the website
add_action('wp_enqueue_scripts', 'u3a_contact_form_style');

function u3a_contact_form_style()
{
    wp_enqueue_style('u3a-contact-form',
                     plugin_dir_url(__FILE__) . 'u3a-contact-form.css',
                     array(),
                     U3A_SITEWORKS_CONTACT_FORM_VERSION,
                     false
                     );
}
// Load CSS for the contact form admin page log table
add_action('admin_enqueue_scripts', 'u3a_cf_log_table_style');

function u3a_cf_log_table_style($hook)
{
    if (!(str_contains($hook,'u3a-contact-form-log'))) {
        return;
    }
    wp_enqueue_style('u3a-cf-log-table',
                     plugin_dir_url(__FILE__) . 'u3a-cf-log-table.css',
                     array(),
                     U3A_SITEWORKS_CONTACT_FORM_VERSION,
                     false
                     );
}

/**
 * Returns a safe HTML link to the contact form page,
 * with a query parameter 'contact_id' set to the id of the contact instance in the db.
 *
 * The shortcode requires either 1 or 2 parameters:
 *    name - the name of the recipient (required)
 *    email - the recipient's email address (required unless name is already a u3a Contact) 
 * Example:  `[u3a_contact name="Freda Smith" email="freda@example.com"]`
 * 
 * If the contact is already included in the u3a Contacts database and an email address is specified there, you can omit the email address.
 * Example:  `[u3a_contact name="Freda Smith"]`
 * 
 * You can also use this alternate form:   
 *           `[u3a_contact] Freda Smith [/u3a_contact]`
 * The spaces around the name are optional.
 * 
 * An optional parameter can override the default page used for the contact form
 *    slug - the slug of the page containing the contact form to use for this contact
 */
function u3a_contact_shortcode($atts, $content = null)
{
    // first, validate parameters
    $addressee = trim($atts['name']  ?? '');
    if ('' == $addressee && $content != null) {
        $addressee = trim($content);
    }
    $addressee = html_entity_decode($addressee); // as WordPress converts some chars to HTML entities
    // exit if no addressee
    if ('' == trim($addressee)) {
        return '<p style="color: #f00; font-weight: bold;">The u3a_contact shortcode does not have an addressee parameter</p>';
    }

    $email = trim($atts['email'] ?? '');
    $email = html_entity_decode($email); // as WordPress converts some chars to HTML entities
    // if no email attribute, try to find it from u3a_contact
    if ('' == $email && post_type_exists('u3a_contact')) {
        $contacts = get_posts(['post_type' => 'u3a_contact', 'title' => $addressee, 'fields' => 'ids']);
        if ($contacts) {
            $id = $contacts[0]; // Get first contact if multiple contacts with same title.
            $email = get_post_meta($id, 'email', true);
        }
        // exit if no email found
        if ($email == '') {
            return '<p style="color: #f00; font-weight: bold;">The u3a_contact addressee is not known or has no email address</p>';
        }
    }
    // exit if no email
    if ($email == '') {
        return '<p style="color: #f00; font-weight: bold;">The u3a_contact shortcode does not have an email parameter</p>';
    }
    // exit if email doesn't parse
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return '<p style="color: #f00; font-weight: bold;">The email address in the u3a_contact shortcode appears invalid</p>';
    }

    // handle a specific page slug if provided
    $slug = sanitize_title(trim($atts['slug'] ?? ''));
    if (empty($slug)) {
        $slug = U3A_CONTACT_PAGE_SLUG;
    } else {
        // does the specified page slug exist?
        global $wpdb;
        $slugfound = $wpdb->get_var(
            $wpdb->prepare("SELECT count(post_title) FROM $wpdb->posts WHERE post_name like %s", $slug)
        );
        if ($slugfound < 1) { // if not found, use the default
            $slug = U3A_CONTACT_PAGE_SLUG;
        }
    }
    

    global $wp;
    // set the page on which the shortcode resides
    $source_url = home_url($wp->request);
    // ideally we could add any query parameters but the following line adds unnecessary ones
    // $source_url = add_query_arg( $wp->query_vars, home_url( $wp->request ) );
    $contact_id = U3aEmailContactsTable::find_or_add_contact_instance($addressee, $email, $source_url);

    $link = get_bloginfo('url') . '/' . $slug . '?contact_id=' . $contact_id;
    $link = esc_url($link);
    $safe_addressee = wp_kses($addressee, []);
    // returned value 
    return "<a title='Opens message form'  href='$link'>$safe_addressee</a>";
}

/**
 * This shortcode should only be used on the page which will hold the contact form,
 * and would normally be the only content on that page. The shortcode is self closing and has no attributes.
 *
 * If processing the short code without a valid 'contact_id', it will return an error message.
 * When processing a page where the form has been created and validly completed
 * it will send out the required email(s), and return a success message.
 * It also gives a logged-in user the option of sending a copy of the message to their specified return email address.
 * If processing a page with a valid contact_id but where the form not been submitted, or submitted with validation errors, it will return the HTML form, with a suitable error message where appropriate.
 *
 * @return str HTML for the form and/or messages.
 */
function u3a_contact_form_shortcode($atts)
{
    if (!isset($_GET['contact_id'])) {
        return '<p>You appear to have come directly to this page. To work correctly, you need to use this page via specially-constructed links on this web site.</p>
        <p>This technique ensures that spammers cannot use a link copied from this site to repeatedly email people.</p>';
    }
    $contact_id = $_GET['contact_id'];
    $contact = U3aEmailContactsTable::get_contact_instance($contact_id);
    if (null === $contact) {
        return '<p>Sorry, the link you used is no longer valid. Please try later.</p>';
    }
    $email = $contact->email;
    $addressee = $contact->addressee;
    $phoneNumber = '';
    $defaultReturnEmail = "";
    $defaultReturnName = "";
    if (!isset($_POST['messageSubject'])) {
        // Not a response to the page, show email form with initial values
        if (is_user_logged_in()) {
            // pre-fill the users email and name.
            $defaultReturnEmail = wp_get_current_user()->user_email;
            $defaultReturnName = wp_get_current_user()->display_name;
        }
        return show_u3a_contact_form($addressee, '', '', $defaultReturnName, $defaultReturnEmail, $phoneNumber, '', $contact->nonce);
    }

    // Process response to the page

    // Get text from form if present
    $messageText = empty($_POST['messageText']) ? '' : sanitize_textarea_field($_POST['messageText']);
    $messageSubject = empty($_POST['messageSubject']) ? '' : sanitize_text_field($_POST['messageSubject']);
    $phoneNumber = empty($_POST['phoneNumber']) ? '' : sanitize_text_field($_POST['phoneNumber']);
    $returnName = empty($_POST['returnName']) ? '' : sanitize_text_field($_POST['returnName']);
    $returnEmail = empty($_POST['returnEmail']) ? '' : sanitize_email($_POST['returnEmail']);
    // Need to strip slashes? Was backslash added to apostrophe in test string?
    $u3aMQDetect = $_POST['u3aMQDetect'];
    $needStripSlashes = (strlen($u3aMQDetect) > 5) ? true : false;
    if ($needStripSlashes) {
        $messageText = stripslashes($messageText);
        $messageSubject = stripslashes($messageSubject);
    }

    // Validate the response
    $message = validate_u3a_contact_form();
    if ('ok' != $message) {
        return show_u3a_contact_form($addressee, $messageSubject, $messageText, $returnName, $returnEmail, $phoneNumber, $message, $contact->nonce);
    }

    // Response validated, set up the email and optional copy to logged in user

    // set single char value of $u3amember
    $u3amember = empty($_POST['u3amember']) ? '---' : sanitize_text_field($_POST['u3amember']);
    $u3amember_codes = ['---' => 'n', 'yes' => 'A', 'no' => 'B'];
    $u3amember = $u3amember_codes[$u3amember] ?? 'C'; // 'C' allows for abnormal $POST value

    // TBD can we deal with adding suffix 'u3a' somewhere else?
    $orgname = html_entity_decode(get_bloginfo('name'));
    // add suffix u3a unless already present
    if (strtolower(substr($orgname, -3)) != 'u3a') {
        $orgname .= ' u3a';
    }
    $to = $addressee . ' <' . $email . '>';
    $reply_to = $returnName . ' <' . $returnEmail . '>';
    $phoneMsg = empty(trim($phoneNumber)) ? 'No phone number provided.' : "Phone: $phoneNumber";
    $separatorLine = "\n\n<div style=\"height: 10px; border-top: 1px dotted #444;\"></div>";
    $prefix = "<p>The following message was sent via the $orgname web site.<br>It was addressed to $addressee.<br>Please reply to $returnName ( $returnEmail ). $phoneMsg</p>$separatorLine";
    $copyPrefix = "<p>This is a copy of your message sent to $addressee via the $orgname web site.$separatorLine";

    // replace eols in text with HTML line breaks
    $messageHTML = '<p>' . str_replace(PHP_EOL, '<br/>', $messageText) . '</p>';

    // If using phpmailer the "From;" header will be overridden by any settings in phpmailer_init,
    // but set here in case of use when phpmailer_init is not used.
    $fromName = $returnName . ' via ' . $orgname;
    $fromEmail = 'WordPress@' . $_SERVER['HTTP_HOST'];
    $message_headers = array(
        'From: ' . $fromName . ' <' . $fromEmail . '>',
        'Reply-To: ' . $reply_to,
    );
    $copy_message_headers = array(
        'From: ' . $fromName . ' <' . $fromEmail . '>',
    );
    // In case php_mailer_init is used, set FromName in an action with priority that will be run last.
    add_action('phpmailer_init',
                function($phpmailer) use ($fromName) {
                    $phpmailer->FromName = $fromName;
                }, 99);

    // Now send the email(s) and return a result message

    $status = u3a_contact_mail($to, $messageSubject, $prefix . $messageHTML, $message_headers, $u3amember);
    if ('ok' == $status) {
        $result_message = '<p>Message to recipient was sent successfully.</p>';
        $copy_to_user = 'n';
        if (is_user_logged_in() && isset($_POST['sendCopy'])) {
            // Only send user a copy if they have used their own email address.
            if (strcasecmp(wp_get_current_user()->user_email, $returnEmail) == 0) {
                // send user a copy
                $copy_to_user = 'y';
                $copy_status = u3a_contact_mail($reply_to, $messageSubject,
                                           $copyPrefix . $messageHTML, $copy_message_headers);
                if ('ok'== $copy_status) {
                    $result_message .= '<p>Message copy sent to you.</p>';
                } else {
                    $result_message .=
                        '<p>There was a problem sending you a message copy.</p>';
                }
            } else {
                $result_message .=
                    '<p>Note: The email address you entered does not match your logged in email address,' .
                    ' and no copy has been sent to you.</p>';
            }
        }
    // log the message
    U3aContactFormLog::log_message($addressee, $email, $returnName, $returnEmail, $messageSubject,
                                    $u3amember, $copy_to_user);
    } else {
        $result_message =
            '<p>Sorry there was a problem sending your message. Please try again later.</p>';
    }

    return $result_message;
}

/**
 * Returns an HTML form for sending an email message to an addressee, possibly with an error message.
 * 
 * @param str $nonce not used in this version
 * @return str HMTL including a form
 * @usedby: u3a_email_contact_shortcode
 */

function show_u3a_contact_form($addressee, $messageSubject, $messageText, $returnName, $returnEmail, $phoneNumber, $errorMessage, $nonce)
{
    $html = '';
    if ('' != $addressee) {
        $html .= '<p>You can use the form below to send an email to ' . $addressee . '</p>';
    }
    if ('' != $errorMessage) {
        $html .= '<p style="color: #f00; font-weight: bold;">' . $errorMessage . '</p>';
    }
    $copyHtml = '';
    if (is_user_logged_in()) {
        $copyHtml = '<div><label for="sendCopy">Send me a copy: </label><input type="checkbox" name="sendCopy" id="sendCopy" value="sendCopy"/></div>';
    }
    $html .= <<< END
<form id="mailContact" method="post">
    <input type="hidden" name="u3aMQDetect" value="test'">
    <div id="show-u3a-contact" class="u3aform">
    <div>
        <label for="returnName">Your name: </label>
        <input type="text" name="returnName" id="returnName" value="$returnName"/>
    </div>
    <div>
        <label for="returnEmail">Your email address: </label>
        <input type="email" name="returnEmail" id="returnEmail" value="$returnEmail"/>
    </div>
    <div id='u3amember'>
        <label>U3A member?</label>
        <label for='memyes'>Yes</label>
        <input type='radio' id='memyes' name='u3amember' value='yes'> &nbsp;
        <label for='memno'>No</label>
        <input type='radio' id='memno' name='u3amember' value='no'> &nbsp;
    </div>
    <div>
        <label for="phoneNumber">Your phone number (optional): </label>
        <input type="tel" name="phoneNumber" id="phoneNumber" value="$phoneNumber"/>
    </div>
    <div>
        <label for="messageSubject">Message subject: </label>
        <input type="text" name="messageSubject" id="messageSubject" value="$messageSubject"/>
    </div>
    <div>
        <label for="messageText">Your message: </label>
        <textarea name="messageText" id="messageText" rows="10">$messageText</textarea>
    </div>
    $copyHtml 
    <p class="hasSubmit"><button class="wp-element-button" id="submitButton" name="sendEmail" type="submit">Send your email</button></p>
    </div>
</form>
<script type="text/javascript">
document.forms["mailContact"].elements["returnName"].focus();
</script>
END;
    return $html;
}

/**
 * Validate the POST data submitted in the form.
 * @return An error message if not validated, else 'ok'.
 */

function validate_u3a_contact_form()
{
    if (empty($_POST['messageSubject'])) {
        return "You must enter a subject for your email";
    }
    if (empty($_POST['messageText'])) {
        return "You must enter some text for your email";
    }
    if (empty($_POST['returnName'])) {
        return "You must enter your name so your recipient(s) can get back to you";
    }
    if (empty($_POST['returnEmail'])) {
        return "You must enter your email address so your recipient(s) can get back to you";
    }
    if (!filter_var($_POST['returnEmail'], FILTER_VALIDATE_EMAIL)) {
        return "The email address you entered does not seem to be valid";
    }
    return 'ok';
}

/** Send the message using wp_mail.
 * @return str 'ok' on success, else error message
 */
function u3a_contact_mail($to, $messageSubject, $messageText, $headers = [], $blocked='n')
{
    if ('n' != $blocked) {
        return 'ok';
    }
    $html_start = <<< END
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>u3a SiteWorks message</title>
    <style>
        body {font-family: sans-serif;}
    </style>
</head>
<body>
END;
    $html_end = <<< END
</body>
</html>
END;
    // Append header specifying content type (default is text/plain)
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    // Generate plain text alt body from the html
    add_action('phpmailer_init',
                function ($phpmailer) {
                    $phpmailer->AltBody = wp_strip_all_tags(str_replace('<br/>', PHP_EOL,$phpmailer->Body));
               });
    $result = wp_mail($to, $messageSubject, $html_start . $messageText . $html_end, $headers);
    return (true === $result) ? 'ok' : 'wp_mail error';
}

// Now functions related to the contact form page

/**
 * Create the contact form page, if it does not exist.
 */
function u3a_contact_form_create_page()
{
    $page = get_posts(['name' => U3A_CONTACT_PAGE_SLUG, 'post_type' => 'page']);
    if (!$page) {
        $page_id = wp_insert_post(
            [
                'comment_status' => 'close',
                'ping_status'    => 'close',
                'post_author'    => 1,
                'post_title'     => 'Contact Us',
                'post_name'      => U3A_CONTACT_PAGE_SLUG,
                'post_status'    => 'publish',
                'post_content'   => '<!-- wp:shortcode --> [u3a_contact_form] <!-- /wp:shortcode -->',
                'post_type'      => 'page',
            ]
        );
    }
}

function u3a_contact_form_set_states($states, $post)
{
    if (('page' == $post->post_type)
        && (U3A_CONTACT_PAGE_SLUG == $post->post_name)
    ) {
        $states[] = 'Contact Form Page, do not remove';
    }
    return $states;
}

function u3a_contact_form_delete_page()
{
    $page = get_posts(['name' => U3A_CONTACT_PAGE_SLUG, 'post_type' => 'page']);
    if ($page) {
        wp_delete_post($page[0]->ID, true);
    }
}

