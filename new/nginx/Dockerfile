FROM alpine:3.13 as nginx
LABEL	maintainer="Jefferson Souza"

RUN adduser -D -u 1000 -g 1000 -s /bin/sh www-data && \
    apk update && \
    apk upgrade && \
    apk add --no-cache \
    nginx \
    nginx-mod-http-geoip \
    nginx-mod-http-cache-purge \
    nginx-mod-http-headers-more \
    nginx-mod-http-upload-progress && \
    mkdir -p /var/www && \
    chown -R www-data:www-data /var/www && \
    mkdir -p /var/cache/nginx && \
    mkdir -p /var/tmp/nginx && \
    chown -R www-data:www-data /var/cache/nginx && \
    chown -R www-data:www-data /var/lib/nginx && \
    chown -R www-data:www-data /var/tmp/nginx

COPY config /etc/nginx
COPY index.html /var/www/html/index.html
ADD https://raw.githubusercontent.com/mitchellkrogza/nginx-ultimate-bad-bot-blocker/master/conf.d/globalblacklist.conf /etc/nginx/security/lists/globalblacklist.conf

WORKDIR /var/www/html

EXPOSE 80 443

STOPSIGNAL SIGTERM

CMD ["nginx", "-g", "daemon off;"]
