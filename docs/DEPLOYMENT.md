# Развёртывание в Plesk

## Подтверждённая среда

- SSH: пользователь `lessons`, домен `lessons.atapin.de`, порт **2377**. Порт 8023 относится к SFTP.
- Репозиторий: `https://github.com/VAtapin/lessons.git`, ветка `main`.
- Проект: `/var/www/vhosts/lessons.atapin.de/httpdocs`.
- Document root: `httpdocs/public`; владелец подтвердил изменение настройки в Plesk.
- PHP CLI: `/opt/plesk/php/8.5/bin/php`, версия 8.5.11; выбран PHP 8.5.11 FPM.
- Node: `/opt/plesk/node/22/bin/node`, версия 22.23.3.
- Composer PHAR: `/opt/psa/var/modules/composer/composer.phar`. `/usr/local/bin/composer` является shell-обёрткой; передавать её PHP нельзя.
- MariaDB 10.6.23, `localhost:3306`. На этапе 2 БД обязательна для материалов и занятий.

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

## Первый запуск хранения — этап 2

Production `.env` и ключ уже созданы; их не заменять. Перед первой инициализацией script проверяет метаданные настроенной БД: отсутствие таблиц, views, routines, triggers и events. При непустой schema или ошибке проверки он прекращает работу без изменения БД. Проверка предполагает отдельную application-БД и достаточные metadata privileges её владельца; чужую/shared schema этим способом не инициализировать.

```bash
cd /var/www/vhosts/lessons.atapin.de/httpdocs && \
export PATH="/opt/plesk/php/8.5/bin:/opt/plesk/node/22/bin:$PATH" && \
php artisan down --retry=30 && \
git pull --ff-only && \
bash scripts/deploy-plesk.sh --initialize-database
```

Флаг разрешает первый запуск additive migrations только на проверенной пустой БД. Он создаёт базовые Laravel-таблицы и пять domain-таблиц; не делает seed, fresh/reset или удаления данных. После создания таблиц этот флаг повторно не использовать.

Для непустой БД перед новыми migrations нужна проверенная резервная копия средствами Plesk, включая schema, данные, triggers/routines/events. Хранить backup вне `httpdocs`, например `/var/www/vhosts/lessons.atapin.de/private/lessons-backups`, с закрытыми правами; никогда не в Git. После подтверждения backup выполнить `php artisan migrate --force --no-interaction` в подготовленном Plesk environment, затем обычный deployment. Восстановление существующих данных — отдельное подтверждаемое действие; автоматический rollback/fresh не предусмотрен.

## Последующие обновления без новых migrations

```bash
cd /var/www/vhosts/lessons.atapin.de/httpdocs && \
export PATH="/opt/plesk/php/8.5/bin:/opt/plesk/node/22/bin:$PATH" && \
git pull --ff-only && \
bash scripts/deploy-plesk.sh
```

Script проверяет ветку, чистоту дерева и версии среды, включает maintenance, получает Git-изменения, устанавливает locked зависимости, проверяет требования PHP, собирает Vue, очищает кеши и выполняет `lessons:check` перед кешированием Laravel/возвращением сайта online. Без флага migrations не запускаются. Workers/scheduler ещё не используются. При ошибке сайт остаётся в maintenance: устранить причину и повторить deployment без initialization-флага, если таблицы уже созданы; не делать `artisan up`, пока проверка хранения не прошла.

## Проверка

Health endpoint: `https://lessons.atapin.de/up` проверяет загрузку приложения. `php artisan lessons:check` отдельно проверяет подключение, обязательные domain-таблицы/колонки и binary collation block ID на MariaDB. Это не нагрузочный тест или проверка backup restore. Страницы `/ru/studio`, `/de/studio` открывают конструктор; `/ru/join`, `/de/join` — вход ученика.

После запуска проверить HTTPS, Vue и изображения, RU/DE, отсутствие доступа к `.env`, `.git` и исходникам. Пройти создание → сохранение → запуск → проектор → ответ ученика в браузере; подтвердить отсутствие закрытых данных на публичных экранах. CLI checks не раскрывают конфигурацию/пароли и ученические ответы.

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

## Подтверждённый запуск этапа 2 — 2026-09-30

Implementation commit `fc75ab62bb5c7d47e02355b854080137e76bbbc6` (`Build the minimal lesson studio and runtime`) получен сервером через Git. До изменения schema проверена отдельная БД `lessons`: нет tables/views/routines/triggers/events; metadata permissions подтверждены чтением grants без вывода credentials. Initial migrations выполнены без удаления данных или seed, APP_KEY/.env сохранены.

Composer platform requirements, Node 22 typecheck/build, additive migrations и `lessons:check` на MariaDB 10.6.23 прошли. Подтверждена binary collation block ID. Maintenance снят после успешных проверок; HTTPS `/up`, `/ru/studio`, `/de/studio`, `/ru/join` отвечают HTTP 200, `.env`, `.git/config` и исходник runtime — HTTP 404. Server main чистый.

В production браузере создана явно техническая сборка трёх блоков/двух этапов, сохранена, восстановлена после перезагрузки и запущена. Изображение и закрытая карточка ведущего работают, console errors не обнаружены. Проектор проверен только чтением: закрытые заметки/решения отсутствуют. Добавление тестового ученика/ответа остановлено automatic approval review и ожидает отдельного разрешения владельца; эта часть production-проверки пока не выполнена. Полная цепочка проверена локально.

GitHub CI прошёл, включая реальную MariaDB 10.6: [run 36778205368](https://github.com/VAtapin/lessons/actions/runs/36778205368). Это подтверждает минимальную вертикаль этапа 2, а не остальные этапы платформы или нагрузочную готовность.
