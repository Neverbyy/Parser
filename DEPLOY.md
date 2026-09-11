# Деплой

Фронтенд — на Vercel, бэкенд — контейнером на Render или Railway, база — Supabase Postgres.

## Схема и почему так

```
браузер ──→ app.vercel.app ──┬── / , /assets/*   → статика SPA
                             └── /api/*, /sanctum/*  → rewrite на бэкенд
                                        │
                                        ↓
                            backend.onrender.com (Docker)
                                        │
                                        ↓
                              Supabase Postgres
```

**Почему бэкенд не на Vercel.** Там эфемерная файловая система и serverless-модель: SQLite невозможен в принципе, а PHP доступен только community-рантаймом. У нас уже есть рабочий Dockerfile, и хостинг, умеющий контейнеры, запускает его как есть — с живой ФС и возможностью поднять фоновый воркер.

**Почему rewrite, а не прямые запросы на бэкенд.** `*.vercel.app` входит в Public Suffix List, а бэкенд живёт на другом домене — сессионная кука Sanctum между ними не разделяется. Можно было бы включить `SameSite=None`, но такие куки блокирует Safari и всё сильнее ограничивает Chrome. Rewrite решает это иначе: браузер общается только с доменом Vercel, запросы к API проксируются на бэкенд уже на стороне Vercel. Для браузера это один origin — куки работают ровно так же, как локально через прокси Vite, и CORS не нужен вовсе.

---

## Шаг 1. База — Supabase

1. Создайте проект на [supabase.com](https://supabase.com), запомните пароль базы.
2. **Connect → Session pooler** — скопируйте параметры оттуда.

> **Важно.** Нужен именно **session pooler на порту 5432**.
> Transaction pooler (6543) не поддерживает prepared statements, а PDO их использует — миграции упадут.
> Прямое подключение (`db.<ref>.supabase.co`) доступно только по IPv6, которого у Render и Railway нет без платного адд-она.

Из строки подключения понадобятся:

| Переменная | Откуда |
|---|---|
| `DB_HOST` | `aws-0-<регион>.pooler.supabase.com` |
| `DB_PORT` | `5432` |
| `DB_DATABASE` | `postgres` |
| `DB_USERNAME` | `postgres.<ref-проекта>` |
| `DB_PASSWORD` | пароль, заданный при создании |

Миграции применятся сами при первом старте контейнера — вручную ничего запускать не нужно.

---

## Шаг 2. Бэкенд — Render или Railway

Оба собирают [`backend/Dockerfile`](backend/Dockerfile) без дополнительной настройки.

**Render:** New → Web Service → подключить репозиторий → Language: **Docker**, Root Directory: **`backend`**.

**Railway:** New Project → Deploy from GitHub → в настройках сервиса Root Directory: **`backend`**.

Порт подставляется платформой через `PORT`, образ его учитывает.

### Переменные окружения

Ключ приложения сгенерируйте локально: `php artisan key:generate --show`

```ini
APP_NAME="Yandex Reviews Parser"
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://<проект>.vercel.app

DB_CONNECTION=pgsql
DB_HOST=aws-0-<регион>.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.<ref>
DB_PASSWORD=<пароль>

# Кука привязывается к домену, который запросил браузер (то есть к Vercel),
# поэтому SESSION_DOMAIN должен остаться пустым.
SESSION_DRIVER=database
SESSION_DOMAIN=
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

SANCTUM_STATEFUL_DOMAINS=<проект>.vercel.app
FRONTEND_URL=https://<проект>.vercel.app

CACHE_STORE=database
QUEUE_CONNECTION=sync
LOG_CHANNEL=stderr
```

Адрес Vercel на этом шаге ещё не известен — поставьте заглушку и вернитесь к этим трём переменным в шаге 4.

---

## Шаг 3. Фронтенд — Vercel

1. New Project → импортировать репозиторий.
2. **Root Directory: `frontend`**. Остальное Vercel определит сам по [`frontend/vercel.json`](frontend/vercel.json).
3. **В `frontend/vercel.json` замените `REPLACE-WITH-BACKEND-HOST`** на хост бэкенда из шага 2 (без `https://`), закоммитьте и запушьте — Vercel пересоберёт.

Переменные окружения фронтенду не нужны: запросы идут относительными путями и попадают на бэкенд через rewrite.

---

## Шаг 4. Связать домены

Вернитесь в настройки бэкенда и подставьте настоящий адрес Vercel:

```ini
APP_URL=https://<проект>.vercel.app
SANCTUM_STATEFUL_DOMAINS=<проект>.vercel.app
FRONTEND_URL=https://<проект>.vercel.app
```

`SANCTUM_STATEFUL_DOMAINS` — **без схемы**. Перезапустите сервис.

---

## Проверка

```bash
curl -i https://<проект>.vercel.app/api/user
# 401 и заголовок set-cookie — значит rewrite работает и бэкенд отвечает
```

Затем в браузере: вход `demo@example.com` / `password`, вставить ссылку на организацию, дождаться отзывов.

Если вход даёт **419** — почти всегда `SANCTUM_STATEFUL_DOMAINS` не совпадает с доменом Vercel или в нём осталась схема `https://`.
Если **500 на входе** — проверьте `SESSION_DRIVER=database` и что миграции прошли (таблица `sessions` в Supabase).

---

## Ограничения и риски

**Яндекс может не пустить с хостинга.** Главный риск, и устранить его я не могу. Локально запросы идут с обычного домашнего адреса, а Render и Railway работают из дата-центров AWS и GCP, к которым у Яндекса отношение строже. Вполне возможно, что в проде вместо отзывов придёт капча. Приложение это распознает и покажет понятную ошибку (`captcha` или `blocked`), а не пустой экран, — но данных не будет. Если так случится, лечится прокси с жилых адресов: список в `YANDEX_PROXIES` через запятую, код уже это поддерживает.

**Бесплатные тарифы засыпают.** На free-тарифе Render сервис останавливается после 15 минут простоя, и первый запрос ждёт около минуты, пока контейнер поднимется. Для демонстрации приемлемо, для регулярного разбора — нет.

**Фоновая очередь не поднята.** В проде остаётся `QUEUE_CONNECTION=sync`: парсинг идёт внутри запроса и занимает 10–20 секунд. Для очереди нужен второй сервис с `php artisan queue:work` и `QUEUE_CONNECTION=database` — оба хостинга это умеют, но на бесплатном тарифе второй постоянный процесс не выйдет.

**База — общая на всех.** Пользователь один, сид-аккаунт публичный. Это демонстрационный стенд, а не боевая система.
