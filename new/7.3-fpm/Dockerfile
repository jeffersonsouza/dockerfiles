FROM alpine:3.10 as php

LABEL	maintainer="Jefferson Souza"

RUN apk update                       && \
    apk upgrade                      && \
    apk add --no-cache                  \
    php7 \
    php7-common \
    php7-fpm \
    php7-sodium

FROM php as nginx

RUN apk update                       && \
    apk upgrade                      && \
    apk add --no-cache                  \
    nginx \
    nginx-mod-http-geoip \
    nginx-mod-http-cache-purge \
    nginx-mod-http-headers-more \
    nginx-mod-http-upload-progress

COPY supervisor/conf/nginx/* /etc/nginx/

FROM nginx as supervisor

RUN apk update                       && \
    apk upgrade                      && \
    apk add --no-cache                  \
    supervisor \
    rm -rf /tmp/* /var/cache/apk/* && \
    mkdir -p /tmp/nginx && \
    chown nginx /tmp/nginx

COPY supervisor/conf/supervisor/* /etc/supervisor/

FROM supervisor

EXPOSE 80 443

STOPSIGNAL SIGTERM

ENTRYPOINT ["/usr/bin/supervisord", "-n", "-c",  "/etc/supervisor.conf"]
