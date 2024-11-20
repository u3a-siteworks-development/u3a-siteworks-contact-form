=== u3a-siteworks-contact-form ===
Requires at least: 5.9
Tested up to: 6.7
Stable tag: 5.9
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Provides shortcodes to create a secure contact form for any email recipient

== Description ==

This plugin is part of the u3a SiteWorks project. 
It provides shortcodes to create a contact form links and a secure contact form for any email address. 
It can also configure WordPress to send email to an SMTP server.
The name, email address and email subject entered by a user into the form may be logged to aid in the monitoring of spam email. This logging maybe enabled/disabled.

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

When the shortcode is rendered, the plugin will create a nummeric code which is included in the link to the contact form.  
This is checked by the contact form logic to avoid spammers targeting the form.
The email address given in the shortcode never appears on the web page.

There is a third parameter available for advanced users:
* slug - the slug of the page that contains the u3a_contact_form

Example:
`[u3a_contact name="Freda Smith" slug="slug-of-page"]`

This will override the default behaviour which is to use the page with the slug 'u3a-contact-form'.


= u3a_contact_form shortcode =

This should be added to a page which has the page slug 'u3a-contact-form'.
The shortcode may be the only element on the page, but other content may be included.
The shortcode does not process any parameters.

Example:   
`[u3a_contact_form]`


== Frequently Asked Questions ==

Please refer to the documentation on the [SiteWorks website](https://siteworks.u3a.org.uk/u3a-siteworks-training/)

== Changelog ==

* Feature 1093 - Add prefix 'u3a enquiry: ' to message subject line
* Feature 1092 - Add field for phone number to the contact form (not a required field).
= 1.1.2 =
* Bug 1080: Autofill name and email address for a logged in user to avoid "Send me a copy" failure if
email address only differs in letter case
= 1.1.1 
* Feature 1071 - Add support for alternate contact form pages defined by the slug parameter in the shortcode
= 1.1.0 =
* Added an optional log of email sent by the contact form. The log may be viewed by an 'administrator' user  
* Avoid changing the contact id too frequently, to enable pages contining the id to be cached. 
= 1.0.1 =
* Bug 987 - Amend start of email message text to include the addressee. (Nov 2023)
= 1.0.0 =
* First production code release
* Tested up to WordPress 6.4
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


