#!/bin/bash
xgettext --from-code UTF-8 -o locale/le-chat-php.pot `find . -iname '*.php'`
for translation in `find locale -iname '*.po'`; do msgmerge -U "$translation" locale/le-chat-php.pot; msgfmt -o ${translation:0:-2}mo "$translation"; done
