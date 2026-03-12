FROM php:8.2-cli-alpine

# System dependencies
RUN apk add --no-cache \
    git \
    curl \
    unzip \
    libzip-dev \
    icu-dev \
    && docker-php-ext-install zip intl

# Xdebug for code coverage
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && echo "xdebug.mode=coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Symfony CLI
RUN curl -sS https://get.symfony.com/cli/installer | bash \
    && mv /root/.symfony*/bin/symfony /usr/local/bin/symfony

# Git identity (overridable via environment variables)
ARG GIT_USER_NAME="Eric Gansa"
ARG GIT_USER_EMAIL="ericgansa02@gmail.com"
RUN git config --global user.name "${GIT_USER_NAME}" \
    && git config --global user.email "${GIT_USER_EMAIL}" \
    && git config --global init.defaultBranch main

WORKDIR /app

# Default command: run tests
CMD ["vendor/bin/phpunit", "--colors=always"]
