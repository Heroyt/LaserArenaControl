FROM heroyt/lac_core:nginx-latest AS project

# Project files
RUN mkdir -p "/var/www/.npm" && chown -R 33:33 "/var/www/.npm"
RUN apt-get -y install sudo
RUN echo "www-data:pass" | chpasswd && adduser www-data sudo
RUN cron
#USER www-data

# Move to project directory
WORKDIR /var/www/

# Initialize git and download project
RUN git init
RUN mkdir -p modules
RUN git remote add origin https://github.com/Heroyt/LaserArenaControl.git
RUN git fetch --all --tags
RUN git checkout -t origin/master
RUN git config pull.ff only --autostash
RUN git submodule init
RUN git pull --recurse-submodules=yes
RUN git submodule update --init --recursive --remote

# Cron rights
RUN echo "pass" | sudo -S chmod +x /var/www/cron.sh

# Initialize all configs and create necessary directories
RUN mv private/docker-config.ini private/config.ini
RUN mkdir -p logs
RUN mkdir -p temp
RUN mkdir -p lmx
RUN mkdir -p lmx/results
RUN mkdir -p lmx/games

RUN composer update

# Copy shell scripts
COPY start.sh start.sh

# Initialize crontab
RUN crontab -l | { cat; echo "* * * * * /usr/local/bin/php /var/www/bin/scheduler.php >> /var/www/logs/cron.log 2>&1"; } | crontab -

# Install
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer dump-autoload
RUN npm install
RUN npm run build

RUN chmod 0777 -R logs
RUN chmod 0777 -R temp

# Start command
# Updates project, builds it and runs a start script which starts WS event server and Apache
CMD sh ./start.sh