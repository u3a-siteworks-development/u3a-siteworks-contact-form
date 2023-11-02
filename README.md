# u3a contact fom
This plugin provides a means of using a single contact form to allow email addresses to be hidden from public view.
## Usage:
The shortcode *u3a_contact* must have two attributes *name* and *email*.
- *name* sets the name or role of the recipient
- *email* sets the email address to which the email will be sent.
The second attribute can be omitted if the name is the display name of a custom u3a_contact whose email address should be used.

## Example:
	[u3a_contact  name="Treasurer" email="petergrimes@gmail.com"]
## Typical Output:
	<a href="yourdomain/contact_form?id=1234">email Treasurer</a>

## Notes
The id is a one-time value which will expire when the email has been sent or after 90 minutes.

You must create a page yourdomain/contact_form whose only content should be the shortcode:
	[u3a_contact_form]

The contact form can be styled using the class u3aform.
Emails will be sent through your selected email service.

If the user of the form is logged in they will be given the option of receiving a copy of their email message. This is not permitted for people who are not logged in 