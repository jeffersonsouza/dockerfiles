FROM php:7.3-fpm-alpine

LABEL	maintainer="Jefferson Souza"

ARG     PHPREDIS_VERSION="${PHPREDIS_VERSION:-4.2.0}"
ENV     PHPREDIS_VERSION="${PHPREDIS_VERSION}"

ADD     https://github.com/phpredis/phpredis/archive/${PHPREDIS_VERSION}.tar.gz /tmp/


RUN apk update                       && \
    apk upgrade                      && \
    docker-php-source extract        && \
    apk add --no-cache                  \
    --virtual .build-dependencies   \
    $PHPIZE_DEPS                \
    zlib-dev                    \
    cyrus-sasl-dev              \
    git                         \
    autoconf                    \
    g++                         \
    libtool                     \
    make                        \
    pcre-dev                    \
    tini                            \
    libintl                         \
    icu                             \
    icu-dev                         \
    libxml2-dev                     \
    postgresql-dev                  \
    freetype-dev                    \
    libjpeg-turbo-dev               \
    libpng-dev                      \
    libwebp-dev                      \
    gmp                             \
    gmp-dev                         \
    libmemcached-dev                \
    imagemagick-dev                 \
    libzip-dev                      \
    libmcrypt                         \
    libmcrypt-dev                         \
    libssh2                         \
    libssh2-dev                     \
    libxslt-dev                  && \
    tar xfz /tmp/${PHPREDIS_VERSION}.tar.gz   && \
    mv phpredis-$PHPREDIS_VERSION /usr/src/php/ext/redis    && \
    docker-php-ext-configure gd                 \
    --with-freetype-dir=/usr/include/       \
    --with-webp-dir=/usr/include/           \
    --with-jpeg-dir=/usr/include/       &&  \
    \
    docker-php-ext-install -j"$(getconf _NPROCESSORS_ONLN)" \
    intl                                                \
    bcmath                                              \
    xsl                                                 \
    zip                                                 \
    soap                                                \
    mysqli                                              \
    pdo                                                 \
    pdo_mysql                                           \
    gmp                                                 \
    redis                                               \
    iconv                                               \
    gd                                                  && \
    docker-php-ext-configure opcache --enable-opcache    && \
    docker-php-ext-install opcache && \
    pecl install imagick mcrypt-1.0.1 apcu apcu_bc && \
    docker-php-ext-enable --ini-name 0-apc.ini apcu.so apc.so && \
    docker-php-ext-enable imagick.so && \
    echo "expose_php=0" > /usr/local/etc/php/php.ini     && \
    apk del --no-cache              \
    .build-dependencies             \
    $PHPIZE_DEPS                    \
    g++                             \
    libtool                         \
    make                            \
    pcre-dev                        \
    gmp                             \
    zlib-dev                        \
    cyrus-sasl-dev                  \
    git                             \
    libtool                         \
    make                         && \
    apk add --no-cache              \
    libpng \
    libwebp-dev \
    imagemagick-dev \
    libzip \
    gmp \
    icu \
    libxslt-dev \
    libintl \
    libjpeg-turbo-dev \
    libzip  && \
    rm -rf /tmp/* /var/cache/apk/*

# COPY    conf.d/php/* /usr/local/etc/php/conf.d/
