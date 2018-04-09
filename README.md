user_typo3
========

**Nextcloud SQL user authentication from TYPO3.**

![](https://raw.githubusercontent.com/Lumrenion/nextcloud-user_typo3/master/screenshot.png)

## Getting Started
1. SSH into your server

2. Get into the apps folder of your Nextcloud installation, for example /var/www/nextcloud/apps

3. Git clone this project
```
git clone https://github.com/lumrenion/nextcloud-user_sql.git
```

4. Login your Nextcloud as admin

5. Navigate to Apps from the menu and enable the SQL user backend 

6. Navigate to Admin from menu and switch to Additional Settings, scroll down the page and you will see TYPO3 User Backend settings

## Integration
Input the required database connection settings. 

**Notice:** The password to connect to the TYPO3 database is saved in
plain text in the nextcloud database. Consider using creating a database user with read only access if it is sufficient
for your needs (updating password from within nextcloud and syncing emails towards TYPO3 will not be possible this way).

The hashing method of the password saved in TYPO3's fe_users table will be detected automagically. Selecting a hashing 
method in tab *Additional Configuration* is only required when changing password is enabled.

##Features
The following salted hashing methods are supported:
- md5
- blowfish
- phpass
- pbkdf2

Password changing is disabled by default, but can be enabled in the Admin area. It is recommended to select the crypt_type
that is selected in the TYPO3 extension configuration of saltedpasswords (basic.FE.saltedPWHashingMethod).

You may want to select TYPO3 fe_groups as admin_groups. Users in those groups will be considered admins in nextcloud.

### Currently supported parameters

- sql_driver
- sql_hostname
- sql_username
- sql_password
- sql_database
- set_admin_groups
- set_allow_pwchange
- set_crypt_type
- set_mail_sync_mode
- set_default_domain
- set_strip_domain

## Credits
This app is heavily based on user_sql: https://github.com/nextcloud/user_sql

It was changed to implement all hashing methods TYPO3 supports and developed further to support admin groups. Hashing
methods were directly ported from TYPO3 source code and slightly changed to work in nextcloud.

Code not required for the specific purpose of importing users and groups from TYPO3 was removed from this app. 