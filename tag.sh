#!/usr/bin/env bash

[[ -z $1 ]] && echo 'give a version please' && exit 1

version=$1

# version bump commit
sed -e "s/\(\$application = new Application('.*', \).*\().*\)/\1'$version'\2/" \
    -i cli/console.php
git commit -asm "bump to version: $version"

cat > tag-message.txt <<EOF
Pimcore Migrations $version
===========================

*
EOF

vim tag-message.txt

git tag -a $version -F tag-message.txt

rm tag-message.txt
