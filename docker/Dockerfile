FROM composer/composer:2-bin AS composer
FROM mlocati/php-extension-installer:latest AS php-extension-installer

FROM php:8.5-cli-bookworm AS base

COPY --from=php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN apt-get update && apt-get -y install unzip --no-install-recommends && rm -rf /var/lib/apt/lists/*

COPY docker/php.ini $PHP_INI_DIR/conf.d/98-yiipress.ini

RUN install-php-extensions \
    opcache \
    mbstring \
    inotify \
    dom \
    ctype \
    curl \
    phar \
    openssl \
    xml \
    xmlwriter \
    simplexml \
    yaml \
    md4c \
    pcntl \
    posix

FROM base AS highlighter-extension-builder
ARG HIGHLIGHTER_VERSION=1.0.1
ENV CARGO_HOME=/root/.cargo \
    RUSTUP_HOME=/usr/local/rustup \
    PATH="/root/.cargo/bin:${PATH}"
COPY --from=composer /composer /usr/bin/composer
COPY --from=rust:bookworm /usr/local/cargo /root/.cargo
COPY --from=rust:bookworm /usr/local/rustup /usr/local/rustup
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache composer create-project --no-dev --no-progress --no-interaction yiipress/highlighter /build/highlighter-extension "${HIGHLIGHTER_VERSION}"
RUN --mount=type=cache,target=/root/.cargo/git \
    --mount=type=cache,target=/root/.cargo/registry \
    cd /build/highlighter-extension \
    && phpize \
    && ./configure \
    && make -j"$(nproc)" \
    && make install

FROM base AS app-base
COPY --from=highlighter-extension-builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
RUN docker-php-ext-enable highlighter

LABEL org.opencontainers.image.licenses="BSD-3-Clause" \
    org.opencontainers.image.title="YiiPress" \
    org.opencontainers.image.description="Static website builder" \
    org.opencontainers.image.source="https://github.com/yiipress/engine" \
    org.opencontainers.image.url="https://github.com/yiipress/engine" \
    org.opencontainers.image.vendor="Alexander Makarov" \
    org.opencontainers.image.created="" \
    org.opencontainers.image.revision="" \
    org.opencontainers.image.version=""

#
# Development
#

FROM app-base AS dev
ARG USER_ID=10001
ARG GROUP_ID=10001
ARG USER_NAME=appuser
ARG GROUP_NAME=appuser

RUN install-php-extensions \
    xdebug

COPY --from=composer /composer /usr/bin/composer

RUN \
	groupadd --gid ${GROUP_ID} ${GROUP_NAME}; \
    useradd --gid ${GROUP_ID} --uid ${USER_ID} ${GROUP_NAME}
USER ${USER_NAME}
WORKDIR /app
CMD ["./yii", "serve", "0.0.0.0", "--port=19777", "--workers=1"]

#
# Production
#

FROM app-base AS prod-builder
COPY --from=composer /composer /usr/bin/composer
WORKDIR /app
COPY config /app/config
COPY public /app/public
COPY src /app/src
COPY themes /app/themes
COPY yii composer.json composer.lock /app/
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache composer install --no-dev --no-progress --no-interaction --classmap-authoritative && \
    rm composer.lock composer.json

FROM app-base AS prod
ENV APP_ENV=prod
COPY docker/php-prod.ini $PHP_INI_DIR/conf.d/99-yiipress-prod.ini
COPY --from=prod-builder --chown=www-data:www-data /app /app
USER www-data
WORKDIR /app
CMD ["./yii", "serve", "0.0.0.0", "--port=19777", "--workers=1"]

#
# Packaging
#

FROM app-base AS phar-builder
ENV APP_ENV=prod
COPY --from=composer /composer /usr/bin/composer
WORKDIR /app
COPY config /app/config
COPY public /app/public
COPY src /app/src
COPY themes /app/themes
COPY build/package-phar.php build/PharArchiveFilter.php /app/build/
COPY yii composer.json composer.lock /app/
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache composer install --no-dev --no-progress --no-interaction --classmap-authoritative && \
    php -d phar.readonly=0 build/package-phar.php dist/yiipress.phar

FROM app-base AS static-package
ARG PHP_VERSION=8.5
ARG MD4C_VERSION=1.1
ARG HIGHLIGHTER_VERSION=1.0.1
ARG STATIC_PHP_CLI_REF=5b5861c366a0d94bc84002db7b3f46144b388fbb
ARG STATIC_PHP_EXTENSIONS=ctype,dom,filter,highlighter,inotify,mbstring,md4c,opcache,pcntl,phar,posix,xml,xmlwriter,yaml
ARG TARGETARCH
ENV CARGO_HOME=/root/.cargo \
    RUSTUP_HOME=/usr/local/rustup \
    PATH="/usr/local/musl/bin:/root/.cargo/bin:${PATH}" \
    CARGO_BUILD_TARGET=x86_64-unknown-linux-musl \
    HIGHLIGHTER_SOURCE=/opt/yiipress-highlighter \
    YIIPRESS_MD4C_SOURCE=/opt/md4c-extension
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        autoconf \
        automake \
        bison \
        build-essential \
        ca-certificates \
        cmake \
        curl \
        file \
        autopoint \
        flex \
        git \
        gzip \
        libtool \
        musl-tools \
        patch \
        pkgconf \
        pkg-config \
        re2c \
        tar \
        unzip \
        xz-utils \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer /composer /usr/bin/composer
COPY --from=rust:bookworm /usr/local/cargo /root/.cargo
COPY --from=rust:bookworm /usr/local/rustup /usr/local/rustup
RUN rustup target add x86_64-unknown-linux-musl
WORKDIR /static-php
RUN --mount=type=cache,target=/tmp/composer-cache \
    curl -fsSL "https://github.com/crazywhalecc/static-php-cli/archive/${STATIC_PHP_CLI_REF}.tar.gz" \
    | tar -xz --strip-components=1 -C /static-php \
    && COMPOSER_CACHE_DIR=/tmp/composer-cache composer install --no-dev --no-progress --no-interaction --classmap-authoritative
RUN mkdir -p /opt/md4c-extension \
    && curl -fsSL "https://pecl.php.net/get/md4c-${MD4C_VERSION}.tgz" \
    | tar -xz --strip-components=1 -C /opt/md4c-extension
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache composer create-project --no-dev --no-progress --no-interaction yiipress/highlighter /opt/yiipress-highlighter "${HIGHLIGHTER_VERSION}"
COPY build/static-php/patch-extension-config.php /opt/patch-extension-config.php
COPY build/static-php/patch-source-config.php /opt/patch-source-config.php
COPY build/static-php/register-yiipress-highlighter.php /opt/register-yiipress-highlighter.php
RUN mkdir -p /artifacts
RUN --mount=type=cache,target=/root/.cargo/git \
    --mount=type=cache,target=/root/.cargo/registry \
    php /opt/patch-extension-config.php /static-php/config/ext.json \
    && php /opt/patch-source-config.php /static-php/config/source.json /static-php/config/lib.json \
    && bin/spc download --for-extensions="${STATIC_PHP_EXTENSIONS}" --with-php="${PHP_VERSION}" --without-suggestions \
    && bin/spc install-pkg upx \
    && mkdir -p buildroot/bin \
    && ln -sf /usr/bin/pkg-config buildroot/bin/pkg-config \
    && SPC_TOOLCHAIN='SPC\toolchain\MuslToolchain' SPC_TARGET=x86_64-linux-musl bin/spc doctor --auto-fix \
    && (bin/spc build "${STATIC_PHP_EXTENSIONS}" --build-micro --with-micro-fake-cli --with-upx-pack -P /opt/register-yiipress-highlighter.php \
        || (tail -n 200 /static-php/log/spc.shell.log && false))
COPY --from=phar-builder /app/dist/yiipress.phar /artifacts/yiipress.phar
RUN bin/spc micro:combine /artifacts/yiipress.phar -O /artifacts/yiipress \
    && chmod +x /artifacts/yiipress

FROM scratch AS package-phar-artifacts
COPY --from=phar-builder /app/dist/yiipress.phar /yiipress.phar

FROM scratch AS package-linux-artifacts
COPY --from=static-package /artifacts/yiipress /yiipress

FROM package-linux-artifacts AS package-artifacts

FROM gcr.io/distroless/static-debian12:nonroot AS distroless
LABEL org.opencontainers.image.licenses="BSD-3-Clause" \
    org.opencontainers.image.title="YiiPress" \
    org.opencontainers.image.description="YiiPress static website builder" \
    org.opencontainers.image.source="https://github.com/yiipress/engine" \
    org.opencontainers.image.url="https://github.com/yiipress/engine" \
    org.opencontainers.image.vendor="Alexander Makarov" \
    org.opencontainers.image.created="" \
    org.opencontainers.image.revision="" \
    org.opencontainers.image.version=""
COPY --from=static-package /artifacts/yiipress /yiipress
WORKDIR /site
ENTRYPOINT ["/yiipress"]
CMD ["--help"]
