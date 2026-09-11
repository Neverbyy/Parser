# Образ для локальной разработки: исходники монтируются снаружи,
# внутрь кладутся только PHP с расширениями и composer.
FROM php:8.4-cli

# zip нужен composer'у для распаковки пакетов, intl и bcmath — Laravel,
# pdo_sqlite — наша база. Всё остальное есть в базовом образе.
# Пакеты -dev тянут за собой и рантаймовые библиотеки, поэтому удалять их
# после сборки нельзя: расширения перестанут загружаться.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libsqlite3-dev \
        libzip-dev \
        libicu-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" pdo_sqlite zip intl bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
# sed — на случай, если файл выгружен из git с CRLF: иначе shell
# не найдёт интерпретатор и контейнер упадёт с невнятной ошибкой.
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["docker-entrypoint.sh"]

# Встроенный сервер PHP напрямую, а не через `artisan serve`.
#
# Важно: artisan serve запускает сервер отдельным процессом и пробрасывает в
# него только белый список переменных окружения (APP_ENV, PATH, XDEBUG_* и
# ещё несколько — см. ServeCommand::$passthroughVariables). Всё, что задано
# в docker-compose, до запросов при этом не доезжает, и приложение молча
# работает на значениях из .env. Здесь сервер — сам процесс контейнера,
# поэтому окружение видно целиком.
#
# Для боевого развёртывания понадобился бы php-fpm за nginx.
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
