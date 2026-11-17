General Information:
--------------------
**WARNING: WE DO NOT RECOMMEND UPGRADING FROM v1.x.x TO v2.x.x AS IT HAS NOT BEEN TESTED. TRY AT YOUR OWN RISK. WE ARE NOT RESPONSIBLE FOR ANY DAMAGE CAUSED.**

This is a PHP Chat based on LE CHAT v.1.14. An up-to-date copy of this script can be downloaded at https://github.com/DanWin/le-chat-php
The original perl LE CHAT script by Lucky Eddie can be downloaded at [this github fork](https://github.com/virtualghetto/lechat).
If you add your own cool features or have a feature request, please tell me and I will add them, if I like them.
Please also let me know about any bugs you find in the code, so I can fix them.
Now a piece of information about the origin of the name "LE CHAT" copied from the original script:
The "LE" in the name you can take as  "Lucky Eddie", or since the script was meant to be lean and easy on server resources, as "Light Edition".
It may even be the French word for "the" if you prefer. Translated from French to English, "le chat" means: "the cat".

Features:
---------

* Optimized for Tor
* No JavaScript required or emitted; works with Tor Browser's Safest security level
* Cookies supported, but not needed
* CAPTCHA
* Multiple languages (20+ languages)
* Members and guests
* Waiting room for guests
* Moderatoral approval of new guests
* Public, member, moderator and admin only chats
* Admin-created chatrooms with built-in-role and custom-role access restrictions
* Custom permission roles alongside the built-in roles
* Private messages
* Multi-line messages
* Change font, colour and refresh rate in profile settings
* Autologout when inactive for some time
* Image embedding
* Notes for admins and moderators
* Message reporting, a staff review inbox and an auditable staff log
* PGP-authenticated self-service password resets for accounts with a PGP key configured
* Passwordless PGP login, no-script emoji reactions and clickable reply links
* Optional encrypted email addresses for verification, passwordless login, password reset and email 2FA
* Message numbers shown before each message for easy reference
* Admin, staff, public and personal notes encrypted in the database
* Clone the chat to have multiple tabs
* Kick chatters
* Clean selected messages
* Clean the whole room
* Plain text message filter
* Regex message filter
* 2FA via PGP
* And more

Administration:
----------------

After upgrading, visit the Setup page once to apply the database updates. Admins can create custom roles and assign them to members; custom permissions supplement the existing built-in roles. Staff with room-management permission can create rooms restricted by built-in role and, optionally, a custom role. Members can report messages for review in the staff inbox. PGP login and password recovery require a PGP public key; email login and password reset require a verified email address.

Staff with moderation access can kick reported users from the staff inbox; this applies the configured kick penalty. Staff-log entries, including usernames and deleted-message contents, are encrypted at rest with the database encryption key and decrypted for authorized viewers. Existing log entries are encrypted when you visit the Setup page after upgrading. Because the log can contain private message content, grant staff-log access only to trusted staff and keep `ENCRYPTKEY_PASS` backed up securely.

Members can optionally add an email address in their profile. The address must be verified before it can be used for email login, password reset, or email two-factor authentication, and it is encrypted before it is stored in the database. Configure SMTP and database credentials in the configuration section at the bottom of `chat.php`. Use TLS or SSL for SMTP and protect the script because it contains credentials. When both PGP and email are configured for two-factor authentication, PGP is used for password logins; passwordless login and password reset let the member choose either configured method. Backup files include decrypted email addresses and must be kept secure.

Database note encryption, staff-log encryption, and optional message encryption require `libsodium`. New encrypted data uses XChaCha20-Poly1305 with a fresh random nonce per value; the updater migrates legacy AES-GCM data while retaining read compatibility for old ciphertext. Set `ENCRYPTKEY_PASS` to a unique, high-entropy secret before relying on database encryption, and preserve that secret in a secure backup. Encryption protects database contents at rest, not data from a compromised application server.

To configure the application, edit the database, encryption, and SMTP constants in the configuration section at the bottom of `chat.php`. `SMTP_PORT` defaults to `587`, and `SMTP_SECURITY` accepts `tls`, `ssl`, or `none`. Set unique encryption keys for a new installation. When upgrading an existing deployment, retain its current `ENCRYPTKEY_PASS` and `AES_IV_PASS` values so encrypted data remains readable.

Messages support safe inline BBCode (`[b]`, `[i]`, `[u]`, `[s]`, `[quote]`, `[code]`, and `[url]`) and common Markdown emphasis, links, headings, quotes, lists, and code. Open a separate JavaScript-free PM window at `chat.php?window=pm` while logged in.

Installation Instructions:
--------------------------

You'll need to have php with `gettext`, `pdo`, `pcre`, `mbstring`, `date` and `sodium` extensions, and a web-server installed. The `sodium` extension is required for encrypted staff logs.
You will also need the `pdo_sqlite`, `pdo_mysql` or `pdo_pgsql` extension, depending on which database you choose.
Optionally, you can install:
- the `gd` extension for the captcha feature
- the `json` extension for save/restore
- the `intl` extension for browser language detection
- a memcached server and the memcached extension and change the configuration to use memcached. This will lessen the database load a bit.
- a MySQL or PostgreSQL server to use as an external database instead of SQLite
When you have everything installed and use MySQL or PostgreSQL, you'll have to create a database and a user for the chat.
Edit the database credentials and encryption secrets in the configuration section at the bottom of the script. Protect the script file because it contains these settings.
Then copy the script to your web-server directory and call the script in your browser with a parameter like this:
	http://(server)/(script-name).php?action=setup
Now you can create the Superadmin account. With this account you can administer the chat and add new members and set the guest access.
As soon as you are done with the setup, all necessary database tables will be created and the chat can be used.
Note: If you updated the script, please visit http://(server)/(script-name).php?action=setup again, to make sure, that any database changes are applied and no errors occur.

Translating:
------------

Translations are managed in [Weblate](https://weblate.danwin1210.de/projects/DanWin/le-chat-php).
If you prefer manually submitting translations, the script `update-translations.sh` can be used to update the language template and translation files from source.
It will generate the file `locale/le-chat-php.pot` which you can then use as basis to create a new language file in `YOUR_LANG_CODE/LC_MESSAGES/le-chat-php.po` and edit it with a translation program, such as [Poedit](https://poedit.net/).
Once you are done, you can open a pull request, or [email me](mailto:daniel@danwin1210.de), to include the translation.

Regex:
------

Yes, the chat supports regular expression filtering of messages. As regex tends to be difficult for most people, I decided to give it an extra section here.
Regex is very powerful and can be used to filter messages that contain certain expressions and replace them with something else.
It can be used e.g. to turn BBCode into HTML, so it is possible to use BBCode in the chat to format messages.
To do this, use this Regex-Match `\[(u|b)\](.*?)\[\/\1\]` and this Regex-Replace `<$1>$2</$1>` and your text will be `[b]bold[/b]` or `[u]underlined[/u]`.
You can also use smilies by using this Regex-Match `(?-i::(cry|eek|lol|sad|smile|surprised|wink):)` and this Regex-Replace `<img src="/pictures/$1.gif" alt=":$1:">`
And now if you enter `:smile:` an image with the smiley will be loaded from your server at `/pictures/smile.gif`.
The following should be escaped by putting `\` in front of it, if you are trying to match one of these characters `/ \ ^ . $ | ( ) [ ] * + ? { } ,`.
I used `/` as delimiter, so you will have to escape that, too. The only options I used is `i` to make the regex case insensitive.
If you want to test your regex, before applying you can use [this site](http://www.phpliveregex.com/) and enter your Regex and Replacement there and click on preg_replace.
If you never used regex before, check out [this starting guide](http://docs.activestate.com/komodo/4.4/regex-intro.html) to begin with regular expressions.
