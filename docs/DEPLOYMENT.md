# Развёртывание в Plesk

## Подтверждённая среда

- SSH: пользователь `lessons`, домен `lessons.atapin.de`, порт **2377**. Порт 8023 относится к SFTP.
- Репозиторий: `https://github.com/VAtapin/lessons.git`, ветка `main`.
- Проект: `/var/www/vhosts/lessons.atapin.de/httpdocs`.
- Document root: `httpdocs/public`; владелец подтвердил изменение настройки в Plesk.
- PHP CLI: `/opt/plesk/php/8.5/bin/php`, версия 8.5.11; выбран PHP 8.5.11 FPM.
- Node: `/opt/plesk/node/22/bin/node`, версия 22.23.3.
- Composer PHAR: `/opt/psa/var/modules/composer/composer.phar`. `/usr/local/bin/composer` является shell-обёрткой; передавать её PHP нельзя.
- MariaDB 10.6.23, `localhost:3306`. На этапе 1 приложение БД не использует.

Исходники доставляются только через Git. `.env` и ключ приложения создаются на сервере, не коммитятся и не передаются через SFTP. `vendor`, `node_modules` и `public/build` собираются на сервере и исключены из Git. Файлы хранилища и конфигурация Plesk сохраняются при обновлении.

## Первоначальный запуск

Каталог исходно не содержит приложения; Plesk может уже создать пустую папку `public`. Git подключается без удаления этой папки. При наличии других файлов сначала проверить конфликт; пользовательские файлы нельзя перезаписывать:

```bash
cd /var/www/vhosts/lessons.atapin.de/httpdocs && \
git init --initial-branch=main && \
git remote add origin https://github.com/VAtapin/lessons.git && \
git pull --ff-only origin main && \
git branch --set-upstream-to=origin/main main && \
cp .env.example .env && \
chmod 600 .env
```

Перед запуском отредактировать `.env` в терминале: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://lessons.atapin.de`, `LOG_LEVEL=warning`, `SESSION_SECURE_COOKIE=true`. Задать MariaDB-параметры, если проверяется подключение, но не запускать migrations на этапе 1. Пароли вводить только в серверном файле/безопасном менеджере, не в Git и не в командной строке. Для первого этапа оставить `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`.

```bash
cd /var/www/vhosts/lessons.atapin.de/httpdocs && \
export PATH="/opt/plesk/php/8.5/bin:/opt/plesk/node/22/bin:$PATH" && \
php /opt/psa/var/modules/composer/composer.phar install --no-dev --prefer-dist --optimize-autoloader --no-interaction && \
php artisan key:generate --force && \
bash scripts/deploy-plesk.sh
```

Ключ создаётся один раз; повторно `key:generate` при обновлениях не выполнять. Права записи нужны владельцу FPM на `storage` и `bootstrap/cache`; не использовать `chmod 777`. Установку Composer dev dependencies и PHPUnit на production не выполнять.

## Последующие обновления

```bash
cd /var/www/vhosts/lessons.atapin.de/httpdocs && \
export PATH="/opt/plesk/php/8.5/bin:/opt/plesk/node/22/bin:$PATH" && \
git pull --ff-only && \
bash scripts/deploy-plesk.sh
```

Script проверяет ветку, чистоту дерева и версии среды; получает Git-изменения, устанавливает locked зависимости, проверяет требования PHP, собирает Vue и кеширует Laravel. На этапе 1 нет domain migrations, workers или scheduler: эти действия не запускаются. При появлении persistence и очередей workflow обновляется отдельной задачей с требованиями backup и rollback.

## Проверка

Health endpoint: `https://lessons.atapin.de/up`. Он проверяет загрузку приложения, а не БД или будущий движок проведения. Страницы `/`, `/ru`, `/de` — временная информация о подготовке платформы.

После первого запуска проверить HTTPS, Vue и изображения, RU/DE, отсутствие доступа к `.env`, `.git` и исходникам. Проверка базы при необходимости — только подключение и `SELECT VERSION()`, без schema/data mutations.

Commit/push и зелёная локальная проверка не означают успешный deployment. Статус production подтверждается отдельно после выполнения команд и HTTP-проверки.

## Подтверждённый первый запуск — 2026-09-30

Implementation commit `c0341cffb502c65f26d33e5d55cbbd8db42dbbb1` (`Initialize lesson platform foundation`) отправлен в `origin/main` и получен сервером через Git. Initial Git setup сохранил пустую папку `public`, созданную Plesk.

- Production Composer install и platform requirements на PHP 8.5.11 прошли; Node 22.23.3 typecheck и Vite build завершены, Laravel configuration/routes/views закешированы.
- `.env`: режим production, debug выключен, HTTPS URL и secure session cookie; права 600. Ключ создан один раз. Содержимое и credentials не выводились и не коммитились.
- HTTPS `/up` — HTTP 200. RU/DE, Vue и обе иллюстрации проверены в браузере, console errors не обнаружены.
- `/.env`, `/.git/config`, `/app/Domain/Lessons/LessonDocument.php`, `/UI-Design/UI-Design.png` — HTTP 404.
- MariaDB-подключение проверено `SELECT VERSION()`: 10.6.23-MariaDB. Migrations, seed и изменения таблиц/данных не выполнялись.
- GitHub CI implementation commit прошёл: [run 36771254689](https://github.com/VAtapin/lessons/actions/runs/36771254689).

Это deployment основы этапа 1 и временной стартовой страницы. Конструктор, проведение занятий и остальные возможности платформы не запущены и не выдаются за готовые.
