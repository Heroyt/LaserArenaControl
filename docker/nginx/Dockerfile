ARG VERSION=alpine
FROM nginx:${VERSION} as nginx

MAINTAINER Tomáš Vojík <vojik@wboy.cz>
LABEL Name=Nginx
LABEL Vendor=LaserLiga

RUN apk add --no-cache --virtual .build-deps \
    gcc \
    libc-dev \
    make \
    openssl-dev \
    pcre-dev \
    zlib-dev \
    linux-headers \
    libxslt-dev \
    gd-dev \
    geoip-dev \
    perl-dev \
    libedit-dev \
    mercurial \
    bash \
    alpine-sdk \
    findutils

SHELL ["/bin/ash", "-eo", "pipefail", "-c"]

# Buld QuicTSL
RUN git clone https://github.com/quictls/openssl.git /usr/src/quictls
WORKDIR /usr/src/quictls
RUN ./Configure && make && make install

# Build nginx
RUN wget "http://nginx.org/download/nginx-${NGINX_VERSION}.tar.gz" -O nginx.tar.gz

RUN rm -rf /usr/src/nginx /usr/src/extra_module &&  \
    mkdir -p /usr/src/nginx && \
    tar -zxC /usr/src/nginx -f nginx.tar.gz

WORKDIR /usr/src/nginx/nginx-${NGINX_VERSION}
RUN git clone https://github.com/arut/nginx-rtmp-module.git

RUN CONFARGS=$(nginx -V 2>&1 | sed -n -e 's/^.*arguments: //p') && \
    sh -c "./configure --with-compat $CONFARGS --add-module=./nginx-rtmp-module --with-http_v3_module --with-cc-opt=\"-I/usr/local/include/openssl\" --with-ld-opt=\"-L/usr/local/lib\"" &&  \
    make && \
    make install

RUN apk add --update pcre ffmpeg

RUN mkdir /var/www
RUN mkdir /var/www/hls
RUN mkdir /var/www/dash

COPY docker/nginx/fastcgi_params.conf /etc/nginx/fastcgi_params.conf
COPY docker/nginx/mime-types.conf /etc/nginx/mime-types.conf
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf