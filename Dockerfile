FROM heroyt/lac_core:latest AS project

COPY cron /etc/cron.d/lac-cron
RUN chmod 0644 /etc/cron.d/lac-cron &&\
    crontab /etc/cron.d/lac-cron
COPY cron.sh /var/www/html/cron.sh
RUN chmod +x /var/www/html/cron.sh

# Project files
RUN mkdir -p "/var/www/.npm" && chown -R 33:33 "/var/www/.npm"
RUN apt-get -y install sudo
RUN echo "www-data:pass" | chpasswd && adduser www-data sudo
RUN cron
USER www-data

# Move to project directory
WORKDIR /var/www/html/

# Initialize git and download project
RUN git init && git remote add origin https://github.com/Heroyt/LaserArenaControl.git && git fetch && git checkout -t origin/master
RUN git config pull.ff only
RUN git pull --recurse-submodules
RUN git submodule init
RUN git submodule update
RUN git submodule update --init --recursive --remote

# Initialize all configs and create necessary directories
RUN mv private/docker-config.ini private/config.ini
RUN mkdir -p logs
RUN mkdir -p temp
RUN mkdir -p lmx
RUN mkdir -p lmx/results
RUN mkdir -p lmx/games

RUN composer update

# Copy shell scripts
COPY start.sh .

# Initialize crontab
COPY cron.txt .
RUN crontab cron.txt

# Start command
# Updates project, builds it and runs a start script which starts WS event server and Apache
CMD git pull --recurse-submodules && git submodule update --init --recursive --remote && composer build && php install.php && sh ./start.sh