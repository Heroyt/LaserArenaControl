FROM apline:latest
LABEL authors="Tomáš Vojík"

SHELL ["/bin/ash", "-eo", "pipefail", "-c"]

RUN apk add --update pcre ffmpeg

COPY start.sh /usr/local/start.sh
RUN mkdir /var/log/stream
RUN mkdir /var/stream
RUN mkdir /var/stream/hls
RUN mkdir /var/stream/config

ENTRYPOINT ["/usr/local/start.sh"]