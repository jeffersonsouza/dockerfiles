FROM jeffersonsouza/nginx:alpine as php

LABEL maintainer="Jefferson Souza<hsinfo@gmail.com>"

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
    php7-gd

COPY config/fpm /etc/php/fpm

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
