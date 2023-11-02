=== u3a-wp-configuration ===
Requires at least: 5.9
Tested up to: 6.3
Stable tag: 5.9
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Provides shortcodes to create a secure contact form for any email recipient

== Description ==

This plugin is part of the u3a SiteWorks project. 
It provides shortcodes to create a contact form links and a secure contact form for any email address. 
It can also configure WordPress to send email to an SMTP server.

= u3a_contact shortcode =

The shortcode requires either 1 or 2 parameters:
* name - the name of the recipient (required)
* email - the recipient's email address (required unless name is already a u3a Contact) 

Example:   
`[u3a_contact name="Freda Smith" email="freda@example.com"]`

If the contact is already included in the u3a Contacts database and an email address is specified there, you can omit the email address.

Example:   
`[u3a_contact name="Freda Smith"]`

You can also use this alternate form:   
`[u3a_contact] Freda Smith [/u3a_contact]`  The spaces around the name are optional.

When the shortcode is rendered, the plugin will create a one-time nummeric code which is included in the link to the contact form.  
This is checked by the contact form logic to avoid spammers targeting the form.
The email address given in the shortcode never appears on the web page.

= u3a_contact_form shortcode =

This should be added to a page which has the page slug 'u3a-contact-form'.
The shortcode may be the only element on the page, but other content may be included.
The shortcode does not process any parameters.

Example:   
`[u3a_contact_form]`


== Frequently Asked Questions ==

Please refer to the documentation on the [SiteWorks website](https://siteworks.u3a.org.uk/u3a-siteworks-training/)

== Changelog ==
= 0.4.98 =
* Release candidate 1
* Update plugin update checker library to v5p2
= 0.4.6 =
* Remove SMTP setup (now in u3a-sitewoks-configuration plugin) 
= 0.4.5 =
* Include missing LICENSE file
* Extend description in this readme to include SMTP mail configuration information
= 0.4.4 =
* Bug 691 Use values from wp_config (if present) to set up PHP Mailer to use TLS and authentication credentials, 
configure PHP Mailer to send both HTML and plain text email content, and add full HMTL wrapper for HTML email
= 0.4.3 =
* Bug 736 Change email validation in shortcode processing to use standard PHP function
= 0.4.2 =
* Changed email validation check to use native PHP function to avoid PHP warnings

= 0.4.1 =
* Enabled message sending sending via wp_mail.  Removed testing output.  Correct handling of apostrophes.  Some rewording of error messages and description.

= 0.3.1 =
* Added support for simpler shortcode format for u3a_contacts

= 0.2.4 =
* Changed plugin name to u3a-siteworks-contact-form

= 0.2.3 =
* Render form submit button as <button> with class wp-element-button so that button styles from theme.json are used.  Remove submit button styling from plugin css file

= 0.2.2 =
* Removed the word "Contact" from displayed text of the shortcode generated link.

= 0.2.1 =
* Add support for plugin updates via the SiteWorks WP Update Server.

= 0.1.x series =
* Initial development code.   SiteWorks 'Alpha' release was 0.1.3


