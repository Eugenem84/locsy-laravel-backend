# Locsy — backend (Laravel API)

> REST API сервиса фотолокаций: города, локации, фотографии, избранное,
> профили фотографов и модерация пользовательского контента.

## Стек

- **Framework:** Laravel 12 (PHP 8.2)
- **Аутентификация:** Laravel Sanctum (stateful cookie + Bearer-токены)
- **База данных:** PostgreSQL (справочник городов — GeoNames)
- **Админка:** Filament 3 (локации, фотографии, категории, настройки модерации)
- **Изображения:** Intervention Image (нормализация размера, JPEG)
- **Инфраструктура:** Docker Compose (nginx + PHP-FPM + Postgres), Let's Encrypt

## Стенды

| Контур | Адрес | Что это |
|---|---|---|
| local | `http://localhost` (`docker compose up -d`) | разработка на Mac: `nginx` (80/443) + `app` + `db` (`postgres:15`) + `frontend` |
| **обкатка** | https://locsy.dev.medovf2h.beget.tech | **домашний сервер** (Ubuntu за VPS-шлюзом), каталог `/opt/projects/locsy`: сюда выкладываем и проверяем, и только потом — на dev |
| dev | `dev.medovf2h.beget.tech` | цель выкладки (`217.114.0.27`); Locsy туда ещё не переносили |

Контуры целиком (схема запроса, контейнеры, где задаётся домен, миграции, проверка) —
[`docs/ENVIRONMENTS.md`](./docs/ENVIRONMENTS.md); регламент деплоя — [`DEPLOY.md`](./DEPLOY.md).

## Запуск для разработки

```sh
cp .env.example .env
docker compose up -d --build          # nginx (80/443), app, db, frontend
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan storage:link   # обязательно: фото/аватары
```

- API: `http://localhost/api/...`
- Админка: `http://localhost/admin` — вход только для пользователей с `is_admin = true`.
  Локально `nginx.dev.conf` (подменяет прод-конфиг через `docker-compose.override.yml`)
  проксирует в Laravel пути `/admin`, `/livewire`, `/filament`, `/up` и статику
  `/js|/css/filament`; всё остальное уходит в SPA. Поэтому админка открывается
  на порту 80 (`http://localhost/admin`), а SPA — на `http://localhost:9000`.
- Фронтенд (SPA) собирается из соседней папки `../locsy-spa-quasar` сервисом
  `frontend`, внешний nginx проксирует: `/api/`, `/sanctum/`, `/storage/`,
  `/admin`, `/livewire`, `/filament` → Laravel, остальное → SPA.

Первый администратор назначается вручную (в интерфейсе ещё некому нажать кнопку):

```sh
docker compose exec app php artisan tinker \
  --execute="\App\Models\User::where('email','you@example.com')->update(['is_admin' => true]);"
# либо SQL:
docker exec locsy-laravel-backend-db-1 psql -U sail -d locsy \
  -c "update users set is_admin = true where email = 'you@example.com';"
```

Дальше права раздаются в админке: «Администрирование» → «Пользователи» → тумблер
«Админ». Пароль, если письмо для сброса не доходит: `php artisan locsy:user-password <email>`.

В `.env` для локальной разработки должно быть:

```env
APP_URL=http://localhost            # в продакшене — реальный домен с https!
FRONTEND_URL=http://localhost:9000  # origin SPA (CORS)
SANCTUM_STATEFUL_DOMAINS=localhost:9000
DB_CONNECTION=pgsql
DB_HOST=db
DB_DATABASE=locsy
DB_USERNAME=sail
DB_PASSWORD=password
```

> `APP_URL` влияет на абсолютные ссылки фотографий (`asset('storage/...')`).

## API

### Публичные

| Метод | Путь | Описание |
|---|---|---|
| GET | `/api/cities?search=` | Города РФ (население > 100 тыс.), поиск по названию/региону |
| GET | `/api/categories` | Категории локаций |
| GET | `/api/locations?city_id=&category_ids[]=` | Локации города (только одобренные) |
| GET | `/api/locations/by-bounds?sw_lat&sw_lng&ne_lat&ne_lng` | Локации в видимой области карты |
| GET | `/api/location/{id}` | Карточка локации с одобренными фото |
| GET | `/api/photographers/{userId}` | Профиль фотографа: портфолио + точки съёмок |
| POST | `/api/forgot-password` | Письмо со ссылкой для сброса пароля (лимит 5 запросов/мин на email+IP) |
| POST | `/api/reset-password` | Новый пароль по токену из письма |

Во всех ответах отдаются **только фотографии со статусом `approved`**.

Названия городов отдаются в виде «Город (Регион)»: русское имя выбирается из `alternatenames`,
известные расхождения GeoNames правятся в `CityController` (`CITY_NAME_OVERRIDES`,
`CITY_REGION_OVERRIDES`), регион не дублирует название города. Подробности — §9 в
[`docs/ENVIRONMENTS.md`](./docs/ENVIRONMENTS.md).

### Почта

Письма уходят только через SMTP — в образе нет `sendmail`, а API-транспорты (`resend`,
`mailgun`, `ses`, `postmark`) потребовали бы дополнительных пакетов. Локально удобно
смотреть письма в **Mailpit**: `docker compose up -d mailpit` → http://localhost:8025
(в `.env`: `MAIL_MAILER=smtp`, `MAIL_HOST=mailpit`, `MAIL_PORT=1025`), либо
`MAIL_MAILER=log` — тогда письмо пишется в `storage/logs/laravel.log`.

Приложение шлёт четыре письма:

- **подтверждение почты** — сразу после регистрации (`VerifyEmailNotification`):
  подписанная ссылка на `GET /email/verify/{id}/{hash}` (живёт 60 минут);
- **приветствие** — после успешного подтверждения (`WelcomeNotification`):
  фотографу со ссылкой на его профиль, обычному пользователю — на каталог локаций;
- **сброс пароля** — по запросу `POST /api/forgot-password` (`ResetPasswordNotification`);
- **пароль изменён** — после успешной смены пароля (`PasswordChangedNotification`).

Подтверждение адреса обязательное: до перехода по ссылке функции аккаунта закрыты
(middleware `verified`, ответ — `403` с `email_verified: false`), гостю и
неподтверждённому пользователю доступны карта и локации. Отправка писем обёрнута
в `try/catch` — если почта недоступна, регистрация/подтверждение/сброс всё равно
проходят, а ошибка пишется в лог.

Локальный `.env` и `.env.example` по умолчанию настроены на Mailpit:
`MAIL_MAILER=smtp`, `MAIL_HOST=mailpit`, `MAIL_PORT=1025`,
`MAIL_FROM_ADDRESS=no-reply@locsy.local`, `MAIL_FROM_NAME=getlocsy`.
В `MAIL_REPLY_TO_ADDRESS` можно указать свой живой ящик — адрес попадёт в
заголовок `Reply-To`. Проверить отправку можно и без браузера:

```sh
docker compose exec app php artisan tinker \
  --execute="\App\Models\User::where('email','test@mail.ru')->first()
    ->notify(new \App\Notifications\ResetPasswordNotification('test-token'));"
curl -s http://localhost:8025/api/v1/messages | head -c 200   # письмо в Mailpit
```

На обкатке/проде: `smtp.beget.com`, порт `465`, `MAIL_SCHEME=smtps`, логин — полный
адрес ящика (`no-reply@<домен>`), ответы уводятся на живой ящик через
`MAIL_REPLY_TO_ADDRESS`. Ссылки в письмах строятся от `FRONTEND_URL` контура.
Ответ `POST /api/forgot-password` всегда одинаковый — по нему нельзя узнать,
зарегистрирован адрес или нет. Настройка DNS (SPF/DKIM/DMARC), проверка доставки
и админский сброс пароля — §8 в [`docs/ENVIRONMENTS.md`](./docs/ENVIRONMENTS.md).

### Требуют авторизации (`auth:sanctum`)

| Метод | Путь | Описание |
|---|---|---|
| GET | `/api/user` | Текущий пользователь + профиль фотографа |
| GET | `/api/user/locations` | Мои локации со статусом модерации и счётчиками фото |
| GET | `/api/user/photos` | Мои фото: статус, причина отказа, локация |
| PUT | `/api/user/photographer-profile` | Создать/обновить профиль фотографа |
| POST | `/api/locations` | Создать локацию (+ фото, `photos[]`) |
| POST | `/api/locations/{id}/photos` | Добавить фото к локации |
| DELETE | `/api/photos/{id}` | Удалить своё фото |
| GET | `/api/favorites` | Избранные локации |
| POST/DELETE | `/api/locations/{id}/favorite` | Добавить/убрать из избранного |
| PUT | `/api/user/city` | Сменить город |
| POST | `/api/user/avatar` | Загрузить аватар |
| POST | `/api/email/verification-notification` | Повторная отправка письма подтверждения (доступно и без подтверждения) |

Все пути выше, кроме `GET /api/user` и повторной отправки письма, требуют
подтверждённой почты (middleware `verified`); иначе — `403` с `email_verified: false`.

### Регистрация и вход

| Метод | Путь | Описание |
|---|---|---|
| POST | `/api/register` | Регистрация: `role=user` или `role=photographer` (+ поля профиля); шлёт письмо-подтверждение (лимит 5/мин на IP) |
| POST | `/api/login` | Вход (лимит 5 попыток в минуту на email+IP) |
| POST | `/api/logout` | Выход: отзыв токена + инвалидация сессии |
| GET | `/email/verify/{id}/{hash}` | Ссылка из письма: подтверждает почту и редиректит на страницу SPA |

## Модерация

- **Локации:** статусы `pending / approved / rejected`. Автор-пользователь видит
  статус в профиле; модерация включается настройкой `location_moderation_enabled`.
- **Фотографии:** статусы `pending / approved / rejected`, причина отказа
  (`moderation_note`) видна автору. Модерация фото включена по умолчанию
  (`photo_moderation_enabled = true`): в публичные галереи попадают только
  одобренные снимки.
- Админка: `/admin` (Filament), интерфейс на русском
  (`App\Http\Middleware\SetAdminLocale`; локаль API при этом не меняется), бренд «Locsy».
  Разделы:
  - **Дашборд** — виджет «очередь модерации»: фото и локации на модерации,
    опубликованные фото, пользователи и фотографы (`ModerationStatsWidget`);
  - **Модерация → Фотографии** — фильтр по статусу, счётчик очереди в боковом меню,
    «Одобрить» (с подтверждением, очищает причину отказа) и «Отклонить»
    (обязательная причина), массовое одобрение, автообновление списка раз в 30 с;
  - **Модерация → Локации** — по умолчанию открыт фильтр «На модерации»,
    фильтр по городу, «Одобрить»/«Отклонить», массовое одобрение, количество фото
    у локации и relation manager «Фотографии»;
  - **Администрирование → Пользователи** — роли (фотограф / ищу места),
    тумблер «Админ» (доступ в админку), город, ручная смена пароля;
  - **Каталог → Категории** — справочник категорий локаций;
  - **Настройки → Правила модерации** — переключатели модерации локаций и
    фотографий (`App\Settings\ModerationSettings`).

Доступ в панель — `User::canAccessPanel()` по флагу `is_admin`: гость уходит на
`/admin/login`, авторизованный не-админ получает 403. Поведение зафиксировано в
`tests/Feature/AdminPanelTest.php`.

## Команды

```sh
composer test                       # php artisan test (сначала чистит кеш конфига)
vendor/bin/pint --test app tests    # стиль кода
php artisan migrate --seed          # миграции + сиды (города, категории, настройки)
```

### Демо-данные для карты: города

Городские наборы лежат в двух сидерах — `MoscowParksSeeder` (21 место) и
`YaroslavlPlacesSeeder` (14 мест). Общая механика (поиск города по geonameid,
`updateOrCreate` по паре `(city_id, name)`, категории через `firstOrCreate`)
вынесена в базовый класс `CityPlacesSeeder` — новый город добавляется одним
классом-наследником с массивом мест. Координаты взяты из открытых источников
(Wikidata `P625`, OpenStreetMap Nominatim), все локации публикуются сразу со
статусом `approved`, иначе их не покажет публичный каталог.

Сидеры идемпотентные: повторный запуск обновляет записи, ничего не удаляет и не
делает `truncate`, поэтому на существующей базе их запускают отдельно:

```sh
# локально
docker compose exec app php artisan db:seed --class=MoscowParksSeeder
docker compose exec app php artisan db:seed --class=YaroslavlPlacesSeeder

# на сервере обкатки (см. DEPLOY.md): сервис называется locsy-app, artisan — от пользователя application
docker compose exec -T -u application locsy-app php artisan db:seed --class=MoscowParksSeeder --force
docker compose exec -T -u application locsy-app php artisan db:seed --class=YaroslavlPlacesSeeder --force
```

На сервере обязателен флаг `--force`: при `APP_ENV=production` Laravel спрашивает
подтверждение, а в неинтерактивном режиме (`-T`) без флага команда отменяется
(«Command cancelled»).

Для сидов не нужны ни миграции, ни пересборка образа: они работают с уже
существующими таблицами. Если города нет в `cities`, сидер пишет ошибку и
ничего не создаёт (сначала `CitiesTableSeeder`).

В `DatabaseSeeder` они подключены последними, так что свежая установка сразу
получает наполненный каталог. На рабочей базе не запускайте `migrate --seed`
целиком: `CitiesTableSeeder` и `CategorySeeder` рассчитаны на чистую базу
(пересоздают справочники городов и категорий).

Тесты работают только с базой `locsy_testing`: она зафиксирована в
`tests/TestCase.php` (константа `TESTING_DATABASE`) и дополнительно проверяется в
`setUp()` — если подключение ушло в другую базу, прогон останавливается с ошибкой,
чтобы `RefreshDatabase` не очистил рабочие данные.

> Не добавляйте `env_file: .env` обратно в сервис `app` (docker-compose.yml):
> переменные окружения контейнера перебивают `.env.testing` и `phpunit.xml`,
> из-за чего тесты уходят в dev-базу. Laravel читает `.env` сам из кода.

Тестовые файлы:

- `tests/Feature/PhotoModerationTest.php` — модерация фото, роль фотографа при
  регистрации, границы карты;
- `tests/Feature/CityApiTest.php` — города, только одобренные локации, редирект `/`;
- `tests/Feature/LocationApiTest.php`, `tests/Feature/PhotoApiTest.php` — доступы.

## Изображения и хранилище

Фото и аватары лежат на диске `public` (`storage/app/public`), поэтому обязателен
симлинк `public/storage`:

```sh
docker compose exec app php artisan storage:link
```

В продакшене внешний nginx проксирует `/storage/` в Laravel (`nginx.conf`),
иначе картинки будут отдаваться SPA и превратятся в 404.

## Структура

```
app/
  Enums/         LocationStatus, PhotoStatus
  Services/      PhotoStorage (обработка и сохранение фото)
  Http/Controllers/Api/   Auth, City, Category, Location, Photo, Favorite, Photographer
  Models/        User, City, Location, Photo, PhotographerProfile, Category
  Filament/      Resources (Location, Category, Photo), Pages (ManageModeration)
  Settings/      ModerationSettings
routes/api.php   публичные и защищённые маршруты API
```
