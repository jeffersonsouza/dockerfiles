FROM jeffersonsouza/nginx:alpine as php

LABEL maintainer="Jefferson Souza<hsinfo@gmail.com>"

ADD https://dl.bintray.com/php-alpine/key/php-alpine.rsa.pub /etc/apk/keys/php-alpine.rsa.pub

# https://github.com/Hermsi1337/docker-fpm-php/blob/master/conf.d/
# https://github.com/codecasts/php-alpine
RUN apk --update add ca-certificates && \
    echo "https://dl.bintray.com/php-alpine/v3.10/php-7.4" >> /etc/apk/repositories

RUN apk update                       && \
    apk upgrade                      && \
    apk add --no-cache                  \
    php7 \
    php7-common \
    php7-fpm \
    php7-sodium \
    php7-intl                                                \
    php7-bcmath                                              \
    php7-mysqli                                                 \
    php7-pdo                                                 \
    php7-pdo_mysql                                           \
    php7-redis                                               \
    php7-gd \
    php7-json \
    php7-zip \
    php7-dom \
    php7-xml \
    php7-simplexml \
    php7-mbstring \
    php7-curl \
    php7-ctype \
    php7-apcu \
    php7-zlib \
    php7-opcache && \
    ln -s /usr/sbin/php-fpm7 /usr/local/bin/php && \
    mkdir /run/php/ && chown -R nginx.www-data /run/php/

COPY config/fpm/php-fpm.conf /etc/php7/php-fpm.d/custom.conf

FROM php as supervisor

RUN apk update                       && \
    apk upgrade                      && \
    apk add --no-cache                  \
    supervisor \
    rm -rf /tmp/* /var/cache/apk/* && \
    mv /var/www/html/index.html /var/www/html/index.php && \
    echo '<?php phpinfo(); ?>' >> /var/www/html/index.php && \
    sed -i 's/#include/include/g' /etc/nginx/conf.d/default.conf

COPY config/supervisor /etc/supervisor

FROM supervisor

EXPOSE 80 443

STOPSIGNAL SIGTERM

ENTRYPOINT ["/usr/bin/supervisord", "-n", "-c",  "/etc/supervisor/supervisor.conf"]
