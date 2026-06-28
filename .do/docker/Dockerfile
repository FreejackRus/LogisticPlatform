# Specify platform argument
ARG TARGETPLATFORM
FROM --platform=$TARGETPLATFORM ghcr.io/loadpartner/tms:image-base

WORKDIR /var/www/html
COPY . .

ENV DEBIAN_FRONTEND=noninteractive
ENV SUPERVISOR_PHP_USER="www-data"

RUN cp .env.example .env \
    && composer install \
    && touch database/database.sqlite \
    && php artisan migrate --seed \
    && npm install \
    && php artisan key:generate \
    && npm run build \
    && find . -path './vendor' -prune -o -path './node_modules' -prune -o -exec chown www-data:www-data {} +

EXPOSE 8080/tcp

ENTRYPOINT ["start-container"]