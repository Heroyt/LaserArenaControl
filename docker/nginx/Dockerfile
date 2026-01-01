ARG VERSION=alpine
FROM nginx:${VERSION} AS nginx

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
RUN wget "http://nginx.org/download/nginx-${NGINX_VERSION}.tar.gz" -O nginx.tar.gz &&  \
    rm -rf /usr/src/nginx /usr/src/extra_module && \
    mkdir -p /usr/src/nginx && \
    tar -zxC /usr/src/nginx -f nginx.tar.gz

WORKDIR /usr/src/nginx/nginx-${NGINX_VERSION}

RUN CONFARGS=$(nginx -V 2>&1 | sed -n -e 's/^.*arguments: //p') && \
    sh -c "./configure --with-compat $CONFARGS --with-http_v3_module --with-cc-opt=\"-I/usr/local/include/openssl\" --with-ld-opt=\"-L/usr/local/lib\"" &&  \
    make &&  \
    make install

RUN mkdir -p /var/www &&  \
    mkdir -p /var/www/hls &&  \
    mkdir -p /var/www/dash &&  \
    mkdir -p /var/streams

COPY nginx/fastcgi_params.conf /etc/nginx/fastcgi_params.conf
COPY nginx/mime-types.conf /etc/nginx/mime-types.conf
COPY nginx/nginx.conf /etc/nginx/nginx.conf

EXPOSE 80
EXPOSE 443

STOPSIGNAL SIGTERM

HEALTHCHECK --interval=1m --timeout=3s CMD curl --fail http://127.0.0.1:80/ || exit 1