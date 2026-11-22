ARG NGINX_IMAGE=jeffersonsouza/nginx:alpine-edge
FROM ${NGINX_IMAGE}

LABEL maintainer="Jefferson Souza<hsinfo@gmail.com>"
LABEL name="jeffersonsouza/php"
ENV PHP_INI_DIR=/usr/local/etc/php \
    PHP_FPM_MAX_CHILDREN=1

# Keep the customized Nginx binary and modules while installing prebuilt PHP packages.
RUN grep -Eq '^https?://[^[:space:]]+/edge/main/?$' /etc/apk/repositories && \
    printf '%s\n' '@testing https://dl-cdn.alpinelinux.org/alpine/edge/testing' >> /etc/apk/repositories && \
    sha256sum /usr/sbin/nginx /usr/lib/nginx/modules/*.so > /tmp/nginx-checksums && \
    apk add --no-cache --upgrade ca-certificates curl supervisor \
        "php86@testing>=8.6.0" \
        php86-fpm@testing \
        php86-bcmath@testing \
        php86-ctype@testing \
        php86-curl@testing \
        php86-dom@testing \
        php86-fileinfo@testing \
        php86-gd@testing \
        php86-iconv@testing \
        php86-intl@testing \
        php86-mbstring@testing \
        php86-mysqli@testing \
        php86-openssl@testing \
        php86-pdo@testing \
        php86-pdo_mysql@testing \
        php86-pdo_sqlite@testing \
        php86-posix@testing \
        php86-sqlite3@testing \
        php86-xmlreader@testing \
        php86-phar@testing \
        php86-session@testing \
        php86-simplexml@testing \
        php86-sodium@testing \
        php86-tokenizer@testing \
        php86-xml@testing \
        php86-xmlwriter@testing \
        php86-zip@testing \
        php86-pecl-redis@testing \
        php86-pecl-brotli@testing \
        php86-ftp@testing && \
    sha256sum -c /tmp/nginx-checksums && \
    rm /tmp/nginx-checksums && \
    mkdir -p /usr/local/bin /usr/local/sbin /usr/local/etc /usr/share/nginx/fastcgi_cache && \
    ln -s /usr/bin/php86 /usr/local/bin/php && \
    ln -s /usr/sbin/php-fpm86 /usr/local/sbin/php-fpm && \
    ln -s /etc/php86 /usr/local/etc/php && \
    ln -s /etc/php86/php-fpm.d /usr/local/etc/php-fpm.d && \
    ln -s /etc/php86/php-fpm.conf /usr/local/etc/php-fpm.conf && \
    chown www-data:www-data /usr/share/nginx/fastcgi_cache

COPY 8.6-fpm/config/fpm/php-fpm.conf /etc/php86/php-fpm.d/zz-custom.conf
COPY 8.6-fpm/config/php.ini /etc/php86/conf.d/zz-custom.ini
COPY 8.6-fpm/config/supervisor /etc/supervisor
COPY php/ /var/www/html/
COPY nginx/config/nginx.conf /etc/nginx/nginx.conf
COPY nginx/config/http.d/default.conf nginx/config/http.d/php-fpm.conf nginx/config/http.d/cache_config.conf /etc/nginx/http.d/

# hadolint ignore=SC2016
RUN sed -i 's|#include /etc/nginx/http.d/php-fpm.conf;|include /etc/nginx/http.d/php-fpm.conf;|' /etc/nginx/http.d/default.conf && \
    sed -i 's/worker_processes[[:space:]]*auto;/worker_processes 1;/' /etc/nginx/nginx.conf && \
    php -r 'if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 6 || version_compare(PHP_VERSION, "8.6.0", "<")) { fwrite(STDERR, "Unexpected or vulnerable PHP version: " . PHP_VERSION . PHP_EOL); exit(1); }' && \
    php-fpm --test && \
    nginx -t

WORKDIR /var/www/html

EXPOSE 80 443
STOPSIGNAL SIGTERM
ENTRYPOINT ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisor.conf"]
CMD []
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 CMD ["curl", "--silent", "--show-error", "--fail", "--max-time", "4", "http://127.0.0.1/fpm-ping"]
