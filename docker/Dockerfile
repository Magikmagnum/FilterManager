FROM php:8.2-cli

# System dependencies
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        curl \
        unzip \
        libzip-dev \
        libicu-dev \
        libxml2-dev \
        autoconf \
        g++ \
        make \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-configure intl \
    && docker-php-ext-install zip intl

# Xdebug for code coverage
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && echo "xdebug.mode=coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Symfony CLI
RUN curl -sS https://get.symfony.com/cli/installer | bash \
    && mv /root/.symfony*/bin/symfony /usr/local/bin/symfony

# Git identity
ARG GIT_USER_NAME="Eric Gansa"
ARG GIT_USER_EMAIL="ericgansa01@gmail.com"
RUN git config --global user.name "${GIT_USER_NAME}" \
    && git config --global user.email "${GIT_USER_EMAIL}" \
    && git config --global init.defaultBranch main

WORKDIR /app

# ✅ Garde le conteneur vivant, sans lancer PHPUnit au démarrage
CMD ["tail", "-f", "/dev/null"]