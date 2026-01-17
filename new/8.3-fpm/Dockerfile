FROM jeffersonsouza/nginx:alpine as php

LABEL maintainer="Jefferson Souza<hsinfo@gmail.com>"
LABEL name="jeffersonsouza/php"

RUN apk --update add ca-certificates

RUN apk update && \
    apk upgrade && \
    apk add --no-cache \
    php83 \
    php83-common \
    php83-fpm \
    php83-sodium \
    php83-intl \
    php83-bcmath \
    php83-mysqli \
    php83-pdo \
    php83-pdo_mysql \
    php83-redis \
    php83-gd \
    php83-json \
    php83-zip \
    php83-dom \
    php83-xml \
    php83-simplexml \
    php83-xmlwriter \
    php83-mbstring \
    php83-curl \
    php83-ctype \
    php83-pecl-imagick \
    php83-pecl-redis \
    php83-zlib \
    php83-tokenizer \
    php83-fileinfo \
    php83-pdo_sqlite \
    php83-iconv \
    php83-phar \
    php83-pecl-brotli \
    php83-opcache && \
    rm -rf /tmp/* /var/cache/apk/* && \
    mkdir /run/php/ && chown -R www-data:www-data /run/php/

COPY config/fpm/php-fpm.conf /etc/php83/php-fpm.d/custom.conf

FROM php as supervisor

RUN apk update && \
    apk upgrade && \
    apk add --no-cache \
    supervisor \
    curl && \
    rm -rf /tmp/* /var/cache/apk/* && \
    mv /var/www/html/index.html /var/www/html/index.php && \
    echo '<?php phpinfo(); ?>' >> /var/www/html/index.php && \
    sed -i 's/#include/include/g' /etc/nginx/http.d/default.conf && \
    # ln -s /usr/bin/php83 /usr/bin/php && \
    php -v

COPY config/supervisor /etc/supervisor

FROM supervisor

EXPOSE 80 443

STOPSIGNAL SIGTERM

ENTRYPOINT ["/usr/bin/supervisord", "-n", "-c",  "/etc/supervisor/supervisor.conf"]

# Configure a healthcheck to validate that everything is up&running
HEALTHCHECK --timeout=10s CMD curl --silent --fail http://127.0.0.1/fpm-ping
