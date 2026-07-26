FROM php:8.2-cli

# Instalace ovladačů pro PostgreSQL
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo_pgsql pgsql

WORKDIR /app
COPY . .

# Spuštění PHP vestavěného serveru
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} index.php"]
