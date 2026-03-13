# Locsy - Backend

> REST API для сервиса по поиску фотолокаций Locsy.

## Стек технологий

- **Framework:** Laravel
- **База данных:** PostgreSQL
- **Окружение для разработки:** Docker

---

## Как запустить для разработки

Этот проект использует Docker для создания консистентного и изолированного окружения. Вам не нужно устанавливать PHP или PostgreSQL на ваш компьютер, только Docker.

**1. Клонируйте репозиторий**

```sh
git clone <адрес-вашего-репозитория>
cd locsy-laravel-backend
```

**2. Создайте файл окружения (`.env`)**

Скопируйте файл с примером настроек.

```sh
cp .env.example .env
```

**3. Настройте подключение к базе данных**

Откройте только что созданный файл `.env` и убедитесь, что секция с настройками базы данных выглядит так:

```env
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=locsy
DB_USERNAME=sail
DB_PASSWORD=password
```

**4. Запустите Docker-контейнеры**

Эта команда соберет и запустит контейнеры с приложением и базой данных в фоновом режиме.

```sh
docker compose up -d --build
```

**5. Установите PHP-зависимости**

Выполните установку зависимостей с помощью Composer внутри Docker-контейнера.

```sh
docker compose exec app composer install
```

**6. Сгенерируйте ключ приложения**

```sh
docker compose exec app php artisan key:generate
```

**7. Выполните миграции базы данных**

Эта команда создаст все необходимые таблицы в базе данных.

```sh
docker compose exec app php artisan migrate
```

---

**Готово!**

Ваше приложение будет доступно по адресу: [http://localhost:8000](http://localhost:8000)

## Полезные команды Docker

- **Остановить контейнеры:**
  ```sh
  docker compose down
  ```
- **Запустить остановленные контейнеры:**
  ```sh
  docker compose start
  ```
- **Зайти в командную строку контейнера приложения:**
  ```sh
  docker compose exec app bash
  ```
- **Посмотреть логи контейнера приложения:**
  ```sh
  docker compose logs -f app
  ```
