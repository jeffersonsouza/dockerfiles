ARG NGINX_IMAGE=jeffersonsouza/nginx:alpine-3.24
FROM ${NGINX_IMAGE}

LABEL maintainer="Jefferson Souza<hsinfo@gmail.com>"
LABEL name="jeffersonsouza/php"
ENV PHP_INI_DIR=/usr/local/etc/php \
    PHP_FPM_MAX_CHILDREN=1

# Keep the customized Nginx binary and modules while installing prebuilt PHP packages.
RUN test "$(cut -d . -f 1,2 /etc/alpine-release)" = 3.24 && \
    sha256sum /usr/sbin/nginx /usr/lib/nginx/modules/*.so > /tmp/nginx-checksums && \
    apk add --no-cache --upgrade ca-certificates curl supervisor \
        "php85>=8.5.12" \
        php85-fpm \
        php85-bcmath \
        php85-ctype \
        php85-curl \
        php85-dom \
        php85-fileinfo \
        php85-gd \
        php85-iconv \
        php85-intl \
        php85-mbstring \
        php85-mysqli \
        php85-openssl \
        php85-pdo \
        php85-pdo_mysql \
        php85-pdo_sqlite \
        php85-posix \
        php85-sqlite3 \
        php85-xmlreader \
        php85-phar \
        php85-session \
        php85-simplexml \
        php85-sodium \
        php85-tokenizer \
        php85-xml \
        php85-xmlwriter \
        php85-zip \
        php85-zlib \
        php85-pecl-redis \
        php85-pecl-brotli \
        php85-ftp && \
    sha256sum -c /tmp/nginx-checksums && \
    rm /tmp/nginx-checksums && \
    mkdir -p /usr/local/bin /usr/local/sbin /usr/local/etc /usr/share/nginx/fastcgi_cache && \
    ln -s /usr/bin/php85 /usr/local/bin/php && \
    ln -s /usr/sbin/php-fpm85 /usr/local/sbin/php-fpm && \
    ln -s /etc/php85 /usr/local/etc/php && \
    ln -s /etc/php85/php-fpm.d /usr/local/etc/php-fpm.d && \
    ln -s /etc/php85/php-fpm.conf /usr/local/etc/php-fpm.conf && \
    chown www-data:www-data /usr/share/nginx/fastcgi_cache

COPY 8.5-fpm/config/fpm/php-fpm.conf /etc/php85/php-fpm.d/zz-custom.conf
COPY 8.5-fpm/config/php.ini /etc/php85/conf.d/zz-custom.ini
COPY 8.5-fpm/config/supervisor /etc/supervisor
COPY php/ /var/www/html/
COPY nginx/config/nginx.conf /etc/nginx/nginx.conf
COPY nginx/config/http.d/default.conf nginx/config/http.d/php-fpm.conf nginx/config/http.d/cache_config.conf /etc/nginx/http.d/

# hadolint ignore=SC2016
RUN sed -i 's|#include /etc/nginx/http.d/php-fpm.conf;|include /etc/nginx/http.d/php-fpm.conf;|' /etc/nginx/http.d/default.conf && \
    sed -i 's/worker_processes[[:space:]]*auto;/worker_processes 1;/' /etc/nginx/nginx.conf && \
    php -r 'if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 5 || version_compare(PHP_VERSION, "8.5.12", "<")) { fwrite(STDERR, "Unexpected or vulnerable PHP version: " . PHP_VERSION . PHP_EOL); exit(1); }' && \
    php-fpm --test && \
    nginx -t

WORKDIR /var/www/html

EXPOSE 80 443
STOPSIGNAL SIGTERM
ENTRYPOINT ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisor.conf"]
CMD []
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 CMD ["curl", "--silent", "--show-error", "--fail", "--max-time", "4", "http://127.0.0.1/fpm-ping"]
