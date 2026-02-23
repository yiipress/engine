FROM rust:bookworm AS rust-builder
WORKDIR /build
COPY src/Highlighter/ .
RUN cargo build --release

FROM composer/composer:2-bin AS composer

FROM dunglas/frankenphp:1-php8.5-bookworm AS base

COPY --from=rust-builder /build/target/release/libyiipress_highlighter.so /usr/local/lib/libyiipress_highlighter.so

RUN ldconfig && apt-get update && apt-get -y install unzip --no-install-recommends

COPY docker/php.ini $PHP_INI_DIR/conf.d/98-yiipress.ini

RUN install-php-extensions \
    opcache \
    mbstring \
    intl \
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
    ffi

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

FROM base AS dev
ARG USER_ID=10001
ARG GROUP_ID=10001
ARG USER_NAME=appuser
ARG GROUP_NAME=appuser

RUN install-php-extensions \
    xdebug

COPY --from=composer /composer /usr/bin/composer

# Based on https://frankenphp.dev/docs/docker/#running-as-a-non-root-user
RUN \
	groupadd --gid ${GROUP_ID} ${GROUP_NAME}; \
    useradd --gid ${GROUP_ID} --uid ${USER_ID} ${GROUP_NAME}; \
	# Add additional capability to bind to port 80 and 443 \
	setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp; \
	# Give write access to /data/caddy and /config/caddy \
	chown -R ${USER_NAME}:${GROUP_NAME} /data/caddy && chown -R ${USER_NAME}:${GROUP_NAME} /config/caddy
USER ${USER_NAME}

#
# Production
#

FROM base AS prod-builder
COPY --from=composer /composer /usr/bin/composer
COPY config /app/config
COPY public /app/public
COPY src /app/src
COPY themes /app/themes
COPY yii composer.json composer.lock /app/
RUN --mount=type=cache,target=/tmp/cache \
    composer install --no-dev --no-progress --no-interaction --classmap-authoritative && \
    rm composer.lock composer.json

FROM base AS prod
ENV APP_ENV=prod
COPY docker/php-prod.ini $PHP_INI_DIR/conf.d/99-yiipress-prod.ini
COPY --from=prod-builder --chown=www-data:www-data /app /app
USER www-data
