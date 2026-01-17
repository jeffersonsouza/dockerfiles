FROM jeffersonsouza/nginx:alpine as php

LABEL maintainer="Jefferson Souza<hsinfo@gmail.com>"
LABEL name="jeffersonsouza/php"

RUN apk --update add ca-certificates

RUN apk update && \
    apk upgrade && \
    apk add --no-cache \
    php83 \
    php84-common \
    php84-fpm \
    php84-sodium \
    php84-intl \
    php84-bcmath \
    php84-mysqli \
    php84-pdo \
    php84-pdo_mysql \
    php84-redis \
    php84-gd \
    php84-json \
    php84-zip \
    php84-dom \
    php84-xml \
    php84-simplexml \
    php84-xmlwriter \
    php84-mbstring \
    php84-curl \
    php84-ctype \
    php84-pecl-imagick \
    php84-pecl-redis \
    php84-zlib \
    php84-tokenizer \
    php84-fileinfo \
    php84-pdo_sqlite \
    php84-iconv \
    php84-phar \
    php84-pecl-brotli \
    php84-opcache && \
    rm -rf /tmp/* /var/cache/apk/* && \
    mkdir /run/php/ && chown -R www-data:www-data /run/php/

COPY config/fpm/php-fpm.conf /etc/php84/php-fpm.d/custom.conf

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
#    ln -s /usr/bin/php84 /usr/bin/php && \
    php -v

COPY config/supervisor /etc/supervisor

FROM supervisor

EXPOSE 80 443

STOPSIGNAL SIGTERM

ENTRYPOINT ["/usr/bin/supervisord", "-n", "-c",  "/etc/supervisor/supervisor.conf"]

# Configure a healthcheck to validate that everything is up&running
HEALTHCHECK --timeout=10s CMD curl --silent --fail http://127.0.0.1/fpm-ping
