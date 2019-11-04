FROM alpine:3.10 as nginx
LABEL	maintainer="Jefferson Souza"

RUN apk update                       && \
    apk upgrade                      && \
    apk add --no-cache                  \
    nginx \
    nginx-mod-http-geoip \
    nginx-mod-http-cache-purge \
    nginx-mod-http-headers-more \
    nginx-mod-http-upload-progress && \
    mkdir -p /tmp/nginx && \
    chown nginx /tmp/nginx

COPY config /etc/nginx
COPY index.html /var/www/html/index.html
ADD https://raw.githubusercontent.com/mitchellkrogza/nginx-ultimate-bad-bot-blocker/master/conf.d/globalblacklist.conf /etc/nginx/security/lists/globalblacklist.conf

WORKDIR /var/www/html

EXPOSE 80 443

STOPSIGNAL SIGTERM

CMD ["nginx", "-g", "daemon off;"]