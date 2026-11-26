FROM php:7.4-cli-bullseye

RUN printf '%s\n' \
        'deb https://archive.debian.org/debian bullseye main' \
        'deb https://archive.debian.org/debian-security bullseye-security main' \
        > /etc/apt/sources.list \
    && apt-get -o Acquire::Check-Valid-Until=false update \
    && apt-get install -y --no-install-recommends git unzip libonig-dev libxml2-dev \
    && docker-php-ext-install -j4 pdo_mysql mbstring bcmath dom xml xmlwriter \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
