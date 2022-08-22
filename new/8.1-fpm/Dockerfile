FROM jeffersonsouza/nginx:alpine as php

LABEL maintainer="Jefferson Souza<hsinfo@gmail.com>"

# ADD https://dl.bintray.com/php-alpine/key/php-alpine.rsa.pub /etc/apk/keys/php-alpine.rsa.pub

# https://github.com/Hermsi1337/docker-fpm-php/blob/master/conf.d/
# https://github.com/codecasts/php-alpine
RUN apk --update add ca-certificates

RUN apk update && \
    apk upgrade && \
    apk add --no-cache \
    php81 \
    php81-common \
    php81-fpm \
    php81-sodium \
    php81-intl \
    php81-bcmath \
    php81-mysqli \
    php81-pdo \
    php81-pdo_mysql \
    php81-redis \
    php81-gd \
    php81-json \
    php81-zip \
    php81-dom \
    php81-xml \
    php81-simplexml \
    php81-xmlwriter \
    php81-mbstring \
    php81-curl \
    php81-ctype \
    php81-pecl-imagick \
    php81-pecl-redis \
    php81-zlib \
    php81-tokenizer \
    php81-fileinfo \
    php81-brotli \
    php81-opcache && \
    rm -rf /tmp/* /var/cache/apk/* && \
    ln -s /usr/sbin/php-fpm7 /usr/local/bin/php && \
    mkdir /run/php/ && chown -R nginx.www-data /run/php/

COPY config/fpm/php-fpm.conf /etc/php8/php-fpm.d/custom.conf

FROM php as supervisor

RUN apk update && \
    apk upgrade && \
    apk add --no-cache \
    supervisor \
    curl && \
    rm -rf /tmp/* /var/cache/apk/* && \
    mv /var/www/html/index.html /var/www/html/index.php && \
    echo '<?php phpinfo(); ?>' >> /var/www/html/index.php && \
    sed -i 's/#include/include/g' /etc/nginx/http.d/default.conf

COPY config/supervisor /etc/supervisor

FROM supervisor

EXPOSE 80 443

STOPSIGNAL SIGTERM

ENTRYPOINT ["/usr/bin/supervisord", "-n", "-c",  "/etc/supervisor/supervisor.conf"]

# Configure a healthcheck to validate that everything is up&running
HEALTHCHECK --timeout=10s CMD curl --silent --fail http://127.0.0.1/fpm-ping
