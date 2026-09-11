#!/bin/sh
set -e

cd /app

# Каждый шаг идемпотентен: контейнер можно перезапускать сколько угодно,
# уже сделанное второй раз не делается.

# Локально исходники и vendor приезжают томами, и на первом запуске vendor пуст.
# В образе для хостинга зависимости уже вшиты — сюда не заходим.
#
# Второе условие: в образ ставятся только боевые зависимости, и если том
# засеялся из него, локально не окажется phpunit и pint. Вне production
# доустанавливаем dev-пакеты.
if [ ! -f vendor/autoload.php ] \
    || { [ "${APP_ENV:-local}" != "production" ] && [ ! -f vendor/bin/phpunit ]; }; then
    echo "→ Устанавливаю зависимости composer…"
    composer install --no-interaction --prefer-dist --no-progress
fi

# На хостинге переменные приходят из окружения, и .env не нужен: Dotenv
# всё равно не перекрывает уже заданные переменные. Файл создаём только
# когда ключа снаружи нет — то есть при локальном запуске.
if [ -z "$APP_KEY" ]; then
    if [ ! -f .env ]; then
        echo "→ Создаю .env из .env.example"
        cp .env.example .env
    fi

    if ! grep -q '^APP_KEY=base64:' .env; then
        echo "→ Генерирую ключ приложения"
        php artisan key:generate --force
    fi
fi

# Файл базы нужен только для SQLite; на Postgres шаг пропускается.
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ] && [ ! -f database/database.sqlite ]; then
    echo "→ Создаю файл базы SQLite"
    touch database/database.sqlite
fi

echo "→ Применяю миграции и сидер"
php artisan migrate --seed --force

echo "→ Бэкенд готов"

exec "$@"
