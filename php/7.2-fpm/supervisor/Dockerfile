FROM jeffersonsouza/php:7.2-fpm

LABEL	maintainer="Jefferson Souza"

RUN apk update               && \
    apk upgrade              && \
    apk add --no-cache          \
    supervisor                  \
    nginx                    && \
    rm -rf /tmp/* /var/cache/apk/*

COPY conf/supervisor/supervisor.conf /etc/supervisor.conf
COPY conf/supervisor/php.conf /etc/supervisor/conf.d/
COPY conf/supervisor/nginx.conf /etc/supervisor/conf.d/
COPY conf/nginx.conf /etc/nginx/nginx.conf
COPY conf/default.conf /etc/nginx/conf.d/default.conf

RUN mkdir -p /tmp/nginx && \
    chown nginx /tmp/nginx


EXPOSE 80

STOPSIGNAL SIGTERM

ENTRYPOINT ["/usr/bin/supervisord", "-n", "-c",  "/etc/supervisor.conf"]