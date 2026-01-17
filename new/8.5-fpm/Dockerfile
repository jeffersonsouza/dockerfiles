FROM jeffersonsouza/nginx:alpine as php

LABEL maintainer="Jefferson Souza<hsinfo@gmail.com>"
LABEL name="jeffersonsouza/php"

RUN apk --update add ca-certificates

RUN apk update && \
    apk upgrade && \
    apk add --no-cache \
    php85 \
    php85-common \
    php85-fpm \
    php85-sodium \
    php85-intl \
    php85-bcmath \
    php85-mysqli \
    php85-pdo \
    php85-pdo_mysql \
    php85-redis \
    php85-gd \
    php85-json \
    php85-zip \
    php85-dom \
    php85-xml \
    php85-simplexml \
    php85-xmlwriter \
    php85-mbstring \
    php85-curl \
    php85-ctype \
    # php82-pecl-imagick \
    php85-pecl-redis \
    php85-zlib \
    php85-tokenizer \
    php85-fileinfo \
    php85-pdo_sqlite \
    php85-iconv \
    php85-phar \
    php85-pecl-brotli \
#    php85-opcache \
    && rm -rf /tmp/* /var/cache/apk/* \
    && mkdir /run/php/ && chown -R www-data:www-data /run/php/

COPY config/fpm/php-fpm.conf /etc/php85/php-fpm.d/custom.conf

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
    ln -s /usr/bin/php85 /usr/bin/php && \
    php -v

COPY config/supervisor /etc/supervisor

FROM supervisor

EXPOSE 80 443

STOPSIGNAL SIGTERM

ENTRYPOINT ["/usr/bin/supervisord", "-n", "-c",  "/etc/supervisor/supervisor.conf"]

# Configure a healthcheck to validate that everything is up&running
HEALTHCHECK --timeout=10s CMD curl --silent --fail http://127.0.0.1/fpm-ping
