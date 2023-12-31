<?php

/**
 * Plugin Name: u3a SiteWorks Contact Form
 * Description: Provides shortcodes to create a secure contact form for any email recipient
 * Version: 1.0.1
 * Author: u3a SiteWorks team
 * Author URI: https://siteworks.u3a.org.uk/
 * Plugin URI: https://siteworks.u3a.org.uk/
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) exit;

define('U3A_SITEWORKS_CONTACT_FORM_VERSION', '1.0.1'); // Set to current plugin version number

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
}
register_uninstall_hook(__FILE__, 'u3a_contact_form_uninstall');
function u3a_contact_form_uninstall()
{
    U3aEmailContactsTable::delete_table();
    u3a_contact_form_delete_page();
}


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

/**
 *Returns a safe HTML link to the contact form page with a query parameter 'contact_id' set to the id of the contact instance in the db.
 *
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

    global $wp;
    // set the page on which the shortcode resides
    $source_url = home_url($wp->request);
    // ideally we could add any query parameters but the following line adds unnecessary ones
    // $source_url = add_query_arg( $wp->query_vars, home_url( $wp->request ) );
    $contact_id = U3aEmailContactsTable::add_contact_instance($addressee, $email, $source_url);

    // delete stale database contact instances (that were never used), just to tidy up.
    // done here in case the contact form is not visited.
    U3aEmailContactsTable::clear_old_contact_instances();

    $link = get_bloginfo('url') . '/' . U3A_CONTACT_PAGE_SLUG . '?contact_id=' . $contact_id;
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
        return '<p>You appear to have already sent your message.</p>
        <p>If you need to send another message please use the website menu to revisit the page which contained the contact link.<br>
        Do not try using your browser back-button as this will not work.</p>';
    }
    if ((time() - $contact->created) > 60 * 90) {
        return '<p>Sorry, the link you used is more than 90 minutes old.
                <a href= "' . $contact->source_url . '">Click here </a> and try again.</p>';
    }
    $email = $contact->email;
    $addressee = $contact->addressee;
    if (!isset($_POST['messageSubject'])) {
        // Not a response to the page, show email form with initial values
        return show_u3a_contact_form($addressee, '', '', '', '', '', $contact->nonce);
    }

    // Process response to the page
    $nonce = empty($_POST['nonce']) ? '' : $_POST['nonce'];
    if (empty($_POST['nonce']) || ($_POST['nonce'] !== $contact->nonce)) {
        return '<p>Sorry, the link you used is not valid.
                <a href= "' . $contact->source_url . '">Click here </a> and try again.</p>';
    }

    // Get text from form if present
    $messageText = empty($_POST['messageText']) ? '' : sanitize_textarea_field($_POST['messageText']);
    $messageSubject = empty($_POST['messageSubject']) ? '' : sanitize_text_field($_POST['messageSubject']);
    $returnName = empty($_POST['returnName']) ? '' : sanitize_text_field($_POST['returnName']);
    $returnEmail = empty($_POST['returnEmail']) ? '' : sanitize_email($_POST['returnEmail']);

    // Need to strip slashes?
    $u3aMQDetect = $_POST['u3aMQDetect'];
    $needStripSlashes = (strlen($u3aMQDetect) > 5) ? true : false; // backslash added to apostrophe in test string?
    if ($needStripSlashes) {
        $messageText = stripslashes($messageText);
        $messageSubject = stripslashes($messageSubject);
    }

    // Validate the response
    $errorMessage = validate_u3a_contact_form();
    if ($errorMessage != '') {
        return show_u3a_contact_form($addressee, $messageSubject, $messageText, $returnName, $returnEmail, $errorMessage, $contact->nonce);
    }

    // Response validated, send the email

    $orgname = html_entity_decode(get_bloginfo('name'));
    // add suffix u3a unless already present
    if (strtolower(substr($orgname, -3)) != 'u3a') {
        $orgname .= ' u3a';
    }
    $to = $addressee . ' <' . $email . '>';
    $reply_to = $returnName . ' <' . $returnEmail . '>';
    $separatorLine = "\n\n<div style=\"height: 10px; border-top: 1px dotted #444;\"></div>";
    $prefix = "<p>The following message was sent via the $orgname web site. It was addressed to $addressee. Please reply to $returnName ( $returnEmail ).$separatorLine";
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
    // In case php_mailer_init is used, set FromName in a filter with priority that will be run last.
    global $u3a_contact_form_fromname; // just for use in filter!
    $u3a_contact_form_fromname = $fromName; 
    add_action('phpmailer_init', 'u3a_contact_form_set_fromname', 99);

    $return = u3a_contact_mail($to, $messageSubject, $prefix . $messageHTML, $message_headers);
    if (empty($return)) {
        // successful send to addressee, so delete this contact instance so that it cannot be reused.
        U3aEmailContactsTable::delete_contact_instance($contact_id);
        if (is_user_logged_in() && isset($_POST['sendCopy'])) {
            // TODO? Should we check wp_get_current_user()->user_email is the same as $returnEmail?
            if (u3a_contact_mail($reply_to, $messageSubject, $copyPrefix . $messageHTML, $copy_message_headers) == '') {
                $result_message = '<p>Messages sent successfully.</p>';
            } else {
                $result_message =
                    '<p>Sorry there was a problem sending you a message copy.' .
                    'The message to the recipient was sent successfully.</p>';
            }
        } else {  // user not to be sent a copy
            $result_message = '<p>Message sent successfully.</p>';
        }
    } else {
        $result_message =
            '<p>Sorry there was a problem sending your message.  Please try again later.</p>';
    }
    return $result_message;
}

/**
 * Returns an HTML form for sending an email message to an addressee, possibly with an error message.
 * @usedby: u3a_email_contact_shortcode
 */

function show_u3a_contact_form($addressee, $messageSubject, $messageText, $returnName, $returnEmail, $errorMessage, $nonce)
{
    $html = '';
    if ('' != $addressee) {
        $html .= '<p>You can use the form below to send an email to ' . $addressee . '</p>';
    }
    if ('' != $errorMessage) {
        $html .= '<p style="color: #f00; font-weight: bold;">' . $errorMessage . '</p>';
    }
    $html .= '
<form id="mailContact" method="post">
  <input type="hidden" id="nonce" name="nonce" value="' . $nonce . '"/>
  <input type="hidden" name="u3aMQDetect" value="test' . "'" . '\">
  <div id="show-u3a-contact" class="u3aform">
    ';
    $copyHtml = '';
    if (is_user_logged_in()) {
        $copyHtml = '<div><label for="sendCopy">Send me a copy: </label><input type="checkbox" name="sendCopy" id="sendCopy" value="sendCopy"/></div>';
    }
    $html .= '
    <div>
    <label for="returnName">Your name: </label>
    <input type="text" name="returnName" id="returnName" value="' . $returnName . '"/>
    </div>
    <div>
    <label for="returnEmail">Your email address: </label>
    <input type="email" name="returnEmail" id="returnEmail" value="' . $returnEmail . '"/>
    </div>
    <div>
    <label for="messageSubject">Message subject: </label>
    <input type="text" name="messageSubject" id="messageSubject" value="' . $messageSubject . '"/>
    </div>
    <div>
    <label for="messageText">Your message: </label>
    <textarea name="messageText" id="messageText" rows="10">' . $messageText . '</textarea>
    </div>
    ' . $copyHtml . '
    <p class="hasSubmit"><button class="wp-element-button" id="submitButton" name="sendEmail" type="submit">Send your email</button></p>
    </div>
</form>
<script type="text/javascript">
document.forms["mailContact"].elements["returnName"].focus();
</script>
    ';
    return $html;
}

/**
 * Validate the POST data submitted in the form.
 * @return An error message if not validated.
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
}

/** Send the message using wp_mail.
 * @return Empty string on success or error message
 */
function u3a_contact_mail($to, $messageSubject, $messageText, $headers = [])
{
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
    // append header specifying content type (default is text/plain)
    //$headers[] = 'Content-Type: text/html; charset=UTF-8';
    add_filter('wp_mail_content_type', 'u3a_set_wpmail_type');
    // generate plain text alt body from the html
    add_action( 'phpmailer_init','u3a_add_plain_text_body');
    $result = wp_mail($to, $messageSubject, $html_start.$messageText.$html_end, $headers);
    remove_filter('wp_mail_content_type', 'u3a_set_wpmail_type');
    return ($result == true) ? '' : 'wp_mail error';
}

/**
 * Helper function to set wp_mail content type
 */
function u3a_set_wpmail_type() {
    return 'text/html';
}

/**
 * Helper function to ad plain text alternative email content
 */
function u3a_add_plain_text_body( $phpmailer ) {
    $phpmailer->AltBody = wp_strip_all_tags( $phpmailer->Body );
}

/**
 * Helper function to set email FromName.
 * Uses global variable $u3a_contact_form_fromname
 */
function u3a_contact_form_set_fromname( $phpmailer ) {
    global $u3a_contact_form_fromname;
    $phpmailer->FromName = $u3a_contact_form_fromname;
}

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
