#!/bin/bash

echo $PWD

php index.php event/server & cron & php-fpm
