# syntax=docker/dockerfile:1

FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js postcss.config.cjs tailwind.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm run build

FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts

FROM php:8.3-apache AS runtime

LABEL maintainer="axeloz"

SHELL ["/bin/bash", "-c"]

RUN apt-get update -y && apt-get install -y \
	libonig-dev \
	libxml2-dev \
	libzip-dev \
	cron \
	&& docker-php-ext-install \
	bcmath \
	ctype \
	fileinfo \
	mbstring \
	opcache \
	xml \
	zip \
	&& a2enmod rewrite \
	&& rm -rf /var/lib/apt/lists/*

COPY .docker/vhost.conf /etc/apache2/sites-available/000-default.conf

COPY . /app
WORKDIR /app

COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build

RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache \
	&& chmod +x /app/.docker/entrypoint.sh

RUN echo "* * * * * www-data php /app/artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/filesharing \
	&& echo "* * * * * www-data php /app/artisan queue:work --sleep=3 --tries=3 --stop-when-empty >> /dev/null 2>&1" >> /etc/cron.d/filesharing \
	&& chmod 0644 /etc/cron.d/filesharing

VOLUME /app/storage

EXPOSE 80

ENTRYPOINT ["/app/.docker/entrypoint.sh"]
