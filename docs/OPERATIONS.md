# Эксплуатация: SQL/media restore и retention

Production: `/var/www/vhosts/lessons.atapin.de/httpdocs`, Plesk PHP 8.5, Node.js 22, MariaDB 10.6. SQL/media bundles создаёт существующий `lessons:backup`; deployment остаётся в `docs/DEPLOYMENT.md`. Ни успешный backup, ни manifest SHA256 сами по себе не доказывают восстановление. Эта процедура не восстанавливает production и не даёт команду перезаписи production.

## Проверенные материалы «Кто мой ближний?»

После обычного deployment и migrations выполнить в окружении PHP проекта:

```bash
php artisan lessons:install-neighbor-docs && \
php artisan lessons:upgrade-neighbor
```

На новой БД исходную v1 сначала устанавливают командой `lessons:install-neighbor`. После перепривязки каталога на v2 первоначальный installer повторно не запускают: он намеренно принимает только исходный receipt v1. Две приведённые команды допускают повторный запуск при точном совпадении уже установленного состояния.

`install-neighbor-docs` добавляет write-once sidecar к каноническому исходному released snapshot v1: полный план RU/DE, исходные русские PDF/PPTX и видео с явным языком RU. Он проверяет исходную версию, владельца, содержимое документа и хеши доверенных файлов. Это не перезапись released документа и не массовое дополнение пользовательских копий.

`upgrade-neighbor` требует точного source receipt v1, создаёт новый released snapshot v2 в том же исходном материале и защищённой операцией модели перепривязывает существующий каталог. Новый документ включает планы и ссылки на файлы; скрытие, административное одобрение и slug сохраняются. Неожиданный receipt, правка исходного материала или занятый ID приводят к отказу без overwrite. Не исправлять такой отказ SQL-правкой receipt: сначала требуется разбор несоответствия.

Активные занятия и прежние копии остаются на прежних immutable версиях. Для обновлённого проведения нужно создать новую копию или запуск из каталога. Карта поведения и автоматических проверок — [LESSON_CONDUCTING.md](LESSON_CONDUCTING.md); установка данных сама по себе не является браузерной приёмкой.

## Изолированная проверка восстановления

Новые bundles содержат schema-scoped SQL: dump не включает `--databases`, `CREATE DATABASE` или `USE` исходной БД. SQL-only `lessons:database-backup` сохраняет прежний формат. Старые bundles с `CREATE DATABASE`/`USE` сохраняются без изменений; `lessons:restore-test` их отвергает. Для них нужна отдельная согласованная процедура на изолированном сервере, а не подстановка нового имени БД в SQL или импорт привилегированным аккаунтом.

`lessons:restore-test` принимает только `APP_ENV=local|testing`, MariaDB 10.6 на `127.0.0.1`/`::1`, фактическую БД `lessons_restore_test` без URL/socket/replicas/prefix. БД должна быть пустой: запрещены существующие tables/views/routines/events. Media destination — новый абсолютный каталог вне приложения, отдельно от bundle. Existing directory также отвергается. Это инструмент эксплуатационной приёмки, а не восстановления рабочего сайта.

Создание тестовой БД выполняется администратором только на изолированном тестовом сервере. Import account должен иметь только schema grant для точного имени БД, без global privileges, roles и GRANT OPTION. Подчёркивания в MariaDB database grant нужно экранировать; необработанный `lessons_restore_test` является wildcard pattern и командой отвергается. Пример для ephemeral CI, с заведомо синтетическими credentials:

```sql
CREATE DATABASE lessons_restore_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'lessons_restore'@'%' IDENTIFIED BY 'test-only-restore-password';
GRANT ALL PRIVILEGES ON `lessons\_restore\_test`.* TO 'lessons_restore'@'%';
```

CLI проверяет фактический `SELECT DATABASE()/VERSION()` и `SHOW GRANTS`. Root не подходит даже на loopback. Credentials import client получает через временный 0600 файл, без password в argv и без Laravel secrets в дочернем environment; stdout/stderr import не публикуются.

Перед первой записью в целевую БД/media CLI проверяет ожидаемый manifest SHA256, формат, canonical immutable paths/IDs, уникальность версий, наличие всех файлов, каждый размер и SHA256, отсутствие traversal/symlinks/лишних файлов. Затем создаёт приватный стабильный snapshot и повторно проверяет bytes/пустой target перед реальным import. После import каждая SQL media version должна иметь точное совпадение IDs/path/versionNo/bytes/SHA256 в manifest. Все проверенные media атомарно переносятся в новый каталог. Builtin media приходят из Git. `.env`/APP_KEY/credentials не входят в bundles.

После проверки environment/server/schema grants CLI захватывает неблокирующий MariaDB named lock `lessons:restore-test:lessons_restore_test` на restricted guard connection. Он удерживается через окончательную проверку пустоты, отдельный реальный import, coverage и media finalization; второй CLI на том же test server прекращается до import, даже с другим media destination. Проверки используют clone connection, pinned к тому же PDO; reconnect запрещён. Фактические connection ID и lock owner проверяются перед import, после него и перед finalization, release выполняется в `finally`. Потеря connection/ownership прекращает acceptance и оставляет partial test target изолированным. Это не lock production БД и не разрешение стороннему client изменять test schema в обход принятой процедуры.

Новый manifest формата 1 дополнительно содержит optional `requiredMediaVersionIds`: уникальные canonical IDs уже committed immutable versions, прочитанные **до** SQL dump. Они должны входить в manifest files и присутствовать в восстановленной SQL БД. Исчезновение такой версии во время backup прекращает создание bundle; исчезновение после import прекращает restore acceptance. Это доказательство покрытия pre-dump inventory, а не притворная общая транзакция между двумя разными DB clients. Как и раньше, physical deletion/DDL во время backup запрещены.

Commit новой immutable версии между SQL snapshot и post-dump inventory может добавить безопасный файл, которого ещё нет в SQL. Restore сохраняет такие fully verified extra files приватно без DB rows/runtime references/quota изменений; CLI сообщает их число `unreferencedMediaVersions`. Они не являются потерянными SQL versions и не отвергают корректный backup. Legacy format1 без `requiredMediaVersionIds` остаётся совместимым: проверяется каждая восстановленная SQL row, verified extras допускаются, но обнаружение отсутствующей source SQL row по одним legacy file entries **не доказано**. Bundle с новым coverage evidence предпочтителен для эксплуатационной приёмки; original database-scoped legacy dumps всё равно требуют отдельной процедуры.

На отдельном Linux test checkout с приватным test `.env`, где DB настроена на этот restricted target, команда имеет следующий контракт:

```bash
php artisan lessons:restore-test "$BUNDLE" \
  --manifest-sha256="$ACCEPTED_MANIFEST_SHA256" \
  --media="$NEW_ISOLATED_MEDIA_DIRECTORY" --timeout=300
```

`BUNDLE`, checksum и destination должны быть получены из конкретного проверяемого bundle; значения не угадываются. Успех выводит только target name, число media files, число unreferenced extra files и checksum. Неуспех import может оставить частично восстановленную **тестовую** БД: CLI не удаляет её, не выполняет автоматический rollback и не повторяет import поверх данных. Держать target изолированным и удалить/пересоздать его только в рамках утверждённого test process. Исходный bundle и SQL/media источники не изменяются.

После настоящего restore проверять доменные данные, immutable snapshots, старые и архивные media versions, права и действующие retention cutoffs. До возвращения восстановленного сайта online применить актуальные cutoffs: старый backup не должен вновь открыть истёкшие participant details. Production restore, изменение APP_KEY и возврат online требуют отдельного согласованного плана.

## CI acceptance

`tests/Integration/BackupRestoreTest.php` выполняет настоящий `mariadb-dump` → `BackupBundle` → настоящий `mariadb` через публичную CLI-команду. Синтетические fixtures содержат Unicode/literal текст в lesson JSON, две реальные PNG immutable versions и архивный asset; третий настоящий PNG committed после завершения SQL dump, до чтения post-dump inventory. Проверяются exact restored JSON/IDs/relations, старые/архивные файлы/размеры/SHA256/0600, directories0700, отсутствие временных credentials/partials, отсутствие writes в source при restore, отказ повторного restore и SQL/media corruption **до writes**. Реальный import восстанавливает 2 SQL media rows и 3 файла, корректно сообщает 1 unreferenced extra; injected удаление pre-dump SQL metadata после реального import прекращает acceptance. Test cleanup работает только после доказательства пустого ограниченного target; неочищенная чужая test БД не удаляется.

Тот же integration test открывает вторую настоящую restricted MariaDB connection и запускает overlapping restore с другим media destination, пока первый restore уже держит lock, но ещё не передал SQL клиенту. Второй получает busy failure, не вызывает importer, не создаёт tables/media, удаляет только свой staging; затем первый выполняет настоящий import. После failed coverage и успешной finalization проверяется освобождение named lock. Synthetic `RestoreDatabaseLockTest` дополнительно проверяет unsafe guards до acquisition, busy refusal, lost ownership, reconnect prohibition и release при ошибке; это не замена Linux evidence.

SQL fixture также включает released lesson snapshot, active session, joined participant, choice/private free-text answers и finished guest session старше 30 дней. После настоящего import сравниваются исходные/restored sessions/participants/answers/command receipts. Actual `RuntimeService/HistoryService` на guarded target проверяют owner-only доступ, отказ чужому owner/participant и неверному projector token, сохранённые ответы, отсутствие authored private notes/solutions в student/projector DTO и отсутствие pending original text в projector. Истёкшие owner/history/student/projector/join reads остаются закрытыми **до** cleanup, хотя raw expired rows восстановлены. Actual target retention удаляет expired session/details, сохраняя active session/answers и released version; source DB rows остаются неизменны. `RestoreRuntimeFixtureTest` проверяет эти fixture/assertions на локальной БД и отдельно обозначен как не являющийся доказательством SQL import.

В MariaDB 10.6 job нужны `pdo_mysql`, `gd`, `mariadb-client`, существующая loopback `lessons_test` для source и отдельная пустая `lessons_restore_test` с вышеуказанным restricted account. Задать `LESSONS_RESTORE_TEST_USERNAME=lessons_restore` и `LESSONS_RESTORE_TEST_PASSWORD=test-only-restore-password`. Отсутствие этого контракта в MariaDB CI — failure, не skip. Запуск:

```bash
php artisan test --compact tests/Integration/BackupRestoreTest.php
```

На Windows или SQLite integration test честно пропускается: это не доказательство restore. `RestoreGuardTest` и `RestoreBundleValidationTest` отдельно проверяют блокировки unsafe targets/root/wildcards и integrity failures на synthetic fixtures; внешние процессы в этих feature tests не являются restore acceptance.

## Ежедневный background backup

Manual pre-deployment `lessons:backup` сохраняется. Дополнительно `ScheduledBackup` может ежедневно создавать такие же полные SQL/media bundles через существующий `BackupBundle`; это не SQL-only копия и не отдельная fake integration. По умолчанию расписание выключено:

```dotenv
LESSONS_BACKUP_ENABLED=false
LESSONS_BACKUP_TIME=02:30
LESSONS_BACKUP_DIRECTORY=/var/www/vhosts/lessons.atapin.de/private/lessons-backups
```

После согласованного включения `LESSONS_BACKUP_ENABLED=true` и обновления config cache event ежедневно в configured `HH:MM` UTC отправляет unique job в queue `backups` через отдельную database connection `operations-backups`. Dump timeout300, общий worker/job timeout600, reservation `retry_after=660`; обычная queue сохраняет своё прежнее окно90. Некорректное время не регистрирует schedule. Worker повторно проверяет enabled и, уже удерживая общий operations lock, пропускает maintenance; existing private backup path/permissions и local media проверяются существующим bundle service.

Этот job только создаёт проверенную локальную копию. Владелец выбрал хранение копий на сервере без автоматического удаления; rotation/deletion не включены. Место внешней копии пока не задано. Возможны нормальные concurrent immutable uploads, покрываемые pre/post inventory contract.

`ScheduledBackup`, `ScheduledRetention` и `scripts/deploy-plesk.sh` используют один persistent private файл `storage/framework/cache/operations/lock`: directory0700, file0600, без symlinks, один Plesk application UID. PHP удерживает `flock` на время проверки maintenance и создания полного bundle либо bounded retention run; deployment требует util-linux `flock` и удерживает тот же lock **до** `artisan down`, через backup/migrate/check, до `artisan up` и завершения процесса. `optimize:clear` очищает `cache/data`, не удаляя этот sibling lock/inode. При contention deployment прекращается до изменений; scheduled job честно откладывается без execution record, копии или cleanup, следующий daily trigger потребует повторного запуска. Lock/path/permissions failure не позволяет начать backup/retention/deployment. Manual deployment backup не захватывает lock повторно, чтобы избежать nested deadlock. Самостоятельный ручной backup/retention, сторонние DDL и physical cleanup должны соблюдать такую же исключительность; перед ними остановить допуск scheduled jobs и дождаться активного job.

Deployment EXIT trap сохраняет исходный exit status. Если ошибка произошла до начала schema changes и приложение изначально было online, trap сначала запускает `lessons:check` и возвращает online только после успешной проверки. После начала migration, failed check или при изначальной maintenance приложение остаётся в maintenance для отдельного восстановления. Ошибка `artisan up` тоже не скрывает первоначальную ошибку. Не удалять lock file и не менять его inode при обслуживании.

После успешных migrations/check и `artisan optimize` deployment выполняет `artisan queue:restart`, пока maintenance и operations lock ещё активны, затем `artisan up`. Это сигнал поддерживаемым долгоживущим workers закончить текущий job и загрузить новый code/config при перезапуске; managed process должен автоматически запускать workers снова. Bounded Plesk minute workers стартуют заново своей задачей.

Используется та же отдельная Plesk minute task `schedule:run`, показанная ниже. Для `backups` добавить отдельный bounded worker task раз в минуту:

```bash
cd /var/www/vhosts/lessons.atapin.de/httpdocs && \
export PATH="/opt/plesk/php/8.5/bin:/opt/plesk/node/22/bin:$PATH" && \
php artisan queue:work operations-backups --queue=backups --stop-when-empty --max-jobs=1 --max-time=600 --timeout=600 --tries=1
```

Не запускать эту очередь worker через ordinary database connection с `retry_after=90`: reservation должен быть больше timeout. При использовании managed worker задать тот же connection/queue/timeout и перезапустить его после deployment. HTTP не запускает backup, `.env`/password не передаются в args, diagnostic subprocess outputs скрыты. Scheduled job записывает backup execution в `operation_runs`: running/succeeded/failed, UTC timestamps и только `databaseBytes/mediaBytes/mediaVersions`. Paths, manifest content, SQL, credentials, exception texts и individual media IDs не публикуются в execution record. Неуспех сохраняет предыдущие complete bundles, отмечает generic `backup_failed` и передаёт generic failure queue handler; timeout/record-storage failure может оставить running для эксплуатационной проверки. Успешный creation всё ещё не является restore acceptance.

## Retention scheduler

Согласованная policy остаётся в `RetentionPolicy/RetentionService`: finished guest history/details — 30 дней от `finished_at`; account history — 2 календарных года; participant details — 30 дней; finished rehearsal — 7 дней от `created_at`; command/save receipts — 30 дней. Active prepared/running/paused и unknown legacy anchors, authoring resources и permanent claim tombstones защищены. Отдельного persistent event log сейчас нет; command receipts являются сохраняемыми operational records, очищаемыми через эту policy. Scheduler не удаляет physical media и backups.

`config/operations.php` по умолчанию:

```dotenv
LESSONS_RETENTION_ENABLED=false
LESSONS_RETENTION_RESTORE_VERIFIED=false
LESSONS_RETENTION_DRY_RUN=true
LESSONS_RETENTION_BATCH=100
LESSONS_RETENTION_TIME=03:15
```

До успешной реальной SQL/media restore acceptance оба gates остаются выключенными. После принятия конкретного CI результата владелец может выставить `RESTORE_VERIFIED=true` и `ENABLED=true`, сначала оставив `DRY_RUN=true`. Write mode — отдельное явное `LESSONS_RETENTION_DRY_RUN=false`; никакой checksum автоматически не включает writes. После изменения приватного `.env` обновить config cache и перезапустить workers:

```bash
cd /var/www/vhosts/lessons.atapin.de/httpdocs && \
export PATH="/opt/plesk/php/8.5/bin:/opt/plesk/node/22/bin:$PATH" && \
php artisan config:cache && \
php artisan queue:restart && \
php artisan schedule:list
```

Расписание ежедневно в configured `HH:MM` UTC; batch 1–1000, default100. Некорректные значения/неподтверждённые gates не регистрируют event. `ScheduledRetention` отправляется в существующую database queue `retention`: unique job, один try, timeout60, dispatch overlap lock30 минут. Worker повторно проверяет gates, maintenance под shared operations lock и ограничивает batch текущей конфигурацией; старый queued write job не обходит новое отключение/dry-run. Contention/maintenance пропускают execution без cleanup и без success record. Logs содержат только dryRun/counts или generic deferred notice, без names/answers.

В Plesk Scheduled Tasks добавить отдельную задачу раз в минуту, не заменяя существующий crontab:

```bash
cd /var/www/vhosts/lessons.atapin.de/httpdocs && \
export PATH="/opt/plesk/php/8.5/bin:/opt/plesk/node/22/bin:$PATH" && \
php artisan schedule:run
```

Для отдельной очереди нужен worker. Если долгоживущий managed worker не настроен, допустима отдельная Plesk задача раз в минуту:

```bash
cd /var/www/vhosts/lessons.atapin.de/httpdocs && \
export PATH="/opt/plesk/php/8.5/bin:/opt/plesk/node/22/bin:$PATH" && \
php artisan queue:work database --queue=retention --stop-when-empty --max-time=50 --timeout=60 --tries=1
```

Default database `retry_after=90` больше worker timeout60; не уменьшать его до timeout. После изменения job code deployment должен выполнить `queue:restart`, если работает долгоживущий worker. Проверять `schedule:list`, dry-run counts и private failed jobs/logs. При ошибке ранее завершённые bounded batches остаются committed; повторная очистка идемпотентна. Ошибка — повод проверить эксплуатационную конфигурацию и anchors, а не увеличивать привилегии/автоматически retry destructive work.

Отключение: `LESSONS_RETENTION_ENABLED=false`, затем `config:cache`/`queue:restart`; worker gates защищают уже queued jobs. Существующий ручной `lessons:retention --dry-run --batch=100` сохраняется. Offsite copy и backup rotation не включены: срок/место/защита внешней копии и deletion policy должны быть отдельно утверждены; ни scheduler, ни restore-test не удаляют старые backups.

## Доступ администратора к результатам и ошибкам очистки

Additive migration `2026_10_01_200000` создаёт `operation_runs`. Scheduled job после проверки gates записывает `running` до первой очистки; без доступного record storage cleanup не начинается. Успех записывает `succeeded`, фактические UTC started/finished timestamps, dryRun и только числовые aggregate counts. При ошибке записываются `failed`, generic `retention_failed` и `counts=null`: некоторые предыдущие batches могли уже завершиться, точный частичный итог неизвестен. Исключение повторно сигнализирует queue failed-job handler через generic code; SQL/answers/names/passwords/exception texts в execution record не записываются.

Если БД недоступна во время обновления record, worker возвращает `operation_record_failed`; запись может остаться `running`, и успешно завершённая часть cleanup не выдаётся за полный записанный успех. Kill/timeout тоже может оставить `running`; администратор сопоставляет её с safe failed-job metadata и приватным состоянием worker. Старые незавершённые executions не удаляются автоматически и требуют эксплуатационной проверки.

`GET /api/admin/operations` защищён `RequireCatalogAdmin` и повторной проверкой verified administrator. Ответ содержит только flags эффективной retention configuration и `backup:{enabled,time}`, последние не более 20 retention/backup executions (`id,operation,status,dryRun,counts,errorCode,startedAt,finishedAt`) и не более 20 failed-job records очередей `retention/backups` (`id,failedAt,queue`). Payload, exception, connection configuration, backup directory и любые пользовательские подробности не выбираются из failed_jobs и не возвращаются. Unknown error codes и нечисловые/неразрешённые count keys не раскрываются; backup counts ограничены `databaseBytes/mediaBytes/mediaVersions`.

Завершённые technical execution records хранятся 30 дней от `finished_at`. Существующий bounded retention CLI/job дополнительно возвращает `operationRunsDeleted`; dry-run считает candidates без writes, write run удаляет не более batch finished records с повторной проверкой terminal status/cutoff в самом DELETE. Это не меняет history policy, queued/failed jobs, active executions или backup rotation.
