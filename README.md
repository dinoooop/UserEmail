# User Email
Most of the web application contain some common user activities like registration, login, password reset etc. The UserEmail wrap a bunch of emails are associated with these functions.


## Stand for...
- Send confirmation email : For confirm register or verify the user email id
- Send welcome email : For successful registration we welcome the user to the application
- Send a password reset link: When user forget the password send an encrypted url to the user registered mail
- Send notification password reset successfully: When the user successfully change the password notify him with an email

## Configuration
Set *link_life* for the auto expiry of the link to maintain security. Also change the #company_name# will make your mail content in set.

