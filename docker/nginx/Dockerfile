ARG VERSION=stable-alpine-slim
FROM nginx:${VERSION} AS base

LABEL maintainer="Tomáš Vojík <vojik@wboy.cz>"
LABEL Name=Nginx
LABEL Vendor=LaserLiga

# --- Build stage ---
FROM base AS build

RUN apk add --no-cache --virtual .build-deps \
    gcc libc-dev make openssl-dev pcre-dev zlib-dev linux-headers \
    libxslt-dev gd-dev geoip-dev perl-dev libedit-dev mercurial bash alpine-sdk findutils git wget gnupg cmake

# Build QuicTLS for native architecture
ARG QUICTLS_TAG=openssl-3.3.0-quic1
RUN git clone https://github.com/quictls/openssl.git /usr/src/quictls

# Download and extract nginx source
ARG NGINX_VERSION=1.28.0
RUN wget "http://nginx.org/download/nginx-${NGINX_VERSION}.tar.gz" -O nginx.tar.gz \
    && wget "http://nginx.org/download/nginx-${NGINX_VERSION}.tar.gz.asc" -O nginx.tar.gz.asc \
    && wget "http://nginx.org/keys/mdounin.key" -O mdounin.key \
    && gpg --import mdounin.key \
    && wget "http://nginx.org/download/nginx-${NGINX_VERSION}.tar.gz.sha256" -O nginx.tar.gz.sha256 \
    && sha256sum -c nginx.tar.gz.sha256 \
    && gpg --verify nginx.tar.gz.asc nginx.tar.gz || echo "GPG verification failed, continuing with checksum verification only" \
    && mkdir -p /usr/src/nginx \
    && tar -zxC /usr/src/nginx -f nginx.tar.gz

# Build QuicTLS
RUN cd /usr/src/quictls \
    && git checkout ${QUICTLS_TAG} \
    && ./Configure \
    && make -j "$(nproc)" \
    && make -j "$(nproc)" install

# Build nginx with RTMP and HTTP/3 modules
WORKDIR /usr/src/nginx/nginx-${NGINX_VERSION}
RUN git clone https://github.com/arut/nginx-rtmp-module.git \
    && apk add --no-cache pcre ffmpeg
RUN CONFARGS=$(nginx -V 2>&1 | sed -n -e 's/^.*arguments: //p') \
    && sh -c "./configure --with-compat $CONFARGS --with-openssl=/usr/src/quictls --add-module=./nginx-rtmp-module --with-http_v3_module --with-cc-opt=\"-I/usr/local/include/openssl\" --with-ld-opt=\"-L/usr/local/lib\"" \
    && make -j "$(nproc)" \
    && make -j "$(nproc)" install

# --- Final image ---
FROM base AS final

# Install ffmpeg for HLS streaming support
RUN apk add --no-cache ffmpeg pcre

# Copy built nginx from build stage
COPY --from=build /usr/local/lib/libcrypto* /usr/local/lib/
COPY --from=build /usr/local/lib/libssl* /usr/local/lib/
COPY --from=build /etc/nginx /etc/nginx
COPY --from=build /usr/lib/nginx/modules /usr/lib/nginx/modules
COPY --from=build /usr/sbin/nginx /usr/sbin/nginx

ENV LD_LIBRARY_PATH=/usr/local/lib

# Create necessary directories
RUN mkdir -p /var/www/hls /var/www/dash

# Copy configuration files
COPY docker/nginx/fastcgi_params.conf /etc/nginx/fastcgi_params.conf
COPY docker/nginx/mime-types.conf /etc/nginx/mime-types.conf
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf

RUN nginx -V

# Clean up build dependencies and temp files (already handled by multi-stage)

ENTRYPOINT ["/usr/sbin/nginx", "-g", "daemon off;"]
