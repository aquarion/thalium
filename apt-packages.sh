#!/bin/bash
export PHPVERS=`php -r ' echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION ;'`
sudo apt install php${PHPVERS}-curl php${PHPVERS}-mbstring php${PHPVERS}-zip php${PHPVERS}-xml
