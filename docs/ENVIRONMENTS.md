# Среды и выкладка: local → обкатка → dev

> Канонический документ по контурам бэкенда Locsy (Laravel API): где что запускается, где
> задаётся домен контура и как проверить, что выкладка удалась. Инфраструктура обкаточного
> стенда описана в репозитории `home-server-vps` (`docs/08-locsy.md`, `.clinerules`).
> Связанные документы: [`../README.md`](../README.md), [`../DEPLOY.md`](../DEPLOY.md),
> контуры фронтенда — `locsy-spa-quasar/docs/ENVIRONMENTS.md`.

## 1. Контуры

|  | **local (Mac)** | **обкатка — домашний сервер** | **dev (цель выкладки)** |
| --- | --- | --- | --- |
| Адрес | `http://localhost` (`docker compose up -d`) | `https://locsy.dev.medovf2h.beget.tech`<br>A → `90.156.169.123` | `dev.medovf2h.beget.tech`<br>`217.114.0.27` |
| Где живёт | локальный репозиторий | `/opt/projects/locsy/backend` (git-клон, ветка `main`, монтируется в `locsy-app` как `/app`) | Locsy туда ещё **не выкладывали** |
| Сервисы | `docker-compose.yml`: `nginx` (80/443), `app`, `db` (`postgres:15`), `frontend` (сборка SPA), `certbot` | `locsy-app` (`webdevops/php-nginx:8.2`, PHP 8.2.33), `locsy-db` (`postgres:16-alpine`, PostgreSQL 16.15) | — |
| Назначение | разработка и тесты | **обкатка** фич и миграций на реальном домене и TLS | будущий дев-контур |
| Данные | локальный том, `locsy_testing` для тестов | реальная PostgreSQL, том `locsy_locsy_db_data` | — |

**Правило.** Изменение сначала проверяется на **обкатке** — миграции, `smoke.sh`, ручная проверка
админки Filament, — и только потом выкладывается на **dev**. Выкладки на dev, минуя обкатку, нет.

## 2. Обкаточный контур: схема

```
браузер → https://locsy.dev.medovf2h.beget.tech (A → 90.156.169.123)
        → VPS 90.156.169.123: только WireGuard + nftables (никакого Docker и прокси)
            DNAT 80  → 10.10.0.2:80     (HTTP + ACME HTTP-01 для Let's Encrypt)
            DNAT 443 → 10.10.0.2:8443   (HTTPS; домашний 443 занят чужим Traefik — не трогаем)
            SNAT (masquerade) для DNAT-потоков + MSS-clamp (MTU туннеля 1420)
        → WireGuard wgvps: VPS 10.10.0.1 ↔ дом 10.10.0.2 (инициатор — дом, keepalive 25)
        → Caddy дома: слушает только 10.10.0.2:80 и :8443, admin off, TLS Let's Encrypt
        → docker-сеть infra_net → locsy-web:80 (nginx проекта)
            /api/, /sanctum/, /storage/, /admin|livewire|filament|up, /js|/css/filament → locsy-app:80
            всё остальное                                                                 → locsy-spa:80
        → locsy-app работает с locsy-db:5432 внутри сети locsy_net
```

## 3. Что важно знать про обкаточный контур

- Домен: `locsy.dev.medovf2h.beget.tech`; A-запись → `90.156.169.123` (создаёт владелец вручную).
- Домашний сервер: Ubuntu 24.04, `192.168.2.207` (`enp4s0`), каталог проекта `/opt/projects/locsy`.
- Контейнеры: `locsy-app` (PHP 8.2.33, `webdevops/php-nginx:8.2`, исходники — bind-mount `./backend:/app`),
  `locsy-db` (`postgres:16-alpine`, 16.15), `locsy-web` (`nginx:alpine`), `locsy-spa` (сборка Quasar).
- Сети: `infra_net` (external; в ней живёт Caddy; подключён **только** `locsy-web`)
  и `locsy_net` (web/app/spa/db). БД доступна только внутри `locsy_net`.
- Тома: `locsy_locsy_db_data` (PostgreSQL); загруженные фото и аватары — `backend/storage/app/public`
  (bind-mount на диск ОС).
- **Порты не публикуются вообще** (ни БД, ни app): проверка —
  `docker compose -f /opt/projects/locsy/compose.yml ps`; у контейнеров порты видны только внутри сетей.
- `.env` Laravel **не передаётся** через `env_file` — контейнер читает `./backend/.env` сам
  (в репозитории лежит только `.env.example`). Права файла — `600`.
- Продовые значения на обкатке: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`,
  `LOG_CHANNEL=stderr`, `APP_URL` / `FRONTEND_URL` / `SANCTUM_STATEFUL_DOMAINS` / `SESSION_DOMAIN` —
  домен обкатки.
- TLS терминирует Caddy; nginx проекта **передаёт** `X-Forwarded-Proto` (не подменяет своим `$scheme`),
  а Laravel доверяет прокси (`$middleware->trustProxies(at: '*')`) — иначе ссылки на фото уходят в `http`.
- Клиент во всех логах дома виден как `10.10.0.1` (на VPS включён SNAT) — реальные IP клиентов недоступны.
- Бэкапов БД на обкаточном контуре пока нет (в инфра-проекте это этап 9) — данные обкатки считаем
  расходными; перед выкладкой на dev дамп нужно снять вручную.

## 4. Где задаётся контур (backend)

| Что | Где | Комментарий |
| --- | --- | --- |
| Домен приложения | `backend/.env` → `APP_URL` | влияет на абсолютные ссылки фото: `asset('storage/...')` |
| Origin SPA (CORS) | `.env` → `FRONTEND_URL` | на обкатке — домен контура, локально — `http://localhost:9000` |
| Stateful-cookie Sanctum | `.env` → `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE=true` | без этого cookie-сессия не работает через прокси |
| Доверие прокси | `bootstrap/app.php` → `$middleware->trustProxies(at: '*')` | за Caddy иначе генерируются `http`-ссылки (mixed content) |
| Разводка путей | `nginx/web.conf` на сервере (в инфра-репо — `projects/locsy/nginx/web.conf`) | `/api/`, `/sanctum/`, `/storage/`, `/admin`, `/livewire`, `/filament`, `/up`, `/js/filament`, `/css/filament` → Laravel |
| Передача схемы | `nginx/web.conf` → `map $http_x_forwarded_proto` | nginx **передаёт** `X-Forwarded-Proto`, а не подменяет своим `$scheme` |
| Подключение к БД | `.env` → `DB_HOST=locsy-db`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | секреты только на сервере; в git — `.env.example` |
| Логи | `.env` → `LOG_CHANNEL=stderr` | логи уходят в `docker logs locsy-app` и ротируются Docker'ом |
| Локальный контур | `docker-compose.yml` (`nginx` 80/443, `app`, `db` `postgres:15`, `frontend`, `certbot`) | `frontend` собирается из соседнего каталога `../locsy-spa-quasar` |

## 5. Доступ

```bash
# домашний сервер (Ubuntu за VPS-шлюзом)
ssh euegene@192.168.2.207
cd /opt/projects/locsy

# консоль приложения (от пользователя application — как в остальных командах контура)
docker compose exec -T -u application locsy-app php artisan about

# БД (переменные POSTGRES_* живут внутри контейнера)
docker compose exec -T locsy-db sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c "\dt"'
```

Снаружи проверяем **только** с `dev-vps` (217.114.0.27, host `mbmpuqvzic`):

```bash
ssh dev-vps 'curl -sS -m 10 https://locsy.dev.medovf2h.beget.tech/up'
```

Не проверять «снаружи» с Mac (активный VPN Happ искажает результат) и с домашнего сервера
(провайдер подменяет SYN-ACK на закрытых портах).

## 6. Миграции и деплой

```bash
cd /opt/projects/locsy

git -C backend pull                                  # этот репозиторий (ветка main)
docker compose up -d --build                         # образы; код бэкенда подхватывается bind-mount'ом
docker compose exec -T -u application locsy-app php artisan migrate --force
docker compose exec -T -u application locsy-app php artisan config:cache
docker compose ps
```

Изменения схемы БД без `--force` не применятся (`APP_ENV=production`).
Перед изменением схемы снять дамп БД вручную — автоматических бэкапов на контуре пока нет.

Откат: `git -C backend checkout <предыдущий-коммит>` → `docker compose up -d --build`;
миграции откатываются `php artisan migrate:rollback` (только осознанно).

## 7. Проверка выкладки

```bash
# на сервере: полный smoke-тест контура
bash /opt/projects/locsy/smoke.sh

# снаружи (только с dev-vps)
ssh dev-vps 'curl -sS -m 10 https://locsy.dev.medovf2h.beget.tech/up'                          # 200
ssh dev-vps 'curl -sS -m 10 "https://locsy.dev.medovf2h.beget.tech/api/cities?search=Моск" | head -c 200'
ssh dev-vps 'curl -sS -o /dev/null -w "%{http_code}\n" https://locsy.dev.medovf2h.beget.tech/admin'  # 302 → /admin/login
ssh dev-vps 'curl -sS -o /dev/null -w "%{http_code}\n" https://locsy.dev.medovf2h.beget.tech/sanctum/csrf-cookie'  # 204
```

`smoke.sh` проверяет: CSRF-cookie Sanctum, вход администратора (`POST /api/login`), `/api/user`
по cookie **и** по Bearer-токену, `/api/user/photos`, публичный `/api/cities`, набор PHP-расширений
(`gd`, `imagick`, `pdo_pgsql`, `zip`), ключевые значения `APP_*` в `.env` (секреты маскируются) и статус контейнеров.

## 8. Почта и восстановление пароля

Отправка идёт **только через SMTP**: в образе `webdevops/php-nginx` нет `sendmail`,
поэтому `MAIL_MAILER=sendmail` не вариант, а API-транспорты (`resend`, `mailgun`,
`ses`, `postmark`) требуют дополнительных composer-пакетов. «Из коробки» работает `smtp`.

### Каналы по контурам

| Контур | Настройка | Где смотреть письма |
|---|---|---|
| local (Mac) | `MAIL_MAILER=smtp`, `MAIL_HOST=mailpit`, `MAIL_PORT=1025` (`docker compose up -d mailpit`) | http://localhost:8025 |
| local, быстрый вариант | `MAIL_MAILER=log` | `storage/logs/laravel.log` |
| обкатка / dev / прод | `smtp.beget.com`, `MAIL_PORT=465`, `MAIL_SCHEME=smtps`, `MAIL_USERNAME=no-reply@<домен>` | ящик `dev@<домен>` в `web.beget.email` |

### Переменные `.env` контура

```env
APP_URL=https://<домен контура>
FRONTEND_URL=https://<домен контура>   # из него строятся ссылки в письмах (SPA, hash-роутер)
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.beget.com
MAIL_PORT=465
MAIL_USERNAME=no-reply@<домен>
MAIL_PASSWORD=<пароль ящика>          # только в .env (600), не в git и не в отчётах
MAIL_FROM_ADDRESS=no-reply@<домен>
MAIL_FROM_NAME=Locsy
MAIL_REPLY_TO_ADDRESS=dev@<домен>     # ответы пользователей уводим на живой ящик
```

После правки `.env` — `optimize:clear` и заново `config:cache` (§6), иначе контейнер
продолжит работать со старым конфигом.

### DNS домена, с которого уходят письма

- **MX** — `mx1.beget.com`, `mx2.beget.com` (шаблон beget; по ним приходит входящая почта домена).
- **SPF** — TXT на корне: `v=spf1 redirect=beget.com` (у beget-домена есть по умолчанию).
- **DKIM** — включается в панели beget («Почта» → домен → цифровая подпись), после чего в
  «DNS-записях» появляется TXT `mail._domainkey`. Без DKIM письма тоже уходят, но выше
  шанс попасть в спам.
- **DMARC** — TXT `_dmarc`: `v=DMARC1; p=none; rua=mailto:dev@<домен>` (добавляется вручную;
  `p=none` — только отчёты, ничего не отклоняем).
- **«Почта домена» (catch-all)** — обязательно указать живой ящик, иначе письма на
  несуществующие адреса домена (включая `postmaster@` и `abuse@`) просто теряются.
- Статус домена в реестре `.ru`: `whois -h whois.tcinet.ru <домен>`. Значение
  `REGISTERED, DELEGATED, UNVERIFIED` означает, что нужно **подтвердить данные
  администратора** у регистратора — иначе домен снимут с делегирования.
- Пока зона не разошлась, `dig` может отдавать пустые ответы: у `.ru` negative-TTL 3600 с,
  поэтому после регистрации домена записи видны не сразу (до ~часа).

### Как проверить

```bash
# локально: письмо в Mailpit
docker compose up -d mailpit && open http://localhost:8025

# на контуре: запрос письма (ответ всегда один и тот же — существование адреса не утекает)
curl -sS -X POST https://locsy.dev.medovf2h.beget.tech/api/forgot-password \
  -H 'Content-Type: application/json' -d '{"email":"твой@ящик"}' | head -c 200

# доставку и подписи смотрим в оригинале письма (Gmail/Яндекс → «показать оригинал»):
# ожидаем Authentication-Results: spf=pass; dkim=pass; dmarc=pass
```

### Поведение и страховка

- `POST /api/forgot-password` — `{ email }`; ответ всегда `200` с одинаковым текстом,
  лимит 5 запросов в минуту на email+IP, повторное письмо Laravel не отправит раньше 60 секунд.
- `POST /api/reset-password` — `{ token, email, password, password_confirmation }`,
  пароль от 8 символов; после успешной смены **отзываются все токены Sanctum** и
  удаляются сессии пользователя (`PasswordResetController`).
- Ссылка в письме: `<FRONTEND_URL>/#/reset-password?token=…&email=…` (роутер SPA в
  hash-режиме, поэтому путь идёт после «#»; плюс токен не попадает в логи nginx).
  Токен живёт 60 минут (`config/auth.php` → `passwords.users.expire`).
- Если письмо не доходит (почта не настроена, адрес недоступен) — пароль можно сменить
  админски: `docker compose exec -T -u application locsy-app php artisan locsy:user-password user@example.com`
  (пароль сгенерируется и покажется в выводе; `--password=...` — если нужен свой).
  Команда заодно отзывает токены и сессии пользователя.

## 9. Известные грабли

- **Artisan запускаем от пользователя `application`** (`docker compose exec -u application locsy-app …`):
  `bootstrap/cache` и `storage` в контейнере принадлежат `application:application` (uid/gid 1000);
  запуск от root создаёт там root-овые файлы кеша и логов, из-за чего штатные команды потом падают с отказом доступа.
- **После правок `.env` нужен `php artisan config:cache`** (или `optimize:clear`, затем кеш заново):
  иначе контейнер продолжает работать со старым конфигом.
- **Фото отдаются по `http` / не открываются** — проверять `trustProxies(at: '*')` в `bootstrap/app.php`
  и передачу `X-Forwarded-Proto` в `nginx/web.conf`; `APP_URL` при этом должен быть `https`-доменом контура.
- **`/storage/...` → 404** — маршрут `/storage/` есть в `nginx/web.conf`, но нужен и симлинк
  `php artisan storage:link` в контейнере (иначе файлы не находятся).
- **Sanctum возвращает `401`** при проверке «напрямую» через Caddy дома (`--resolve …:8443`): в `Referer`
  появляется порт `:8443`, которого нет в `SANCTUM_STATEFUL_DOMAINS`. Проверяйте stateful-путь через
  публичный домен (`bash smoke.sh` без `--local`) или Bearer-токеном.
- **Тесты не гоняем на обкаточном контуре.** В `tests/TestCase.php` зафиксирована база `locsy_testing`
  (`TESTING_DATABASE`) и есть проверка в `setUp()`; на сервере такой БД нет (там только `locsy`),
  поэтому `php artisan test` — локально: `composer test`.
- **`APP_DEBUG` и `APP_ENV`.** На обкатке обязательно `APP_ENV=production`, `APP_DEBUG=false` — иначе
  на публичном домене видны стектрейсы и переменные окружения.
- **Русские названия городов собираются эвристикой.** В `cities.name` лежит латинское имя GeoNames
  («Moscow», «Odintsovo»), русское выбирается из `alternatenames` по схожести с `asciiname`
  (`CityController::pickRussianName`). GeoNames хранит там и обратные транслитерации латинского
  имени, поэтому подбор иногда выдаёт мусор: «Москох» (Москва), «Одинтсово», «Шчолково», «Казан».
  Такие города перечислены в `CityController::CITY_NAME_OVERRIDES` (ключ — `geonameid`), регионы
  с ошибочной подписью — в `CITY_REGION_OVERRIDES` (у Москвы в GeoNames регион — сама Москва,
  в списке она подписана Московской областью). Регион в скобках не дублирует название города:
  «Санкт-Петербург», а не «Санкт-Петербург (Санкт-Петербург)». При расширении справочника
  (другой порог населения, другая страна) список надо пополнять; правильное решение — импортировать
  предпочтительное ru-имя из GeoNames `alternateNames` (isolanguage = 'ru') в отдельную колонку.

## 10. Что нужно, чтобы выложить Locsy на dev (`dev.medovf2h.beget.tech`)

- [ ] решить, что переносим: контейнеры целиком, только код или код + дамп БД;
- [ ] A-запись уже есть (`dev.*` → 217.114.0.27), но уточнить, не занят ли этот хост другим проектом;
- [ ] свои секреты на dev: `APP_KEY` (`php artisan key:generate`), пароль БД, ключи Яндекса для сборки SPA;
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `APP_URL` / `FRONTEND_URL` /
      `SANCTUM_STATEFUL_DOMAINS` / `SESSION_DOMAIN` — домен dev;
- [ ] TLS на dev (на домашнем контуре сертификат выпускает Caddy автоматически);
- [ ] первичная инициализация: `composer install --no-dev --optimize-autoloader`, `migrate --seed`,
      `storage:link`, `filament:assets`, `config:cache`, создание администратора;
- [ ] дамп БД с обкатки, снятый вручную (`pg_dump -Fc`) — автоматических бэкапов пока нет;
- [ ] **важно:** `dev-vps` сейчас используется как независимый наблюдатель для внешних проверок.
      Если Locsy переедет на него, проверки «снаружи» нужно будет делать с другого хоста.

## 11. Ограничения (нельзя)

- публиковать порты проектов на хост (включая PostgreSQL); `privileged`; `network_mode: host` без обоснования;
- трогать чужие проекты: `ledger_craft_*` (Traefik на `0.0.0.0:443`/`8080`, Postgres на `0.0.0.0:5433`,
  том `test28_postgresql_data`) и системный PostgreSQL на `127.0.0.1:5432`;
- использовать любые диски, кроме диска ОС (`/dev/sda5`): `/dev/sdb1` и `/dev/sda3` не монтируем;
- печатать содержимое `.env`, ключей, паролей и токенов в логах и отчётах;
- перезагружать серверы и чистить Docker (`system prune`, `autoremove`) без отдельного согласия.
