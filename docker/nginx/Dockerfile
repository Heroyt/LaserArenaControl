ARG VERSION=stable-alpine-slim
FROM nginx:${VERSION} as base

LABEL maintainer="Tomáš Vojík <vojik@wboy.cz>"
LABEL Name=Nginx
LABEL Vendor=LaserLiga

# --- Build stage ---
FROM base AS build

RUN apk add --no-cache --virtual .build-deps \
    gcc libc-dev make openssl-dev pcre-dev zlib-dev linux-headers \
    libxslt-dev gd-dev geoip-dev perl-dev libedit-dev mercurial bash alpine-sdk findutils git wget

# Build QuicTLS
RUN git clone https://github.com/quictls/openssl.git /usr/src/quictls \
    && cd /usr/src/quictls \
    && ./Configure \
    && make \
    && make install

# Download and extract nginx source
ARG NGINX_VERSION
RUN wget "http://nginx.org/download/nginx-${NGINX_VERSION}.tar.gz" -O nginx.tar.gz \
    && mkdir -p /usr/src/nginx \
    && tar -zxC /usr/src/nginx -f nginx.tar.gz

# Build nginx with RTMP and HTTP/3 modules
WORKDIR /usr/src/nginx/nginx-${NGINX_VERSION}
RUN git clone https://github.com/arut/nginx-rtmp-module.git \
    && apk add --no-cache pcre ffmpeg \
    && CONFARGS=$(nginx -V 2>&1 | sed -n -e 's/^.*arguments: //p') \
    && sh -c "./configure --with-compat $CONFARGS --add-module=./nginx-rtmp-module --with-http_v3_module --with-cc-opt=\"-I/usr/local/include/openssl\" --with-ld-opt=\"-L/usr/local/lib\"" \
    && make \
    && make install

# --- Final image ---
FROM base AS final

# Install ffmpeg for HLS streaming support
RUN apk add --no-cache ffmpeg

# Copy built nginx from build stage
COPY --from=build /usr/local/nginx/sbin/nginx /usr/sbin/nginx
COPY --from=build /usr/local/nginx/modules /usr/local/nginx/modules

# Create necessary directories
RUN mkdir -p /var/www/hls /var/www/dash

# Copy configuration files
COPY docker/nginx/fastcgi_params.conf /etc/nginx/fastcgi_params.conf
COPY docker/nginx/mime-types.conf /etc/nginx/mime-types.conf
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf

# Clean up build dependencies and temp files (already handled by multi-stage)

ENTRYPOINT ["/usr/sbin/nginx", "-g", "daemon off;"]
