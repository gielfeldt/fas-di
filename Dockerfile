FROM php:7.4-cli AS base

RUN apt update && \
    apt install -y \
        bash \
        unzip \
        inotify-tools

ARG REAL_UID=1000
ARG REAL_GID=1000
RUN usermod -u ${REAL_UID} www-data
RUN groupmod -g ${REAL_GID} www-data
RUN mkdir -p /var/www/.ssh
RUN chown -R www-data:www-data /var/www

WORKDIR /app

FROM base AS dev

RUN pecl install xdebug
RUN docker-php-ext-enable \
        xdebug

RUN curl -sS https://getcomposer.org/installer | \
    php -- --install-dir=/usr/local/bin --filename=composer

USER www-data

CMD /app/watch.sh
