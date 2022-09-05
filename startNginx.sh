#!/bin/bash

echo $PWD

php index.php event/server &

php-fpm
