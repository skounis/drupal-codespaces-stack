#!/bin/bash

# This is a simple script to pull down the specified version of editoria11y from github

GIT_REF="2.3.x"

mkdir -p tmp/
cd tmp/
git clone git@github.com:itmaybejj/editoria11y.git .
git checkout $GIT_REF
rm -rf ../library/js
rm -rf ../library/css
rm -rf ../library/dist
mv js ../library/js
mv css ../library/css

# This duplicate is temporary for a few releases after 2.1.11, until everybody clears cache.
mkdir ../library/js/dist
cp dist/editoria11y.min.js ../library/js/dist/

mv dist ../library/dist
cd ../
rm -rf tmp
