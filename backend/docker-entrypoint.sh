#!/bin/sh
set -e

cd /app

# Каждый шаг идемпотентен: контейнер можно перезапускать сколько угодно,
# уже сделанное второй раз не делается.

if [ ! -f vendor/autoload.php ]; then
    echo "→ Устанавливаю зависимости composer…"
    composer install --no-interaction --prefer-dist --no-progress
fi

if [ ! -f .env ]; then
    echo "→ Создаю .env из .env.example"
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    echo "→ Генерирую ключ приложения"
    php artisan key:generate --force
fi

if [ ! -f database/database.sqlite ]; then
    echo "→ Создаю файл базы SQLite"
    touch database/database.sqlite
fi

echo "→ Применяю миграции и сидер"
php artisan migrate --seed --force

echo "→ Бэкенд готов: http://localhost:8000"

exec "$@"
