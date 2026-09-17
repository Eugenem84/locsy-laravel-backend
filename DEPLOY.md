# DEPLOY — Locsy Backend (locsy.dev.medovf2h.beget.tech)

Регламент этого репозитория в инфраструктуре домашнего сервера.
Контуры (local / обкатка / dev) и все детали — [`docs/ENVIRONMENTS.md`](./docs/ENVIRONMENTS.md).
Инфраструктура стенда целиком — репозиторий `home-server-vps` (`docs/08-locsy.md`).

## Где живёт

- Домен: `locsy.dev.medovf2h.beget.tech` (A → `90.156.169.123`).
- Сервер: домашний Ubuntu `192.168.2.207`; каталог проекта `/opt/projects/locsy`,
  этот репозиторий — `/opt/projects/locsy/backend` (деплой-клон, ветка `main`),
  монтируется в контейнер `locsy-app` как `/app`.
- Путь запроса: интернет → VPS `90.156.169.123` (DNAT 80/443, SNAT, MSS-clamp) → WireGuard-туннель
  (`10.10.0.1` ↔ `10.10.0.2`) → Caddy дома (`10.10.0.2:80` / `:8443`, TLS) → `infra_net` →
  `locsy-web:80` → `/api`, `/sanctum`, `/storage`, `/admin`, `/up`, `/js|/css/filament` → `locsy-app:80`.
- TLS терминирует Caddy; nginx проекта **передаёт** `X-Forwarded-Proto`, поэтому Laravel генерирует
  `https`-ссылки (в коде — `trustProxies(at: '*')`).
- PostgreSQL: контейнер `locsy-db` в сети `locsy_net`, наружу **не публикуется**.

## Роль в проекте

- Laravel 12 / PHP 8.2 (`webdevops/php-nginx:8.2`) + Filament 3 + Sanctum; SPA — отдельный репозиторий
  `locsy-spa-quasar` (его сборка живёт в контейнере `locsy-spa`).
- Данные: том `locsy_locsy_db_data`; загруженные фото и аватары — `backend/storage/app/public`
  (bind-mount на диск ОС).

## Секреты

- `backend/.env` на сервере (APP_KEY, доступ к БД, домен контура) — права `600`, в git не попадает.
- `.env` **не** передаётся через `env_file` в compose: Laravel читает файл сам из кода.
- Никогда не печатать содержимое `.env`, ключей, паролей и токенов в логах и отчётах.

## Миграции и деплой

```bash
cd /opt/projects/locsy

git -C backend pull
docker compose exec -T -u application locsy-app php artisan migrate --force
docker compose exec -T -u application locsy-app php artisan optimize:clear
docker compose up -d --build locsy-app
docker compose exec -T -u application locsy-app php artisan config:cache
```

Artisan запускается от пользователя `application` (владелец `bootstrap/cache` и `storage` в контейнере).
Перед изменениями схемы — вручную снять дамп БД: автоматических бэкапов на контуре пока нет.

Откат: `git -C backend checkout <предыдущий-коммит>` → `docker compose up -d --build`.

## Проверка

```bash
curl -sSI https://locsy.dev.medovf2h.beget.tech/up | head -2                       # 200
ssh dev-vps "curl -sS https://locsy.dev.medovf2h.beget.tech/api/locations | head -c 200"
bash /opt/projects/locsy/smoke.sh                                                  # полный прогон контура
```

Снаружи — только с `dev-vps`; не с Mac (активный VPN) и не с домашнего сервера (провайдер подменяет SYN-ACK).

## Нельзя

- публиковать порты на хост (включая PostgreSQL), использовать `privileged` или `network_mode: host`;
- трогать чужие проекты (в частности Ledger-Craft: Traefik на `0.0.0.0:443`/`8080`,
  Postgres на `0.0.0.0:5433`) и любые диски, кроме диска ОС `/dev/sda5`;
- менять конфигурацию Caddy, не записав проект в `projects/REGISTRY.md` инфра-репозитория.
