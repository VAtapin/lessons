# Приёмка реальной HTTP-нагрузки

Профиль **10 занятий × 30 учеников и p95 ≤ 1000 мс предложен, но требует явного подтверждения владельца**. Успешный CI подтверждает целостность данного профиля; до согласования чисел он не закрывает нагрузочную приёмку. По умолчанию latency не блокирует CI. Согласованный порог можно передать явно через `--max-p95-ms 1000`.

`tests/load/run.mjs` создаёт настоящие HTTP-клиенты, а не вызывает runtime service для измерения. Нужны Linux, Node.js 22, PHP 8.5 с `pdo_mysql`, `mbstring`, `fileinfo`, `gd`, MariaDB 10.6 и установленные зависимости проекта. PHP CLI server запускается с 16 worker processes. Это воспроизводимый стенд для общей машины CI, а не измерение мощности Plesk/FPM production.

## Ограничение стенда

- `APP_ENV=testing`; эффективная MySQL/MariaDB БД строго `lessons_test` на loopback, без DB URL, Unix socket, table prefix и replicas. Проверяются фактическое `DATABASE()` и MariaDB 10.6.
- Без закешированной конфигурации; file sessions/cache, retention выключен, cookie domain пустой, cookies подходят для локального HTTP.
- HTTP URL строго `http://127.0.0.1:PORT/`, без credentials/query/path. Redirects не выполняются. Каждый ответ обязан иметь проверенный test-router marker. Router находится только в `tests/load`, не подключается к production routes.
- Test router проверяет конфигурацию и БД перед **каждым** запросом, затем использует существующий Laravel HTTP Kernel со штатными middleware, CSRF, rate limits, session locks и controller routes. Два guard SELECT входят в опубликованное время HTTP-запроса.
- Production-нагрузка этим инструментом запрещена. Запускать только в отдельном disposable CI service/локальном тестовом MariaDB. Fixtures добавляются через HTTP; никаких `migrate:fresh`, truncate или удаления чужих записей. Повторные запуски оставляют только synthetic fixtures в тестовой БД; сервис CI удаляется целиком по завершении job.

Node `http.request` и `http.Agent` используются без новых зависимостей: встроенный `fetch` не предоставляет bind исходящего TCP-адреса. Каждое занятие реально подключается с собственного Linux loopback IP `127.0.0.2`…`127.0.0.11`. Это модель десяти отдельных сетей/NAT, по 30 учеников в каждой. Штатный `lesson-join` 120/min/IP остаётся включён; заголовки IP не подделываются. Профиль всех 300 учеников за одним NAT — отдельная проверка rate limiting и здесь не заявляется.

## Сценарий и результат

Каждый ведущий и ученик имеет отдельную cookie jar и собственный CSRF token, полученный из настоящей `/ru`. CLI `fixture.php` сначала проверяет test environment и валидирует двухэтапный документ через domain registry. Затем каждый ведущий через HTTP создаёт материал, выпускает immutable version и начинает отдельное занятие. IDs, revisions и invite codes читаются из реальных ответов, а не задаются заранее. Изменение authoring draft после выпуска проверяет неизменность runtime snapshot.

Все ученики одновременно подключаются. Три concurrent answer waves: single-choice, poll, после перехода на второй этап free-response. На фоне ученики, ведущие и проекторы опрашивают состояние с cadence 2 секунды; у одной cookie session одновременно не более одного poll. Ответы повторяются через новое TCP-соединение с сохранёнными cookies и неизменённым телом. Команды ведущих повторяются с замороженными UUID, expectedRevision и payload; подтверждённая revision не должна увеличиться повторно. Это детерминированный lost-ack/reconnect сценарий: первый успешный ответ получен, но для retry намеренно игнорируется. Остановка сервера/настоящая потеря пакетов этим сценарным повтором не заявляется.

После волн каждый ведущий получает ровно своих учеников и `30 × 3` ответов. Проверяются реальные сохранённые значения, revisions, отсутствие duplicate `(participantId, blockId)`, собственные ответы учеников и неизменная revision после answer retry. Ученик и ведущий запрашивают соседнюю комнату и должны получить 404. Public projector/student DTO проверяются на отсутствие чужих ответов, участников, private notes и solutions. Все synthetic занятия завершаются штатной командой.

stdout — одна JSON-запись: counts, errors, p50/p95/max по операциям и общий `measured`, без cookies, bearer URLs, ответов учеников и SQL. Время — от начала отправки HTTP-запроса до полного чтения тела, включая ожидание socket/server и реальные DB locks. `setup`, `verify`, `isolation` исключены из общего latency, отрицательные ожидаемые 404 не считаются ошибками. Join, answer, poll, command и retries входят. Минимум 15 секунд polling после join по умолчанию; фактическое wall time может быть больше из-за ответа сервера. CI exit code ненулевой при HTTP/transport ошибках, нарушении целостности либо явно переданном latency threshold.

## Запуск в disposable Linux job

База service должна быть заранее создана как `lessons_test`; использовать service credentials из CI environment, не credentials production. Для существующего MariaDB job запускать **после** `composer test`: PHPUnit не должен параллельно сбрасывать таблицы load fixtures. Нужен Vite build, поскольку настоящий `/ru` рендерит стандартную страницу с manifest.

В `checks.yml` MariaDB job выполняет этот сценарий после `composer test`: устанавливает Node 22, собирает frontend, запускает четыре unit tests клиента и отдельный guarded HTTP step. Шаг создаёт новый test APP_KEY, проверяет fixture guard до migrations, ждёт `/up`, запускает 10×30 без latency threshold и выводит только агрегированный JSON, включая неуспешный результат. PHP workers завершаются через trap отдельной process group; server log находится в приватной временной директории и не публикуется. Artifact upload не добавлен: численные результаты доступны в log данного CI step.

```bash
export APP_ENV=testing APP_DEBUG=false APP_URL=http://127.0.0.1:8765 && \
export DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=lessons_test DB_URL='' && \
export SESSION_DRIVER=file SESSION_DOMAIN='' SESSION_SECURE_COOKIE=false CACHE_STORE=file && \
export LESSONS_RETENTION_ENABLED=false LESSONS_RETENTION_RESTORE_VERIFIED=false && \
export QUEUE_CONNECTION=database MAIL_MAILER=array LOG_CHANNEL=null && \
export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')" && \
php artisan config:clear && \
php artisan migrate --force --no-interaction && \
npm ci --ignore-scripts && npm run build && \
node --test tests/load/client.test.mjs
```

`DB_USERNAME` и `DB_PASSWORD` уже заданы в environment job. В dedicated job требуются обычные checkout/setup PHP/composer install/setup Node 22 шаги из `checks.yml`. Следующий блок завершает все созданные PHP worker processes через отдельную process group; raw server log остаётся в приватной временной директории и не загружается в artifacts.

```bash
set -euo pipefail
export PHP_CLI_SERVER_WORKERS=16
LOAD_LOG_DIR=$(mktemp -d)
setsid php -S 127.0.0.1:8765 -t public tests/load/router.php >"$LOAD_LOG_DIR/server.log" 2>&1 &
LOAD_SERVER_PID=$!
trap 'kill -- -"$LOAD_SERVER_PID" 2>/dev/null || true' EXIT
for attempt in $(seq 1 60); do
    if curl --silent --fail http://127.0.0.1:8765/up >/dev/null; then break; fi
    sleep 1
done
node tests/load/run.mjs --url http://127.0.0.1:8765/ --rooms 10 --students 30 --duration-seconds 15 >"$LOAD_LOG_DIR/metrics.json"
cat "$LOAD_LOG_DIR/metrics.json"
```

Для CI artifacts сохранять только `metrics.json`. При ошибке тоже вывести JSON: в CI использовать перенаправление в `storage/load-metrics.json` (synthetic public metrics), отдельно загрузить его `if: always()`, затем удалить со стендом. Не выводить/загружать `server.log` или database fixtures. `--rooms` разрешает 2…100, `--students` 1…100, duration 6…120 секунд. Большой профиль должен быть согласован отдельно.

## Подтверждённые проверки

Локально на Windows проверяются PHP syntax/Pint, Node syntax и unit tests парсера cookie jar, URL guard, метрик и отказа redirect/test marker. HTTP/MariaDB load runner намеренно отклоняет Windows. Успех реальной нагрузки может быть записан только после Linux CI run с его численными metrics; подготовка скрипта и unit tests не заменяют такую проверку.
