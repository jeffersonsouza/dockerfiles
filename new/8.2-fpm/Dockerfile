FROM jeffersonsouza/nginx:alpine as php

LABEL maintainer="Jefferson Souza<hsinfo@gmail.com>"
LABEL name="jeffersonsouza/php"

RUN apk --update add ca-certificates

RUN apk update && \
    apk upgrade && \
    apk add --no-cache \
    php82 \
    php82-common \
    php82-fpm \
    php82-sodium \
    php82-intl \
    php82-bcmath \
    php82-mysqli \
    php82-pdo \
    php82-pdo_mysql \
    php82-redis \
    php82-gd \
    php82-json \
    php82-zip \
    php82-dom \
    php82-xml \
    php82-simplexml \
    php82-xmlwriter \
    php82-mbstring \
    php82-curl \
    php82-ctype \
    # php82-pecl-imagick \
    php82-pecl-redis \
    php82-zlib \
    php82-tokenizer \
    php82-fileinfo \
    # php82-brotli \
    php82-opcache && \
    rm -rf /tmp/* /var/cache/apk/* && \
    ln -s /usr/sbin/php-fpm7 /usr/local/bin/php && \
    mkdir /run/php/ && chown -R nginx.www-data /run/php/

COPY config/fpm/php-fpm.conf /etc/php82/php-fpm.d/custom.conf

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
