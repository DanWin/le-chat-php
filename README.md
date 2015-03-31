General Information:
--------------------

This is a PHP Chat based on LE CHAT v.1.14 . An up-to-date copy of this script can be downloaded at http://github.com/DanWin/le-chat-php
The original perl LE CHAT script by Lucky Eddie can be downloaded at [his site](http://4fvfamdpoulu2nms.onion/lechat/) or via a tor2web proxy like [this one](https://4fvfamdpoulu2nms.tor2web.blutmagie.de/lechat/) if you don't have TOR installed.
If you add your own cool features or have a feature request, please tell me and I will add them, if I like them.
Please also let me know about any bugs you find in the code, so I can fix them.
Now a piece of information about the origin of the name "LE CHAT" copied from the original script:
The "LE" in the name you can take as  "Lucky Eddie", or since the script was meant to be lean and easy on server resources, as "Light Edition".
It may even be the French word for "the" if you prefer. Translated from French to English, "le chat" means: "the cat".

Features:
---------

* Optimized for TOR
* No JavaScript
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
* Image and video embedding
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

You'll need to have mysql, php and a web-server installed. For the captcha feature, you also need php5-gd.
When you have everything installed, you'll have to create a database and a user for the chat in mysql.
Then edit the configuration at the bottom of the script to reflect the appropriate database settings and to modify the chat settings the way you like them.
Then copy the script to your web-server directory and call the script in your browser with a parameter like this:
	http://(server)/(script-name).php?action=setup
Now you can create the Superadmin account. With this account you can administer the chat and add new members and set the guest access.
As soon as you are done with the setup, all necessary database tables will be created and the chat can be used.

Translating:
------------

Copy lang_en.php and rename it to lang_YOUR_LANGCODE.php
Then edit the file and translate the messages into your language.
If you ever use a ' character, you have to escape it by using \' instead or the script will fail.
When you are done, you have to edit the chat script, to include your translation. Simply add a line with
		'lang_code'	=>'Language name',
to the $L array in the load_lang() function at the bottom, similar to what I did for the German translation.
Please share your translation with me, so I can add it to the official version.

Live demo:
----------

If you want to see the script in action, you can visit my [TOR hidden site](http://tt3j2x4k5ycaa5zt.onion/chat.php) or via a tor2web proxy like [this one](http://tt3j2x4k5ycaa5zt.tor2web.blutmagie.de/chat.php) if you don't have TOR installed.
Considering this is a hidden service, you should be prepared for the worst case, as people often tend to do illegal activities in the TOR network. I'm not online 24/7 so it might not be possible to remove such content right away.
If you should see illegal content, don't panic. Use the contact form on my site to notify me and clean your browser cache afterwards. I will remove the content as soon as possible.
