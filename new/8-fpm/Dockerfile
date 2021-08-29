FROM jeffersonsouza/nginx:alpine as php

LABEL maintainer="Jefferson Souza<hsinfo@gmail.com>"

# ADD https://dl.bintray.com/php-alpine/key/php-alpine.rsa.pub /etc/apk/keys/php-alpine.rsa.pub

# https://github.com/Hermsi1337/docker-fpm-php/blob/master/conf.d/
# https://github.com/codecasts/php-alpine
RUN apk --update add ca-certificates

RUN apk update && \
    apk upgrade && \
    apk add --no-cache \
    php8 \
    php8-common \
    php8-fpm \
    php8-sodium \
    php8-intl \
    php8-bcmath \
    php8-mysqli \
    php8-pdo \
    php8-pdo_mysql \
    php8-redis \
    php8-gd \
    php8-json \
    php8-zip \
    php8-dom \
    php8-xml \
    php8-simplexml \
    php8-xmlwriter \
    php8-mbstring \
    php8-curl \
    php8-ctype \
    php8-pecl-imagick \
    php8-pecl-redis \
    php8-zlib \
    php8-tokenizer \
    php8-fileinfo \
    php8-opcache && \
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
