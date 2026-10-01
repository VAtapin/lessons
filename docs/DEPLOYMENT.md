# Развёртывание в Plesk

## Подтверждённая среда

- SSH: пользователь `lessons`, домен `lessons.atapin.de`, порт **2377**. Порт 8023 относится к SFTP.
- Репозиторий: `https://github.com/VAtapin/lessons.git`, ветка `main`.
- Проект: `/var/www/vhosts/lessons.atapin.de/httpdocs`.
- Document root: `httpdocs/public`; владелец подтвердил изменение настройки в Plesk.
- PHP CLI: `/opt/plesk/php/8.5/bin/php`, версия 8.5.11; выбран PHP 8.5.11 FPM.
- Node: `/opt/plesk/node/22/bin/node`, версия 22.23.3.
- Composer PHAR: `/opt/psa/var/modules/composer/composer.phar`. `/usr/local/bin/composer` является shell-обёрткой; передавать её PHP нельзя.
- MariaDB 10.6.23, `localhost:3306`. БД обязательна для материалов, занятий, заготовок и метаданных медиа.

Исходники доставляются только через Git. `.env` и ключ приложения создаются на сервере, не коммитятся и не передаются через SFTP. `vendor`, `node_modules` и `public/build` собираются на сервере и исключены из Git. Файлы хранилища и конфигурация Plesk сохраняются при обновлении.

## Требования библиотеки и медиатеки — этап 4

PHP CLI и выбранный PHP 8.5 FPM должны иметь `ext-gd`. Composer platform requirements проверяет CLI; наличие расширения и настройки FPM проверить отдельно в Plesk. Для загрузки 20 MiB с multipart-метаданными установить `upload_max_filesize` и `post_max_size` не ниже `32M`, `memory_limit` — `512M`. Эти параметры задаются в PHP configuration, не в Laravel `.env`; HTTP/proxy body limit также должен пропускать разрешённую загрузку. Лимиты приложения заданы в `config/lessons.php` и перечислены в `.env.example`: guest quota 100 MiB, file limit 20 MiB, до 8192 по стороне и 32 × 1024² пикселей. Изменять pixel limits вместе с доступной памятью для полного декодирования GD.

Private media disk по умолчанию — `storage/app/media`; FPM-владельцу нужны права записи. Файлы создаются с private permissions (0600, каталоги 0700), не публикуются через `storage:link`. Встроенные 19 иллюстраций поставляются Git из `UI-Design` и `assets/library`, выдаются через version manifest. Uploads и архивные версии не удаляются deployment script; архив в приложении не означает физическое удаление.

Изменения этапа 4 требуют additive migrations заготовок/медиа; для уже инициализированной production использовать описанный ниже `--migrate`. Следующие миграции предваряются полным приватным bundle SQL и immutable media, описанным ниже. Подтверждённое состояние CI/production записывается в PROJECT_STATUS.md.

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

Перед запуском отредактировать `.env` в терминале: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://lessons.atapin.de`, `LOG_LEVEL=warning`, `SESSION_SECURE_COOKIE=true`. Задать параметры отдельной MariaDB-БД. Пароли вводить только в серверном файле/безопасном менеджере, не в Git и не в командной строке. Оставить `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`.

```bash
cd /var/www/vhosts/lessons.atapin.de/httpdocs && \
export PATH="/opt/plesk/php/8.5/bin:/opt/plesk/node/22/bin:$PATH" && \
php /opt/psa/var/modules/composer/composer.phar install --no-dev --prefer-dist --optimize-autoloader --no-interaction && \
php artisan key:generate --force && \
bash scripts/deploy-plesk.sh --initialize-database
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

Флаг разрешает первый запуск additive migrations только на проверенной пустой БД. Он создаёт базовые Laravel-таблицы и domain-таблицы текущей версии; не делает seed, fresh/reset или удаления данных. После создания таблиц этот флаг повторно не использовать.

## Обновление непустой БД с новыми migrations

Production уже инициализирована: для новых additive migrations использовать `--migrate`, а не повторную инициализацию.

```bash
cd /var/www/vhosts/lessons.atapin.de/httpdocs && \
export PATH="/opt/plesk/php/8.5/bin:/opt/plesk/node/22/bin:$PATH" && \
php artisan down --retry=30 && \
git pull --ff-only && \
bash scripts/deploy-plesk.sh --migrate
```

После установки locked dependencies/build и очистки кешей script проверяет подключение БД, выполняет `lessons:backup` и только после успешного полного bundle запускает `migrate --force --no-interaction`. Флаги `--migrate` и `--initialize-database` взаимоисключающие. Без флага migrations не выполняются. Migration failure не снимает maintenance и не запускает автоматический rollback/fresh/reset.

### Приватная резервная копия

`php artisan lessons:database-backup` использует конфигурацию активного Laravel connection; поддерживаются только MySQL/MariaDB. На подтверждённом сервере доступны `/usr/bin/mariadb-dump` и `/usr/bin/mysqldump` версии MariaDB 10.6.23. Команда предпочитает `mariadb-dump`, при его отсутствии ищет `mysqldump`; отсутствие обоих — ошибка до migrations.

Default directory: `/var/www/vhosts/lessons.atapin.de/private/lessons-backups`, вне `httpdocs`. При первом запуске отсутствующие приватные каталоги создаются с правами 0700. SQL-файл и временный defaults-file создаются эксклюзивно с правами 0600 под umask 077. Уже существующий backup directory с другими POSIX-правами отклоняется. Произвольный `--directory` должен быть абсолютным, вне приложения, без traversal и symbolic links. Тестовые backups также сохраняются вне checkout; SQL и credentials не включаются в Git и не выводятся в терминал.

Dump включает всю выбранную schema и данные, views, triggers, routines и events; используется `--single-transaction --quick`. Credentials записываются в временный приватный файл: первым аргументом передаётся `--defaults-file`, чтобы dump читал только этот файл, без глобальных defaults и `~/.my.cnf` ([MariaDB: mariadb-dump](https://mariadb.com/docs/server/clients-and-utilities/backup-restore-and-import-clients/mariadb-dump)). Password не передаётся через argv или environment дочернего процесса. Symfony Process запускает массив аргументов без shell и получает минимальное окружение без Laravel DB credentials. Credentials и частичный dump удаляются при штатной ошибке/timeout; процессные diagnostics не выводятся, так как могут содержать данные.

Timeout по умолчанию — 300 секунд, допустимый `--timeout` — 1–3600 секунд. Успех требует нулевого exit code и непустого regular SQL-файла; временное расширение `.sql.partial` меняется на `.sql` только после этих проверок. Команда сообщает путь и SHA256. Это проверка создания файла, а не доказательство успешного restore. Backup не архивируется и не удаляется автоматически; хранение и отдельная копия за пределами сервера остаются задачами владельца.

**SQL-only команда `lessons:database-backup` сохраняется для диагностики и не включает media.** Для миграций применяется `php artisan lessons:backup`: каталог bundle содержит `database.sql`, `media/versions/{assetId}/{versionId}.{ext}` и `manifest.json` формата 1. Manifest фиксирует UTC-время, размер/SHA256 SQL и точные IDs, versionNo, относительные пути, размер/SHA256 каждой сохранённой private версии, включая старые и архивные. Builtins восстанавливаются Git; `.env`, credentials и незарегистрированные файлы в bundle не копируются.

Bundle поддерживает MySQL/MariaDB и настроенный local media disk. SQL создаётся первым; затем потоково копируются committed immutable версии с проверкой source и destination по размеру и SHA256. Отсутствующий/повреждённый файл, неизвестная media schema, неверный storage key или symlink прекращают операцию до migrations. Неиспользованный media disk может отсутствовать, если в БД нет версий. Временный каталог `.partial` становится завершённым только после проверки всех файлов и записи manifest; directories имеют 0700, files — 0600. Штатный отказ удаляет только созданный partial, сохраняя источники и прежние backups. Команда выводит путь bundle и SHA256 manifest, без SQL/медиа/credentials.

Операция предполагает неизменность сохранённых версий и отсутствие физического удаления файлов. Более поздние committed версии могут попасть в bundle как безопасные дополнительные файлы относительно SQL snapshot. На время backup исключить параллельные DDL, сторонние записи и физическую очистку media; будущая очистка должна координироваться с backup. `--timeout=300` ограничивает dump, а не всё копирование файлов. Автоматическое удаление backups и offsite copy пока не реализованы. Проверка checksum не заменяет тест восстановления SQL и media в изолированной среде.

Дамп выполняется в maintenance до изменения schema. `--single-transaction` даёт согласованный snapshot данных InnoDB; для nontransactional tables такой гарантии нет. Параллельные DDL и внешние записи в nontransactional tables недопустимы. Maintenance блокирует HTTP-запросы приложения, но не другие CLI-команды и внешние DB-клиенты: на время backup/migrations владелец должен исключить их записи и schema changes. Application DB user должен иметь права чтения schema/data, views, triggers, routines и events; нехватка прав прекращает deployment до migrations. Права БД автоматически не меняются.

### Восстановление после ошибки

Если backup завершился ошибкой, migrations не выполнялись: исправить доступ к dump utility/БД, приватные permissions или свободное место и повторить `--migrate`. Если CLI был аварийно убит, проверить приватный каталог на оставшиеся credentials/`.partial`; не считать такой файл завершённой копией и не раскрывать его содержимое.

Если миграция завершилась ошибкой, MariaDB DDL могла примениться частично. Сохранить первоначальный bundle и SHA256 manifest, оставить maintenance, проверить `php artisan migrate:status` и причину отказа. После согласованного исправления продолжить additive migrations через `--migrate` либо выполнить отдельно разрешённое восстановление SQL и media из первоначального bundle. Повторный backup после частичной миграции не заменяет исходный снимок. Не запускать `migrate:fresh`, `reset` или автоматический rollback. Возвращать сайт online только после успешных `lessons:check` и deployment. Production restore требует отдельного разрешения и здесь не выполняется.

## Последующие обновления без новых migrations

```bash
cd /var/www/vhosts/lessons.atapin.de/httpdocs && \
export PATH="/opt/plesk/php/8.5/bin:/opt/plesk/node/22/bin:$PATH" && \
git pull --ff-only && \
bash scripts/deploy-plesk.sh
```

Script проверяет ветку, чистоту дерева и версии среды, включает maintenance, получает Git-изменения, устанавливает locked зависимости, проверяет требования PHP, собирает Vue, очищает кеши и выполняет `lessons:check` перед кешированием Laravel/возвращением сайта online. Без флага migrations не запускаются. При ошибке до возвращения online сайт остаётся в maintenance: устранить причину, выбрать соответствующий режим обновления; не делать `artisan up`, пока проверка хранения не прошла. Если итоговый HTTP check завершился ошибкой после `artisan up`, maintenance уже снят: сообщить об этом отдельно и проверить приложение; такой deployment не считать проверенным.

## Проверка

Health endpoint: `https://lessons.atapin.de/up` проверяет загрузку приложения. `php artisan lessons:check` отдельно проверяет подключение, обязательные domain-таблицы/колонки (включая заготовки/медиа) и binary collation block ID на MariaDB. Это не нагрузочный тест или проверка backup restore. Страницы `/ru/studio`, `/de/studio` открывают конструктор; `/ru/library`, `/de/library` — заготовки; `/ru/media`, `/de/media` — медиатеку; `/ru/join`, `/de/join` — вход ученика.

После запуска проверить HTTPS, Vue и изображения, RU/DE, отсутствие доступа к `.env`, `.git` и исходникам. Пройти создание → сохранение → запуск → проектор → ответ ученика в браузере; подтвердить отсутствие закрытых данных на публичных экранах. CLI checks не раскрывают конфигурацию/пароли и ученические ответы.

Для этапа 4 дополнительно пройти upload → выбор своей версии → save/reload → заготовка → две независимые вставки → runtime с private image. Проверить замену/архив/восстановление без потери старой версии. Owner URL `/media/owned/{assetId}/{versionId}` требует владельца; `/media/participation/{sessionId}/{assetId}/{versionId}` и `/media/projection/{token}/{assetId}/{versionId}` разрешают только references активного этапа соответствующего занятия. Прямой storage URL и чужие/future references не должны отдавать файл. API библиотеки и медиатеки описаны в [STAGE4_API.md](STAGE4_API.md).

## Аккаунт, почта и retention — этап 6A

Этап 6A добавляет совместимые migrations account ownership и history/rehearsal metadata. Обновление выполняется через `bash scripts/deploy-plesk.sh --migrate`, с приватным SQL/media bundle до изменения schema. Не переносить гостевые ресурсы SQL-командой: подтверждённый пользователь выполняет explicit claim в аккаунте, с общим owner mutex, проверкой квоты и permanent receipt.

Для PHP-почты на этом сервере подтверждены CLI/FPM `sendmail_path=/usr/sbin/sendmail -t -i` и executable Plesk wrapper `/usr/lib/plesk-9.0/postfix-sendmail-wrapper`. В приватном `.env` настроить:

```dotenv
MAIL_MAILER=sendmail
MAIL_SENDMAIL_PATH="/usr/sbin/sendmail -t -i"
MAIL_FROM_ADDRESS=lessons@atapin.de
MAIL_FROM_NAME=lessons.atapin.de
```

После изменения config выполнить обычный deployment script, который пересобирает Laravel config cache. На другом Plesk сервере сначала проверить его действующий binary/CLI/FPM конфигурацию. Production auth notification не принимает log/array/failover как успешную реальную отправку; отказ регистрации оставляет созданный аккаунт и позволяет повторить email verification. При forgot-password ответ остаётся generic. Проверки notifications в тестах и наличие sendmail не подтверждают получение письма: реальная доставка требует отдельного согласованного получателя, без рассылки технических писем людям автоматически.

Закрытые репетиции доступны только владельцу, без ordinary join/projector bearer access. История и media-проекции проверяют expiry до чтения, даже если cleanup не выполнялся. Сроки: finished guest lesson/details — 30 дней от finished_at, account history — 2 календарных года, finished rehearsal — 7 дней от created_at, receipts — 30 дней от created_at. Активные реальные занятия и finished legacy без достоверной finished_at защищены. Authored materials/media/templates и permanent claim receipts не очищаются.

Безопасная проверка плана очистки:

```bash
cd /var/www/vhosts/lessons.atapin.de/httpdocs && \
export PATH="/opt/plesk/php/8.5/bin:$PATH" && \
php artisan lessons:retention --dry-run --batch=100
```

Команда выводит counts без имён, ответов, SQL или secrets. `--batch` ограничивает просмотр кандидатов; dry-run ничего не меняет. Write CLI `lessons:retention --batch=100` и background `ScheduledRetention` реализованы. Расписание выключено по умолчанию; фактическая production настройка записана в PROJECT_STATUS.md. Перед автоматическим запуском проверить восстановление bundle и актуальные cutoffs, затем отдельно настроить Plesk task/очередь с явным PHP 8.5. Не запускать job через HTTP и не заменять существующий crontab. Очистка нескольких batches идемпотентна; после operational failure ранее завершённые batches остаются committed. После restore применить согласованные cutoffs до возвращения сайта online; старый backup не должен вновь открыть истёкшие подробности.

Auth/claim, history и rehearsal API описаны в [STAGE6_API.md](STAGE6_API.md). Autosave/undo/partial translations относятся к [STAGE6_EDITOR_API.md](STAGE6_EDITOR_API.md) и не считаются выполненными вместе с регистрацией.

Для этапа 6B требуется additive migration `2026_10_01_150000`: private `editor_draft` и `lesson_save_receipts`. Применять штатный `deploy-plesk.sh --migrate` после полного SQL/media backup. Retention дополнительно возвращает `saveReceiptsDeleted`: отдельный bounded batch квитанций сохранения старше 30 дней, с owner/material locks и повторной проверкой после переноса аккаунта. Содержание, редакции, quota и permanent claim tombstones эта очистка не меняет; расписание остаётся выключено до эксплуатационной приёмки.

Commit/push и зелёная локальная проверка не означают успешный deployment. Статус production подтверждается отдельно после выполнения команд и HTTP-проверки.

## Главная, каталог и двуязычная тема

Миграция `2026_10_01_160000` добавляет каталог, привязанный к проверенному immutable released-снимку. При первом обновлении применить штатный deployment с SQL/media backup, затем установить тему из версионированного источника:

```bash
cd /var/www/vhosts/lessons.atapin.de/httpdocs && \
export PATH="/opt/plesk/php/8.5/bin:/opt/plesk/node/22/bin:$PATH" && \
bash scripts/deploy-plesk.sh --migrate && \
php artisan lessons:install-neighbor && \
php artisan lessons:check
```

Установка идемпотентна: повтор проверяет источник и изображения; существующий выпущенный снимок не перезаписывается. Тема содержит 13 этапов и 8 общих иллюстраций на RU/DE. Проверить `/ru/catalog/kto-moi-blizhnii` и `/de/catalog/kto-moi-blizhnii`, поиск/фильтры, запуск нового занятия и отсутствие закрытых заметок/решений в публичном preview. Публикация личных материалов требует отдельной административной проверки.

## Администрирование и фоновые операции

Миграции `2026_10_01_190000` и `2026_10_01_200000` добавляют административные справочники и технические execution records. Применяется штатный `bash scripts/deploy-plesk.sh --migrate`: private SQL/media backup обязателен перед migrations, затем выполняются schema check, caches, `queue:restart`, возврат online и HTTPS health check.

Deployment и фоновые backup/retention используют один приватный flock. Если lock занят, deployment прекращается до maintenance и Git updates. При pre-schema ошибке maintenance снимается только после успешного `lessons:check`; после начала migration или при неуспешной проверке сайт остаётся закрыт до проверки оператором. Первоначально закрытый сайт также не открывается автоматически при ошибке.

Фоновые расписания реализованы, но выключены по умолчанию. Их включение, отдельные Plesk minute tasks/queues, реальный isolated restore и server-side gates описаны в [OPERATIONS.md](OPERATIONS.md). Исторические отчёты ниже относятся к указанным commits; актуальный проверенный production и настройки находятся в [PROJECT_STATUS.md](../PROJECT_STATUS.md).

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

В production браузере создана явно техническая сборка трёх блоков/двух этапов, сохранена, восстановлена после перезагрузки и запущена. Изображение и закрытая карточка ведущего работают, console errors не обнаружены. Проектор проверен только чтением: закрытые заметки/решения отсутствуют. После отдельного явного разрешения владельца тестовый ученик вошёл в занятие, отправил ответ; ответ дошёл ведущему и сохранился после перехода вперёд/назад. Тестовые данные не удалялись; schema/data reset и SQL-удаления не выполнялись.

GitHub CI прошёл, включая реальную MariaDB 10.6: [run 36778205368](https://github.com/VAtapin/lessons/actions/runs/36778205368). Это подтверждает минимальную вертикаль этапа 2, а не остальные этапы платформы или нагрузочную готовность.

## Подтверждённый запуск этапа 3 — 2026-10-01

Implementation commit `4f3d8e4a017e366821b21e78c2291fe76e85bf17` (`Add reliable conducting controls and private database backups`) установлен через Git и `deploy-plesk.sh --migrate`. Composer platform requirements, Node 22 typecheck/build, preflight, additive migration, `lessons:check`, Laravel caches и HTTPS health check прошли. Server main чистый; APP_KEY и production credentials сохранены.

Перед migration создан приватный backup `/var/www/vhosts/lessons.atapin.de/private/lessons-backups/lessons-20260930-220534-a0c20e20547a4753.sql`: 21 972 байта, SHA256 `ac83b29fc3069d1ffd28a40245f46167e399747c5d52e821e154014caf0f2a7d`, файл 0600, каталоги 0700. Временные credentials и partial-файлы отсутствуют. Restore не выполнялся. HTTP `/up`, RU/DE studio и join — 200; `.env`, `.git/config`, исходник RuntimeService и URL private backup — 404.

В production браузере проверен отдельный технический запуск: подготовка/начало, QR, вход/ответ ученика, таймер, пауза/перезагрузка/продолжение. Compact controller управляет тем же состоянием, сообщение отображается literal текстом; завершение через inline confirmation подтверждено сервером. Старые занятия и данные других участников не изменялись. Native popup подключение/возврат и потеря связи проверены локально; полная browser matrix и backup restore остаются отдельными проверками.

GitHub CI: PHP 8.4/8.5, Node 22 и MariaDB 10.6 прошли — [run 36783228232](https://github.com/VAtapin/lessons/actions/runs/36783228232).
