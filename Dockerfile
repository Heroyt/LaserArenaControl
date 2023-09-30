FROM heroyt/lac_core:latest AS project

COPY cron /etc/cron.d/lac-cron
RUN chmod 0644 /etc/cron.d/lac-cron &&\
    crontab /etc/cron.d/lac-cron

# Project files
RUN mkdir -p "/var/www/.npm" && chown -R 33:33 "/var/www/.npm"
RUN apt-get -y install sudo
RUN echo "www-data:pass" | chpasswd && adduser www-data sudo
RUN cron
USER www-data

# Move to project directory
WORKDIR /var/www/html/

ENV LAC_VERSION="0.4.5"
ENV LAC_MODELS_VERSION="0.4.3"

# Initialize git and download project
RUN git init
RUN git remote add origin https://github.com/Heroyt/LaserArenaControl.git
RUN git fetch --all --tags
RUN git checkout -t origin/master
RUN git config pull.ff only --autostash
RUN git pull --recurse-submodules=yes
RUN git submodule init
RUN git submodule update
RUN git submodule update --init --recursive --remote
RUN git checkout "v${LAC_VERSION}" -b "v${LAC_VERSION}"
RUN git -C src/GameModels fetch --all --tags
RUN git -C src/GameModels checkout "v${LAC_MODELS_VERSION}" -b "v${LAC_MODELS_VERSION}"

# Cron rights
RUN echo "pass" | sudo -S chmod +x /var/www/html/cron.sh

# Initialize all configs and create necessary directories
RUN mv private/docker-config.ini private/config.ini
RUN mkdir -p logs
RUN mkdir -p temp
RUN mkdir -p lmx
RUN mkdir -p lmx/results
RUN mkdir -p lmx/games

RUN composer update

# Copy shell scripts
COPY startApache.sh start.sh

# Initialize crontab
COPY cron.txt .
RUN crontab cron.txt

# Install
RUN composer build

# Start command
# Updates project, builds it and runs a start script which starts WS event server and Apache
CMD php install.php && sh ./start.sh