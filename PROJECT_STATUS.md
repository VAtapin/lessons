# Состояние проекта

Обновлено: 2026-10-01.

## Реализовано

- Laravel 13.34 / Vue 3 / TypeScript / Vite 8; строгий версионируемый документ, единый registry и renderer. Сохраняется совместимость старых text/image/single-choice@1.
- Конструктор RU/DE: 11 типов блоков, несколько блоков на этапе, три раскладки, приватные заметки; autosave с frozen UUID/revision, исправляемые ошибки, конфликты без overwrite, undo/redo и независимые копии. Частичные переводы сохраняются в private editor_draft; immutable release содержит только готовые выбранные языки.
- Кабинет, пульт, ученик и проектор: запуск/пауза/завершение, этапы, независимый таймер, сообщение, волна добра, QR, ответы, роли и сигналы. Одобрение письменного ответа и его анонимная публикация разделены; оригинал сохраняется. Решения и заметки исключены из публичных представлений.
- Главная следует UI-Design.png, кабинет/пульт — teacher-panel-concept-v1.png: бумага, акварель, карточки, скрываемое меню. Отдельный tab/popup пульта использует подтверждённый BroadcastChannel handshake; закрытие окна не завершает занятие. Длинные desktop списки этапов прокручиваются внутри панели.
- Личная библиотека заготовок и медиатека: поиск/метки/версии/использование/архив, независимые вставки, immutable PNG/JPEG/WebP. 19 общих иллюстраций; руководство генерации и новая сцена взаимопомощи сохранены в assets/library.
- Регистрация, signed email verification, password broker/reset, RU/DE профиль, история/избранное/продолжение/новый запуск, закрытая owner-only репетиция. Explicit verified claim переносит все гостевые ресурсы с постоянным receipt и owner mutex. Заявка на удаление и отмена сохраняются; физического удаления аккаунта нет.
- Фирменные multipart HTML/plain-text письма: язык сохранённого аккаунта RU/DE, кремовая/коричневая/оранжевая палитра; штатные подписи и reset tokens сохранены.
- Публичный каталог: поиск и фильтры, безопасный preview, гостевой запуск отдельной собственной копии. В каталог попадают только проверенные pinned released версии; personal release не публикует материал.
- OLD/kto-moi-blizhnii адаптирован в БД: 13 этапов / 45 минут / 9 типов блоков / 8 иллюстраций, полное RU/DE содержание; идемпотентный installer не перезаписывает выпущенную версию.
- Совместное проведение: одноразовый invitation, отдельный scoped grant одного занятия, помощник/ведущий, передача/возврат/отзыв, отдельный пульт и проектор. Владелец сохраняет finish/manage; все чтения и команды проверяют свежие права, expiry, actor и controlEpoch. Старые команды не получают ACK после передачи и возврата. После revoke учительский экран удаляет закрытые данные.
- Read-only schema/health проверки, additive migrations и Git-only deployment. Перед production migrations приватный SQL/media bundle проверяет SHA256 и committed immutable versions; ошибка блокирует миграции. Bounded retention CLI/dry-run работает; автоматическое production расписание пока выключено.

## Решения

- Сначала движок и переиспользуемые блоки; технические сборки не считаются учебными темами. UI и content locales независимы; незавершённые переводы не подменяются fallback текстом.
- Файл до 20 MiB, аккаунт 1 GiB, гость 100 MiB; quota учитывает старые/архивные версии. Guest identity зависит от cookies; восстановление потерянного guest proof пока отсутствует.
- File session lock + owner mutex + session row lock. Polling 2 секунды, устаревшие GET отбрасываются. Приглашение по умолчанию 1 час, grant 4 часа без автоматического продления. Отдельный приглашённый учитель видит ответы/заметки/решения после явного предупреждения.
- Согласованное хранение: finished guest и подробные ответы 30 дней, account history без этих данных 2 года, rehearsal 7 дней, технические события 30 дней. Активные занятия и неизвестные legacy anchors защищены. Физическое удаление media/authoring resources/claim tombstones не выполняется.
- Почта: lessons@atapin.de через PHP/Plesk sendmail. Получение прежнего письма подтверждено пользователем; новое оформление проверено renderer/browser, получение в реальном почтовом клиенте пока не подтверждено.
- Production: Plesk PHP 8.5.11 CLI/FPM, Node 22.23.3, MariaDB 10.6.23. /var/www/vhosts/lessons.atapin.de/httpdocs, document root public; SSH :2377. Локально PHP 8.4.25/SQLite/Node22.

## Проверки и состояние production

- Фактический production commit 867521ffb5b50cd8309506c0af696b3c059d6974 — Build approved homepage and bilingual lesson catalog. CI 36852028847: PHP8.4/8.5, MariaDB10.6, frontend passed. Git deployment, migrations, installer, lessons:check и HTTPS /up выполнены; main чистый. Transient final curl DNS failure устранён отдельным успешным HTTPS check.
- Перед migrations создан private bundle lessons-20261001-105724-ea8db42dd0b19535; manifest SHA256 a6a0b242a47b7f82215bc1dd69eb6a1044a0c01377f3978b96070ab78cb7a83d. Production restore не выполнялся.
- Production браузер: главная по макету, тема из каталога и собственное техническое занятие 01a0f728-9ee5-72e5-8c02-bdfe9441dde8; begin и этап Самарянин подтверждены. Изображения .local/production-homepage-ru.png и .local/production-teacher-ru.png. Исходное занятие пользователя не изменялось.
- Предыдущий редактор 2389f9a: CI 36851328471 passed, включая настоящие MariaDB editor-save races. Браузер подтвердил autosave/reload, partial DE/ready RU, копии/undo/redo, приватный teacher preview, две конфликтующие вкладки без overwrite и RU release при незавершённом DE.
- Совместное проведение: scoped 27 tests / 25 passed / 326 assertions / 2 честных Windows MariaDB skips; scoped Pint passed. Targeted Astra checkpoint B не нашёл P0/P1; P2 замены истёкшего grant исправлен с regressions. Локальный браузер подтвердил принятие, отдельный grant DTO, передачу, запрет команд владельцу, begin/timer соведущим, возврат ведения и отзыв: закрытые ответы/заметки/управление исчезли из открытой grant вкладки. Desktop список из 13 этапов прокручивается внутри панели.
- Полный текущий working-tree PHP: 500 tests / 486 passed / 5317 assertions / 14 Windows skips, Pint passed. Реальные MariaDB/restore/POSIX проверки требуют Linux CI и не засчитываются по skips. Frontend: 60 tests, typecheck и build 148 modules passed; этот прогон включает ещё не опубликованные части этапов 8/9.
- Проверены все 11 типов и 8 видов ответов через браузер; original/private notes защищены, approve не publish, изменение ответа снимает публикацию. RU/DE, 360/768/1366 px, таймер/пауза/reload, отделение/возврат и восстановление локального runtime после обрыва проверены. Полная browser/window matrix ещё не закрыта.

## Git и следующие работы

- main → origin/main, https://github.com/VAtapin/lessons.git. Последний связанный implementation commit: 6759c55e5bebaaa1848b0f6979902be1f7251baf — Enable scoped co-teacher lesson control. Production остаётся 867521f до успешного CI.
- CI 36856178271: frontend passed; backend остановился на новом HTML route assertion из-за отсутствия Vite manifest в backend-only job. Тест переведён на штатный withoutVite; реальный frontend build остаётся отдельной проверкой. Исправление проверяется новым CI перед deployment.
- Git никогда не содержит .env, credentials, cookies, private data, local DB, logs, backups или build. Windows PHP child commands требуют PHPRC=D:\Projekte\lessons\.local\php.ini; Node22 — .local/node/node-v22.23.3-win-x64.
- Этап 7: закончить browser revoke/отдельный пульт, CI настоящих concurrent MariaDB connections и Git deployment. Этап 8: административная проверка, общие блоки и динамические справочники реализованы в working tree; интеграция проверяется перед отдельным commit.
- Этап 9: проверка реального изолированного restore, execution records, безопасное retention расписание и HTTP load harness реализованы в working tree, но ещё не прошли Linux CI/production настройку. Предварительный load10×30 не считается согласованной нагрузочной приёмкой.
- Нужны ответы владельца: существующий verified email для production admin и согласованный load target. Внешняя backup копия/rotation, поддерживаемая browser matrix, итоговая визуальная/keyboard приёмка и получение нового письма пока не закрыты. Не объявлять весь roadmap завершённым.
