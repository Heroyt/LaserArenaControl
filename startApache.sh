#!/bin/bash

echo $PWD

php index.php event/server &

apache2-foreground
