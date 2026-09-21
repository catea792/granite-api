FROM php:8.4-fpm-trixie

SHELL ["/bin/bash", "-euxo", "pipefail", "-c"]

# timezone
ARG TZ=UTC
ARG HOST_UID=1000
ARG HOST_GID=1000

# composer
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_HOME=/composer
ENV TZ=${TZ}

RUN ln -snf "/usr/share/zoneinfo/${TZ}" /etc/localtime \
    && echo "${TZ}" > /etc/timezone

RUN set -eux; \
    savedAptMark="$(apt-mark showmanual)"; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        gettext-base \
        git \
        nginx \
        supervisor \
        tzdata \
        ${PHPIZE_DEPS} \
        libmariadb-dev \
        libexif-dev \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libgomp1 \
        libmagickwand-7.q16-10 \
        libmagickwand-dev \
        libonig-dev \
        libpng-dev \
        libwebp-dev \
        libxpm-dev \
        libzip-dev \
        zlib1g-dev; \
    docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
        --with-xpm; \
    docker-php-ext-install -j"$(nproc)" \
        intl \
        pdo_mysql \
        mbstring \
        zip \
        bcmath \
        exif \
        gd; \
    pecl install imagick xdebug; \
    docker-php-ext-enable imagick xdebug; \
    apt-mark auto '.*' > /dev/null; \
    { [ -z "${savedAptMark}" ] || apt-mark manual ${savedAptMark}; }; \
    apt-mark manual ca-certificates gettext-base git libgomp1 libmagickwand-7.q16-10 nginx supervisor tzdata; \
    find /usr/local -type f -executable -exec ldd '{}' ';' \
        | awk '/=>/ { \
            so = $(NF-1); \
            if (index(so, "/usr/local/") == 1) { next }; \
            if (so ~ /^\(/) { next }; \
            gsub("^/(usr/)?", "", so); \
            printf "*%s\n", so \
        }' \
        | sort -u \
        | xargs -rt dpkg-query --search \
        | awk 'sub(":$", "", $1) { print $1 }' \
        | sort -u \
        | xargs -r apt-mark manual; \
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false ${PHPIZE_DEPS}; \
    rm -rf /var/lib/apt/lists/*

COPY --from=composer:lts /usr/bin/composer /usr/bin/composer

RUN groupmod --gid "${HOST_GID}" www-data \
    && usermod --uid "${HOST_UID}" --gid "${HOST_GID}" www-data \
    && mkdir -p /work/app \
    && chown -R www-data:www-data /work/app
