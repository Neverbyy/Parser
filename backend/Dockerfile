# Один образ и для локальной разработки, и для деплоя.
#
# Локально docker-compose монтирует исходники поверх образа и подменяет
# vendor томом, поэтому правки видны без пересборки. На хостинге тома нет —
# работают код и зависимости, вшитые в образ на этапе сборки.
FROM php:8.4-cli

# zip нужен composer'у для распаковки пакетов, intl и bcmath — Laravel,
# pdo_sqlite — локальная база, pdo_pgsql — боевая.
# Пакеты -dev тянут за собой и рантаймовые библиотеки, поэтому удалять их
# после сборки нельзя: расширения перестанут загружаться.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libsqlite3-dev \
        libpq-dev \
        libzip-dev \
        libicu-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" pdo_sqlite pdo_pgsql zip intl bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Зависимости ставятся до копирования кода: слой переиспользуется, пока
# composer.lock не изменился.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && chmod -R ug+w storage bootstrap/cache

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
# sed — на случай, если файл выгружен из git с CRLF: иначе shell
# не найдёт интерпретатор и контейнер упадёт с невнятной ошибкой.
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["docker-entrypoint.sh"]

# Встроенный сервер PHP, а не `artisan serve`.
#
# Важно: artisan serve запускает сервер отдельным процессом и пробрасывает в
# него только белый список переменных окружения (APP_ENV, PATH, XDEBUG_* и
# ещё несколько — см. ServeCommand::$passthroughVariables). Всё, что задано
# в окружении контейнера, до запросов при этом не доезжает, и приложение
# молча работает на значениях из .env. Здесь сервер — сам процесс контейнера.
#
# PORT подставляют Railway и Render; локально остаётся 8000.
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8000} -t public public/index.php"]
