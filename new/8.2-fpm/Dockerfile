ARG NGINX_IMAGE=jeffersonsouza/nginx:alpine-3.23

FROM php:8.2.35-fpm-alpine3.23 AS php-runtime

COPY --from=ghcr.io/mlocati/php-extension-installer:2@sha256:ac4b252c153a9f6de5578ff2c417790aba836bb49855543144606033eceda253 /usr/bin/install-php-extensions /usr/local/bin/

RUN apk upgrade --no-cache && \
    install-php-extensions bcmath gd intl mysqli pdo_mysql redis zip opcache && \
    rm /usr/local/bin/install-php-extensions && \
    cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

FROM ${NGINX_IMAGE} AS runtime

LABEL maintainer="Jefferson Souza<hsinfo@gmail.com>"
LABEL name="jeffersonsouza/php"
ENV PHP_INI_DIR=/usr/local/etc/php \
    PHP_FPM_MAX_CHILDREN=1

COPY --from=php-runtime /usr/local/ /usr/local/
COPY --from=php-runtime /etc/apk/world /tmp/php-runtime-dependencies

# Add PHP runtime libraries without replacing the customized Nginx installation.
RUN test "$(cut -d . -f 1,2 /etc/alpine-release)" = 3.23 && \
    sha256sum /usr/sbin/nginx /usr/lib/nginx/modules/*.so > /tmp/nginx-checksums && \
    xargs apk add --no-cache --upgrade supervisor < /tmp/php-runtime-dependencies && \
    sha256sum -c /tmp/nginx-checksums && \
    rm /tmp/php-runtime-dependencies /tmp/nginx-checksums && \
    mkdir -p /usr/share/nginx/fastcgi_cache && \
    chown www-data:www-data /usr/share/nginx/fastcgi_cache

COPY 8.2-fpm/config/fpm/php-fpm.conf /usr/local/etc/php-fpm.d/zz-custom.conf
COPY 8.2-fpm/config/php.ini /usr/local/etc/php/conf.d/zz-custom.ini
COPY 8.2-fpm/config/supervisor /etc/supervisor
COPY php/ /var/www/html/
COPY nginx/config/nginx.conf /etc/nginx/nginx.conf
COPY nginx/config/http.d/default.conf nginx/config/http.d/php-fpm.conf nginx/config/http.d/cache_config.conf /etc/nginx/http.d/

# hadolint ignore=SC2016
RUN sed -i 's|#include /etc/nginx/http.d/php-fpm.conf;|include /etc/nginx/http.d/php-fpm.conf;|' /etc/nginx/http.d/default.conf && \
    sed -i 's/worker_processes[[:space:]]*auto;/worker_processes 1;/' /etc/nginx/nginx.conf && \
    php -r 'if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 2 || version_compare(PHP_VERSION, "8.2.35", "<")) { fwrite(STDERR, "Unexpected or vulnerable PHP version: " . PHP_VERSION . PHP_EOL); exit(1); }' && \
    php-fpm --test && \
    nginx -t

WORKDIR /var/www/html

EXPOSE 80 443
STOPSIGNAL SIGTERM
ENTRYPOINT ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisor.conf"]
CMD []
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 CMD ["curl", "--silent", "--show-error", "--fail", "--max-time", "4", "http://127.0.0.1/fpm-ping"]
