General Information:
--------------------

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
* No JavaScript needed
* Cookies supported, but not needed
* Captcha
* Multiple languages
* Members and guests
* Waiting room for guests
* Moderatoral approval of new guests
* Public, member, moderator and admin only chats
* Private messages
* Multi-line messages
* Change font, colour and refresh rate in profile settings
* Autologout when inactive for some time
* Image embedding
* Notes for admins and moderators
* Clone the chat to have multiple tabs
* Kick chatters
* Clean selected messages
* Clean the whole room
* Plain text message filter
* Regex message filter
* And more

Installation Instructions:
--------------------------

You'll need to have php with intl, gettext, pdo, pcre, mbstring and date extension, and a web-server installed.
You will also need the pdo_sqlite, pdo_mysql or pdo_pgsql extension, depending on which database you choose.
Optionally, you can install:
- the gd extension for the captcha feature
- the json extension for save/restore
- a memcached server and the memcached extension and change the configuration to use memcached. This will lessen the database load a bit.
- a MySQL or PostgreSQL server to use as an external database instead of SQLite
- the libsodium extension (PHP >= 7.2) for encryption of messages and notes in the database
When you have everything installed and use MySQL or PostgreSQL, you'll have to create a database and a user for the chat.
Then edit the configuration at the bottom of the script to reflect the appropriate database settings and to modify the chat settings the way you like them.
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
It can be used e.g. to turn BB Code into html, so it is possible to use BB Code in the chat to format messages.
To do this, use this Regex-Match `\[(u|b)\](.*?)\[\/\1\]` and this Regex-Replace `<$1>$2</$1>` and your text will be `[b]bold[/b]` or `[u]underlined[/u]`.
You can also use smilies by using this Regex-Match `(?-i::(cry|eek|lol|sad|smile|surprised|wink):)` and this Regex-Replace `<img src="/pictures/$1.gif" alt=":$1:">`
And now if you enter `:smile:` an image with the smiley will be loaded from your server at `/pictures/smile.gif`.
The following should be escaped by putting `\` in front of it, if you are trying to match one of these characters `/ \ ^ . $ | ( ) [ ] * + ? { } ,`.
I used `/` as delimiter, so you will have to escape that, too. The only options I used is `i` to make the regex case insensitive.
If you want to test your regex, before applying you can use [this site](http://www.phpliveregex.com/) and enter your Regex and Replacement there and click on preg_replace.
If you never used regex before, check out [this starting guide](http://docs.activestate.com/komodo/4.4/regex-intro.html) to begin with regular expressions.
