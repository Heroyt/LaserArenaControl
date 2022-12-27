#!/bin/bash

/usr/local/bin/php /var/www/index.php games/sync >> /var/www/logs/cron.log 2>&1
