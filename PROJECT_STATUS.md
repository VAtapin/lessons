# Состояние проекта

Обновлено: 2026-10-01.

## Реализовано

- Laravel 13.34, Vue 3/TypeScript/Vite 8; независимый строгий домен документа и единый registry блоков.
- Гостевой кабинет RU/DE, конструктор этапов, ручное сохранение с optimistic revision/conflict, общий renderer и preview. Immutable released-снимок не меняется при последующей правке материала.
- Пульт, ученический экран и read-only проектор: подготовка/начало/пауза/продолжение/завершение, навигация без потери ответов, независимый серверный таймер, сообщение, ограниченная временем «волна добра», QR и активность участников. UUID/fingerprint/receipt делает повтор команды безопасным.
- Пульт следует teacher-panel-concept-v1.png: скрываемое меню, этапы слева, preview в центре, таймер/действия справа, ответы/заметки снизу. Компактный отдельный пульт подтверждает подключение BroadcastChannel и возвращает управление при потере связи.
- Личная библиотека заготовок: переводы, поиск/метки/версии, независимые вставки. Приватная медиатека PNG/JPEG/WebP: загрузка/замена, immutable версии, права/происхождение, использование, архив/восстановление. 19 общих иллюстраций; новая сцена взаимопомощи и её промпт находятся в assets/library.
- Этап 5: legacy text@1/image@1/single-choice@1 сохранены; добавлены text@2, prompt, multiple-choice, poll, free-response, sequence, matching, roles, signals и приватные teacherNotes блока. Конструктор и общий renderer поддерживают все 11 типов.
- Runtime упражнений prepared/open/closed/revealed, совместимые прежние ответы и новые value DTO, индивидуальные answer revisions, оценка и разрешённое раскрытие. Письменные оригиналы не заменяются редакцией ведущего; одобрение и анонимная публикация — разные команды. Capacity роли защищён session lock; вопрос ученика имеет адресное серверное подтверждение.
- Этап 6A: Laravel register/login/logout, signed email verification, password broker/reset и отзыв прежних authenticated sessions; RU/DE профиль, квота аккаунта 1 GiB, explicit verified guest claim всех четырёх видов ресурсов с permanent receipt и общим owner mutex. Смена identity блокирует открытые вкладки; неприсвоенный guest proof сохраняется при logout/reset, account access не сохраняется.
- История с фильтрами/пагинацией, избранное, просмотр authoring versions, продолжение и чистый новый запуск той же версии; личные заметки, anonymous aggregates и достоверные UTC lifecycle anchors. Закрытая owner-only репетиция использует immutable internal snapshot и общий runtime, без ordinary join/bearer доступа.
- Retention policy проверяет expiry при чтении, включая published free text и media; bounded CLI/dry-run и queue job реализованы. Автоматическое расписание и production write cleanup пока выключены.
- Авторизация owner/participant/projector и выдача exact media version по активному этапу. GD полностью декодирует изображение, ограничены bytes/pixels/dimensions. Чужие ответы, закрытые заметки и ещё не раскрытые решения исключены из публичных DTO.
- Read-only проверки схемы/БД, additive migrations. Deployment --migrate для непустой БД сначала создаёт приватный SQL/media bundle с manifest, SHA256 и проверкой всех committed immutable версий; ошибка блокирует миграции. SQL-only backup остаётся отдельной командой.

## Принятые решения

- Сначала движок и переиспользуемые блоки; технические сборки не являются готовыми учебными темами.
- Языки интерфейса и содержания независимы. Сохранённый документ пока обязан содержать полные переводы; добавление языка через UI и незавершённые переводы — следующий этап.
- Гостевой доступ связан с серверной cookie-сессией браузера; после потери cookies восстановление пока отсутствует. Регистрация не является условием проведения.
- Cookie-запросы сериализуются file lock; команды/ответы — session row lock. Ответы не увеличивают revision команд ведущего. Block IDs case-sensitive; role capacities остаются JSON object даже при ID «0»/«1».
- Polling раз в две секунды; один снимок block states на response, устаревшие GET отбрасываются. Ручное сохранение сохраняет локальные правки при конфликте и предупреждает перед уходом.
- Таймер и пауза занятия независимы; переход между этапами не сбрасывает ответы/время. Revealed пока терминален: reset/new attempt ещё отсутствует.
- 20 MiB на файл, 100 MiB на гостя, 1 GiB на аккаунт; все старые и архивные private versions учитываются. Claim переносит ресурсы/quota целиком, сохраняя IDs, версии, файлы, ответы и действующие ссылки.
- Отправитель lessons@atapin.de через PHP/Plesk; фактический sendmail wrapper и CLI/FPM flags подтверждены, получение email ещё не проверено. Finished guest lesson/details — 30 дней от finished_at, account history — 2 календарных года, finished rehearsal — 7 дней от created_at, receipts — 30 дней. Активные реальные занятия/legacy unknown anchors/authoring resources/claim tombstones защищены; auto schedule выключен до эксплуатационной проверки.
- Главная и пульт следуют UI-Design.png/teacher-panel-concept-v1.png по композиции, типографике, карточкам и акварельному стилю. Главная/публичный каталог относятся к этапу 8. Выпуск личной версии не публикует материал в каталоге.
- Production: Plesk PHP 8.5.11 FPM/CLI, Node 22.23.3, MariaDB 10.6.23; локально PHP 8.4.25/SQLite/Node 22.

## Проверки

- Этап 6A, полный Composer test: 310 tests, 301 passed, 2744 assertions, 9 Windows skips (4 POSIX/symlink, 3 runtime и 2 claim MariaDB concurrency). Composer validate --strict и Pint прошли после исправления порядка imports. Frontend: 31 tests, Vue typecheck и Vite build прошли. Local lessons:check/dry-run retention прошли без writes, все planned counts 0.
- Этап 5, полный Composer test: 268 tests, 261 passed, 2125 assertions, 7 skips на Windows (4 POSIX/symlink, 3 реальной MariaDB concurrency). Полный Pint и composer validate --strict прошли. Vue typecheck, Vite build и 22 frontend tests прошли.
- Integration suite выполнена в CI 36794654989 на Linux/loopback lessons_test/MariaDB 10.6: два независимых PHP соединения, наблюдаемые InnoDB lock waits, последняя роль, одинаковый UUID, разные UUID с одной revision. Все assertions прошли; CI выдавал предупреждения отсутствующего .env, поэтому тестовый .env из example добавляется в следующий CI workflow. Локальные skips не считались проверкой concurrency.
- Этап 5, браузер: 11 типов созданы/сохранены/reload/запущены; ученик отправил 8 видов ответов. Проверены выбор, множественный выбор, poll, письменный ответ с Unicode/literal HTML, порядок, соответствия, роль и сигналы. Original/private notes не видны проектору; approve не публикует, publish показывает только анонимную редакцию, повторная правка ученика убирает её. Close/reveal показывает решение/агрегат poll и собственную оценку; подтверждение вопроса видно ученику. RU пульт и DE ученик при 360 px без горизонтального overflow; console errors не обнаружены.
- Библиотека/медиа: реальный локальный upload → exact version → сохранение/reload → заготовка → две независимые вставки. Новая версия/архив не меняют старый проектор. RU/DE и 360 px проверены. На production проверены все 19 builtins и независимость шаблонов; private browser upload проверен локально и в CI, на production не выполнялся.
- Пульт: 360/768/1366 px и compact DE 360 px; timer/pause/reload, отделение/возврат, меню с клавиатуры и восстановление после остановки локального сервера проверены. Native popup подключение наблюдалось на parent, команды compact — отдельной вкладкой; полная popup/browser matrix ещё не проверена.
- Последний production технический запуск 01a0f4a4-6a2b-702a-a6d4-490f5d042281: begin → timer90 → pause28 → reload28 → finish подтверждены. Исходное занятие пользователя не изменялось.
- CI текущего production commit 04f6051: run 36794654989, PHP 8.4/8.5, Node22, MariaDB10.6 — passed. Git deployment с backup/migration 120000, lessons:check и /up прошли; приватные исходники/.env/.git недоступны через сайт.
- Реальный production bundle lessons-20260930-233058-3f98af9dc168cfe0 вне httpdocs: SQL 42301 bytes/0600, directories0700, manifest SHA256 2eff5c32c8d47f8197410a4678666485795c6a07deebdf72edbd6e23be10e9b5. Private media versions тогда отсутствовали; копирование bytes/архивов и failure cleanup проверены fixture tests. Реальный dump подтверждён; restore пока не выполнялся.
- Перед migration этапа 5 создан bundle lessons-20261001-001454-92042856343d0cbd: SQL 42496 bytes/0600, manifest SHA256 6c59b537695eaf65cf5d7b39476a0e52b1e0294e3c2c168766d36d2321e7e1c6; metadata и post-deployment health подтверждены.
- Этап 5 на production: отдельный технический запуск 01a0f4d5-5422-7138-86d1-2270d41a0b54, все 11 типов, 8 видов ответов, edited anonymous publish и адресное acknowledgment проверены; технический запуск завершён. Исходное занятие пользователя не изменялось.
- Этап 6A, локальный браузер: existing synthetic account login; explicit claim перенёс 4 материала, 1 заготовку, 1 media asset со всеми версиями и 6 занятий; quota 4.9 MiB/1 GiB. Старые вкладки остановили writes, participant map/8 ответов сохранились. Profile/favorite/versions/history note save+reload, clean again/new code/finish и rehearsal student answer→teacher проверены. Решение не видно в student preview; invite code/QR отсутствуют у rehearsal. RU/DE account, RU 360 px без overflow; email людям не отправлялся.

## Git и production

- main → origin/main, https://github.com/VAtapin/lessons.git. Последний связанный implementation commit и подтверждённая версия production: 04f605107f57b41f77d6657ebeb598238de5bc64 — Add reusable interactive blocks and moderated lesson responses.
- SSH lessons.atapin.de:2377; /var/www/vhosts/lessons.atapin.de/httpdocs, document root httpdocs/public. Исходники доставляются только Git.
- Production .env private0600/debug=false/secure HTTPS session cookie; APP_KEY неизменен. Секреты, local DB, backups и build исключены из Git.
- Windows PHP child commands требуют PHPRC=D:\Projekte\lessons\.local\php.ini; Node22 находится в .local/node/node-v22.23.3-win-x64.

## Что дальше и ограничения

- Завершается проверка/CI/Git deployment этапа 6A; production пока остаётся на этапе 5. Следующий этап 6B: autosave/undo, копирование/layouts, private partial translations/readiness и preview трёх audiences по согласованному STAGE6_EDITOR_API.
- Совместный учитель и публичный каталог ещё не реализованы. Full browser matrix, фактическая mail delivery, restore, автоматическое retention расписание и нагрузка остаются приёмке.
- Полный backup требует immutable media и отсутствия physical cleanup/DDL во время копирования. Внешняя копия/rotation/restore ещё не проверены. Активные занятия нельзя очищать по одному старому created_at.
